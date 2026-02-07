<?php

/**
 * Função Genérica: Clona tabelas simples onde basta trocar o system_unit_id.
 * Funciona para: products, categorias, compositions, productions.
 */
function cloneTableBySystemUnit(PDO $pdo, string $table, int $source, int $target): void
{
    // 1. Descobre as colunas da tabela
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute([":table" => $table]);
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$columns) {
        throw new Exception("Tabela '{$table}' não encontrada.");
    }

    $insertCols = [];
    $selectCols = [];

    foreach ($columns as $col) {
        // Ignora a Primary Key (auto_increment gera nova)
        if ($col === 'id') {
            continue;
        }

        $insertCols[] = "`$col`";

        // Se for a coluna da unidade, injeta o ID de destino
        if ($col === 'system_unit_id') {
            $selectCols[] = $target . " AS system_unit_id";
        } else {
            $selectCols[] = "`$col`";
        }
    }

    // 2. Monta o INSERT ... SELECT
    $sql = "
        INSERT INTO `$table` (" . implode(", ", $insertCols) . ")
        SELECT " . implode(", ", $selectCols) . "
        FROM `$table`
        WHERE system_unit_id = :source
    ";

    $stmtInsert = $pdo->prepare($sql);
    $stmtInsert->execute([":source" => $source]);
}

/**
 * Função Especializada: Clona 'modelos_balanco' e seus itens.
 * Motivo: Precisa tratar a coluna UNIQUE 'tag' e atualizar o ID do pai nos filhos.
 */
function cloneModelosBalanco(PDO $pdo, int $sourceId, int $targetId): void
{
    // 1. Busca todos os modelos (Pais) da unidade de origem
    $stmt = $pdo->prepare("SELECT * FROM modelos_balanco WHERE system_unit_id = ?");
    $stmt->execute([$sourceId]);
    $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($modelos as $modelo) {
        $oldId = $modelo['id'];

        // --- TRATAMENTO DA TAG (Evitar erro Duplicate Entry) ---
        // A tag geralmente é "IDLOJA-NOME". Vamos trocar o ID da loja antiga pelo da nova.
        $oldTag = $modelo['tag'];
        $prefixOld = "$sourceId-";
        $prefixNew = "$targetId-";

        if (strpos($oldTag, $prefixOld) === 0) {
            // Se a tag começa com "20-...", vira "50-..."
            $newTag = str_replace($prefixOld, $prefixNew, $oldTag);
        } else {
            // Se não seguir o padrão, criamos uma nova: IDNOVO-NOME-TIMESTAMP
            $cleanName = preg_replace('/[^a-zA-Z0-9]/', '', $modelo['nome']); // Remove caracteres especiais
            $newTag = "$targetId-" . strtolower($cleanName) . "-" . time();
        }

        // Verifica se essa TAG já existe no destino (Segurança Extra)
        // Se existir, adiciona um número aleatório no fim
        $check = $pdo->prepare("SELECT COUNT(*) FROM modelos_balanco WHERE tag = ?");
        $check->execute([$newTag]);
        if ($check->fetchColumn() > 0) {
            $newTag .= "-" . rand(1000, 9999);
        }

        // 2. Insere o MODELO (Pai) com a nova TAG
        $sqlPai = "INSERT INTO modelos_balanco 
                   (system_unit_id, nome, usuario_id, ativo, tag, created_at, updated_at) 
                   VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

        $stmtPai = $pdo->prepare($sqlPai);
        $stmtPai->execute([
            $targetId,              // Novo System Unit ID
            $modelo['nome'],
            $modelo['usuario_id'],  // Mantém o ID do usuário (atenção: o usuário deve existir)
            $modelo['ativo'],
            $newTag                 // A TAG tratada e única
        ]);

        // Pega o ID gerado para este novo pai
        $newModelId = $pdo->lastInsertId();

        // 3. Busca os ITENS (Filhos) do modelo antigo
        $stmtItens = $pdo->prepare("SELECT * FROM modelos_balanco_itens WHERE id_modelo = ?");
        $stmtItens->execute([$oldId]);
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($itens)) {
            $sqlItem = "INSERT INTO modelos_balanco_itens 
                        (system_unit_id, id_modelo, id_produto, created_at, updated_at) 
                        VALUES (?, ?, ?, NOW(), NOW())";
            $stmtInsertItem = $pdo->prepare($sqlItem);

            foreach ($itens as $item) {
                // Insere o filho vinculando ao NOVO pai ($newModelId)
                $stmtInsertItem->execute([
                    $targetId,
                    $newModelId,        // <--- Aqui está o segredo: vincula ao novo pai
                    $item['id_produto']
                ]);
            }
        }
    }
}