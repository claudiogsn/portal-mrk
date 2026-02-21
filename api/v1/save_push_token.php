<?php

// save_push_token.php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
    'http://localhost:8081',
    'http://127.0.0.1:8081',
    'http://localhost:19006',
    'http://127.0.0.1:19006',
];

if ($origin && in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Vary: Origin");
    header("Access-Control-Allow-Credentials: true");
} else {

    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
header("Access-Control-Max-Age: 86400");

require_once __DIR__ . '/database/db.php';

// Lê o JSON enviado pelo React Native
$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? null;
$token = $data['token'] ?? null;

if (!$userId || !$token) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

try {
    global $pdo;
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Exemplo de UPSERT (Insere se não existir, atualiza se já existir)
    // Assumindo que você crie uma tabela: user_push_tokens (user_id INT, token VARCHAR(255), updated_at DATETIME)
    $stmt = $pdo->prepare("
        INSERT INTO user_push_tokens (user_id, token, updated_at) 
        VALUES (:user_id, :token, NOW())
        ON DUPLICATE KEY UPDATE token = :token, updated_at = NOW()
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':token' => $token
    ]);

    echo json_encode(['success' => true, 'message' => 'Token salvo com sucesso']);

} catch (PDOException $e) {
    // Em produção, evite expor o erro real do banco
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados', 'error' => $e->getMessage()]);
}
