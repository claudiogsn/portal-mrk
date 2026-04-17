<?php
/**
 * refactor_to_mrk_iframe.php
 *
 * Converte controllers do padrão antigo (TPage com iframe + boilerplate)
 * para o padrão novo (extends MRKIframePage com só o getFrontendUrl).
 *
 * COMO USAR:
 *   php refactor_to_mrk_iframe.php /caminho/para/app/control
 *
 * OPÇÕES:
 *   --dry-run    Só mostra o que faria, não altera nada
 *   --no-backup  Não gera arquivo .bak (use por sua conta)
 *
 * EXEMPLOS:
 *   php refactor_to_mrk_iframe.php /var/www/portal-mrk/app/control --dry-run
 *   php refactor_to_mrk_iframe.php /var/www/portal-mrk/app/control
 *
 * O QUE ELE FAZ:
 *   1. Lê cada *.class.php da pasta
 *   2. Detecta se é um controller no padrão iframe (tem "new TElement('iframe')")
 *   3. Extrai:
 *        - nome da classe
 *        - bloco if/else do $link (que vira o getFrontendUrl)
 *   4. Gera novo arquivo: class X extends MRKIframePage com só o getFrontendUrl
 *   5. Salva backup .bak do original
 *
 * O QUE ELE NÃO MEXE:
 *   - Arquivos que não têm "new TElement('iframe')"
 *   - Arquivos que já estendem MRKIframePage
 *   - Arquivos com múltiplas classes no mesmo arquivo
 *   - Controllers que têm lógica customizada dentro do construtor além do iframe
 *     (nesse caso ele avisa e pula)
 */

// ========== CONFIG ==========
$dir       = $argv[1] ?? null;
$dryRun    = in_array('--dry-run', $argv);
$noBackup  = in_array('--no-backup', $argv);

if (!$dir || !is_dir($dir)) {
    echo "❌ Uso: php refactor_to_mrk_iframe.php /caminho/para/pasta [--dry-run] [--no-backup]\n";
    exit(1);
}

$dir = rtrim(realpath($dir), '/');

echo "📂 Pasta: $dir\n";
echo $dryRun   ? "🔍 Modo: DRY-RUN (nenhum arquivo será alterado)\n"   : "✍️  Modo: APLICAR alterações\n";
echo $noBackup ? "⚠️  Backup desativado\n\n"                            : "💾 Backups .bak serão criados\n\n";

// ========== PROCESSAMENTO ==========

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$stats = [
    'analisados'   => 0,
    'refatorados'  => 0,
    'ja_refatorado'=> 0,
    'nao_aplicavel'=> 0,
    'customizado'  => 0,
    'erros'        => 0,
];

$skippedCustom = [];

foreach ($iterator as $file) {
    if (!$file->isFile() || !preg_match('/\.class\.php$/', $file->getFilename())) {
        continue;
    }

    $path    = $file->getPathname();
    $relPath = str_replace($dir . '/', '', $path);
    $content = file_get_contents($path);
    $stats['analisados']++;

    // -------- Filtros iniciais --------

    // Já refatorado?
    if (preg_match('/extends\s+MRKIframePage/', $content)) {
        echo "⏭  [já refatorado]  $relPath\n";
        $stats['ja_refatorado']++;
        continue;
    }

    // É um controller de iframe?
    if (strpos($content, "new TElement('iframe')") === false
        && strpos($content, 'new TElement("iframe")') === false) {
        echo "⏭  [não é iframe]   $relPath\n";
        $stats['nao_aplicavel']++;
        continue;
    }

    // -------- Extrai nome da classe --------
    if (!preg_match('/class\s+([A-Za-z0-9_]+)\s+extends\s+TPage/', $content, $matchClass)) {
        echo "❌ [sem classe]     $relPath\n";
        $stats['erros']++;
        continue;
    }
    $className = $matchClass[1];

    // -------- Extrai bloco if/else do $link --------
    // Captura: if($_SERVER['SERVER_NAME'] == "localhost"){ $link = "..."; } else { $link = "..."; }
    $linkPattern = '/if\s*\(\s*\$_SERVER\[[\'"]SERVER_NAME[\'"]\]\s*==\s*[\'"]localhost[\'"]\s*\)\s*\{\s*\$link\s*=\s*(["\'].+?["\']);\s*\}\s*else\s*\{\s*\$link\s*=\s*(["\'].+?["\']);\s*\}/s';

    if (!preg_match($linkPattern, $content, $matchLink)) {
        echo "⚠️  [sem \$link]     $relPath — pulando\n";
        $stats['erros']++;
        continue;
    }
    $linkLocal    = $matchLink[1];
    $linkProducao = $matchLink[2];

    // -------- Extrai TSession::getValue usadas antes do $link --------
    // Pega só as variáveis usadas pra montar o link
    $sessionVars = [];
    if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*TSession::getValue\([\'"]([^\'"]+)[\'"]\)\s*;/', $content, $matchSession)) {
        for ($i = 0; $i < count($matchSession[1]); $i++) {
            $varName = $matchSession[1][$i];
            $sessName = $matchSession[2][$i];
            // Só inclui se a variável é referenciada no link
            if (strpos($linkLocal, '{$' . $varName . '}')    !== false
                || strpos($linkProducao, '{$' . $varName . '}') !== false
                || strpos($linkLocal, '$' . $varName)           !== false) {
                $sessionVars[$varName] = $sessName;
            }
        }
    }

    // -------- Detecta se tem lógica customizada --------
    // Procura funções/métodos PHP da CLASSE (não funções JS dentro de strings).
    // Estratégia: remover temporariamente o conteúdo de TScript::create(...) e strings
    // antes de procurar "function".
    $codeWithoutStrings = $content;

    // Remove blocos TScript::create("..."); ou TScript::create('...'); (inclusive multiline)
    $codeWithoutStrings = preg_replace('/TScript::create\s*\(\s*"(?:[^"\\\\]|\\\\.)*"\s*\)\s*;/s', '', $codeWithoutStrings);
    $codeWithoutStrings = preg_replace("/TScript::create\s*\(\s*'(?:[^'\\\\]|\\\\.)*'\s*\)\s*;/s", '', $codeWithoutStrings);

    // Remove strings simples que possam ter "function" dentro
    $codeWithoutStrings = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/s', '""', $codeWithoutStrings);
    $codeWithoutStrings = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/s", "''", $codeWithoutStrings);

    if (preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $codeWithoutStrings, $matchFuncs)) {
        $funcs = array_diff($matchFuncs[1], ['__construct', 'onFeed', 'onEdit']);
        if (!empty($funcs)) {
            echo "⚠️  [customizado]   $relPath — métodos extras: " . implode(', ', $funcs) . "\n";
            $skippedCustom[] = $relPath;
            $stats['customizado']++;
            continue;
        }
    }

    // -------- Monta o novo arquivo --------
    $sessionBlock = '';
    foreach ($sessionVars as $varName => $sessName) {
        $sessionBlock .= "        \$$varName = TSession::getValue('$sessName');\n";
    }

    $newContent = "<?php\n";
    $newContent .= "class $className extends MRKIframePage\n";
    $newContent .= "{\n";
    $newContent .= "    protected function getFrontendUrl(): string\n";
    $newContent .= "    {\n";
    if (!empty($sessionBlock)) {
        $newContent .= $sessionBlock;
        $newContent .= "\n";
    }
    $newContent .= "        if (\$_SERVER['SERVER_NAME'] == 'localhost') {\n";
    $newContent .= "            return $linkLocal;\n";
    $newContent .= "        }\n";
    $newContent .= "        return $linkProducao;\n";
    $newContent .= "    }\n";
    $newContent .= "}\n";

    // -------- Grava --------
    if ($dryRun) {
        echo "✅ [seria alterado] $relPath  →  $className\n";
        $stats['refatorados']++;
        continue;
    }

    // Backup
    if (!$noBackup) {
        file_put_contents($path . '.bak', $content);
    }

    // Escreve novo
    file_put_contents($path, $newContent);
    echo "✅ [refatorado]     $relPath  →  $className\n";
    $stats['refatorados']++;
}

// ========== RESUMO ==========

echo "\n========== RESUMO ==========\n";
echo "Analisados:            {$stats['analisados']}\n";
echo "✅ Refatorados:         {$stats['refatorados']}\n";
echo "⏭  Já refatorados:      {$stats['ja_refatorado']}\n";
echo "⏭  Não aplicáveis:      {$stats['nao_aplicavel']}\n";
echo "⚠️  Com lógica custom:   {$stats['customizado']}\n";
echo "❌ Erros:               {$stats['erros']}\n";

if (!empty($skippedCustom)) {
    echo "\n📝 Arquivos com lógica customizada (refatorar manualmente):\n";
    foreach ($skippedCustom as $f) {
        echo "   - $f\n";
    }
}

if (!$dryRun && $stats['refatorados'] > 0) {
    echo "\n💾 Backups salvos como .bak ao lado de cada arquivo.\n";
    echo "\n🔙 Pra reverter tudo:\n";
    echo "   find \"$dir\" -name '*.bak' -exec sh -c 'mv \"\$1\" \"\${1%.bak}\"' _ {} \\;\n";
}

if ($dryRun) {
    echo "\n💡 Rode sem --dry-run pra aplicar as mudanças.\n";
}