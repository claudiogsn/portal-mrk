<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'controllers/OrderController.php';
require_once 'controllers/ClienteController.php';
require_once 'controllers/EventController.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['method']) && isset($data['data'])) {
    $method = $data['method'];
    $requestData = $data['data'];
    $requestToken = $data['token'];

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
            // Métodos para OrderController
            case 'createOrder':
                $response = OrderController::createOrder($requestData);
                break;
            case 'updateOrder':
                if (isset($requestData['id']) && isset($requestData)) {
                    $response = OrderController::updateOrder($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetros order_id ou data ausentes');
                }
                break;
            case 'createOrderItem':
                $response = OrderController::createOrderItem($requestData);
                break;
            case 'updateOrderItem':
                if (isset($requestData['id'])) {
                    $response = OrderController::updateOrderItem($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetro id ausente');
                }
                break;
            case 'createOrderPayment':
                $response = OrderController::createOrderPayment($requestData);
                break;
            case 'updateOrderPayment':
                if (isset($requestData['id'])) {
                    $response = OrderController::updateOrderPayment($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetro id ausente');
                }
                break;
            case 'createOrderService':
                $response = OrderController::createOrderService($requestData);
                break;
            case 'updateOrderService':
                if (isset($requestData['id']) && isset($requestData)) {
                    $response = OrderController::updateOrderService($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetros id ou data ausentes');
                }
                break;
            case 'getOrderDetails':
                if (isset($requestData['order_id'])) {
                    $response = OrderController::getOrderDetails($requestData['order_id']);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetro order_id ausente');
                }
                break;
            case 'listOrders':
                $response = OrderController::listOrders();
                break;

            case 'listMaterials':
                $response = OrderController::listMaterials();
                break;

            case 'listServices':
                $response = OrderController::listServices();
                break;
            
            case 'listPaymentMethods':
                $response = OrderController::listPaymentMethods();
                break;

            // Métodos para ClienteController
            case 'createCliente':
                $response = ClienteController::createCliente($requestData);
                break;
            case 'updateCliente':
                if (isset($requestData['id'])) {
                    $response = ClienteController::updateCliente($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetro id ausente');
                }
                break;
            case 'getClienteById':
                if (isset($requestData['id'])) {
                    $response = ClienteController::getClienteById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetro id ausente');
                }
                break;
            case 'listClients':
                $response = ClienteController::listClients();
                break;

            // Métodos para EventController
            case 'createEvent':
                $response = EventController::createEvent($requestData);
                break;
            case 'updateEvent':
                if (isset($requestData['id']) && isset($requestData)) {
                    $response = EventController::updateEvent($requestData['id'], $requestData);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetros id ou data ausentes');
                }
                break;
            case 'getEventById':
                if (isset($requestData['id'])) {
                    $response = EventController::getEventById($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetro id ausente');
                }
                break;
            case 'deleteEvent':
                if (isset($requestData['id'])) {
                    $response = EventController::deleteEvent($requestData['id']);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetro id ausente');
                }
                break;
            case 'listEvents':
                $response = EventController::listEvents();
                break;

            case 'validateCPF':
                if (isset($requestData['cpf'])) {
                    $response = ClienteController::validateCPF($requestData['cpf']);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetro cpf ausente');
                }
                break;
            case 'validateCNPJ':
                if (isset($requestData['cnpj'])) {
                    $response = ClienteController::validateCNPJ($requestData['cnpj']);
                } else {
                    http_response_code(400);
                    $response = array('error' => 'Parâmetro cnpj ausente');
                }
                break;

            default:
                http_response_code(405);
                $response = array('error' => 'Método não suportado');
                break;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        $response = array('error' => 'Erro interno do servidor: ' . $e->getMessage());
        echo json_encode($response);
    }
} else {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(array('error' => 'Parâmetros inválidos'));
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
