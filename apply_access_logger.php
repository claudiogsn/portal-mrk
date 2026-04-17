<?php
/**
 * apply_access_logger.php
 *
 * Script para aplicar o AccessLogger em massa nos controllers do Portal MRK.
 *
 * O que ele faz:
 *   1. Percorre uma pasta procurando arquivos *.class.php
 *   2. Em cada arquivo que tenha o padrão do iframe:
 *      - Insere o $logId = AccessLogger::log(...) logo depois do bloco do $link
 *      - Insere o TScript do beacon logo antes do fechamento do __construct
 *   3. Pula arquivos que já foram modificados (detecta "AccessLogger::log")
 *
 * COMO USAR:
 *   php apply_access_logger.php /caminho/para/app/control
 *
 * DICA: faça backup antes. O script também gera .bak de cada arquivo alterado.
 */

// ========== CONFIG ==========
$dir = $argv[1] ?? null;
$dryRun = in_array('--dry-run', $argv);   // só mostra o que faria, sem alterar

if (!$dir || !is_dir($dir)) {
    echo "Uso: php apply_access_logger.php /caminho/para/pasta [--dry-run]\n";
    exit(1);
}

// ========== REGEX ==========

// Identifica o padrão do $link do controller
// Captura o bloco inteiro do if/else do $link até a chave de fechamento
$linkPattern = '/(\s*if\s*\(\s*\$_SERVER\[[\'"]SERVER_NAME[\'"]\]\s*==\s*[\'"]localhost[\'"]\s*\)\s*\{[^}]+\}\s*else\s*\{[^}]+\}\s*)/s';

// Trecho a inserir APÓS o bloco do $link
$logCode = <<<'PHP'


        // ---- LOG DE ACESSO ----
        $linkParaLog = preg_replace('/\?.*$/', '', $link);
        $logId = AccessLogger::log(__CLASS__, null, $linkParaLog);

PHP;

// Trecho do beacon — vai ANTES do último "}" do __construct
$beaconCode = <<<'PHP'

        // ---- TRACKING DE SAÍDA ----
        if ($logId) {
            TScript::create("
                (function() {
                    var logId = " . (int) $logId . ";
                    var sent  = false;
                    function sendExit() {
                        if (sent) return;
                        sent = true;
                        var url = 'engine.php?class=AccessLoggerEndpoint&method=logout&log_id=' + logId;
                        if (navigator.sendBeacon) {
                            navigator.sendBeacon(url);
                        } else {
                            var xhr = new XMLHttpRequest();
                            xhr.open('GET', url, false);
                            try { xhr.send(); } catch(e) {}
                        }
                    }
                    window.addEventListener('beforeunload', sendExit);
                    window.addEventListener('pagehide', sendExit);
                })();
            ");
        }

PHP;

// ========== PROCESSA ARQUIVOS ==========

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$stats = ['analisados' => 0, 'modificados' => 0, 'pulados' => 0, 'erros' => 0];

foreach ($iterator as $file) {
    if (!$file->isFile() || !preg_match('/\.class\.php$/', $file->getFilename())) {
        continue;
    }

    $path     = $file->getPathname();
    $content  = file_get_contents($path);
    $original = $content;
    $stats['analisados']++;

    // Pula se já tem o AccessLogger
    if (strpos($content, 'AccessLogger::log') !== false) {
        echo "⏭  Já aplicado: {$file->getFilename()}\n";
        $stats['pulados']++;
        continue;
    }

    // Pula se não tem o padrão do $link
    if (!preg_match($linkPattern, $content)) {
        echo "⏭  Sem padrão \$link: {$file->getFilename()}\n";
        $stats['pulados']++;
        continue;
    }

    // 1) Insere o log depois do bloco do $link
    $content = preg_replace(
        $linkPattern,
        '$1' . $logCode,
        $content,
        1    // só a primeira ocorrência
    );

    // 2) Insere o beacon antes do último "}" do __construct.
    //    Estratégia: encontrar a função __construct e colocar antes do fechamento dela.
    //    Como o TScript do gtag fica no final, colocamos o beacon antes desse bloco.
    //
    //    Detecta o fechamento do __construct olhando a indentação ("    }").
    //    Procura o último TScript::create do arquivo e injeta ANTES dele.
    //
    //    Se não achar TScript, coloca antes do primeiro "    }" que fecha __construct.

    // Tenta injetar antes do último TScript::create do __construct
    $injected = false;
    if (preg_match_all('/TScript::create\(/', $content, $matches, PREG_OFFSET_CAPTURE)) {
        // Pega a POSIÇÃO do primeiro TScript (o do resizeIframe)
        // e insere o beacon entre o resize e o gtag
        $lastMatch = end($matches[0]);
        $insertPos = $lastMatch[1];

        // Backtrack até achar início da linha (pra manter indentação)
        while ($insertPos > 0 && $content[$insertPos - 1] !== "\n") {
            $insertPos--;
        }

        $content = substr($content, 0, $insertPos) . $beaconCode . "\n" . substr($content, $insertPos);
        $injected = true;
    }

    if (!$injected) {
        echo "⚠️  Não consegui injetar beacon: {$file->getFilename()}\n";
        $stats['erros']++;
        continue;
    }

    if ($content === $original) {
        echo "⏭  Sem alteração: {$file->getFilename()}\n";
        $stats['pulados']++;
        continue;
    }

    if ($dryRun) {
        echo "🔍 [DRY-RUN] Modificaria: {$file->getFilename()}\n";
        $stats['modificados']++;
        continue;
    }

    // Backup
    file_put_contents($path . '.bak', $original);
    // Escreve o novo
    file_put_contents($path, $content);

    echo "✅ Modificado: {$file->getFilename()}\n";
    $stats['modificados']++;
}

echo "\n========== RESUMO ==========\n";
echo "Analisados:  {$stats['analisados']}\n";
echo "Modificados: {$stats['modificados']}\n";
echo "Pulados:     {$stats['pulados']}\n";
echo "Erros:       {$stats['erros']}\n";

if (!$dryRun && $stats['modificados'] > 0) {
    echo "\n💾 Backups salvos como .bak ao lado de cada arquivo modificado.\n";
    echo "   Pra reverter tudo:\n";
    echo "   find $dir -name '*.bak' -exec sh -c 'mv \"\$1\" \"\${1%.bak}\"' _ {} \\;\n";
}