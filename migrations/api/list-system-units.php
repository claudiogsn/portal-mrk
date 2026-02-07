<?php
header('Content-Type: application/json');

require_once 'db.php';

global $pdo;

$stmt = $pdo->query("
    SELECT id, name, cnpj, status
    FROM system_unit
    ORDER BY id
");

echo json_encode([
    "success" => true,
    "units" => $stmt->fetchAll()
]);
