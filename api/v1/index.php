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
header("Access-Control-Allow-Origin: http://localhost");
header('Content-Type: application/json; charset=utf-8');

require_once 'controllers/ComposicaoController.php';
require_once 'controllers/DashboardController.php';
require_once 'controllers/EstoqueController.php';
require_once 'controllers/FornecedoresController.php';
require_once 'controllers/InsumoController.php';
require_once 'controllers/NecessidadesController.php';
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
    $noAuthMethods = ['validateCPF', 'persistMovimentoCaixa','validateCNPJ','getModelByTag','saveBalanceItems','getUnitsByGroup','registerJobExecution','persistSales','consolidateSalesByGroup','importMovBySalesCons'];

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
            // Métodos para BiController
            case 'getUnitsByGroup':
                if (isset($requestData['group_id'])) {
                    $response = BiController::getUnitsByGroup($requestData['group_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro group_id ausente'];
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
                if (isset($requestData['system_unit_id'], $requestData['data'])) {
                    $response = BiController::getSalesByInsumos($requestData['system_unit_id'], $requestData['data']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id ou data ausentes'];
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
            case 'importComprasCSV':
                if (isset($requestData['usuario_id'], $requestData['itens'], $requestData['data_importacao'])){
                    $response = MovimentacaoController::importComprasCSV($requestData['usuario_id'], $requestData['itens'], $requestData['data_importacao']);
                    http_response_code(200);
                }else{
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'importMovBySales':
                if (isset($requestData['system_unit_id'], $requestData['data'])){
                    $response = MovimentacaoController::importMovBySales($requestData['system_unit_id'], $requestData['data']);
                    http_response_code(200);
                }else{
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'importMovBySalesCons':
                if (isset($requestData['system_unit_id'], $requestData['data'])){
                    $response = MovimentacaoController::importMovBySalesCons($requestData['system_unit_id'], $requestData['data']);
                    http_response_code(200);
                }else{
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'getDiferencasEstoque':
                if (isset($requestData['start_date'], $requestData['end_date'],$requestData['system_unit_id'])) {
                    $response = MovimentacaoController::getDiferencasEstoque($requestData['start_date'], $requestData['end_date'],$requestData['system_unit_id']);
                    http_response_code(200);
                }else{
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
                }else{
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
                }else{
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
                }else{
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'getDatesByDoc':
                if ($requestData['system_unit_id'] && $requestData['doc']){
                    $response = MovimentacaoController::getDatesByDoc($requestData['system_unit_id'], $requestData['doc']);
                    http_response_code(200);
                }else{
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Missing required fields.'
                    ];
                }
                break;
            case 'updateDataByDoc':
                if ($requestData['system_unit_id'] && $requestData['doc'] && $requestData['data']){
                    $response = MovimentacaoController::updateDataByDoc($requestData['system_unit_id'], $requestData['doc'], $requestData['data']);
                    http_response_code(200);
                }else{
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
                if (isset($requestData['system_unit_id']) && isset($requestData['dates']) && isset($requestData['productCodes']) && isset($requestData['username'])) {
                    $response = NecessidadesController::getInsumoConsumption($requestData['system_unit_id'], $requestData['dates'], $requestData['productCodes'], $requestData['username']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, dates ou productCodes ausentes'];
                }
                break;
            case 'getInsumoConsumptionMatriz':
                if (isset($requestData['system_unit_id']) && isset($requestData['dates']) && isset($requestData['productCodes'])) {
                    $response = NecessidadesController::getInsumoConsumptionMatriz($requestData['system_unit_id'], $requestData['dates'], $requestData['productCodes']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros system_unit_id, dates ou productCodes ausentes'];
                }
                break;
            case 'getFiliaisProduction':
                if (isset($requestData['username'])) {
                    $response = NecessidadesController::getFiliaisProduction($requestData['username']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro $unit_matriz_id ausente'];
                }
                break;
            case 'getFiliaisByMatriz':
                        if (isset($requestData['unit_matriz_id'])) {
                            $response = NecessidadesController::getFiliaisByMatriz($requestData['unit_matriz_id']);
                        } else {
                            http_response_code(400);
                            $response = ['error' => 'Parâmetro $unit_matriz_id ausente'];
                        }
                        break;
            // Métodos para ComposicaoController
            case 'createComposicao':
                $response = ComposicaoController::createComposicao($requestData);break;
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
                $response = ComposicaoController::listFichaTecnica($requestData['product_id'],$requestData['unit_id']);
            } else {
                http_response_code(400);
                $response = ['error' => 'Parâmetros unit_id ou product_id ausente'];
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
            case 'listBalance':
                if (isset($requestData['system_unit_id'])) {
                    // Verifica se as datas estão presentes e as atribui, caso contrário passa null
                    $data_inicial = isset($requestData['data_inicial']) ? $requestData['data_inicial'] : null;
                    $data_final = isset($requestData['data_final']) ? $requestData['data_final'] : null;

                    // Chama o método listBalance com os parâmetros corretos
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
            case 'getProductsToBuy':
                if (isset($requestData['matriz_id']) && isset($requestData['vendas'])) {
                    $response = NecessidadesController::getProductsToBuys($requestData['matriz_id'], $requestData['vendas']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou doc ausente'];
                }
                break;
            case 'getConsumptionBuy':
                if (isset($requestData['matriz_id']) && isset($requestData['insumoIds']) && isset($requestData['dias'])) {
                    $response = NecessidadesController::getConsumptionBuy(
                        $requestData['matriz_id'],
                        $requestData['insumoIds'],
                        $requestData['dias']
                    );
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro matriz_id, insumoIds ou dias ausente'];
                }
                break;
            case 'createTransferItems':
                    $response = MovimentacaoController::createTransferItems($requestData);
                    break;
            case 'contarDiasSemana':
                $response = NecessidadesController::contarDiasSemana($requestData['dias']);
                break;
            case 'ultimasQuatroDatasPorDiaSemana':
                $response = NecessidadesController::ultimasQuatroDatasPorDiaSemana();
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
                $response = DashboardController::ListMov($requestData['dt_inicio'],$requestData['dt_fim']);
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


            // Métodos para EstoqueController
            case 'createEstoque':
                $response = EstoqueController::createEstoque($requestData);
                break;
            case 'updateEstoque':
                if (isset($requestData['id'])) {
                    $response = EstoqueController::updateEstoque($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getEstoqueById':
                if (isset($requestData['id'])) {
                    $response = EstoqueController::getEstoqueById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listEstoque':
                if (isset($requestData['unit_id'])) {
                    $response = EstoqueController::listEstoque($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;

            // Métodos para FornecedoresController
            case 'createFornecedor':
                $response = FornecedoresController::createFornecedor($requestData);
                break;
            case 'updateFornecedor':
                if (isset($requestData['id'])) {
                    $response = FornecedoresController::updateFornecedor($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getFornecedorById':
                if (isset($requestData['id'])) {
                    $response = FornecedoresController::getFornecedorById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listFornecedores':
                if (isset($requestData['unit_id'])) {
                    $response = FornecedoresController::listFornecedores($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;
            // Métodos para NecessidadesController
            case 'createNecessidade':
                $response = NecessidadesController::createNecessidade($requestData);
                break;
            case 'updateNecessidade':
                if (isset($requestData['id'])) {
                    $response = NecessidadesController::updateNecessidade($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getNecessidadeById':
                if (isset($requestData['id'])) {
                    $response = NecessidadesController::getNecessidadeById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'listNecessidades':
                if (isset($requestData['unit_id'])) {
                    $response = NecessidadesController::listNecessidades($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
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
            // Métodos para ProductController
            case 'createProduct':
                $response = ProductController::createProduct($requestData);
                break;
            case 'updateProduct':
                if (isset($requestData['codigo']  )) {
                    $response = ProductController::updateProduct($requestData['codigo'], $requestData, $requestData['system_unit_id']);
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
                    $response = ModeloBalancoController::toggleModeloStatus($requestData['unit_id'],$requestData['tag'],$requestData['status']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro ausentes'];
                }
                break;
            case 'getProductCards':
                if (isset ($requestData['system_unit_id'])){
                        $response = ProductController::getProductCards($requestData['system_unit_id']);
                }else{
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ausente'];
                }
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
                        $response = MovimentacaoController::listarMovimentacoesPorData($requestData['system_unit_id'],$requestData['data_inicial'],$requestData['data_final']);
                    } else {
                        http_response_code(400);
                        $response = ['error' => 'Parâmetro system_unit_id, data_inicial ou data_final ausente'];
                    }
                    break;
            case 'getMovimentacao':
                    if (isset($requestData['system_unit_id']) && isset($requestData['doc'])) {
                        $response = MovimentacaoController::getMovimentacao($requestData['system_unit_id'],$requestData['doc']);
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
                    $response = ConsolidationEstoqueController::getStatusConsolidationMonth($requestData['month'], $requestData['year'],$requestData['system_unit_id']);
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
            case 'persistStockDifferences':
                if (isset($requestData['system_unit_id']) && isset($requestData['date'])&& isset($requestData['data'])) {
                    $response = BiController::persistStockDifferences($requestData['system_unit_id'], $requestData['date'], $requestData['data']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro system_unit_id ou data ausente'];
                }
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
function verifyToken($token) {
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
