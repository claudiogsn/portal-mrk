<?php

$ini = parse_ini_file(__DIR__ . '/../../app/config/communication.ini');

$dsn = "{$ini['type']}:host={$ini['host']};port={$ini['port']};dbname={$ini['name']}";

try {
    $pdo = new PDO($dsn, $ini['user'], $ini['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao conectar no banco",
        "error" => $e->getMessage()
    ]);
    exit;
}
