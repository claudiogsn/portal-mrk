<?php


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




require_once 'controllers/FinanceiroPlanoController.php';
require_once 'controllers/FinanceiroRateioController.php';
require_once 'controllers/FinanceiroApiMenewController.php';
require_once 'controllers/FinanceiroContaController.php';
require_once 'controllers/FinanceiroFornecedorController.php';
require_once 'controllers/FinanceiroClienteController.php';
require_once 'controllers/FinanceiroBancoController.php';


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['method']) && isset($data['data'])) {
    $method = $data['method'];
    $requestData = $data['data'];
    if (isset($data['token'])){$requestToken = $data['token'];}

    // Métodos que não precisam de autenticação
    $noAuthMethods = ['validateCPF', 'validateCNPJ','getModelByTag','saveBalanceItems','getUnitsByGroup','registerJobExecution','persistSales','consolidateSalesByGroup','importMovBySales'];

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

            case 'listBancos':
                if (isset($requestData['system_unit_id'])) {
                    $apenasAtivos = isset($requestData['apenas_ativos']) ? (bool)$requestData['apenas_ativos'] : false;

                    $response = FinanceiroBancoController::listBancos(
                        $requestData['system_unit_id'],
                        $apenasAtivos
                    );
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'ID do estabelecimento (system_unit_id) não informado'
                    ];
                }
                break;

            case 'createBanco':
                if (
                    isset($requestData['system_unit_id']) &&
                    isset($requestData['codigo']) &&
                    isset($requestData['nome'])
                ) {
                    // Passa o payload inteiro, o controller se vira com os campos
                    $response = FinanceiroBancoController::createBanco($requestData);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: system_unit_id, codigo, nome'
                    ];
                }
                break;

            case 'updateBanco':
                if (isset($requestData['id'])) {
                    // Passa o payload inteiro, o controller filtra os campos permitidos
                    $response = FinanceiroBancoController::updateBanco($requestData);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetro obrigatório: id'
                    ];
                }
                break;

            case 'deleteBanco':
                if (isset($requestData['id'])) {
                    $response = FinanceiroBancoController::deleteBanco($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetro obrigatório: id'
                    ];
                }
                break;

            case 'getBancoById':
                if (isset($requestData['system_unit_id']) && isset($requestData['id'])) {
                    $response = FinanceiroBancoController::getBancoById(
                        $requestData['id'],
                        $requestData['system_unit_id']
                    );
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: system_unit_id e id'
                    ];
                }
                break;

            case 'importarBancosPadrao':
                if (isset($requestData['system_unit_id'])) {
                    $response = FinanceiroBancoController::importarBancosPadrao(
                        $requestData['system_unit_id']
                    );
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetro obrigatório: system_unit_id'
                    ];
                }
                break;


            case 'fetchFinanceiroConta':
                $response = FinanceiroApiMenewController::fetchFinanceiroConta($requestData['estabelecimento'], $requestData['tipo']);
                break;
            case 'fetchFinanceiroFornecedor':
                $response = FinanceiroApiMenewController::fetchFinanceiroFornecedor($requestData['estabelecimento']);
                break;
            case 'fetchFinanceiroCliente':
                $response = FinanceiroApiMenewController::fetchFinanceiroCliente($requestData['estabelecimento']);
                break;
            case 'fetchFinanceiroPlano':
                $response = FinanceiroApiMenewController::fetchFinanceiroPlano($requestData['estabelecimento']);
                break;
            case 'fetchFinanceiroRateio':
                $response = FinanceiroApiMenewController::fetchFinanceiroRateio($requestData['estabelecimento']);
                break;
            case 'importarContaApiDesativado':
                $response = FinanceiroContaController::importarContaApi($requestData['system_unit_id']);
                break;
            case 'importarRateiosApiDesativado':
                $response = FinanceiroRateioController::importarRateiosApi($requestData['system_unit_id']);
                break;
            case 'importarFornecedoresApi':
                $response = FinanceiroFornecedorController::importarFornecedoresApi($requestData['system_unit_id']);
                break;
            case 'importarClientesApiDesativado':
                $response = FinanceiroClienteController::importarClientesApi($requestData['system_unit_id']);
                break;
            case 'importarPlanosApiDesativado':
                $response = FinanceiroPlanoController::importarPlanosApi($requestData['system_unit_id']);
                break;



            case 'listPlanos':
                if (isset($requestData['system_unit_id'])) {
                    $response = FinanceiroPlanoController::listPlanos($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => 'ID do estabelecimento (system_unit_id) não informado'];
                }
                break;
            // CRIAR PLANO
            case 'createPlano':
                if (
                    isset($requestData['system_unit_id']) &&
                    isset($requestData['codigo']) &&
                    isset($requestData['descricao'])
                ) {
                    $data = [
                        'system_unit_id' => $requestData['system_unit_id'],
                        'codigo'         => $requestData['codigo'],
                        'descricao'      => $requestData['descricao'],
                    ];
                    $response = FinanceiroPlanoController::createPlano($data);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: system_unit_id, codigo, descricao'
                    ];
                }
                break;
            // ATUALIZAR PLANO (só descricao, via ID)
            case 'updatePlano':
                if (isset($requestData['id']) && isset($requestData['descricao'])) {
                    $data = [
                        'descricao' => $requestData['descricao']
                    ];
                    $response = FinanceiroPlanoController::updatePlano($requestData['id'], $data);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: id e descricao'
                    ];
                }
                break;
            // EXCLUIR PLANO (recebe system_unit_id + codigo)
            case 'deletePlano':
                if (isset($requestData['system_unit_id']) && isset($requestData['codigo'])) {
                    $response = FinanceiroPlanoController::deletePlano(
                        $requestData['system_unit_id'],
                        $requestData['codigo']
                    );
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: system_unit_id e codigo'
                    ];
                }
                break;
            // INATIVAR PLANO (recebe system_unit_id + codigo)
            case 'inativarPlano':
                if (isset($requestData['system_unit_id']) && isset($requestData['codigo'])) {
                    $response = FinanceiroPlanoController::inativarPlano(
                        $requestData['system_unit_id'],
                        $requestData['codigo']
                    );
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: system_unit_id e codigo'
                    ];
                }
                break;
            // BUSCAR PLANO POR CODIGO + UNIDADE
            case 'getPlanoByCodigo':
                if (isset($requestData['system_unit_id']) && isset($requestData['codigo'])) {
                    $response = FinanceiroPlanoController::getPlanoByCodigo(
                        $requestData['system_unit_id'],
                        $requestData['codigo']
                    );
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: system_unit_id e codigo'
                    ];
                }
                break;
            // IMPORTAR PLANOS DA API (Menew)
            case 'importarPlanosApi':
                if (isset($requestData['system_unit_id'])) {
                    $response = FinanceiroPlanoController::importarPlanosApi($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'ID do estabelecimento (system_unit_id) não informado'
                    ];
                }
                break;

            case 'ApiMenewAuthenticate':
                $response = FinanceiroApiMenewController::authenticate();
                break;

            case 'getDreGerencial':
               if(isset($requestData['system_unit_id']) && isset($requestData['data_inicial']) && isset($requestData['data_final'])){
                   $response = FinanceiroContaController::getDreGerencial($requestData['system_unit_id'], $requestData['data_inicial'], $requestData['data_final']);
                }else{
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros inválidos'];
                }
               break;

            case 'listContas':
                if (
                    isset($requestData['system_unit_id']) &&
                    isset($requestData['data_inicial']) &&
                    isset($requestData['data_final']) &&
                    isset($requestData['tipoData'])
                ) {
                    $response = FinanceiroContaController::listContas(
                        $requestData['system_unit_id'],
                        $requestData['data_inicial'],
                        $requestData['data_final'],
                        $requestData['tipoData'], // 'emissao' ou 'vencimento'
                        $requestData['tipo'] ?? null // 'credito', 'debito' ou null (traz todos)
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros inválidos'];
                }
                break;
            case 'getContaByMonth':
                if(isset($requestData['system_unit_id']) && isset($requestData['month']) && isset($requestData['year'])){
                    $response = FinanceiroContaController::getContaByMonth($requestData['system_unit_id'], $requestData['month'], $requestData['year'], $requestData['plano_contas']);
                }else{
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros inválidos'];
                }
                break;
            case 'createConta':
                $response = FinanceiroContaController::createConta($requestData);
                break;
            case 'editConta':
                $response = FinanceiroContaController::editConta($requestData);
                break;
            case 'deleteConta':
                $response = FinanceiroContaController::deleteConta($requestData['id'],$requestData['usuario_id'],$requestData['motivo']);
                break;
            case 'createContaLote':
                $response = FinanceiroContaController::createContaLote($requestData);
                break;
            case 'getContaById':
                $response = FinanceiroContaController::getContaById($requestData['id']);
                break;

            case 'lancarNotaNoFinanceiroConta':
                $response = FinanceiroContaController::lancarNotaNoFinanceiroConta($requestData);
                break;
            case 'lancarNotaNoFinanceiroContaLote':
                $response = FinanceiroContaController::lancarNotaNoFinanceiroContaLote($requestData);
                break;

            case 'exportContasF360':
                $response = FinanceiroContaController::exportContasF360($requestData);
                break;
            case 'marcarExportadoF360':
                $response = FinanceiroContaController::marcarExportadoF360($requestData);
                break;
            case 'hasF360Integration':
                if (isset($requestData['system_unit_id'])) {
                    $response = FinanceiroContaController::hasF360Integration($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'ID do estabelecimento não informado'];
                }
                break;


            case 'listFornecedores':
                $response = FinanceiroFornecedorController::listFornecedores($requestData['system_unit_id']);
                break;
            case 'getFornecedorById':
                $response = FinanceiroFornecedorController::getFornecedorById($requestData['id'], $requestData['system_unit_id']);
                break;
            case 'createFornecedor':
                $response = FinanceiroFornecedorController::createFornecedor($requestData);
                break;
            case 'updateFornecedor':
                $response = FinanceiroFornecedorController::updateFornecedor($requestData);
                break;
            case 'listItensFornecedor':
                $response = FinanceiroFornecedorController::listItensFornecedor($requestData['system_unit_id'], $requestData['fornecedor_id']);
                break;


            default:
                http_response_code(405);
                $response = ['error' => 'Método não suportado'];
                break;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
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
