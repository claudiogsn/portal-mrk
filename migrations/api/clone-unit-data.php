<?php
header('Content-Type: application/json');

// Ajuste os caminhos conforme sua estrutura de pastas real
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

global $pdo;

// --- 1. Inicialização de Variáveis de Log ---
$startTime = microtime(true);
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$requestRaw = file_get_contents("php://input");
$data = json_decode($requestRaw, true);

// Captura dados enviados pelo Front
$source = (int)($data['source_system_unit_id'] ?? 0);
$target = (int)($data['target_system_unit_id'] ?? 0);
$userId = $data['user_id'] ?? 'system'; // Login ou ID do usuário vindo da URL
$token  = $data['token'] ?? null;

// Variável padrão de resposta
$response = [
    "success" => false,
    "message" => "Erro desconhecido"
];
$statusCode = 200; // HTTP Status Code

try {
    // --- 2. Validações ---
    if ($source <= 0 || $target <= 0) {
        throw new Exception("IDs de unidade inválidos.");
    }
    if ($source === $target) {
        throw new Exception("Origem e destino não podem ser iguais.");
    }

    // --- 3. Processamento (Clonagem) ---
    $pdo->beginTransaction();

    /**
     * 🔥 LIMPA DESTINO (Ordem Reversa de Dependência)
     * Primeiro apaga os filhos (FKs), depois os pais.
     */
    $tablesToDelete = [
        'modelos_balanco_itens', // FK de modelos_balanco
        'modelos_balanco',       // Tabela com constraint Unique
        'productions',           // FK de products
        'compositions',          // FK de products
        'products',              // FK de categorias
        'categorias'
    ];

    foreach ($tablesToDelete as $table) {
        // Deleta os dados da unidade de destino
        $pdo->exec("DELETE FROM `$table` WHERE system_unit_id = $target");
    }

    /**
     * 🔁 CLONAGEM GENÉRICA (Tabelas Simples)
     * Apenas tabelas que não possuem constraints UNIQUE complexas.
     * REMOVIDO: modelos_balanco e seus itens daqui.
     */
    $cloneOrder = [
        'categorias',
        'products',
        'compositions',
        'productions'
    ];

    foreach ($cloneOrder as $table) {
        // Função que está no helpers.php
        cloneTableBySystemUnit($pdo, $table, $source, $target);
    }

    /**
     * 🔁 CLONAGEM ESPECÍFICA (Customizada)
     * Resolve o problema da TAG duplicada e reconecta Pai/Filho corretamente.
     */
    cloneModelosBalanco($pdo, $source, $target);

    // Se tudo der certo, commita a transação
    $pdo->commit();

    // Sucesso
    $response = [
        "success" => true,
        "message" => "Clonagem concluída com sucesso de ID $source para ID $target"
    ];

} catch (Exception $e) {
    // Erro: Desfaz tudo
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $statusCode = 400; // Bad Request
    $response = [
        "success" => false,
        "message" => "Erro na clonagem: " . $e->getMessage()
    ];
}

// --- 4. Gravação do Log (api_access_logs) ---
try {
    $endTime = microtime(true);
    $executionTimeMs = ($endTime - $startTime) * 1000;

    // Prepara dados para salvar (pode remover o token do log se desejar privacidade)
    $logRequest = $data;
    // unset($logRequest['token']);

    $stmtLog = $pdo->prepare("
        INSERT INTO api_access_logs 
        (user_login, method_name, status_code, execution_time_ms, request_data, response_data, ip_address, created_at) 
        VALUES 
        (:user, :method, :status, :time, :req, :res, :ip, NOW())
    ");

    $stmtLog->execute([
        ':user'   => substr($userId, 0, 100), // Limita tamanho para caber no varchar
        ':method' => 'clone_unit_data',       // Nome identificador da ação
        ':status' => $statusCode,
        ':time'   => $executionTimeMs,
        ':req'    => json_encode($logRequest),
        ':res'    => json_encode($response),
        ':ip'     => $ipAddress
    ]);

} catch (Exception $logEx) {
    // Se falhar o log, apenas registra no erro do PHP, mas não quebra a resposta para o usuário
    error_log("Falha ao salvar log de API: " . $logEx->getMessage());
}

// --- 5. Retorno Final ---
http_response_code($statusCode);
echo json_encode($response);