<?php

function cloneTableBySystemUnit(PDO $pdo, string $table, int $source, int $target): void
{
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
        // ignora PK padrão
        if ($col === 'id') {
            continue;
        }

        $insertCols[] = "`$col`";

        if ($col === 'system_unit_id') {
            $selectCols[] = $target . " AS system_unit_id";
        } else {
            $selectCols[] = "`$col`";
        }
    }

    $sql = "
        INSERT INTO `$table` (" . implode(", ", $insertCols) . ")
        SELECT " . implode(", ", $selectCols) . "
        FROM `$table`
        WHERE system_unit_id = :source
    ";

    $stmtInsert = $pdo->prepare($sql);
    $stmtInsert->execute([":source" => $source]);
}
