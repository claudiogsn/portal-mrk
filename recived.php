<?php

// Caminho absoluto para a pasta "receiveds"
$folder = __DIR__ . '/logs';

// Cria a pasta se não existir (com permissão 0777)
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
    chmod($folder, 0777); // Garante permissão total após criar
}

// Lê o corpo cru da requisição (JSON enviado pela Z-API)
$rawInput = file_get_contents('php://input');

// Nome único baseado na data/hora e um ID aleatório
$filename = $folder . '/webhook_' . date('Ymd_His') . '_' . uniqid() . '.json';

// Grava o conteúdo no arquivo
file_put_contents($filename, $rawInput);

// Responde para o remetente (Z-API)
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'Webhook recebido com sucesso',
    'file' => basename($filename)
]);
