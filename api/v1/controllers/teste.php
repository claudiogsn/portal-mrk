<?php

date_default_timezone_set('America/Recife');

function fetchDiference($groupId, $date) {
    try {
        // Passo 1: Chamada para a API getUnitsByGroup para obter lojas vinculadas ao grupo
        echo "Iniciando chamada à API getUnitsByGroup para o group_id: {$groupId}...\n";

        $groupResponse = apiRequest('https://portal.mrksolucoes.com.br/api/v1/index.php', [
            'method' => 'getUnitsByGroup',
            'data' => ['group_id' => $groupId]
        ]);

        // Verificando se a resposta tem dados
        if (empty($groupResponse)) {
            throw new Exception("Nenhuma loja encontrada para o grupo fornecido.");
        }

        foreach ($groupResponse as $loja) {
            $customCode = $loja['custom_code'];
            $systemUnitId = $loja['system_unit_id'];

            echo "Iniciando chamada à API itemvenda para a loja: {$customCode}...\n";
            $inicio = microtime(true);

            // Chamada para a API do Menew para obter os dados de item venda
            try {


                $DifResponse = apiRequest('https://portal.mrksolucoes.com.br/api/v1/index.php', [
                    'method' => 'criarDiferencasEstoque',
                    'data' => [
                        'system_unit_id' => $systemUnitId,
                        'data' => $date,
                    ]
                ]);

                $items = $DifResponse['result'];
                if (!is_array($items) || count($items) === 0) {
                    echo "Nenhum item encontrado para a loja {$customCode}.\n";
                    continue;
                }


                echo "Bloco de dados da loja {$systemUnitId} enviado com sucesso.\n";

                // Log de execução do job
                $final = microtime(true);
                apiRequest('https://portal.mrksolucoes.com.br/api/v1/index.php', [
                    'method' => 'registerJobExecution',
                    'data' => [
                        'nome_job' => 'diferenca-estoque-php',
                        'system_unit_id' => $systemUnitId,
                        'custom_code' => $customCode,
                        'inicio' => date('c', (int)$inicio),
                        'final' => date('c', (int)$final)
                    ]
                ]);

                $executionTime = round(($final - $inicio) / 60, 2);
                echo "Custom Code: {$customCode} - Execução: {$executionTime} minutos\n";

            } catch (Exception $e) {
                echo "Erro ao processar loja {$customCode}: " . $e->getMessage() . "\n";
            }
        }

        return ['success' => true, 'message' => 'Dados enviados com sucesso'];
    } catch (Exception $e) {
        echo "Erro ao buscar ou salvar dados: " . $e->getMessage() . "\n";
        return ['success' => false, 'message' => 'Erro ao buscar ou salvar dados'];
    }
}

function apiRequest($url, $data) {
    $ch = curl_init($url);

    // Definir as opções do cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    // Executar a requisição e obter a resposta
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $errorMessage = "Erro de cURL: " . curl_error($ch);
        curl_close($ch);
        throw new Exception($errorMessage);
    }

    curl_close($ch);

    // Imprimir a resposta bruta para depuração
    echo "Resposta da API ({$url}): " . $response . "\n";
    echo "<p>----------------------------------------------------</p>\n";

    // Decodificar a resposta JSON
    $decodedResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar resposta JSON: " . json_last_error_msg());
    }

    return $decodedResponse;
}

// Exemplo de execução com a data de ontem
$groupId = 1;
$yesterday = date('Y-m-d', strtotime('-1 day'));
fetchDiference($groupId, $yesterday);

?>
