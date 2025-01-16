<?php

ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');


class FinanceiroApiMenewController {

    private static $authToken = null;

    const DEFAULT_REQUEST_ID = "100000";

    private static function makeRequest($token, $method, $params, $id = 1) {
        $payload = [
            "token" => $token,
            "requests" => [
                "jsonrpc" => "2.0",
                "method" => $method,
                "params" => $params,
                "id" => $id
            ]
        ];

        $apiUrl = "https://www.portalmenew.com.br/public-api/";

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Erro na requisição: $error");
        }

        return json_decode($response, true);
    }

    public static function authenticate($username = "batech", $token = "X7K1g6VJLrcWPM2adw2O") {
        if (self::$authToken !== null) {
            return self::$authToken;
        }

        try {
            $response = self::makeRequest(null, "Usuario/login", [
                "usuario" => $username,
                "token" => $token
            ]);

            if (isset($response["result"])) {
                self::$authToken = $response["result"];
                return self::$authToken;
            }

            throw new Exception("Falha na autenticação: " . json_encode($response));
        } catch (Exception $e) {
            throw new Exception("Erro ao autenticar: " . $e->getMessage());
        }
    }

    public static function fetchFinanceiroConta(string $estabelecimento, string $tipo): array {
        try {
            $token = self::authenticate();

            $response = self::makeRequest($token, "FinanceiroConta/fetch", [
                "estabelecimento" => $estabelecimento,
                "tipo" => $tipo
            ], "100");

            if (isset($response["result"])) {
                return ["success" => true, "contas" => $response["result"]];
            }

            return ["success" => true, "contas" => []];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public static function fetchFinanceiroFornecedor(string $estabelecimento): array {
        try {
            $token = self::authenticate();

            $response = self::makeRequest($token, "FinanceiroFornecedor/fetch", [
                "estabelecimento" => $estabelecimento
            ], self::DEFAULT_REQUEST_ID);

            if (isset($response["result"])) {
                return ["success" => true, "fornecedores" => $response["result"]];
            }

            return ["success" => false, "message" => "Erro ao buscar fornecedores", "response" => $response];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public static function fetchFinanceiroPlano(string $estabelecimento): array {
        try {
            $token = self::authenticate();

            $response = self::makeRequest($token, "FinanceiroPlano/fetch", [
                "estabelecimento" => $estabelecimento
            ], self::DEFAULT_REQUEST_ID);

            if (isset($response["result"])) {
                return ["success" => true, "planos" => $response["result"]];
            }

            return ["success" => false, "message" => "Erro ao buscar planos", "response" => $response];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public static function fetchFinanceiroCliente(string $estabelecimento): array {
        try {
            $token = self::authenticate();

            $response = self::makeRequest($token, "FinanceiroCliente/fetch", [
                "estabelecimento" => $estabelecimento
            ], self::DEFAULT_REQUEST_ID);

            if (isset($response["result"])) {
                return ["success" => true, "clientes" => $response["result"]];
            }

            return ["success" => false, "message" => "Erro ao buscar clientes", "response" => $response];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public static function fetchFinanceiroRateio(string $estabelecimento): array {
        try {
            $token = self::authenticate();

            $response = self::makeRequest($token, "FinanceiroRateio/fetch", [
                "estabelecimento" => $estabelecimento
            ], self::DEFAULT_REQUEST_ID);

            if (isset($response["result"])) {
                return ["success" => true, "rateios" => $response["result"]];
            }

            return ["success" => false, "message" => "Erro ao buscar rateios", "response" => $response];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

     public static function importarContaApi($system_unit_id) {
        global $pdo;

        try {
            // Obtém o custom_code a partir do system_unit_id
            $stmt = $pdo->prepare("SELECT custom_code AS estabelecimento FROM system_unit WHERE id = :id");
            $stmt->bindParam(':id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                throw new Exception("System Unit ID inválido ou não encontrado.");
            }

            $estabelecimento = $result['estabelecimento'];

            // Chama o método da API para buscar os rateios
            $rateios = FinanceiroApiMenewController::fetchFinanceiroRateio($estabelecimento);

            if (!$rateios['success']) {
                throw new Exception("Erro ao buscar rateios da API: " . $rateios['message']);
            }

            foreach ($rateios['rateios'] as $rateio) {
                $stmt = $pdo->prepare("INSERT INTO financeiro_conta (system_unit_id, codigo, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt, valor, plano_contas, banco, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $system_unit_id,
                    $rateio['codigo'],
                    $rateio['nome'],
                    $rateio['entidade'],
                    $rateio['cgc'],
                    $rateio['tipo'],
                    $rateio['doc'],
                    $rateio['emissao'],
                    $rateio['vencimento'],
                    $rateio['baixa_dt'],
                    $rateio['valor'],
                    $rateio['plano_contas'],
                    $rateio['banco'],
                    $rateio['obs'],
                    $rateio['inc_ope'],
                    $rateio['bax_ope'],
                    $rateio['comp_dt'],
                    $rateio['adic'],
                    $rateio['comissao'],
                    $rateio['local'],
                    $rateio['cheque'],
                    $rateio['dt_cheque'],
                    $rateio['segmento']
                ]);
            }

            return ["success" => true, "message" => "Rateios importados com sucesso"];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }
}
