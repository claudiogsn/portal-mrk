<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$dsnMeraki = 'mysql:host=localhost;dbname=meraki89_portal';
$usernameMeraki = 'meraki89_portal';
$passwordMeraki = 'Portal@159';

$dsnMenew = 'mysql:host=db.prod.menew.cloud;dbname=portalme_api';
$usernameMenew = 'ped_claudio_gomes';
$passwordMenew = 'dvHs3QjJRQDgKwpDe2@mAZmY';

try {
    $pdoMeraki = new PDO($dsnMeraki, $usernameMeraki, $passwordMeraki);
    $pdoMeraki->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdoMenew = new PDO($dsnMenew, $usernameMenew, $passwordMenew);
    $pdoMenew->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents("php://input"), true);
    $tableName = $data['table'];
    $customCode = $data['custom_code'];
    $systemUnitId = $data['system_unit_id'];

    // Apagar dados existentes no banco Meraki para o system_unit_id informado
    $pdoMeraki->prepare("DELETE FROM $tableName WHERE system_unit_id = ?")->execute([$systemUnitId]);

    // Selecionar dados do banco Menew para migração
    switch ($tableName) {
        case 'products':
            $stmt = $pdoMenew->prepare("SELECT * FROM ckpt_produto WHERE estabelecimento = ?");
            break;
        case 'categorias':
            $stmt = $pdoMenew->prepare("SELECT * FROM ckpt_categoria WHERE estabelecimento = ?");
            break;
        case 'compositions':
            $stmt = $pdoMenew->prepare("SELECT * FROM ckpt_prod_composicao WHERE estabelecimento = ?");
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Tabela desconhecida']);
            exit();
    }

    $stmt->execute([$customCode]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Inserir dados migrados no banco Meraki
    foreach ($rows as $row) {
        switch ($tableName) {
            case 'products':
                $pdoMeraki->prepare("INSERT INTO products (system_unit_id, codigo, nome, preco, categoria, und, venda, composicao, insumo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$systemUnitId, $row['codigo'], $row['nome'], $row['preco'], $row['categ'], $row['und'], $row['venda'], $row['composicao'], $row['insumo']]);
                break;
            case 'categorias':
                $pdoMeraki->prepare("INSERT INTO categorias (system_unit_id, codigo, nome) VALUES (?, ?, ?)")
                    ->execute([$systemUnitId, $row['codigo'], $row['nome']]);
                break;
            case 'compositions':
                $pdoMeraki->prepare("INSERT INTO compositions (product_id, insumo_id, quantity, system_unit_id) VALUES (?, ?, ?, ?)")
                    ->execute([$row['produto'], $row['insumo'], $row['quantidade'], $systemUnitId]);
                break;
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'Migração concluída para a tabela ' . $tableName]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>
