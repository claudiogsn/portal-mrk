<?php
$startTime = microtime(true);


ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');


header("Access-Control-Allow-Origin: *"); // Permitir todas as origens
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Cabeçalhos permitidos
header("Access-Control-Allow-Origin: http://localhost:3000");
header('Access-Control-Allow-Origin: http://localhost');
header('Content-Type: application/json; charset=utf-8');




require_once 'controllers/OpenFinanceController.php';
require_once 'controllers/UtilsController.php';


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


$json = file_get_contents('php://input');
$data = json_decode($json, true);

$response = [];
$user = 'anonymous';

if (isset($data['method']) && isset($data['data'])) {
    $method = $data['method'];
    $requestData = $data['data'];
    if (isset($data['token'])){$requestToken = $data['token'];}

    // Métodos que não precisam de autenticação
    $noAuthMethods = [];

    if (!in_array($method, $noAuthMethods)) {
        if (!isset($requestToken)) {
            http_response_code(400);
            echo json_encode(['error' => 'Token ausente']);
            exit;
        }

        $userInfo = verifyToken($requestToken);
        $user = $userInfo['user'];
    }

    try {
        switch ($method) {
            case 'openFinanceCheckPayer':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->checkPayerExists($requestData);
                break;


            case 'requestStatementFromLastYear':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->requestStatementFromLastYear($requestData);
                break;
            // =========================================================================
// OPEN FINANCE - PAINEL ADMINISTRATIVO (GLOBAL)
// =========================================================================

            case 'openFinanceListAllPayers':
                $ctrl = new OpenFinanceController();
                // Não precisa passar $requestData, pois é global
                $response = $ctrl->listAllPayers();
                break;

            case 'openFinanceListAvailableUnits':
                $ctrl = new OpenFinanceController();
                // Não precisa passar $requestData, pois é global
                $response = $ctrl->listAvailableUnits();
                break;

// =========================================================================
// OPEN FINANCE - PAGADORES
// =========================================================================

            case 'openFinanceCreatePayer':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->createPayer($requestData);
                break;

            case 'openFinanceGetPayer':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->getPayer($requestData);
                break;

            case 'openFinanceUpdatePayer':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->updatePayer($requestData);
                break;

            case 'openFinanceListPayers':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->listInternalPayers($requestData);
                break;

            case 'openFinanceDeactivatePayer':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->deactivatePayer($requestData);
                break;

// =========================================================================
// OPEN FINANCE - CONTAS
// =========================================================================

            case 'openFinanceCreateAccount':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->createAccount($requestData);
                break;

            case 'openFinanceSyncAccounts':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->syncAccounts($requestData);
                break;

            case 'openFinanceGetAccountByHash':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->getAccountByHash($requestData);
                break;

            case 'openFinanceUpdateAccount':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->updateAccount($requestData);
                break;

            case 'openFinanceDeleteAccount':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->deleteAccount($requestData);
                break;

            case 'openFinanceListAccounts':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->listInternalAccounts($requestData);
                break;

            case 'openFinanceListLocalAccounts':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->listLocalAccounts($requestData);
                break;

            case 'openFinanceRevoke':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->revokeOpenFinance($requestData);
                break;

// =========================================================================
// OPEN FINANCE - TRANSAÇÕES E HISTÓRICO
// =========================================================================

            case 'listInternalTransactions':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->listInternalTransactions($requestData);
                break;

            case 'openFinanceListImportHistory':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->listImportHistory($requestData);
                break;

            case 'openFinanceListLogs':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->listIntegrationLogs($requestData);
                break;
            case 'getExtrato':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->getExtrato($requestData);
                break;
            case 'getStatementLogs':
                $ctrl = new OpenFinanceController();
                $response = $ctrl->getStatementLogs($requestData);
                break;



            default:
                http_response_code(405);
                $response = ['error' => 'Método não suportado'];
                break;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        if (isset($method) && !in_array($method, $noAuthMethods)) {

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            UtilsController::trackApiToSqs(
                $user,
                $method,
                $requestData ?? $json,
                $response,
                $startTime
            );
        }
    } catch (Exception $e) {
        http_response_code(500);
        $response = ['error' => 'Erro interno do servidor: ' . $e->getMessage()];
        echo json_encode($response);
    }
} else {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos']);
}

// Função de verificação do token
function verifyToken($token)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM system_access_log WHERE sessionid = :sessionid");
    $stmt->bindParam(':sessionid', $token, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);


    if ($result) {
        $logoutTime = $result['logout_time'];

        if (is_null($logoutTime) || $logoutTime === '0000-00-00 00:00:00') {
            if ($result['impersonated'] == 'S') {
                return ['user' => $result['impersonated_by']];
            } else {
                return ['user' => $result['login']];
            }
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Sessão expirada']);
            exit;
        }
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Usuário não encontrado']);
        exit;
    }
}
?>
<?php
