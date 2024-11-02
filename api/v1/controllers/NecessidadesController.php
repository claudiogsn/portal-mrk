<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class NecessidadesController {

    // Metodo para buscar os produtos associados a múltiplos insumos de uma vez só
    private static function getProductsByInsumos($insumoIds) {
        global $pdo;
        $insumoIdsPlaceholders = implode(',', array_fill(0, count($insumoIds), '?'));
        $stmt = $pdo->prepare("SELECT product_id, insumo_id, quantity FROM compositions WHERE insumo_id IN ($insumoIdsPlaceholders)");
        $stmt->execute($insumoIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getSystemUnitData($system_unit_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT custom_code, user_api, pass_api FROM system_unit WHERE id = :id");
        $stmt->bindParam(':id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Metodo para chamar a API Menew e obter o token
    private static function getMenewToken($user_api, $pass_api) {
        $loginPayload = json_encode([
            "token" => null,
            "requests" => [
                "jsonrpc" => "2.0",
                "method" => "Usuario/login",
                "params" => [
                    "usuario" => $user_api,
                    "token" => $pass_api
                ],
                "id" => "1"
            ]
        ]);

        $ch = curl_init('https://public-api.prod.menew.cloud/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $loginPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        $responseArray = json_decode($response, true);
        return $responseArray['result'] ?? null;  // Retorna o token se existir
    }

    // Método para fazer requisições de vendas para múltiplos produtos em uma só requisição por data
    private static function fetchSalesData($token, $custom_code, $date) {

        $salesPayload = json_encode([
            "token" => $token,
            "requests" => [
                "jsonrpc" => "2.0",
                "method" => "itensvenda-read",
                "params" => [
                    "dtinicio" => $date,
                    "dtfim" => $date,
                    "lojas" => $custom_code,
                    "conferido" => 0
                ],
                "id" => "10000"
            ]
        ]);


        $ch = curl_init('https://public-api.prod.menew.cloud/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $salesPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        $responseArray = json_decode($response, true);
        return $responseArray['result']['data'] ?? [];
    }

    public static function getInsumoConsumption($system_unit_id, $dates, $insumoIds) {
        global $pdo;

        // Busca todos os produtos que usam os insumos fornecidos
        $compositions = self::getProductsByInsumos($insumoIds);

        // Mapeia as composições para facilitar o acesso
        $insumoProductMap = [];
        foreach ($compositions as $composition) {
            $insumoProductMap[$composition['insumo_id']][] = $composition;
        }

        // Variável para armazenar o consumo total de cada insumo
        $insumoConsumption = [];
        foreach ($insumoIds as $insumo_id) {
            $insumoConsumption[$insumo_id] = [
                'id' => $insumo_id,
                'sales' => 0,
                'margem' => 0,
                'saldo' => 0,
                'recomendado' => 0,
            ];
        }

        // Itera sobre as datas e faz uma única requisição por data
        foreach ($dates as $date) {
            // Para cada insumo, calcula o consumo total baseado nas vendas e nas composições
            foreach ($insumoIds as $insumo_id) {
                if (isset($insumoProductMap[$insumo_id])) {
                    foreach ($insumoProductMap[$insumo_id] as $composition) {
                        $product_id = $composition['product_id'];
                        $insumoQuantity = $composition['quantity']; // Quantidade de insumo usada no produto

                        // Busca os dados de vendas do produto específico na tabela _bi_sales
                        $salesData = self::fetchSalesDataFromBiSales($system_unit_id, $product_id, $date);

                        // Atualiza a quantidade total de vendas do insumo
                        if (isset($salesData['total_sales'])) {
                            $insumoConsumption[$insumo_id]['sales'] += $salesData['total_sales'] * $insumoQuantity;
                        }
                    }
                }
            }
        }

        // Atualiza saldo e calcula margem e recomendado
        foreach ($insumoIds as $insumo_id) {
            // Obtém o saldo atual do insumo
            $stockData = self::getProductStock($system_unit_id, $insumo_id);
            $insumoConsumption[$insumo_id]['saldo'] = $stockData['saldo'] ?? 0;

            // Calcula margem e recomendado
            $insumoConsumption[$insumo_id]['margem'] = $insumoConsumption[$insumo_id]['sales'] * 0.15; // 15% da venda
            $insumoConsumption[$insumo_id]['recomendado'] = $insumoConsumption[$insumo_id]['sales'] + $insumoConsumption[$insumo_id]['margem'] - $insumoConsumption[$insumo_id]['saldo'];
        }

        return array_values($insumoConsumption); // Retorna um array indexado
    }

// Método para buscar dados de vendas da tabela _bi_sales para um produto específico
    private static function fetchSalesDataFromBiSales($system_unit_id, $product_id, $date) {
        global $pdo;

        $stmt = $pdo->prepare("
        SELECT SUM(quantidade) AS total_sales
        FROM _bi_sales
        WHERE system_unit_id = :system_unit_id AND cod_material = :product_id AND data_movimento = :date
        GROUP BY cod_material
    ");
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_STR); // Aqui, product_id é o código do item
        $stmt->bindParam(':date', $date, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC); // Retorna apenas o total_sales do produto específico
    }



    public static function getFiliaisByMatriz($unit_matriz_id) {
        global $pdo;

        $sql = "
            SELECT
                su.id AS filial_id,
                su.name AS filial_nome
            FROM
                system_unit_rel sur
            JOIN
                system_unit su ON sur.unit_filial = su.id
            WHERE
                sur.unit_matriz = ?;
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$unit_matriz_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getProductStock($system_unit_id, $codigo) {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT COALESCE(saldo, 0) AS saldo
            FROM products 
            WHERE system_unit_id = :system_unit_id 
              AND codigo = :codigo
        ");
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(':codigo', $codigo, PDO::PARAM_INT);

        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            return ['success' => true, 'saldo' => $product['saldo']];
        } else {
            return ['success' => false, 'message' => 'Produto não encontrado ou saldo indisponível.'];
        }
    }






}
