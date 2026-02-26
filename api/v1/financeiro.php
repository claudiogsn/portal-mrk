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
require_once 'controllers/ConferenciaCaixaController.php';
require_once 'controllers/FinanceiroFormaPagamentoController.php';
require_once 'controllers/FinanceiroOpcoesRecebimentoController.php';


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

            case 'listFormasPagamento':
                if (isset($requestData['system_unit_id'])) {
                    $apenasAtivos = isset($requestData['apenas_ativos']) && (bool)$requestData['apenas_ativos'];

                    $response = FinanceiroFormaPagamentoController::listFormasPagamento(
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

            case 'createFormaPagamento':
                if (
                    isset($requestData['system_unit_id']) &&
                    isset($requestData['codigo'])
                ) {
                    // Passa o payload inteiro (nome, banco_padrao_id, etc)
                    $response = FinanceiroFormaPagamentoController::createFormaPagamento($requestData);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: system_unit_id, codigo'
                    ];
                }
                break;

            case 'updateFormaPagamento':
                if (isset($requestData['id'])) {
                    $response = FinanceiroFormaPagamentoController::updateFormaPagamento($requestData);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetro obrigatório: id'
                    ];
                }
                break;

            case 'deleteFormaPagamento':
                if (isset($requestData['id'])) {
                    $response = FinanceiroFormaPagamentoController::deleteFormaPagamento($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetro obrigatório: id'
                    ];
                }
                break;

            case 'getFormaPagamentoById':
                if (isset($requestData['id'])) {
                    // O novo controller só precisa do ID
                    $response = FinanceiroFormaPagamentoController::getFormaPagamentoById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetro obrigatório: id'
                    ];
                }
                break;

            case 'importarFormasPadrao':
                if (isset($requestData['system_unit_id'])) {
                    $response = FinanceiroFormaPagamentoController::importarFormasPadrao(
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


            case 'createOpcaoRecebimento':
                if (
                    isset($requestData['system_unit_id']) &&
                    isset($requestData['codigo'])
                ) {
                    $response = FinanceiroOpcoesRecebimentoController::createOpcaoRecebimento($requestData);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: system_unit_id, codigo   '
                    ];
                }
                break;

            case 'updateOpcaoRecebimento':
                if (isset($requestData['id'])) {
                    $response = FinanceiroOpcoesRecebimentoController::updateOpcaoRecebimento($requestData);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetro obrigatório: id'
                    ];
                }
                break;

            case 'deleteOpcaoRecebimento':
                if (isset($requestData['id'])) {
                    $response = FinanceiroOpcoesRecebimentoController::deleteOpcaoRecebimento($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetro obrigatório: id'
                    ];
                }
                break;

            case 'listOpcoesRecebimento':
                if (isset($requestData['system_unit_id'])) {
                    $response = FinanceiroOpcoesRecebimentoController::listOpcoesRecebimento($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetro obrigatório: system_unit_id'
                    ];
                }
                break;

            case 'importarOpcoesPadrao':
                if (isset($requestData['system_unit_id']) && isset($requestData['plano_contas_id'])) {
                    $response = FinanceiroOpcoesRecebimentoController::importarOpcoesPadrao(
                        $requestData['system_unit_id'],
                        $requestData['plano_contas_id']
                    );
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: system_unit_id, plano_contas_id'
                    ];
                }
                break;
            case 'listMeiosPorOpcao':
                if (
                    isset($requestData['system_unit_id']) &&
                    isset($requestData['codigo_opcao'])
                ) {
                    $response = FinanceiroOpcoesRecebimentoController::listMeiosPorOpcao(
                        $requestData['system_unit_id'],
                        $requestData['codigo_opcao']
                    );
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetros obrigatórios: system_unit_id, codigo_opcao'
                    ];
                }
                break;
            case 'vincularMeioPagamento':
                $response = FinanceiroOpcoesRecebimentoController::vincularMeioPagamento($requestData);
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
            case 'ApiMenewAuthenticate':
                $response = FinanceiroApiMenewController::authenticate();
                break;


            case 'listPlanos':
                if (isset($requestData['system_unit_id'])) {
                    $response = FinanceiroPlanoController::listPlanos($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => 'ID do estabelecimento (system_unit_id) não informado'];
                }
                break;
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
            case 'baixarConta':
                $response = FinanceiroContaController::baixarConta($requestData);
                break;
            case 'baixarContasEmLote':
                $response = FinanceiroContaController::baixarContasEmLote($requestData);
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

            case 'getExtratoBancario':
                $response = FinanceiroContaController::getExtratoBancario($requestData);
                break;
            case 'getDashboardFinanceiroPorGrupo':
                $response = FinanceiroContaController::getDashboardFinanceiroPorGrupo($requestData);
                break;
            case 'getMapaDeContas':
                $response = FinanceiroContaController::getMapaDeContas($requestData);
                break;

            case 'getResumoConferencia':
                $response = ConferenciaCaixaController::getResumoGeral(
                    $requestData['system_unit_id'] ?? null,
                    $requestData['data'] ?? null
                );
                break;
            case 'getPayloadConferencia':
                $response = ConferenciaCaixaController::getPayloadConferencia(
                    $requestData['system_unit_id'] ?? null,
                    $requestData['data_analise'] ?? null
                );
                break;
            case 'getAuditoriaConferencia':
                $response = ConferenciaCaixaController::getAuditoriaMovimentos(
                    $requestData['system_unit_id'] ?? null,
                    $requestData['data_analise'] ?? null,
                    $requestData['forma_pagamento'] ?? null
                );
                break;
            case 'saveConferencia':
                $response = ConferenciaCaixaController::saveConferencia($requestData);
                break;
            case 'getVendasByForma':
                $response = ConferenciaCaixaController::getVendasByForma(
                    $requestData['system_unit_id'] ?? null,
                    $requestData['data_analise'] ?? null,
                    $requestData['chaveBusca'] ?? null,
                    $requestData['tipoBusca'] ?? null
                );
                break;
            case 'sendConferenciaWpp':
                $response = ConferenciaCaixaController::sendConferenciaWpp(
                    $requestData['system_unit_id'] ?? null,
                    $requestData['data_contabil'] ?? null,
                    $requestData['user_id'] ?? $_SESSION['user_id'] ?? null
                );
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
