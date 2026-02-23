<?php

// save_push_token.php

/*************************************************
 * CONFIGURAÇÃO DE LOG
 *************************************************/
$logDir = __DIR__ . '/request_logs';

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
 * CAPTURA DO REQUEST (LOG) E IP PÚBLICO
 *************************************************/
$rawBody = file_get_contents('php://input');

// Captura o IP real (considerando proxies/Cloudflare caso você use no futuro)
$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

$requestLog = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method'    => $_SERVER['REQUEST_METHOD'] ?? null,
    'uri'       => $_SERVER['REQUEST_URI'] ?? null,
    'ip'        => $ipAddress,
    'origin'    => $_SERVER['HTTP_ORIGIN'] ?? null,
    'user_agent'=> $_SERVER['HTTP_USER_AGENT'] ?? null,
    'headers'   => getallheaders(),
    'body_raw'  => $rawBody,
    'body_json' => json_decode($rawBody, true),
];

$logFile = sprintf('%s/%s_%s.json', $logDir, date('Y-m-d_H-i-s'), bin2hex(random_bytes(4)));
file_put_contents($logFile, json_encode($requestLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

/*************************************************
 * LÓGICA PRINCIPAL
 *************************************************/
require_once __DIR__ . '/database/db.php';

$data = $requestLog['body_json'];

$userId = $data['user_id'] ?? null;
$token  = $data['token'] ?? null;

// Extrai os dados do dispositivo (se vierem nulos, o banco aceita graças ao NULL na criação)
$deviceInfo = $data['device_info'] ?? [];
$deviceId   = $deviceInfo['device_id'] ?? null;
$brand      = $deviceInfo['brand'] ?? null;
$model      = $deviceInfo['model'] ?? null;
$os         = $deviceInfo['os'] ?? null;
$osVersion  = $deviceInfo['os_version'] ?? null;
$appVersion = $deviceInfo['app_version'] ?? null;

if (!$userId || !$token) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

try {
    global $pdo;
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Verifica se o usuário já tinha um token salvo ANTES de atualizar
    $checkStmt = $pdo->prepare("SELECT token FROM user_push_tokens WHERE user_id = :user_id");
    $checkStmt->execute([':user_id' => $userId]);
    $tokenAntigo = $checkStmt->fetchColumn();

    // 2. Faz o insert ou update no banco salvando todo o contexto do aparelho e rede
    $stmt = $pdo->prepare("
        INSERT INTO user_push_tokens (
            user_id, token, device_id, brand, model, os, os_version, app_version, ip_address, updated_at
        ) VALUES (
            :user_id, :token, :device_id, :brand, :model, :os, :os_version, :app_version, :ip_address, NOW()
        ) ON DUPLICATE KEY UPDATE
            token = VALUES(token),
            device_id = VALUES(device_id),
            brand = VALUES(brand),
            model = VALUES(model),
            os = VALUES(os),
            os_version = VALUES(os_version),
            app_version = VALUES(app_version),
            ip_address = VALUES(ip_address),
            updated_at = NOW()
    ");

    $stmt->execute([
        ':user_id'     => $userId,
        ':token'       => $token,
        ':device_id'   => $deviceId,
        ':brand'       => $brand,
        ':model'       => $model,
        ':os'          => $os,
        ':os_version'  => $osVersion,
        ':app_version' => $appVersion,
        ':ip_address'  => $ipAddress
    ]);

    // 3. Verifica se é um aparelho novo para disparar a notificação
    // 3. Verifica se é um aparelho novo para disparar a notificação
    if (true) {

        // --- BUSCA O NOME DO USUÁRIO ---
        $stmtUser = $pdo->prepare("SELECT name FROM system_users WHERE id = :user_id");
        $stmtUser->execute([':user_id' => $userId]);
        $nomeCompleto = $stmtUser->fetchColumn();

        // Pega apenas o primeiro nome para a notificação ficar mais amigável
        $primeiroNome = $nomeCompleto ? explode(' ', trim($nomeCompleto))[0] : '';
        $saudacao = $primeiroNome ? "Olá, {$primeiroNome}!" : "Olá!";
        // -------------------------------

        $payload = [
            "to" => $token,
            "title" => "Novo Dispositivo Conectado! 📱✨",
            "body" => "{$saudacao} Suas notificações do MRK chegarão neste aparelho a partir de agora. Se você usava outro celular, os alertas foram transferidos para cá! 🚀",
            "sound" => "default"
        ];

        // Disparo do Push de Boas-vindas via cURL (Servidor para Servidor)
        $ch = curl_init('https://exp.host/--/api/v2/push/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate',
            'Content-Type: application/json'
        ]);
        // Colocamos um timeout baixo (3s) para não travar a resposta do login
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);

        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode(['success' => true, 'message' => 'Token e contexto salvos com sucesso']);

} catch (PDOException $e) {
    file_put_contents($logFile, PHP_EOL . PHP_EOL . 'DB_ERROR:' . PHP_EOL . $e->getMessage(), FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados']);
}