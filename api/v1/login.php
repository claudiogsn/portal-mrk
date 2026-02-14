<?php
// 1. Configurações de API (CORS e JSON)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Se for OPTIONS (preflight), encerra
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Importa a conexão global
// Certifique-se de que o arquivo db.php cria a variável $pdo
require_once __DIR__ . '/database/db.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: Conexão com banco não configurada corretamente.']);
    exit;
}

// 3. Recebendo os dados
$input = json_decode(file_get_contents("php://input"), true);
$loginUsuario = $input['login'] ?? '';
$senhaPlana   = $input['password'] ?? '';
$ipAddress    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if (empty($loginUsuario) || empty($senhaPlana)) {
    http_response_code(400);
    echo json_encode(['error' => 'Login e senha são obrigatórios']);
    exit;
}

try {
    // 1. Busca o usuário ativo
    $stmt = $pdo->prepare("
        SELECT id, name, login, email, password, system_unit_id 
        FROM system_users 
        WHERE login = :login AND active = 'Y' 
        LIMIT 1
    ");
    $stmt->execute(['login' => $loginUsuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Valida usuário e senha
    if (!$user || empty($user['password']) || !password_verify($senhaPlana, $user['password'])) {
        throw new Exception('Credenciais inválidas', 401);
    }

    // 3. Gera Token e Datas
    $sessionId = bin2hex(random_bytes(16));
    $agora = new DateTime();

    // Inicia transação
    $pdo->beginTransaction();

    // 4. Resolve o ID do Log (Legado Adianti)
    $stmtMax = $pdo->query("SELECT MAX(id) as max_id FROM system_access_log");
    $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
    $nextId = ((int)($rowMax['max_id'] ?? 0)) + 1;

    // 5. Grava Log
    $sqlInsert = "
        INSERT INTO system_access_log (
            id, sessionid, login, login_time, 
            login_year, login_month, login_day, 
            access_ip, impersonated
        ) VALUES (
            :id, :sessionid, :login, :login_time, 
            :login_year, :login_month, :login_day, 
            :access_ip, :impersonated
        )
    ";

    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->execute([
        'id'           => $nextId,
        'sessionid'    => $sessionId,
        'login'        => $user['login'],
        'login_time'   => $agora->format('Y-m-d H:i:s'),
        'login_year'   => $agora->format('Y'),
        'login_month'  => $agora->format('m'),
        'login_day'    => $agora->format('d'),
        'access_ip'    => $ipAddress,
        'impersonated' => 'N'
    ]);

    $pdo->commit();

    // 6. Retorno Sucesso
    echo json_encode([
        'token' => $sessionId,
        'user'  => [
            'id'      => $user['id'],
            'name'    => $user['name'],
            'login'   => $user['login'],
            'email'   => $user['email'],
            'unit_id' => $user['system_unit_id'],
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = $e->getCode() === 401 ? 401 : 500;
    http_response_code($code);

    echo json_encode([
        'error'   => true,
        'message' => $e->getMessage()
    ]);
}
?>