<?php
$startTime = microtime(true);
// --- CORS (colocar antes de qualquer outro header / output) ---
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

const ROOT_PATH = __DIR__;

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
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');


ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');

//
//header("Access-Control-Allow-Origin: *"); // Permitir todas as origens
//header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Métodos permitidos
//header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Cabeçalhos permitidos
//header("Access-Control-Allow-Origin: http://localhost:3000");
//header("Access-Control-Allow-Origin: http://localhost:8081");
//header("Access-Control-Allow-Origin: http://localhost");
//header('Content-Type: application/json; charset=utf-8');

require_once 'controllers/ComposicaoController.php';
require_once 'controllers/DashboardController.php';
require_once 'controllers/EstoqueController.php';
require_once 'controllers/FornecedoresController.php';
require_once 'controllers/InsumoController.php';
require_once 'controllers/NecessidadesRefactorController.php';
require_once 'controllers/ProductionController.php';
require_once 'controllers/SalesController.php';
require_once 'controllers/TransfersController.php';
require_once 'controllers/ProductController.php';
require_once 'controllers/CategoriesController.php';
require_once 'controllers/MovimentacaoController.php';
require_once 'controllers/ModeloBalancoController.php';
require_once 'controllers/BiController.php';
require_once 'controllers/ConsolidationEstoqueController.php';
require_once 'controllers/ProducaoController.php';
require_once 'controllers/NotaFiscalEntradaController.php';
require_once 'controllers/DisparosController.php';
require_once 'controllers/SystemUnitController.php';
require_once 'controllers/UserController.php';
require_once 'controllers/MenuMobileController.php';
require_once 'controllers/MdeController.php';
require_once 'controllers/UtilsController.php';
require_once 'controllers/CategoriaController.php';
require_once 'controllers/ProjecaoVendasController.php';


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


$json = file_get_contents('php://input');
$data = json_decode($json, true);

$response = []; // Inicializa a resposta
$user = 'anonymous'; // Default caso não autentique

if (isset($data['method']) && isset($data['data'])) {
    $method = $data['method'];
    $requestData = $data['data'];
    if (isset($data['token'])) {
        $requestToken = $data['token'];
    }

    // Métodos que não precisam de autenticação
    $noAuthMethods = ['getContatosByDisparo',
        'ZigUpdateStatics',
        'ZigRegisterBilling',
        'upsertBiSalesZig',
        'getUnitsIntegrationZigBilling',
        'getProdutosComSkuZig',
        'getUnitsIntegrationZigStock',
        'getUnitsIntegrationMenewStock',
        'getUnitsIntegrationMenewBilling',
        'getGroupsToProcess',
        'getUnitsToProcess',
        'generateResumoFinanceiroPorGrupo',
        'gerarPdfSemanalFaturamento',
        'gerarPdfSemanalCompras',
        'generateNotasPorGrupo',
        'generateResumoEstoquePorGrupoNAuth',
        'generateResumoFinanceiroPorLoja',
        'validateCPF',
        'persistMovimentoCaixa',
        'validateCNPJ',
        'getModelByTag',
        'saveBalanceItems',
        'getUnitsByGroup',
        'getUnitsNotGrouped',
        'registerJobExecution',
        'persistSales',
        'consolidateSalesByGroup',
        'importMovBySalesCons',
        'getIntervalosSemanais',
        'getIntervalosDiarios',
        'getUserDetails',
        'getUnitsUser',
        'getMenuMobile',
        'getIntervalosMensais',
        'gerarPdfFaturamento',
        'gerarPdfCompras',
        'listarNotasNaoImportadasUltimos30Dias',
        'getGroupsToConsolidation',
        'getGroupByUnit',
        'getGroupByUser'
    ];

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
            // Métodos para MdeController
            case 'importNotaFiscal':
                if (isset($requestData['system_unit_id']) && isset($requestData['xml'])) {
                    $response = MdeController::importNotaFiscal($requestData['system_unit_id'], $requestData['xml']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou notaJson ausentes'];
                }
                break;
            case 'lancarNotaAvulsa':
                if (isset($requestData['system_unit_id'])) {
                    $response = MdeController::lancarNotaAvulsa($requestData['system_unit_id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou notaJson ausentes'];
                }
                break;
            case 'getNotaAvulsa':
                if (isset($requestData['system_unit_id']) && isset($requestData['nota_id'])) {
                    $response = MdeController::getNotaAvulsa($requestData['system_unit_id'], $requestData['nota_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou chave ausentes'];
                }
                break;
            case 'updateNotaAvulsa':
                if (isset($requestData['system_unit_id']) && isset($requestData['nota_id'])) {
                    $response = MdeController::updateNotaAvulsa($requestData['system_unit_id'], $requestData['nota_id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou nota_id ausentes'];
                }
                break;
            case 'marcarNotaComoDevolvida':
                if (isset($requestData['system_unit_id']) && isset($requestData['chave'])) {
                    $response = MdeController::marcarNotaComoDevolvida($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou chave ausentes'];
                }
                break;
            case 'listarNotas':
                if (isset($requestData['system_unit_id'])) {
                    $response = MdeController::listarNotas($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ausente'];
                }
                break;
            case 'listarNotasNaoImportadasUltimos30Dias':
                if (isset($requestData['system_unit_id'])) {
                    $response = MdeController::listarNotasNaoImportadasUltimos30Dias($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ausente'];
                }
                break;
            case 'listarNotasNaoImportadasUltimos30DiasCached':
                if (isset($requestData['system_unit_id'])) {
                    $response = MdeController::listarNotasNaoImportadasUltimos30DiasCached($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ausente'];
                }
                break;
            case 'listarChavesNfeComStatusImportacao':
                if (isset($requestData['system_unit_id']) && isset($requestData['data_inicial']) && isset($requestData['data_final'])) {
                    $response = MdeController::listarChavesNfeComStatusImportacao($requestData['system_unit_id'], $requestData['data_inicial'], $requestData['data_final']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id , data_inicial ou data_final ausentes'];
                }
                break;
            case 'importNotasPorChaves':
                if (isset($requestData['system_unit_id']) && isset($requestData['chaves'])) {
                    $response = MdeController::importNotasPorChaves($requestData['system_unit_id'], $requestData['chaves']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou chaves ausentes'];
                }
                break;
            case 'baixarArquivo':
                if (isset($requestData['system_unit_id']) && isset($requestData['chave']) && isset($requestData['tipo'])) {
                    $response = MdeController::baixarArquivo($requestData['system_unit_id'], $requestData['chave'], $requestData['tipo']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id, chave ou tipo ausente'];
                }
                break;


            // Métodos para UserController
            case 'getUserDetails':
                if (isset($requestData['user'])) {
                    $response = UserController::getUserDetails($requestData['user']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro user ausente'];
                }
                break;
            case 'getUnitsUser':
                if (isset($requestData['user'])) {
                    $response = UserController::getUnitsUser($requestData['user']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro user ausente'];
                }
                break;
            case 'getUsers':
                    $response = UserController::getUsers();
                break;
            case 'getMenuMobile':
                if (isset($requestData['user_id'])) {
                    $response = UserController::getMenuMobile($requestData['user_id']);

                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro user ausente'];
                }
                break;
            // Métodos para BiController
            case 'getUnitsByGroup':
                if (isset($requestData['group_id'])) {
                    $response = BiController::getUnitsByGroup($requestData['group_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro group_id ausente'];
                }
                break;
            case 'getUnitsNotGrouped':
                    $response = BiController::getUnitsNotGrouped();
                break;
            case 'getUnitsToProcess':
                    $response = BiController::getUnitsToProcess();
                break;
            case 'getGroupsToProcess':
                $response = BiController::getGroupsToProcess();
                break;
            case 'getGroupsToConsolidation':
                    $response = BiController::getGroupsToConsolidation();
                break;
            case 'ListUnitsByGroup':
                if (isset($requestData['group_id'])) {
                    $response = BiController::ListUnitsByGroup($requestData['group_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro group_id ausente'];
                }
                break;
            case 'createGroup':
                if (isset($requestData['nome'])) {
                    $nome = $requestData['nome'];
                    $slug = $requestData['slug'] ?? null;
                    $ativo = $requestData['ativo'] ?? 'S';
                    $bi = $requestData['bi'] ?? 0;

                    $id = BiController::createGroup($nome, $slug, $ativo, $bi);
                    $response = ['success' => true, 'id' => $id];
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro nome ausente'];
                }
                break;
            case 'editGroup':
                if (isset($requestData['id'], $requestData['nome'])) {
                    $id = $requestData['id'];
                    $nome = $requestData['nome'];
                    $slug = $requestData['slug'] ?? null;
                    $ativo = $requestData['ativo'] ?? 'S';
                    $bi = $requestData['bi'] ?? 0;

                    $success = BiController::editGroup($id, $nome, $slug, $ativo, $bi);
                    $response = ['success' => $success];
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros id e nome são obrigatórios'];
                }
                break;
            case 'toggleGroupAtivo':
                if (isset($requestData['id'], $requestData['ativo'])) {
                    $id = $requestData['id'];
                    $ativo = (int)$requestData['ativo']; // 0 ou 1

                    $success = BiController::toggleGroupAtivo($id, $ativo);
                    $response = ['success' => $success];
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros id e ativo (0 ou 1) são obrigatórios'];
                }
                break;

            case 'updateUnitsGroup':
                if (isset($requestData['grupo_id'], $requestData['unidades']) && is_array($requestData['unidades'])) {
                    $grupoId = $requestData['grupo_id'];
                    $unidades = $requestData['unidades'];

                    $result = BiController::updateUnitsGroup($grupoId, $unidades);
                    $response = is_array($result) ? $result : ['success' => true];
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupo_id e unidades[] são obrigatórios'];
                }
                break;
            case 'getGroupByUnit':
                if (isset($requestData['system_unit_id'])) {
                    $response = BiController::getGroupByUnit($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro group_id ausente'];
                }
                break;
            case 'getGroupByUser':
                if (isset($requestData['user_id'])) {
                    $response = BiController::getGroupByUser($requestData['user_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro group_id ausente'];
                }
                break;
            case 'getGroups':
                    $response = BiController::getGroups();
                break;
            case 'getUnits':
                $response = BiController::getUnits();
                break;
            case 'getUnitsIntegrationZigBilling':
                $response = BiController::getUnitsIntegrationZigBilling($requestData['group_id']);
                break;
            case 'getUnitsIntegrationZigStock':
                $response = BiController::getUnitsIntegrationZigStock($requestData['group_id']);
                break;
            case 'getUnitsIntegrationMenewStock':
                $response = BiController::getUnitsIntegrationMenewStock($requestData['group_id']);
                break;
            case 'getUnitsIntegrationMenewBilling':
                $response = BiController::getUnitsIntegrationMenewBilling($requestData['group_id']);
                break;
            case 'ZigRegisterBilling':
                if (isset($requestData)) {
                $response = BiController::ZigRegisterBilling($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro sales ausente'];
                }
                break;
            case 'upsertBiSalesZig':
                if (isset($requestData)) {
                $response = BiController::upsertBiSalesZig($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro sales ausente'];
                }
                break;
            case 'ZigUpdateStatics':
                if (
                    isset(
                        $requestData['data'],
                        $requestData['lojaId'],
                        $requestData['descontos'],
                        $requestData['gorjeta'],
                        $requestData['total_clientes']
                    )
                ) {
                    $response = BiController::ZigUpdateStatics(
                        $requestData['data'],
                        $requestData['lojaId'],
                        $requestData['descontos'],
                        $requestData['gorjeta'],
                        $requestData['total_clientes']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes: data, lojaId, descontos, gorjeta ou total_clientes.'];
                }
                break;

            case 'generateDashboardData':
                if (isset($requestData['system_unit_id'], $requestData['start_date'], $requestData['end_date'])) {
                    $response = BiController::generateDashboardData($requestData['system_unit_id'], $requestData['start_date'], $requestData['end_date']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou data ausentes'];
                }
                break;
            case 'getSalesByInsumos':
                if (isset($requestData['system_unit_id'], $requestData['data_inicio'], $requestData['data_fim'])) {
                    $response = BiController::getSalesByInsumos($requestData['system_unit_id'], $requestData['data_inicio'], $requestData['data_fim']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou datas ausentes'];
                }
                break;
            case 'getMovsByProd':
                if (isset($requestData['system_unit_id'], $requestData['data'], $requestData['product'])) {
                    $response = MovimentacaoController::getMovsByProd($requestData['system_unit_id'], $requestData['data'], $requestData['product']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou data ausentes'];
                }
                break;
            case 'registerJobExecution':
                if (isset($requestData['nome_job'], $requestData['system_unit_id'], $requestData['custom_code'], $requestData['inicio'])) {
                    $response = BiController::registerJobExecution($requestData);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'importCompras':
                if (isset($requestData['usuario_id'], $requestData['produtos'])) {
                    $response = MovimentacaoController::importCompras($requestData['usuario_id'], $requestData['produtos']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'importMovBySales':
                if (isset($requestData['system_unit_id'], $requestData['data'])) {
                    $response = MovimentacaoController::importMovBySales($requestData['system_unit_id'], $requestData['data']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'importMovBySalesCons':
                if (isset($requestData['system_unit_id'], $requestData['data'])) {
                    $response = MovimentacaoController::importMovBySalesCons($requestData['system_unit_id'], $requestData['data']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'getDiferencasEstoque':
                if (isset($requestData['start_date'], $requestData['end_date'], $requestData['system_unit_id'])) {
                    $response = MovimentacaoController::getDiferencasEstoque($requestData['start_date'], $requestData['end_date'], $requestData['system_unit_id'], $requestData['tipo'] ?? null);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'createAjusteCusto':
                if ($requestData['system_unit_id'] && $requestData['ajuste_date'] && $requestData['itens'] && $requestData['usuario_id']) {
                    $response = MovimentacaoController::ajustarPrecoCusto($requestData['system_unit_id'], $requestData['ajuste_date'], $requestData['itens'], $requestData['usuario_id']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'ajustarPrecoCustoPorGrupo':
                if ($requestData['grupo_id'] && $requestData['ajuste_date'] && $requestData['itens'] && $requestData['usuario_id']) {
                    $response = MovimentacaoController::ajustarPrecoCustoPorGrupo($requestData['grupo_id'], $requestData['ajuste_date'], $requestData['itens'], $requestData['usuario_id']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'createAjusteSaldo':
                if ($requestData['system_unit_id'] && $requestData['ajuste_date'] && $requestData['itens'] && $requestData['usuario_id']) {
                    $response = MovimentacaoController::ajustarSaldo($requestData['system_unit_id'], $requestData['ajuste_date'], $requestData['itens'], $requestData['usuario_id']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'getDatesByDoc':
                if ($requestData['system_unit_id'] && $requestData['doc']) {
                    $response = MovimentacaoController::getDatesByDoc($requestData['system_unit_id'], $requestData['doc']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'updateDataByDoc':
                if ($requestData['system_unit_id'] && $requestData['doc'] && $requestData['data']) {
                    $response = MovimentacaoController::updateDataByDoc($requestData['system_unit_id'], $requestData['doc'], $requestData['data']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'extratoInsumo':
                if (isset($requestData['system_unit_id'], $requestData['insumo_id'], $requestData['dt_inicio'], $requestData['dt_fim'])) {
                    $response = MovimentacaoController::extratoInsumo($requestData['system_unit_id'], $requestData['insumo_id'], $requestData['dt_inicio'], $requestData['dt_fim']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, insumo_id, dt_inicio ou dt_fim ausentes'];
                }
                break;
            case 'extratoCopEntreBalancos':
                if (isset($requestData['system_unit_id'], $requestData['dt_inicio'], $requestData['dt_fim'])) {
                    $response = MovimentacaoController::extratoCopEntreBalancos($requestData['system_unit_id'],$requestData['dt_inicio'], $requestData['dt_fim']);
                }
                else{
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, dt_inicio ou dt_fim ausentes'];
                }
                break;
            case 'getCurvaABCCompras':
                $response = MovimentacaoController::getCurvaABCCompras($requestData);
                break;
            case 'getCurvaABCFaturamento':
            $response = MovimentacaoController::getCurvaABCFaturamento($requestData);
            break;
            case 'consolidateSalesByUnit':
                if (isset($requestData['system_unit_id'], $requestData['dt_inicio'], $requestData['dt_fim'])) {
                    $response = BiController::consolidateSalesByUnit($requestData['system_unit_id'], $requestData['dt_inicio'], $requestData['dt_fim']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, dt_inicio ou dt_fim ausentes'];
                }
                break;
            case 'consolidateSalesByGroup':
                if (isset($requestData['group_id'], $requestData['dt_inicio'], $requestData['dt_fim'])) {
                    $response = BiController::consolidateSalesByGroup($requestData['group_id'], $requestData['dt_inicio'], $requestData['dt_fim']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros group_id, dt_inicio ou dt_fim ausentes'];
                }
                break;
            case 'persistSales':
                if (isset($requestData)) {
                    $response = BiController::persistSales($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro sales ausente'];
                }
                break;
            case 'persistMovimentoCaixa':
                if (isset($requestData)) {
                    $response = BiController::persistMovimentoCaixa($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro sales ausente'];
                }
                break;

            // Métodos para InsumoController
            case 'getInsumosUsage':
                if (isset($requestData['system_unit_id'])) {
                    $response = InsumoController::getInsumosUsage($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'getInsumoConsumption':
                // LÓGICA ANTIGA (Média Histórica)
                // O Frontend manda: system_unit_id, dates, productCodes, username
                if (isset($requestData['system_unit_id']) && isset($requestData['dates']) && isset($requestData['productCodes']) && isset($requestData['username'])) {

                    $response = NecessidadesRefactorController::getInsumoConsumption(
                        $requestData['system_unit_id'],
                        $requestData['dates'],
                        $requestData['productCodes'],
                        $requestData['username']
                    );

                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros para consumo histórico ausentes'];
                }
                break;

            case 'getInsumoProjection':
                // LÓGICA NOVA (Meta Futura)
                // O Frontend manda: system_unit_id, daysOfWeek, insumoIds, user_id
                if (isset($requestData['system_unit_id']) && isset($requestData['daysOfWeek']) && isset($requestData['insumoIds'])) {

                    // Nota: Verifique se o método está no NecessidadesRefactorController ou NecessidadesController
                    $response = ProjecaoVendasController::getInsumoProjection(
                        $requestData['system_unit_id'],
                        $requestData['daysOfWeek'], // Array de inteiros [0, 1, 2...]
                        $requestData['insumoIds'],  // Array de códigos
                        $requestData['user_id'] ?? $requestData['username'] // Fallback caso o nome varie
                    );

                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, daysOfWeek ou insumoIds ausentes'];
                }
                break;

            case 'getInsumoProjectionMatriz':
                $response = ProjecaoVendasController::getInsumoProjectionMatriz($requestData);
                break;

            case 'getInsumoConsumptionTop3':
                if (isset($requestData['system_unit_id']) && isset($requestData['dates']) && isset($requestData['productCodes']) && isset($requestData['username'])) {
                    $response = NecessidadesRefactorController::getInsumoConsumptionTop3($requestData['system_unit_id'], $requestData['dates'], $requestData['productCodes'], $requestData['username']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, dates ou productCodes ausentes'];
                }
                break;
            case 'getInsumoConsumptionMatriz':
                if (isset($requestData['system_unit_id']) && isset($requestData['dates']) && isset($requestData['productCodes'])) {
                    $response = NecessidadesRefactorController::getInsumoConsumptionMatriz($requestData['system_unit_id'], $requestData['dates'], $requestData['productCodes']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, dates ou productCodes ausentes'];
                }
                break;
            case 'getFiliaisProduction':
                if (isset($requestData['username'])) {
                    $response = NecessidadesRefactorController::getFiliaisProduction($requestData['username']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro $unit_matriz_id ausente'];
                }
                break;
            case 'getFiliaisByMatriz':
                if (isset($requestData['unit_matriz_id'])) {
                    $response = NecessidadesRefactorController::getFiliaisByMatriz($requestData['unit_matriz_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro $unit_matriz_id ausente'];
                }
                break;

            // Métodos para ComposicaoController
            case 'createComposicao':
                $response = ComposicaoController::createComposicao($requestData);
                break;
            case 'updateComposicao':
                if (isset($requestData['id'])) {
                    $response = ComposicaoController::updateComposicao($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getComposicaoById':
                if (isset($requestData['id'])) {
                    $response = ComposicaoController::getComposicaoById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listComposicoes':
                if (isset($requestData['unit_id'])) {
                    $response = ComposicaoController::listComposicoes($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listFichaTecnica':
                if (isset($requestData['unit_id']) && isset($requestData['product_id'])) {
                    $response = ComposicaoController::listFichaTecnica($requestData['product_id'], $requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros unit_id ou product_id ausente'];
                }
                break;
            case 'listProdutosComComposicaoStatus':
                if (isset($requestData['unit_id'])) {
                    $response = ComposicaoController::listProdutosComComposicaoStatus($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'getComposicaoByProduto':
                if (isset($requestData['unit_id']) && isset($requestData['product_id'])) {
                    $response = ComposicaoController::getComposicaoByProduto($requestData['product_id'], $requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros unit_id ou product_id ausente'];
                }
                break;
            case 'saveComposition':
                if (isset($requestData['product_id']) && isset($requestData['unit_id']) && isset($requestData['insumos'])) {
                    $data = [
                        'product_id' => $requestData['product_id'],
                        'system_unit_id' => $requestData['unit_id'],
                        'insumos' => $requestData['insumos']
                    ];
                    $response = ComposicaoController::saveComposition($data);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros product_id, unit_id ou insumos ausente'];
                }
                break;
            case 'importCompositions':
                if (isset($requestData['system_unit_id']) && isset($requestData['itens'])) {
                    $response = ComposicaoController::importCompositions(
                        $requestData['system_unit_id'],
                        $requestData['itens']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou itens ausente'];
                }
                break;
            case 'importCompositionsZig':
                if (isset($requestData['system_unit_id']) && isset($requestData['itens'])) {
                    $response = ComposicaoController::importCompositionsZig(
                        $requestData['system_unit_id'],
                        $requestData['itens']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou itens ausente'];
                }
                break;
            case 'importProductions':
                if (isset($requestData['system_unit_id']) && isset($requestData['itens'])) {
                    $response = ComposicaoController::importProductions(
                        $requestData['system_unit_id'],
                        $requestData['itens']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou itens ausente'];
                }
                break;

            // Métodos para ProducaoController
            case 'createProducao':
                if (isset($requestData['items']) && is_array($requestData['items'])) {
                    $response = ProducaoController::createProducao($requestData['items']);
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Parâmetro "items" ausente ou inválido. Esperado: array de produções.'
                    ];
                }
                break;
            case 'updateProducao':
                if (isset($requestData['updates']) && is_array($requestData['updates'])) {
                    $response = ProducaoController::updateProducao($requestData['updates']);
                } else {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => 'Parâmetro "updates" ausente ou inválido. Deve ser um array de atualizações.'];
                }
                break;
            case 'getProducaoById':
                if (isset($requestData['product_id']) && isset($requestData['unit_id'])) {
                    $response = ProducaoController::getProducaoById($requestData['product_id'], $requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros product_id ou unit_id ausente'];
                }
                break;
            case 'deleteProducao':
                if (isset($requestData['product_id'], $requestData['unit_id'])) {
                    $response = ProducaoController::deleteProducao($requestData['product_id'], $requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros product_id ou unit_id ausente'];
                }
                break;
            case 'listProducoes':
                if (isset($requestData['unit_id'])) {
                    $response = ProducaoController::listProducoes($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'expandFichaProducao':
                if (isset($requestData['product_id'], $requestData['unit_id'])) {
                    $response = ProducaoController::expandFichaProducao($requestData['product_id'], $requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => 'Parâmetros product_id e unit_id são obrigatórios'];
                }
                break;
            case 'executeProduction':
                $response = ProducaoController::executeProduction($requestData);
                break;
            case 'executeProductionBatch':
                $response = ProducaoController::executeProductionBatch($requestData);
                break;
            case "listProducoesRealizadasDetalhado":
                $response = ProducaoController::listProducoesRealizadasDetalhado($requestData);
                break;
            case 'listProducoesRealizadasBasico':
                $response = ProducaoController::listProducoesRealizadasBasico($requestData);
                break;
            // Métodos para ModeloBalancoController
            case 'createModelo':
                if (isset($requestData['nome']) && isset($requestData['usuario_id']) && isset($requestData['itens'])) {
                    $response = ModeloBalancoController::createModelo($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes: nome, usuario_id ou itens'];
                }
                break;
            case 'editModelo':
                if (isset($requestData['nome']) && isset($requestData['usuario_id']) && isset($requestData['itens'])) {
                    $response = ModeloBalancoController::editModelo($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes: nome, usuario_id ou itens'];
                }
                break;
            case 'updateModelo':
                if (isset($requestData['id'])) {
                    $response = ModeloBalancoController::updateModelo($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'deleteModelo':
                if (isset($requestData['id'])) {
                    $response = ModeloBalancoController::deleteModelo($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listModelos':
                $response = ModeloBalancoController::listModelos();
                break;
            case 'listItensByModelo':
                if (isset($requestData['id'])) {
                    $response = ModeloBalancoController::listItensByModelo($requestData['id'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'deleteItemFromModelo':
                if (isset($requestData['modelo_id']) && isset($requestData['produto_id'])) {
                    $response = ModeloBalancoController::deleteItemFromModelo($requestData['modelo_id'], $requestData['produto_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros modelo_id ou produto_id ausente'];
                }
                break;
            case 'getModelByTag':
                if (isset($requestData['tag'])) {
                    $response = ModeloBalancoController::getModelByTag($requestData['tag']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro tag ausente'];
                }
                break;
            case  'saveBalanceItems':
                if (isset($requestData['system_unit_id']) && isset($requestData['itens'])) {
                    $response = MovimentacaoController::saveBalanceItems($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou itens ausente'];
                }
                break;
            case  'savePerdaItems':
                if (isset($requestData['system_unit_id']) && isset($requestData['itens'])) {
                    $response = MovimentacaoController::savePerdaItems($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou itens ausente'];
                }
                break;
            case 'getRelatorioPerdas':
                if (isset($requestData['system_unit_id'])) {
                    $response = MovimentacaoController::getRelatorioPerdas($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ausente'];
                }
                break;
            case 'listBalance':
                if (isset($requestData['system_unit_id'])) {
                    // Verifica se as datas estão presentes e as atribui, caso contrário passa null
                    $data_inicial = isset($requestData['data_inicial']) ? $requestData['data_inicial'] : null;
                    $data_final = isset($requestData['data_final']) ? $requestData['data_final'] : null;

                    // Chama o metodo listBalance com os parâmetros corretos
                    $response = MovimentacaoController::listBalance($requestData['system_unit_id'], $data_inicial, $data_final);
                } else {
                    http_response_code(400); // Código HTTP 400 para Bad Request
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'getBalanceByDoc':
                if (isset($requestData['system_unit_id']) && isset($requestData['doc'])) {
                    $response = MovimentacaoController::getBalanceByDoc($requestData['system_unit_id'], $requestData['doc']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou doc ausente'];
                }
                break;
            case 'getLastBalance':
                if (isset($requestData['system_unit_id']) && isset($requestData['produto'])) {
                    $response = MovimentacaoController::getLastBalance($requestData['system_unit_id'], $requestData['produto']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou doc ausente'];
                }
                break;
            case 'getLastBalanceByMatriz':
                if (isset($requestData['matriz_id']) && isset($requestData['produto'])) {
                    $response = MovimentacaoController::getLastBalanceByMatriz($requestData['matriz_id'], $requestData['produto']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou doc ausente'];
                }
                break;

                // NecessidadesRefactorController
            case 'getProductsToBuy':
                if (isset($requestData['matriz_id']) && isset($requestData['vendas'])) {
                    $response = NecessidadesRefactorController::getProductsToBuys($requestData['matriz_id'], $requestData['vendas']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou doc ausente'];
                }
                break;
            case 'getConsumptionBuy':
                if (isset($requestData['matriz_id']) && isset($requestData['insumoIds']) && isset($requestData['dias'])) {
                    $response = NecessidadesRefactorController::getConsumptionBuy(
                        $requestData['matriz_id'],
                        $requestData['insumoIds'],
                        $requestData['dias']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro matriz_id, insumoIds ou dias ausente'];
                }
                break;
            case 'listItemVenda':
                if (isset($requestData['unit_id'])) {
                    $response = ProductController::listItemVenda($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'calculateInsumosByItens':
                if (isset($requestData['system_unit_id']) && isset($requestData['itens'])) {
                    $response = NecessidadesRefactorController::calculateInsumosByItens(
                        $requestData['system_unit_id'],
                        $requestData['itens']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro matriz_id, insumoIds ou dias ausente'];
                }
                break;

            case 'createTransferItems':
                $response = MovimentacaoController::createTransferItems($requestData);
                break;
            case 'getTransferenciaByKey':
                $response = MovimentacaoController::getTransferenciaByKey($requestData);
                break;
            case 'getTransferenciasComCustos':
                $response = MovimentacaoController::getTransferenciasComCustos($requestData);
                break;
            case 'contarDiasSemana':
                $response = NecessidadesRefactorController::contarDiasSemana($requestData['dias']);
                break;
            case 'ultimasQuatroDatasPorDiaSemana':
                $response = NecessidadesRefactorController::ultimasQuatroDatasPorDiaSemana();
                break;
            // Métodos para DashboardController
            case 'getDashboardData':
                if (isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $dt_inicio = $requestData['dt_inicio'];
                    $dt_fim = $requestData['dt_fim'];

                    // Verifica se datas são válidas
                    $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $dt_inicio);
                    $fim = DateTime::createFromFormat('Y-m-d H:i:s', $dt_fim);

                    if (!$inicio || !$fim) {
                        http_response_code(400);
                        $response = ['error' => 'Datas inválidas. Use o formato Y-m-d H:i:s'];
                    } elseif ($fim < $inicio) {
                        http_response_code(400);
                        $response = ['error' => 'A data final deve ser maior ou igual à data inicial.'];
                    } else {
                        $response = DashboardController::generateHourlySalesByStore($dt_inicio, $dt_fim);
                    }
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'ListMov':
                $response = DashboardController::ListMov($requestData['dt_inicio'], $requestData['dt_fim']);
                break;
            case 'generateResumoFinanceiroPorLoja':
                if (isset($requestData['lojaid']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateResumoFinanceiroPorLoja(
                        $requestData['lojaid'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros lojaid, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'getLojaIdBySystemUnitId':
                if (isset($requestData['system_unit_id'])) {
                    $response = DashboardController::getLojaIdBySystemUnitId($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'gerarPdfSemanalFaturamento':
                if (isset($requestData['group_id'])) {
                    $response = DashboardController::gerarPdfSemanalFaturamento($requestData['group_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id e nome_loja são obrigatórios.'];
                }
                break;
            case 'gerarPdfFaturamento':
                if (isset($requestData['group_id'])) {
                    $periodo = isset($requestData['periodo']) && in_array($requestData['periodo'], ['semanal', 'mensal'])
                        ? $requestData['periodo']
                        : 'semanal';

                    $response = DashboardController::gerarPdfFaturamento($requestData['group_id'], $periodo);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro group_id é obrigatório.'];
                }
                break;
            case 'gerarPdfSemanalCompras':
                if (isset($requestData['group_id'])) {
                    $response = DashboardController::gerarPdfSemanalCompras($requestData['group_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id e nome_loja são obrigatórios.'];
                }
                break;
            case 'gerarPdfCompras':
                if (isset($requestData['group_id'])) {
                    $periodo = isset($requestData['periodo']) && in_array($requestData['periodo'], ['semanal', 'mensal'])
                        ? $requestData['periodo']
                        : 'semanal';

                    $response = DashboardController::gerarPdfCompras($requestData['group_id'], $periodo);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro group_id é obrigatório.'];
                }
                break;
            case 'getIntervalosSemanais':
                    $response = DashboardController::getIntervalosSemanais();
                break;
            case 'getIntervalosMensais':
                $response = DashboardController::getIntervalosMensais();
                break;
            case 'getIntervalosDiarios':
                    $response = DashboardController::getIntervalosDiarios();
                break;
            case 'getResumoMeiosPagamento':
                if (isset($requestData['lojaid']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::getResumoMeiosPagamento(
                        $requestData['lojaid'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'getResumoModosVenda':
                if (isset($requestData['lojaid']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::getResumoModosVenda(
                        $requestData['lojaid'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateResumoFinanceiroPorLojaDiario':
                if (isset($requestData['lojaid']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateResumoFinanceiroPorLojaDiario(
                        $requestData['lojaid'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros lojaid, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'getRankingVendasProdutos':
                if (isset($requestData['lojaid']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::getRankingVendasProdutos(
                        $requestData['lojaid'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros lojaid, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateResumoFinanceiroPorGrupo':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateResumoFinanceiroPorGrupo(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateHourlySalesByGrupo':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateHourlySalesByGrupo(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateResumoFinanceiroPorGrupoDiario':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateResumoFinanceiroPorGrupoDiario(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'getResumoModosVendaPorGrupo':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::getResumoModosVendaPorGrupo(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'getResumoMeiosPagamentoPorGrupo':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::getResumoMeiosPagamentoPorGrupo(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'getRankingVendasProdutosPorGrupo':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::getRankingVendasProdutosPorGrupo(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'getRankingTop3ProdutosPorGrupo':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::getRankingTop3ProdutosPorGrupo(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateResumoEstoquePorGrupo':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateResumoEstoquePorGrupo(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateResumoEstoquePorGrupoNAuth':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateResumoEstoquePorGrupoNAuth(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateCmvEvolucao':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateCmvEvolucao(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateTopComprasPorProduto':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateTopComprasPorProduto(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateTopComprasPorFornecedor':
            if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                $response = DashboardController::generateTopComprasPorFornecedor(
                    $requestData['grupoId'],
                    $requestData['dt_inicio'],
                    $requestData['dt_fim']
                );
            } else {
                http_response_code(400);
                $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
            }
            break;
            case 'generateCmvPorProduto':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateCmvPorProduto(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateCmvPorCategoria':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateCmvPorCategoria(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateNotasPorGrupo':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateNotasPorGrupo(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;
            case 'generateComprasPorGrupo':
                if (isset($requestData['grupoId']) && isset($requestData['dt_inicio']) && isset($requestData['dt_fim'])) {
                    $response = DashboardController::generateComprasPorGrupo(
                        $requestData['grupoId'],
                        $requestData['dt_inicio'],
                        $requestData['dt_fim']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros grupoId, dt_inicio e dt_fim são obrigatórios.'];
                }
                break;



            case 'listFornecedores':
                if (isset($requestData['system_unit_id'])) {
                    $response = FinanceiroFornecedorController::listFornecedores($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'addItensFornecedor':
                if (
                    isset($requestData['system_unit_id'], $requestData['fornecedor_id']) &&
                    is_array($requestData['itens'])
                ) {
                    $response = FornecedoresController::addItensFornecedor(
                        $requestData['system_unit_id'],
                        $requestData['fornecedor_id'],
                        $requestData['itens']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes ou inválidos.'];
                }
                break;
            case 'editItemFornecedor':
                if (
                    isset($requestData['system_unit_id'], $requestData['fornecedor_id'], $requestData['produto_codigo'])
                ) {
                    $response = FornecedoresController::editItemFornecedor(
                        $requestData['system_unit_id'],
                        $requestData['fornecedor_id'],
                        $requestData['produto_codigo'],
                        $requestData
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes: system_unit_id, fornecedor_id, produto_codigo'];
                }
                break;
            case 'removeItemFornecedor':
                if (
                    isset($requestData['system_unit_id'], $requestData['fornecedor_id'], $requestData['produto_codigo'])
                ) {
                    $response = FornecedoresController::removeItemFornecedor(
                        $requestData['system_unit_id'],
                        $requestData['fornecedor_id'],
                        $requestData['produto_codigo']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes: system_unit_id, fornecedor_id, produto_codigo'];
                }
                break;
            case 'listItensFornecedor':
                if(isset($requestData['system_unit_id'], $requestData['fornecedor_id'])){
                    $response = FornecedoresController::listItensFornecedor(
                        $requestData['system_unit_id'],
                        $requestData['fornecedor_id']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou fornecedor_id ausente'];
                }
                break;


            // Métodos para ProductionController
            case 'createProduction':
                if (isset($requestData['unit_id'])) {
                    $response = ProductionController::createProduction($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'updateProduction':
                if (isset($requestData['unit_id'])) {
                    if (isset($requestData['id'])) {
                        $response = ProductionController::updateProduction($requestData['id'], $requestData);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro id ausente'];
                    }
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'updateCompravel':
                if (isset($requestData['system_unit_id'], $requestData['itens']) && is_array($requestData['itens'])) {
                    $response = ProductionController::updateCompravel(
                        $requestData['system_unit_id'],
                        $requestData['itens']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios: system_unit_id e itens (array de objetos com codigo_produto e compravel)'];
                }
                break;

            case 'listProdutosCompraveis':
                if (isset($requestData['system_unit_id'])) {
                    $response = ProductionController::listProdutosCompraveis($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro obrigatório: system_unit_id'];
                }
                break;
            case 'listProdutosComFichaStatus':
                if (!isset($requestData['system_unit_id'])) {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                    break;
                }

                $response = ProductionController::listProdutosComFichaStatus($requestData['system_unit_id']);
                break;
            case 'getFichaTecnica':
                if (!isset($requestData['system_unit_id']) || !isset($requestData['product_id'])) {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios: system_unit_id, product_id'];
                    break;
                }

                $response = ProductionController::getFichaTecnica(
                    $requestData['system_unit_id'],
                    $requestData['product_id']
                );
                break;
            case 'saveFichaTecnica':
                if (
                    !isset($requestData['system_unit_id']) ||
                    !isset($requestData['product_id']) ||
                    !isset($requestData['insumos']) ||
                    !is_array($requestData['insumos'])
                ) {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios: system_unit_id, product_id, insumos[]'];
                    break;
                }

                $response = ProductionController::saveFichaTecnica(
                    $requestData['system_unit_id'],
                    $requestData['product_id'],
                    $requestData['insumos']
                );
                break;
            case 'listInsumosDisponiveis':
                if (!isset($requestData['system_unit_id'])) {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                    break;
                }

                $response = ProductionController::listInsumosDisponiveis($requestData['system_unit_id']);
                break;




            case 'getProductionById':
                if (isset($requestData['unit_id'])) {
                    if (isset($requestData['id'])) {
                        $response = ProductionController::getProductionById($requestData['id']);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro id ausente'];
                    }
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listProductions':
                if (isset($requestData['unit_id'])) {
                    $response = ProductionController::listProductions($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para SalesController
            case 'createSale':
                $response = SalesController::createSale($requestData);
                break;
            case 'updateSale':
                if (isset($requestData['id'])) {
                    $response = SalesController::updateSale($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getSaleById':
                if (isset($requestData['id'])) {
                    $response = SalesController::getSaleById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listSales':
                if (isset($requestData['unit_id'])) {
                    $response = SalesController::listSales($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para TransfersController
            case 'createTransfer':
                $response = TransfersController::createTransfer($requestData);
                break;
            case 'updateTransfer':
                if (isset($requestData['id'])) {
                    $response = TransfersController::updateTransfer($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getTransferById':
                if (isset($requestData['id'])) {
                    $response = TransfersController::getTransferById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listTransfers':
                if (isset($requestData['unit_id'])) {
                    $response = TransfersController::listTransfers($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;

            case 'getProjeccoes':
                if (isset($requestData['unit_id'])) {
                    // Retorna a lista formatada (com os dias zerados ou preenchidos)
                    $response = ProjecaoVendasController::getProjeccoes($requestData['unit_id']);

                } else {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => 'Parâmetro unit_id ausente'];
                }
                break;

            case 'saveProjecaoBatch':
                // Verifica se tem ID da unidade e o array de itens
                if (isset($requestData['unit_id']) && isset($requestData['itens'])) {
                    $response = ProjecaoVendasController::saveBatch($requestData['unit_id'], $requestData['itens']);
                } else {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => 'Parâmetros unit_id ou itens ausentes'];
                }
                break;

            case 'clearProjecaoProduto':
                // Verifica unidade e código do produto para limpar
                if (isset($requestData['unit_id']) && isset($requestData['product_codigo'])) {
                    $response = ProjecaoVendasController::clearProductProjections($requestData['unit_id'], $requestData['product_codigo']);
                } else {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => 'Parâmetros unit_id ou product_codigo ausentes'];
                }
                break;

            // Métodos para ProductController
            case 'createProduto':
                $response = ProductController::createProduto($requestData);
                break;
            case 'replicarProdutosEComposicoes':
                if (isset($requestData['codigos'], $requestData['system_unit_id_origem'], $requestData['system_units_destino'])) {
                    $response = ProductController::replicarProdutosEComposicoes(
                        $requestData['codigos'],
                        (int)$requestData['system_unit_id_origem'],
                        $requestData['system_units_destino']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros obrigatórios ausentes'];
                }
                break;

            case 'updateProduto':
                if (isset($requestData)) {
                    $response = ProductController::updateProduto($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro codigo ausente'];
                }
                break;
            case 'getProductById':
                if (isset($requestData['codigo'])) {
                    $response = ProductController::getProductById($requestData['codigo'], $requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listProducts':
                if (isset($requestData['unit_id'])) {
                    $response = ProductController::listProducts($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listProdutosDetalhado':
                if (isset($requestData['unit_id'])) {
                    $response = ProductController::listProdutosDetalhado($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'getProdutoByEan':
                if (isset($requestData['ean'])) {
                    $response = ProductController::getProdutoByEan($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro ean ausente'];
                }
                break;
            case 'recalcularCustosPorFichas':
                if (isset($requestData['system_unit_id'])) {
                    $response = ProductController::recalcularCustosPorFichas($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'deleteProduct':
                if ( isset($requestData['user_id']) && isset($requestData['codigo']) && isset($requestData['system_unit_id'])) {
                    $response = ProductController::deleteProduct($requestData['codigo'], $requestData['system_unit_id'], $requestData['user_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros codigo ou system_unit_id ausente'];
                }
                break;
            case 'getProximoCodigoProduto':
                if (isset($requestData['unit_id']) && isset($requestData['is_insumo'])) {
                    $response = ProductController::getProximoCodigoProduto($requestData['unit_id'], $requestData['is_insumo']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros unit_id ou is_insumo ausente'];
                }
                break;
            case 'checkCodigoDisponivel':
                if (isset($requestData['unit_id']) && isset($requestData['codigo'])) {
                    $response = ProductController::checkCodigoDisponivel($requestData['codigo'], $requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros unit_id ou is_insumo ausente'];
                }
                break;

            case 'listarCategorias':
                $unitId = $requestData['system_unit_id'];

                if (!$unitId) {
                    $response = ['success' => false, 'message' => 'ID da unidade não informado.'];
                } else {
                    $response = CategoriasController::listar((int)$unitId);
                }
                break;

            case 'getCategoria':
                $id = $requestData['id'];

                if (!$id) {
                    $response = ['success' => false, 'message' => 'ID da categoria não informado.'];
                } else {
                    $response = CategoriasController::getById((int)$id);
                }
                break;

            case 'salvarCategoria':
                $response = CategoriasController::salvar($requestData);
                break;

            case 'excluirCategoria':
                $id = $requestData['id'];

                if (!$id) {
                    $response = ['success' => false, 'message' => 'ID da categoria não informado.'];
                } else {
                    $response = CategoriasController::excluir((int)$id);
                }
                break;

            case 'getProximoCodigo':
                $unitId = $requestData['system_unit_id'];

                if (!$unitId) {
                    $response = ['success' => false, 'message' => 'Unidade não informada.'];
                } else {
                    $response = CategoriasController::proximoCodigo((int)$unitId);
                }
                break;

            case 'listModelosWithProducts':
                if (isset($requestData['unit_id'])) {
                    $response = ModeloBalancoController::listModelosWithProducts($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'toggleModeloStatus':

                if (isset($requestData['unit_id'])) {
                    $response = ModeloBalancoController::toggleModeloStatus($requestData['unit_id'], $requestData['tag'], $requestData['status']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro ausentes'];
                }
                break;
            case 'replicateModelo':
                if (isset($requestData)) {
                    $response = ModeloBalancoController::replicateModelo($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros ausentes'];
                }
                break;
            case 'getProductCards':
                if (isset ($requestData['system_unit_id'])) {
                    $response = ProductController::getProductCards($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'importarProdutosPorLoja':
                if ($requestData['system_unit_id'] && $requestData['itens'] && $requestData['usuario_id']) {
                    $response = ProductController::importarProdutosPorLoja(
                        $requestData['system_unit_id'],
                        $requestData['itens'],
                        $requestData['usuario_id']
                    );
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'importarProdutosVenda':
                if ($requestData['system_unit_id'] && $requestData['itens'] && $requestData['usuario_id']) {
                    $response = ProductController::importarProdutosVenda(
                        $requestData['system_unit_id'],
                        $requestData['itens'],
                        $requestData['usuario_id']
                    );
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'importarNotasFiscaisEntrada':
                if ($requestData['system_unit_id'] && $requestData['notas'] && $requestData['usuario_id']) {
                    $response = NotaFiscalEntradaController::importarNotasFiscaisEntrada(
                        $requestData['system_unit_id'],
                        $requestData['notas'],
                        $requestData['usuario_id']
                    );
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'getNotaFinanceiroPayload':
                if ($requestData['system_unit_id'] && $requestData['chave_acesso']) {
                    $response = NotaFiscalEntradaController::getNotaFinanceiroPayload($requestData);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'getNotaItensFornecedorPayload':
                if ($requestData['system_unit_id'] && $requestData['chave_acesso']) {
                    $response = NotaFiscalEntradaController::getNotaItensFornecedorPayload($requestData);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
                case 'vincularItemNotaFornecedor':
                if ($requestData['system_unit_id'] && $requestData['produto_codigo'] && $requestData['fornecedor_id']) {
                    $response = NotaFiscalEntradaController::vincularItemNotaFornecedor($requestData);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
                case 'lancarItensNotaNoEstoque':
                if ($requestData['system_unit_id'] && $requestData['chave_acesso']) {
                    $response = NotaFiscalEntradaController::lancarItensNotaNoEstoque($requestData);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'getItensCompradosPorPeriodo':
                if ($requestData['system_unit_id'] && $requestData['data_inicio'] && $requestData['data_fim']) {
                    $response = NotaFiscalEntradaController::getItensCompradosPorPeriodo($requestData);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'getComprasPorPeriodo':
                $response = NotaFiscalEntradaController::getComprasPorPeriodo($requestData);
                break;
            case 'lancarItensNotaAvulsaNoEstoque':
                if ($requestData['system_unit_id'] && $requestData['itens'] && $requestData['usuario_id']) {
                    $response = NotaFiscalEntradaController::lancarItensNotaAvulsaNoEstoque($requestData);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
                
            case 'importarProdutosZig':
                if ($requestData['system_unit_id'] && $requestData['itens'] && $requestData['usuario_id']) {
                    $response = ProductController::importarProdutosZig(
                        $requestData['system_unit_id'],
                        $requestData['itens'],
                        $requestData['usuario_id']
                    );
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'getProdutosComSkuZig':
                if ($requestData) {
                    $response = ProductController::getProdutosComSkuZig(
                        $requestData
                    );
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'deleteProduto':
                if (isset($requestData['unit_id']) && isset($requestData['codigo']) && isset($requestData['user'])) {
                    $response = ProductController::deleteProduto($requestData['codigo'], $requestData['unit_id'], $requestData['user']);
                } else {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => 'Parâmetros unit_id ou codigo ausente'];
                }
                break;
            case 'getUltimasMovimentacoesProduto':
                if (!isset($requestData['system_unit_id']) || !isset($requestData['codigo_produto'])) {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros unit_id ou codigo_produto ausentes'];
                    break;
                }
                $response = ProductController::getUltimasMovimentacoesProduto($requestData['system_unit_id'], $requestData['codigo_produto']);
                break;
                case 'getProdutoDetalhado':
                if (!isset($requestData['system_unit_id']) || !isset($requestData['codigo'])) {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros unit_id ou codigo_produto ausentes'];
                    break;
                }
                $response = ProductController::getProdutoDetalhado($requestData['system_unit_id'], $requestData['codigo']);
                break;
            case 'listProdutosPorMargem':
                $response = ProductController::listProdutosPorMargem($requestData['system_unit_id']);
                break;
            case 'getRaioXProduto':
                if (!isset($requestData['system_unit_id']) || !isset($requestData['product_id'])) {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou codigo_produto ausentes'];
                    break;
                }
                $response = ProductController::getRaioXProduto($requestData['system_unit_id'], $requestData['product_id']);
                break;
            case 'updateEanBatch':
                $response = ProductController::updateEanBatchByUnit($requestData);
                break;


            case 'listProductsByCategory':
                if (isset($requestData['unit_id'])) {
                    $response = ProductController::listProductsByCategory($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para CategoriesController
            case 'createCategoria':
                if (isset($requestData['unit_id'])) { // Verifica se o unit_id está presente
                    $response = CategoriesController::createCategoria($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listInsumos':
                if (isset($requestData['unit_id'])) {
                    $response = ProductController::listInsumos($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listCompraveis':
                if (isset($requestData['unit_id'])) {
                    $response = ProductController::listCompraveis($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'updateCategoria':
                if (isset($requestData['unit_id'])) { // Verifica se o unit_id está presente
                    if (isset($requestData['id'])) {
                        $response = CategoriesController::updateCategoria($requestData['id'], $requestData[$data], $requestData['unit_id']);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro id ausente'];
                    }
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'getCategoriaById':
                if (isset($requestData['unit_id'])) { // Verifica se o unit_id está presente
                    if (isset($requestData['id'])) {
                        $response = CategoriesController::getCategoriaById($requestData['id'], $requestData['unit_id']);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro id ausente'];
                    }
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            case 'listCategorias':
                if (isset($requestData['unit_id'])) { // Verifica se o unit_id está presente
                    $response = CategoriesController::listCategorias($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para ProductController
            case 'efetivarTransacoes':
                if (isset($requestData['system_unit_id']) && isset($requestData['doc'])) {
                    $response = MovimentacaoController::efetivarTransacoes($requestData['system_unit_id'], $requestData['doc']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'listarMovimentacoesPendentes':
                if (isset($requestData['system_unit_id'])) {
                    $response = MovimentacaoController::listarMovimentacoesPendentes($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
                break;
            case 'rejeitarMovimentacao':
                if (isset($requestData['system_unit_id']) && isset($requestData['doc']) && isset($requestData['usuario_id'])) {
                    $response = MovimentacaoController::rejeitarMovimentacao($requestData['system_unit_id'], $requestData['doc'], $requestData['usuario_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou doc ausente'];
                }
                break;
            case 'listarMovimentacoesPorData':
                if (isset($requestData['system_unit_id']) && isset($requestData['data_inicial']) && isset($requestData['data_final'])) {
                    $response = MovimentacaoController::listarMovimentacoesPorData($requestData['system_unit_id'], $requestData['data_inicial'], $requestData['data_final']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id, data_inicial ou data_final ausente'];
                }
                break;
            case 'getMovimentacao':
                if (isset($requestData['system_unit_id']) && isset($requestData['doc'])) {
                    $response = MovimentacaoController::getMovimentacao($requestData['system_unit_id'], $requestData['doc']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getLastMov':
                if (isset($requestData['system_unit_id']) && isset($requestData['tipo'])) {
                    $response = MovimentacaoController::getLastMov($requestData['system_unit_id'], $requestData['tipo']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou tipo ausente'];
                }
                break;
            case 'validateTagExists':
                if (isset($requestData['tag'])) {
                    $response = ModeloBalancoController::validateTagExists($requestData['tag']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro tag ausente'];
                }
                break;
            // Métodos para ConsolidationEstoqueController
            case 'getStatusConsolidationMonth':
                if (isset($requestData['month']) && isset($requestData['year']) && isset($requestData['system_unit_id'])) {
                    $response = ConsolidationEstoqueController::getStatusConsolidationMonth($requestData['system_unit_id'], $requestData['month'], $requestData['year']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro month, year ou system_unit_id ausente'];
                }
                break;
            case 'GetInfoConsolidationEstoque':
                if (isset($requestData['system_unit_id']) && isset($requestData['data'])) {
                    $response = BiController::GetInfoConsolidationEstoque($requestData['system_unit_id'], $requestData['data']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou data ausente'];
                }
                break;
            case 'GetInfoConsolidationEstoqueSemBalanco':
                if (isset($requestData['system_unit_id']) && isset($requestData['data'])) {
                    $response = BiController::GetInfoConsolidationEstoqueSemBalanco($requestData['system_unit_id'], $requestData['data']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou data ausente'];
                }
                break;
            case 'persistStockDifferences':
                if (isset($requestData['system_unit_id']) && isset($requestData['date']) && isset($requestData['data'])) {
                    $response = BiController::persistStockDifferences($requestData['system_unit_id'], $requestData['date'], $requestData['data']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou data ausente'];
                }
                break;
            case 'salvarContato':
                if (isset($requestData['nome'], $requestData['telefone'])) {
                    $response = DisparosController::salvarContato($requestData);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = ['status' => 'error', 'message' => 'Campos obrigatórios: nome, telefone.'];
                }
                break;

            case 'toggleContatoAtivo':
                if (isset($requestData['id'])) {
                    $response = DisparosController::toggleContatoAtivo($requestData['id']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = ['status' => 'error', 'message' => 'Campo obrigatório: id.'];
                }
                break;

            case 'getContatoById':
                if (isset($requestData['id'])) {
                    $response = DisparosController::getContatoById($requestData['id']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = ['status' => 'error', 'message' => 'Campo obrigatório: id.'];
                }
                break;

            case 'getContato':
                if (isset($requestData['telefone'])) {
                    $response = DisparosController::getContato($requestData['telefone']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = ['status' => 'error', 'message' => 'Campo obrigatório: telefone.'];
                }
                break;

            case 'listContatos':
                $response = DisparosController::listContatos();
                http_response_code(200);
                break;

            case 'salvarRelacionamentosPorContato':
                if (isset($requestData['relacionamentos'], $requestData['usuario_id']) && is_array($requestData['relacionamentos'])) {
                    $response = DisparosController::salvarRelacionamentosPorContato($requestData['relacionamentos'], $requestData['usuario_id']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = ['status' => 'error', 'message' => 'Campos obrigatórios: relacionamentos (array), usuario_id.'];
                }
                break;

            case 'getRelacionamentosByContato':
                if (isset($requestData['id_contato'])) {
                    $response = DisparosController::getRelacionamentosByContato($requestData['id_contato']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = ['status' => 'error', 'message' => 'Campo obrigatório: id_contato.'];
                }
                break;
            case 'getContatosByDisparo':
                if (isset($requestData['id_disparo'])) {
                    $response = DisparosController::getContatosByDisparo($requestData['id_disparo']);
                    http_response_code(200);
                } else {
                    http_response_code(400);
                    $response = ['status' => 'error', 'message' => 'Campo obrigatório: id_contato.'];
                }
                break;

            case 'listRelacionamentos':
                $response = DisparosController::listRelacionamentos();
                http_response_code(200);
                break;

            case 'listarDisparos':
                $response = DisparosController::listDisparos();
                http_response_code(200);
                break;

            case 'listarGrupos':
                $response = DisparosController::listGrupos();
                http_response_code(200);
                break;

            // ===================== DISPAROS =====================
            case 'createOrUpdateDisparo':
                $response = DisparosController::createOrUpdateDisparo($requestData);
                break;

            case 'toggleDisparoAtivo':
                if (isset($requestData['id'])) {
                    $response = DisparosController::toggleDisparoAtivo($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;

            case 'getDisparoById':
                if (isset($requestData['id'])) {
                    $response = DisparosController::getDisparoById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;

            case 'listAllDisparos':
                $response = DisparosController::listAllDisparos();
                break;

// ===================== LOGS DE DISPAROS =====================
            case 'listDisparosLogs':
                $response = DisparosController::listDisparosLogs();
                break;

            case 'listDisparosLogsByDisparo':
                if (isset($requestData['id_disparo'])) {
                    $response = DisparosController::listDisparosLogsByDisparo($requestData['id_disparo']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;

                // ===================== UNIDADES DE SISTEMA =====================

            case 'salvarSystemUnit':
                $response = SystemUnitController::salvarSystemUnit($requestData);
                break;

            case 'toggleSystemUnitStatus':
                if (isset($requestData['id'])) {
                    $response = SystemUnitController::toggleStatus($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;

            case 'getSystemUnitById':
                if (isset($requestData['id'])) {
                    $response = SystemUnitController::getSystemUnitById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;

            case 'getConfigGroupByUnitId':
                if (isset($requestData['system_unit_id'])) {
                    $response = SystemUnitController::getConfigGroupByUnitId($requestData['system_unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listSystemUnitsSameGroup':
                if (isset($requestData['system_unit_id']) && isset($requestData['incluirAtual'])) {
                    $response = SystemUnitController::listSystemUnitsSameGroup($requestData['system_unit_id'], $requestData['incluirAtual']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listSystemUnitsByGrupo':
                if (isset($requestData['grupo']) && isset($requestData['incluirAtivasSomente'])) {
                    $response = SystemUnitController::listSystemUnitsByGrupo($requestData['grupo'], $requestData['incluirAtivasSomente']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;

            case 'listMenus':
                $response = MenuMobileController::listMenus();
                break;

            case 'createMenu':
                $response = MenuMobileController::createMenu($requestData);
                break;

            case 'updateMenu':
                $response = MenuMobileController::updateMenu($requestData);
                break;

            case 'deleteMenu':
                if (isset($requestData['id'])) {
                    $response = MenuMobileController::deleteMenu($requestData['id']);
                } else {
                    $response = ['success' => false, 'message' => 'ID não informado'];
                }
                break;

            case 'getMenuById':
                if (isset($requestData['id'])) {
                    $response = MenuMobileController::getMenuById($requestData['id']);
                } else {
                    $response = ['success' => false, 'message' => 'ID não informado'];
                }
                break;
            case 'toggleMenuStatus':
                if (isset($requestData['id'])) {
                    $response = MenuMobileController::toggleStatus($requestData['id']);
                } else {
                    $response = ["success" => false, "message" => "ID não informado."];
                }
                break;
            case 'createOrUpdateMenuPermission':
                    $response = MenuMobileController::createOrUpdateMenuPermission($requestData);
                break;

            case 'getPermissionsByMenu':
                if (isset($requestData['menu_id'])) {
                    $response = MenuMobileController::getPermissionsByMenu($requestData['menu_id']);
                } else {
                    $response = ["success" => false, "message" => "ID do menu não informado."];
                }
                break;

            case 'deleteMenuPermission':
                if (isset($requestData['id'])) {
                    $response = MenuMobileController::deleteMenuPermission($requestData['id']);
                } else {
                    $response = ["success" => false, "message" => "ID do menu não informado."];
                }
                break;

            case 'getActivePushTokens':
                $response = MenuMobileController::getActivePushTokens();
                break;

            case 'sendPushNotification':
                $response = MenuMobileController::sendPushNotification($requestData);
                break;


            case 'listSystemUnits':
                $response = SystemUnitController::listSystemUnits();
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
