<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php'; // 👈 ISSO resolve o erro


global $pdo;

$data = json_decode(file_get_contents("php://input"), true);

$source = (int)($data['source_system_unit_id'] ?? 0);
$target = (int)($data['target_system_unit_id'] ?? 0);

if ($source <= 0 || $target <= 0 || $source === $target) {
    echo json_encode([
        "success" => false,
        "message" => "Unidades inválidas"
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    /**
     * 🔥 LIMPA DESTINO (ordem reversa de dependência)
     */
    $tables = [
        'productions',
        'compositions',
        'products',
        'categorias'
    ];

    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM `$table` WHERE system_unit_id = $target");
    }

    /**
     * 🔁 CLONAGEM (ordem correta)
     */
    $cloneOrder = [
        'categorias',
        'products',
        'compositions',
        'productions'
    ];

    foreach ($cloneOrder as $table) {
        cloneTableBySystemUnit($pdo, $table, $source, $target);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Clonagem concluída com sucesso"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        "success" => false,
        "message" => "Erro na clonagem",
        "error" => $e->getMessage()
    ]);
}
