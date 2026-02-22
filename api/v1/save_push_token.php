<?php

// save_push_token.php

/*************************************************
 * CONFIGURAÇÃO DE LOG
 *************************************************/
$logDir = __DIR__ . '/request_logs';

// Cria a pasta se não existir
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
    chmod($logDir, 0777);
}

/*************************************************
 * CORS
 *************************************************/
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/*************************************************
 * CAPTURA DO REQUEST (LOG)
 *************************************************/
$rawBody = file_get_contents('php://input');

$requestLog = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method'    => $_SERVER['REQUEST_METHOD'] ?? null,
    'uri'       => $_SERVER['REQUEST_URI'] ?? null,
    'ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
    'origin'    => $_SERVER['HTTP_ORIGIN'] ?? null,
    'user_agent'=> $_SERVER['HTTP_USER_AGENT'] ?? null,
    'headers'   => getallheaders(),
    'body_raw'  => $rawBody,
    'body_json' => json_decode($rawBody, true),
];

// Nome do arquivo: data + random
$logFile = sprintf(
    '%s/%s_%s.json',
    $logDir,
    date('Y-m-d_H-i-s'),
    bin2hex(random_bytes(4))
);

// Salva o JSON formatado
file_put_contents(
    $logFile,
    json_encode($requestLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

/*************************************************
 * LÓGICA PRINCIPAL
 *************************************************/
require_once __DIR__ . '/database/db.php';

// Decodifica JSON já capturado
$data = $requestLog['body_json'];

$userId = $data['user_id'] ?? null;
$token  = $data['token'] ?? null;

if (!$userId || !$token) {
    echo json_encode([
        'success' => false,
        'message' => 'Dados incompletos'
    ]);
    exit;
}

try {
    global $pdo;
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        INSERT INTO user_push_tokens (user_id, token, updated_at)
        VALUES (:user_id, :token, NOW())
        ON DUPLICATE KEY UPDATE
            token = VALUES(token),
            updated_at = NOW()
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':token'   => $token
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Token salvo com sucesso'
    ]);

} catch (PDOException $e) {

    // Loga erro no mesmo arquivo (append)
    file_put_contents(
        $logFile,
        PHP_EOL . PHP_EOL . 'DB_ERROR:' . PHP_EOL . $e->getMessage(),
        FILE_APPEND
    );

    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados'
    ]);
}