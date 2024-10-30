<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class NecessidadesController {

    public static function createNecessidade($data) {
        global $pdo;

        $insumo_id = $data['insumo_id'];
        $estimated_need = $data['estimated_need'];
        $sobras = $data['sobras'];
        $date = $data['date'];
        $unit_id = $data['unit_id'];

        $stmt = $pdo->prepare("
            INSERT INTO necessidades (insumo_id, estimated_need, sobras, date, unit_id)
            VALUES (:insumo_id, :estimated_need, :sobras, :date, :unit_id)
        ");
        $stmt->bindParam(':insumo_id', $insumo_id);
        $stmt->bindParam(':estimated_need', $estimated_need);
        $stmt->bindParam(':sobras', $sobras);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':unit_id', $unit_id);

        if ($stmt->execute()) {
            return ['success' => 'Necessidade criada com sucesso.'];
        } else {
            throw new Exception('Erro ao criar necessidade.');
        }
    }

    public static function updateNecessidade($id, $data) {
        global $pdo;

        $insumo_id = $data['insumo_id'];
        $estimated_need = $data['estimated_need'];
        $sobras = $data['sobras'];
        $date = $data['date'];
        $unit_id = $data['unit_id'];

        $stmt = $pdo->prepare("
            UPDATE necessidades
            SET insumo_id = :insumo_id, estimated_need = :estimated_need, sobras = :sobras, date = :date, unit_id = :unit_id
            WHERE id = :id
        ");
        $stmt->bindParam(':insumo_id', $insumo_id);
        $stmt->bindParam(':estimated_need', $estimated_need);
        $stmt->bindParam(':sobras', $sobras);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':unit_id', $unit_id);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return ['success' => 'Necessidade atualizada com sucesso.'];
        } else {
            throw new Exception('Erro ao atualizar necessidade.');
        }
    }

    public static function getNecessidadeById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM necessidades WHERE id = :id");
        $stmt->bindParam(':id', $id);

        $stmt->execute();
        $necessidade = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($necessidade) {
            return $necessidade;
        } else {
            throw new Exception('Necessidade não encontrada.');
        }
    }

    public static function listNecessidades($unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT * FROM necessidades WHERE unit_id = :unit_id
        ");
        $stmt->bindParam(':unit_id', $unit_id);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Método para buscar os produtos associados a múltiplos insumos de uma vez só
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

    // Método principal para o cálculo de consumo otimizado
    public static function getInsumoConsumption($system_unit_id, $dates, $insumoIds) {
        global $pdo;

        // Primeiro, obtenha os dados da unidade do sistema (custom_code, user_api, pass_api)
        $systemUnitData = self::getSystemUnitData($system_unit_id);

        if (!$systemUnitData) {
            return ['error' => 'Unidade do sistema não encontrada'];
        }

        $custom_code = $systemUnitData['custom_code'];
        $user_api = $systemUnitData['user_api'];
        $pass_api = $systemUnitData['pass_api'];

        // Obtenha o token da API Menew usando os dados da unidade
        $token = self::getMenewToken($user_api, $pass_api);
        if (!$token) {
            return ['error' => 'Falha ao obter token da API Menew'];
        }

        // Busca todos os produtos que usam os insumos fornecidos
        $compositions = self::getProductsByInsumos($insumoIds);


        // Mapeia as composições para facilitar o acesso
        $insumoProductMap = [];
        foreach ($compositions as $composition) {
            $insumoProductMap[$composition['insumo_id']][] = $composition;
        }

        // Variável para armazenar o consumo total de cada insumo
        $insumoConsumption = array_fill_keys($insumoIds, 0);


        // Itera sobre as datas e faz uma única requisição por data
        foreach ($dates as $date) {
            // Busca todas as vendas de uma data
            $salesData = self::fetchSalesData($token, $custom_code, $date);

            // Para cada insumo, calcula o consumo total baseado nas vendas e nas composições
            foreach ($insumoIds as $insumo_id) {
                if (isset($insumoProductMap[$insumo_id])) {
                    foreach ($insumoProductMap[$insumo_id] as $composition) {
                        $product_id = $composition['product_id'];
                        $insumoQuantity = $composition['quantity']; // Quantidade de insumo usada no produto

                        // Filtra as vendas para esse product_id e soma o consumo
                        foreach ($salesData as $sale) {
                            if ($sale['codProduto'] == $product_id) {
                                $insumoConsumption[$insumo_id] += $sale['qtd'] * $insumoQuantity;
                            }
                        }
                    }
                }
            }
        }

        return $insumoConsumption;
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
