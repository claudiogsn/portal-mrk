<?php
header('Content-Type: application/json; charset=utf-8');

// Requerendo os controladores
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

// Pegando o corpo da requisição
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['method']) && isset($data['data'])) {
    $method = $data['method'];
    $requestData = $data['data'];
    $requestToken = $data['token'];

    // Métodos que não precisam de autenticação
    $noAuthMethods = ['validateCPF', 'validateCNPJ'];

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
                    $response = ComposicaoController::listFichaTecnica($requestData['product_id'],$requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetros unit_id ou product_id ausente'];
                }
                    break;

            // Métodos para DashboardController
            case 'getDashboardData':
                if (isset($requestData['unit_id'])) {
                    $response = DashboardController::getDashboardData($requestData['unit_id']);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
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
                if (isset($requestData['id'])) {
                    $response = ProductController::updateProduct($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro id ausente'];
                }
                break;
            case 'getProductById':
                if (isset($requestData['id'])) {
                    $response = ProductController::getProductById($requestData['id']);
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

            // Métodos para CategoriesController
            case 'createCategoria':
                if (isset($requestData['unit_id'])) { // Verifica se o unit_id está presente
                    $response = CategoriesController::createCategoria($requestData);
                } else {
                    http_response_code(400);
                    $response = ['error' => 'Parâmetro unit_id ausente'];
                }
                break;

            case 'updateCategoria':
                if (isset($requestData['unit_id'])) { // Verifica se o unit_id está presente
                    if (isset($requestData['id'])) {
                        $response = CategoriesController::updateCategoria($requestData['id'], $requestData, $requestData['unit_id']);
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
        if ($result['logout_time'] == "0000-00-00 00:00:00") {
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
