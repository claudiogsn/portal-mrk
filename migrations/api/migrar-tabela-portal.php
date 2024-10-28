<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$dsnMeraki = 'mysql:host=localhost;dbname=meraki89_portal';
$usernameMeraki = 'meraki89_portal';
$passwordMeraki = 'Portal@159';

try {
    $pdoMeraki = new PDO($dsnMeraki, $usernameMeraki, $passwordMeraki);
    $pdoMeraki->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents("php://input"), true);
    $tableName = $data['table'];
    $sourceSystemUnitId = $data['source_system_unit_id'];
    $targetSystemUnitId = $data['target_system_unit_id'];

    // Apagar dados existentes no destino
    $pdoMeraki->prepare("DELETE FROM $tableName WHERE system_unit_id = ?")->execute([$targetSystemUnitId]);

    // Selecionar dados da unidade de origem
    switch ($tableName) {
        case 'products':
            $stmt = $pdoMeraki->prepare("SELECT * FROM products WHERE system_unit_id = ?");
            break;
        case 'categorias':
            $stmt = $pdoMeraki->prepare("SELECT * FROM categorias WHERE system_unit_id = ?");
            break;
        case 'compositions':
            $stmt = $pdoMeraki->prepare("SELECT * FROM compositions WHERE system_unit_id = ?");
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Tabela desconhecida']);
            exit();
    }

    $stmt->execute([$sourceSystemUnitId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Inserir dados no destino
    foreach ($rows as $row) {
        switch ($tableName) {
            case 'products':
                $pdoMeraki->prepare("INSERT INTO products (system_unit_id, codigo, nome, preco, categoria, und, venda, composicao, insumo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$targetSystemUnitId, $row['codigo'], $row['nome'], $row['preco'], $row['categoria'], $row['und'], $row['venda'], $row['composicao'], $row['insumo']]);
                break;
            case 'categorias':
                $pdoMeraki->prepare("INSERT INTO categorias (system_unit_id, codigo, nome) VALUES (?, ?, ?)")
                    ->execute([$targetSystemUnitId, $row['codigo'], $row['nome']]);
                break;
            case 'compositions':
                $pdoMeraki->prepare("INSERT INTO compositions (product_id, insumo_id, quantity, system_unit_id) VALUES (?, ?, ?, ?)")
                    ->execute([$row['product_id'], $row['insumo_id'], $row['quantity'], $targetSystemUnitId]);
                break;
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'Migração concluída para a tabela ' . $tableName]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>
