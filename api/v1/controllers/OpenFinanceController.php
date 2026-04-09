<?php

require_once __DIR__ . '/../database/db.php';

class OpenFinanceController {

    private $apiUrl = 'https://api.pagamentobancario.com.br'; // staging: https://staging.pagamentobancario.com.br
    private $cnpjSh = '34040373000108';
    private $tokenSh = 'ea51433317728a404d0312fdef236f62';

    // =========================================================================
    // HTTP Client interno (cURL com Retry + Backoff + Log)
    // =========================================================================

    /**
     * Dispara requisição para a API TecnoSpeed com retry e timeout.
     * Loga todas as chamadas em pluggy_integration_logs.
     *
     * @param string      $method        GET|POST|PUT|DELETE
     * @param string      $endpoint      Ex: /api/v1/payer
     * @param mixed       $body          Array para JSON ou null
     * @param string|null $payerCpfCnpj  CPF/CNPJ do pagador (header)
     * @param int|null    $systemUnitId  Para logging
     * @param int         $retries       Número de tentativas
     * @return array                     Resposta decodificada
     */
    private function apiRequest($method, $endpoint, $body = null, $payerCpfCnpj = null, $systemUnitId = null, $retries = 3) {
        global $pdo;
        $url = $this->apiUrl . $endpoint;
        $startTime = microtime(true);

        $headers = [
            'Content-Type: application/json',
            'cnpjsh: ' . $this->cnpjSh,
            'tokensh: ' . $this->tokenSh
        ];

        if ($payerCpfCnpj) {
            $headers[] = 'payercpfcnpj: ' . preg_replace('/\D/', '', $payerCpfCnpj);
        }

        $lastHttpCode = 0;
        $lastError = '';
        $lastResponse = '';

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
            }

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            $lastHttpCode = $httpCode;
            $lastResponse = $response;

            // Sucesso
            if ($httpCode >= 200 && $httpCode < 300) {
                $this->logApiCall($systemUnitId, $method, $endpoint, $body, $response, $httpCode, null, $startTime);
                return json_decode($response, true) ?? [];
            }

            // 4xx (exceto 429) → não faz retry
            if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 429) {
                $lastError = "HTTP {$httpCode}: {$response}";
                break;
            }

            // 5xx ou timeout → retry com backoff
            $lastError = $curlError ?: "HTTP {$httpCode}: {$response}";
            if ($attempt < $retries) {
                sleep(pow(2, $attempt - 1)); // 1s, 2s, 4s...
            }
        }

        // Falhou em todas as tentativas
        $this->logApiCall($systemUnitId, $method, $endpoint, $body, $lastResponse, $lastHttpCode, $lastError, $startTime);
        error_log("[OpenFinance] Falha após {$retries} tentativas: {$method} {$endpoint} - {$lastError}");

        throw new Exception("Falha na integração bancária. [{$lastHttpCode}] " . $this->extractApiErrorMessage($lastResponse));
    }

    /**
     * Upload de arquivo (multipart/form-data) para a API.
     */
    private function apiUploadFile($endpoint, $filePath, $payerCpfCnpj, $systemUnitId = null) {
        $url = $this->apiUrl . $endpoint;
        $startTime = microtime(true);

        $headers = [
            'cnpjsh: ' . $this->cnpjSh,
            'tokensh: ' . $this->tokenSh,
            'payercpfcnpj: ' . preg_replace('/\D/', '', $payerCpfCnpj)
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => new CURLFile($filePath)]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        $this->logApiCall($systemUnitId, 'POST', $endpoint, ['file' => basename($filePath)], $response, $httpCode, $curlError ?: null, $startTime);

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true) ?? [];
        }

        throw new Exception("Falha no upload do extrato. [{$httpCode}] " . $this->extractApiErrorMessage($response));
    }

    /**
     * Registra chamada à API no banco para auditoria/debug.
     */
    private function logApiCall($systemUnitId, $method, $endpoint, $requestBody, $responseBody, $httpCode, $errorMessage, $startTime) {
        global $pdo;
        try {
            $executionMs = (int) ((microtime(true) - $startTime) * 1000);
            $stmt = $pdo->prepare("
                INSERT INTO pluggy_integration_logs 
                (system_unit_id, endpoint, method, request_body, response_body, http_code, error_message, execution_time_ms)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $systemUnitId ?? 0,
                $endpoint,
                $method,
                $requestBody ? json_encode($requestBody, JSON_UNESCAPED_UNICODE) : null,
                $responseBody ? mb_substr($responseBody, 0, 65000) : null,
                $httpCode,
                $errorMessage,
                $executionMs
            ]);
        } catch (Exception $e) {
            error_log("[OpenFinance] Falha ao gravar log: " . $e->getMessage());
        }
    }

    /**
     * Extrai mensagem amigável de erro da resposta da API.
     */
    private function extractApiErrorMessage($response) {
        $decoded = json_decode($response, true);
        if (isset($decoded['message'])) return $decoded['message'];
        if (isset($decoded['error'])) return is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
        return 'Erro na comunicação com o serviço bancário.';
    }


    // =========================================================================
    // PAGADOR (PAYER)
    // =========================================================================

    /**
     * Cadastrar pagador na API e salvar localmente.
     * Idempotente: se já existe com mesmo CPF/CNPJ no mesmo tenant, retorna o existente.
     *
     * Campos obrigatórios da API: name, cpfCnpj, neighborhood, city, state, zipcode
     */
    public function createPayer($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $cpfCnpj = preg_replace('/\D/', '', $request['cpfCnpj']);

        // 1. Idempotência: verificar se já existe localmente
        $check = $pdo->prepare("SELECT id, token FROM pluggy_payers WHERE system_unit_id = ? AND cpf_cnpj = ?");
        $check->execute([$system_unit_id, $cpfCnpj]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return [
                'status' => 'already_exists',
                'message' => 'Pagador já cadastrado nesta unidade.',
                'payer_id' => $existing['id'],
                'token' => $existing['token']
            ];
        }

        // 2. Payload conforme documentação da API
        $payload = [
            'name'              => $request['name'],
            'cpfCnpj'           => $cpfCnpj,
            'neighborhood'      => $request['neighborhood'],
            'addressNumber'     => $request['addressNumber'] ?? '',
            'zipcode'           => $request['zipcode'],
            'state'             => $request['state'],
            'city'              => $request['city'],
            'statementActived'  => true
        ];

        if (!empty($request['email']))             $payload['email']             = $request['email'];
        if (!empty($request['street']))            $payload['street']            = $request['street'];
        if (!empty($request['addressComplement'])) $payload['addressComplement'] = $request['addressComplement'];

        $apiResponse = null;
        $isSync = false;

        try {
            // Tenta criar o pagador na TecnoSpeed
            $apiResponse = $this->apiRequest('POST', '/api/v1/payer', $payload, null, $system_unit_id);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            // Verifica se o erro é o 422 de pagador já cadastrado
            if (strpos($errorMsg, 'já cadastrado') !== false || strpos($errorMsg, '422') !== false) {
                // Pagador já existe na TecnoSpeed! Vamos buscar os dados dele (GET)
                try {
                    $apiResponse = $this->apiRequest('GET', '/api/v1/payer', null, $cpfCnpj, $system_unit_id);
                    $isSync = true;
                } catch (Exception $e2) {
                    throw new Exception("Pagador já existe na API, mas houve falha ao buscar os dados: " . $e2->getMessage());
                }
            } else {
                // Se for outro erro de validação ou timeout, repassa o erro para o frontend
                throw $e;
            }
        }

        // 3. Salvar localmente (agora com segurança, usando os dados retornados do GET se foi sync)
        $stmt = $pdo->prepare("
            INSERT INTO pluggy_payers 
            (system_unit_id, cpf_cnpj, name, email, token, status, active, statement_actived)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Trata os booleanos e inteiros que podem vir do GET /api/v1/payer
        $status = $apiResponse['status'] ?? 1;
        $active = isset($apiResponse['active']) ? ($apiResponse['active'] ? 1 : 0) : 1;
        $statementActived = isset($apiResponse['statementActived']) ? ($apiResponse['statementActived'] ? 1 : 0) : 1;

        $stmt->execute([
            $system_unit_id,
            $cpfCnpj,
            $apiResponse['name'] ?? $request['name'], // Pega da API se fez o GET, senão usa o do form
            $apiResponse['email'] ?? $request['email'] ?? null,
            $apiResponse['token'] ?? null, // O token crucial retornado pela API
            $status,
            $active,
            $statementActived
        ]);

        $payerId = $pdo->lastInsertId();

        // 4. Atualiza a flag na unidade do sistema
        $pdo->prepare("UPDATE system_unit SET open_finance = 1 WHERE id = ?")->execute([$system_unit_id]);

        return [
            'status' => 'success',
            'message' => $isSync ? 'O CNPJ já existia na API. Os dados foram sincronizados com sucesso!' : 'Integração criada com sucesso!',
            'payer_id' => $payerId,
            'token' => $apiResponse['token'] ?? null,
            'data' => $apiResponse
        ];
    }
    /**
     * Consultar pagador na API e sincronizar dados locais.
     */
    public function getPayer($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $cpfCnpj = preg_replace('/\D/', '', $request['cpfCnpj']);

        $apiResponse = $this->apiRequest('GET', '/api/v1/payer', null, $cpfCnpj, $system_unit_id);

        // Sync local
        $stmt = $pdo->prepare("
            UPDATE pluggy_payers SET 
                token = COALESCE(?, token),
                status = ?,
                active = ?,
                statement_actived = ?
            WHERE system_unit_id = ? AND cpf_cnpj = ?
        ");
        $stmt->execute([
            $apiResponse['token'] ?? null,
            $apiResponse['status'] ?? 1,
            ($apiResponse['active'] ?? true) ? 1 : 0,
            ($apiResponse['statementActived'] ?? false) ? 1 : 0,
            $system_unit_id,
            $cpfCnpj
        ]);

        return ['status' => 'success', 'data' => $apiResponse];
    }

    /**
     * Atualizar dados do pagador na API e localmente.
     */
    public function updatePayer($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $cpfCnpj = preg_replace('/\D/', '', $request['cpfCnpj']);

        // Montar payload apenas com campos enviados
        $allowed = ['name','email','street','neighborhood','addressNumber','addressComplement','city','state','zipcode','statementActived'];
        $payload = [];
        foreach ($allowed as $field) {
            if (isset($request[$field])) {
                $payload[$field] = $request[$field];
            }
        }

        if (empty($payload)) {
            return ['status' => 'error', 'message' => 'Nenhum campo válido para atualização.'];
        }

        $apiResponse = $this->apiRequest('PUT', '/api/v1/payer', $payload, $cpfCnpj, $system_unit_id);

        // Atualizar campos locais que foram enviados
        $dbUpdates = [];
        $dbParams = [];

        $fieldMap = [
            'name' => 'name', 'email' => 'email',
            'statementActived' => 'statement_actived'
        ];
        foreach ($fieldMap as $apiField => $dbField) {
            if (array_key_exists($apiField, $payload)) {
                $val = $payload[$apiField];
                if ($apiField === 'statementActived') $val = $val ? 1 : 0;
                $dbUpdates[] = "{$dbField} = ?";
                $dbParams[] = $val;
            }
        }

        if (!empty($dbUpdates)) {
            $dbParams[] = $system_unit_id;
            $dbParams[] = $cpfCnpj;
            $sql = "UPDATE pluggy_payers SET " . implode(', ', $dbUpdates) . " WHERE system_unit_id = ? AND cpf_cnpj = ?";
            $pdo->prepare($sql)->execute($dbParams);
        }

        return ['status' => 'success', 'data' => $apiResponse];
    }

    /**
     * Listar pagadores locais do tenant.
     */
    public function listInternalPayers($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];

        $stmt = $pdo->prepare("SELECT * FROM pluggy_payers WHERE system_unit_id = ? AND active = 1 ORDER BY name ASC");
        $stmt->execute([$system_unit_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Desativar pagador (soft delete local + desativar na API).
     */
    public function deactivatePayer($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $id = $request['id'];

        // Buscar token do pagador para desativar na API
        $stmt = $pdo->prepare("SELECT token, cpf_cnpj FROM pluggy_payers WHERE id = ? AND system_unit_id = ?");
        $stmt->execute([$id, $system_unit_id]);
        $payer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payer) {
            return ['status' => 'error', 'message' => 'Pagador não encontrado.'];
        }

        // Desativar na API (se tiver token)
        if (!empty($payer['token'])) {
            try {
                $this->apiRequest('DELETE', '/api/v1/payer/' . $payer['token'], null, $payer['cpf_cnpj'], $system_unit_id);
            } catch (Exception $e) {
                // Continua mesmo se a API falhar (pode já estar desativado lá)
                error_log("[OpenFinance] Aviso ao desativar pagador na API: " . $e->getMessage());
            }
        }

        // Soft delete local
        $stmt = $pdo->prepare("UPDATE pluggy_payers SET active = 0 WHERE id = ? AND system_unit_id = ?");
        $stmt->execute([$id, $system_unit_id]);

        return ['status' => 'success', 'message' => 'Pagador desativado.'];
    }


    // =========================================================================
    // CONTAS BANCÁRIAS (ACCOUNTS)
    // =========================================================================

    /**
     * POST /pluggy/accounts/create
     * Cadastra a conta na API. Se já existir (Erro 422), faz o fallback buscando via GET e sincroniza.
     */
    public function createAccount($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];

        // 1. Busca os dados do pagador da unidade logada
        $stmtPayer = $pdo->prepare("SELECT id, cpf_cnpj FROM pluggy_payers WHERE system_unit_id = ? AND active = 1 LIMIT 1");
        $stmtPayer->execute([$system_unit_id]);
        $payer = $stmtPayer->fetch(PDO::FETCH_ASSOC);

        if (!$payer) {
            return [
                'status' => 'error',
                'message' => 'Pagador não configurado. O Suporte precisa ativar o Open Finance para esta unidade primeiro.'
            ];
        }

        $payer_id = $payer['id'];
        $payer_cpf_cnpj = $payer['cpf_cnpj'];

        // 2. Payload da Conta (Sempre Array conforme documentação)
        $payload = [
            [
                'bankCode'           => $request['bank_code'],
                'agency'             => $request['agency'],
                'agencyDigit'        => $request['agency_digit'] ?? '',
                'accountNumber'      => $request['account_number'],
                'accountNumberDigit' => $request['account_number_digit'] ?? '',
                'statementActived'   => true
            ]
        ];

        $apiResponse = null;
        $isSync = false;

        try {
            // Tenta criar
            $apiResponse = $this->apiRequest('POST', '/api/v1/account', $payload, $payer_cpf_cnpj, $system_unit_id);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            // Fallback: Se a conta já existe na Tecnospeed (Erro de validação / 422)
            if (strpos($errorMsg, 'já cadastrad') !== false || strpos($errorMsg, '422') !== false || strpos($errorMsg, 'already') !== false) {
                try {
                    $apiResponse = $this->apiRequest('GET', '/api/v1/account', null, $payer_cpf_cnpj, $system_unit_id);
                    $isSync = true;
                } catch (Exception $e2) {
                    throw new Exception("Conta já existe, mas falhou ao buscar os dados na API: " . $e2->getMessage());
                }
            } else {
                throw $e; // Outro erro, repassa pro front
            }
        }

        // 3. Salvar / Sincronizar localmente as contas retornadas
        $savedHashes = [];
        $accountsToProcess = $isSync ? ($apiResponse['accounts'] ?? []) : ($apiResponse['accounts'] ?? []);

        foreach ($accountsToProcess as $acc) {
            $accountHash = $acc['accountHash'] ?? null;
            if (!$accountHash) continue;

            $stmt = $pdo->prepare("
                INSERT INTO pluggy_accounts 
                (system_unit_id, payer_id, account_hash, bank_code, agency, account_number, account_number_digit, statement_actived, openfinance_link, status_openfinance)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
                ON DUPLICATE KEY UPDATE
                    bank_code = VALUES(bank_code),
                    agency = VALUES(agency),
                    account_number = VALUES(account_number),
                    account_number_digit = VALUES(account_number_digit),
                    statement_actived = VALUES(statement_actived),
                    openfinance_link = VALUES(openfinance_link),
                    status_openfinance = VALUES(status_openfinance)
            ");

            // Corrige o case sensitive do openfinanceLink e pega o statusOpenfinance
            $link = $acc['openfinanceLink'] ?? $acc['openFinanceLink'] ?? null;
            $statusOpen = $acc['statusOpenfinance'] ?? 'PENDENTE_ATIVACAO';

            $stmt->execute([
                $system_unit_id,
                $payer_id,
                $accountHash,
                $acc['bankCode'] ?? $request['bank_code'],
                $acc['agency'] ?? $request['agency'],
                $acc['accountNumber'] ?? $request['account_number'],
                $acc['accountNumberDigit'] ?? ($request['account_number_digit'] ?? ''),
                $link,
                $statusOpen
            ]);

            $savedHashes[] = $accountHash;
        }

        return [
            'status' => 'success',
            'message' => $isSync ? 'Conta bancária já existia na API e foi sincronizada!' : 'Conta bancária configurada com sucesso.',
            'account_hashes' => $savedHashes
        ];
    }

    /**
     * Sincronizar contas da API para o banco local.
     * Idempotente via ON DUPLICATE KEY UPDATE no account_hash.
     */
    public function syncAccounts($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $payer_cpf_cnpj = $request['payer_cpf_cnpj'];
        $payer_id = $request['payer_id'];

        $apiResponse = $this->apiRequest('GET', '/api/v1/account', null, $payer_cpf_cnpj, $system_unit_id);

        $synced = 0;
        if (!empty($apiResponse['accounts'])) {
            foreach ($apiResponse['accounts'] as $acc) {
                $accountHash = $acc['accountHash'] ?? null;
                if (!$accountHash) continue;

                $stmt = $pdo->prepare("
                    INSERT INTO pluggy_accounts 
                    (system_unit_id, payer_id, account_hash, bank_code, agency, account_number, account_number_digit, statement_actived, openfinance_link)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        bank_code = VALUES(bank_code),
                        agency = VALUES(agency),
                        account_number = VALUES(account_number),
                        account_number_digit = VALUES(account_number_digit),
                        statement_actived = VALUES(statement_actived),
                        openfinance_link = VALUES(openfinance_link)
                ");
                $stmt->execute([
                    $system_unit_id,
                    $payer_id,
                    $accountHash,
                    $acc['bankCode'] ?? null,
                    $acc['agency'] ?? null,
                    $acc['accountNumber'] ?? null,
                    $acc['accountNumberDigit'] ?? null,
                    ($acc['statementActived'] ?? false) ? 1 : 0,
                    $acc['openFinanceLink'] ?? null
                ]);
                $synced++;
            }
        }

        return ['status' => 'success', 'message' => "{$synced} conta(s) sincronizada(s)."];
    }

    /**
     * Consultar uma conta específica na API pelo accountHash.
     * GET /api/v1/account/{accountHash}
     */
    /**
     * GET /pluggy/accounts/{accountHash}
     * Sincroniza e atualiza todos os campos de uma conta específica
     */
    public function getAccountByHash($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $account_hash = $request['account_hash'];

        // 1. Descobrir o CPF/CNPJ do pagador atrelado a essa unidade
        $stmtPayer = $pdo->prepare("SELECT cpf_cnpj FROM pluggy_payers WHERE system_unit_id = ? AND active = 1 LIMIT 1");
        $stmtPayer->execute([$system_unit_id]);
        $payer = $stmtPayer->fetch(PDO::FETCH_ASSOC);

        if (!$payer) {
            return ['status' => 'error', 'message' => 'Pagador não encontrado para esta unidade.'];
        }

        $payer_cpf_cnpj = $payer['cpf_cnpj'];

        // 2. Chama a API da TecnoSpeed
        $apiResponse = $this->apiRequest('GET', "/api/v1/account/{$account_hash}", null, $payer_cpf_cnpj, $system_unit_id);

        // 3. A API pode retornar {"accounts": [{...}]} ou direto o objeto. Vamos extrair com segurança:
        $acc = null;
        if (isset($apiResponse['accounts']) && is_array($apiResponse['accounts']) && count($apiResponse['accounts']) > 0) {
            $acc = $apiResponse['accounts'][0];
        } else {
            $acc = $apiResponse; // Fallback se retornar direto
        }

        // 4. Salvar no banco todos os campos
        if (!empty($acc['accountHash'])) {

            // Tratamento das chaves da API
            $link = $acc['openfinanceLink'] ?? $acc['openFinanceLink'] ?? null;
            $statusOpen = $acc['statusOpenfinance'] ?? 'PENDENTE_ATIVACAO';
            $openfinanceId = !empty($acc['openfinanceId']) ? $acc['openfinanceId'] : null;

            $stmt = $pdo->prepare("
                UPDATE pluggy_accounts SET 
                    bank_code = COALESCE(?, bank_code),
                    agency = COALESCE(?, agency),
                    account_number = COALESCE(?, account_number),
                    account_number_digit = COALESCE(?, account_number_digit),
                    statement_actived = ?,
                    openfinance_link = ?,
                    status_openfinance = ?,
                    openfinance_id = ?
                WHERE account_hash = ? AND system_unit_id = ?
            ");

            $stmt->execute([
                $acc['bankCode'] ?? null,
                $acc['agency'] ?? null,
                $acc['accountNumber'] ?? null,
                $acc['accountNumberDigit'] ?? null,
                ($acc['statementActived'] ?? false) ? 1 : 0,
                $link,
                $statusOpen,
                $openfinanceId,
                $account_hash,
                $system_unit_id
            ]);

            return [
                'status' => 'success',
                'message' => 'Status da conta atualizado!',
                'data' => $acc
            ];

        } else {
            return ['status' => 'error', 'message' => 'Conta não retornada pela API.', 'data' => $apiResponse];
        }
    }
    /**
     * Atualizar conta na API.
     * PUT /api/v1/account/{accountHash}
     */
    public function updateAccount($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $account_hash = $request['account_hash'];
        $payer_cpf_cnpj = $request['payer_cpf_cnpj'];

        // Montar payload apenas com campos enviados
        $allowed = ['bankCode','agency','agencyDigit','accountNumber','accountNumberDigit',
            'accountDac','accountType','convenioAgency','convenioNumber',
            'remessaSequential','recipientNotification','statementActived'];
        $payload = [];
        foreach ($allowed as $field) {
            if (isset($request[$field])) {
                $payload[$field] = $request[$field];
            }
        }

        $apiResponse = $this->apiRequest('PUT', "/api/v1/account/{$account_hash}", $payload, $payer_cpf_cnpj, $system_unit_id);

        // Sync local
        $stmt = $pdo->prepare("
            UPDATE pluggy_accounts SET 
                statement_actived = COALESCE(?, statement_actived),
                openfinance_link = COALESCE(?, openfinance_link)
            WHERE account_hash = ? AND system_unit_id = ?
        ");
        $stmt->execute([
            isset($apiResponse['statementActived']) ? ($apiResponse['statementActived'] ? 1 : 0) : null,
            $apiResponse['openFinanceLink'] ?? null,
            $account_hash,
            $system_unit_id
        ]);

        return ['status' => 'success', 'data' => $apiResponse];
    }

    /**
     * Deletar conta na API e desativar localmente.
     * DELETE /api/v1/account
     */
    public function deleteAccount($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $account_hash = $request['account_hash'];
        $payer_cpf_cnpj = $request['payer_cpf_cnpj'];

        $payload = ['accountHash' => [$account_hash]];
        $apiResponse = $this->apiRequest('DELETE', '/api/v1/account', $payload, $payer_cpf_cnpj, $system_unit_id);

        // Desativar localmente (soft delete)
        $stmt = $pdo->prepare("UPDATE pluggy_accounts SET active = 0 WHERE account_hash = ? AND system_unit_id = ?");
        $stmt->execute([$account_hash, $system_unit_id]);

        return ['status' => 'success', 'message' => 'Conta removida.'];
    }


    // =========================================================================
    // OPEN FINANCE (Conexão / Revogação)
    // =========================================================================

    /**
     * Ativar Open Finance numa conta (gera o link de conexão).
     * PUT /api/v1/account/{accountHash} com statementActived: true
     */
    public function connectOpenFinance($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $account_hash = $request['account_hash'];
        $payer_cpf_cnpj = $request['payer_cpf_cnpj'];

        $payload = ['statementActived' => true];
        $apiResponse = $this->apiRequest('PUT', "/api/v1/account/{$account_hash}", $payload, $payer_cpf_cnpj, $system_unit_id);

        $openFinanceLink = $apiResponse['openFinanceLink'] ?? $apiResponse['openfinanceLink'] ?? null;

        // Atualizar local
        $stmt = $pdo->prepare("
            UPDATE pluggy_accounts SET 
                statement_actived = 1,
                openfinance_link = ?
            WHERE account_hash = ? AND system_unit_id = ?
        ");
        $stmt->execute([$openFinanceLink, $account_hash, $system_unit_id]);

        return [
            'status' => 'success',
            'openfinance_link' => $openFinanceLink,
            'message' => 'Open Finance ativado. Envie o link ao usuário para conectar a conta.',
            'data' => $apiResponse
        ];
    }

    /**
     * Revogar Open Finance de uma conta.
     * PUT /api/v1/account/{accountHash}/openfinance/revoke
     */
    public function revokeOpenFinance($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $account_hash = $request['account_hash'];
        $payer_cpf_cnpj = $request['payer_cpf_cnpj'];
        $revokeAndDisable = $request['revokeAndDisable'] ?? false;

        $payload = ['revokeAndDisable' => (bool) $revokeAndDisable];
        $apiResponse = $this->apiRequest(
            'PUT',
            "/api/v1/account/{$account_hash}/openfinance/revoke",
            $payload,
            $payer_cpf_cnpj,
            $system_unit_id
        );

        // Atualizar local
        $stmt = $pdo->prepare("
            UPDATE pluggy_accounts SET 
                openfinance_link = NULL,
                statement_actived = CASE WHEN ? = 1 THEN 0 ELSE statement_actived END
            WHERE account_hash = ? AND system_unit_id = ?
        ");
        $stmt->execute([$revokeAndDisable ? 1 : 0, $account_hash, $system_unit_id]);

        return [
            'status' => 'success',
            'message' => $apiResponse['message'] ?? 'Open Finance revogado.',
            'data' => $apiResponse
        ];
    }


    // =========================================================================
    // LISTAGENS LOCAIS (para servir o frontend)
    // =========================================================================

    /**
     * Listar contas com nome do pagador (para dashboards/selects).
     */
    public function listLocalAccounts($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];

        $stmt = $pdo->prepare("
            SELECT 
                a.*, 
                p.name as payer_name,
                p.cpf_cnpj as payer_document
            FROM pluggy_accounts a
            INNER JOIN pluggy_payers p ON p.id = a.payer_id
            WHERE a.system_unit_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$system_unit_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar contas por pagador.
     */
    public function listInternalAccounts($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $payer_id = $request['payer_id'] ?? null;

        $sql = "SELECT a.*, p.name as payer_name 
                FROM pluggy_accounts a
                INNER JOIN pluggy_payers p ON p.id = a.payer_id
                WHERE a.system_unit_id = ?";
        $params = [$system_unit_id];

        if ($payer_id) {
            $sql .= " AND a.payer_id = ?";
            $params[] = $payer_id;
        }

        $sql .= " ORDER BY p.name, a.bank_code";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar transações locais (tela de extrato bancário).
     */
    public function listInternalTransactions($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $account_id = $request['account_id'];
        $date_start = $request['date_start'] ?? date('Y-m-01');
        $date_end = $request['date_end'] ?? date('Y-m-t');

        $sql = "SELECT * FROM pluggy_transactions 
                WHERE system_unit_id = ? AND account_id = ? 
                AND date BETWEEN ? AND ? ORDER BY date DESC, id DESC";
        $params = [$system_unit_id, $account_id, $date_start, $date_end];


        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Histórico de importações (log de sincronizações).
     */
    public function listImportHistory($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $account_id = $request['account_id'];

        $stmt = $pdo->prepare("
            SELECT * FROM pluggy_statement_imports 
            WHERE system_unit_id = ? AND account_id = ? 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([$system_unit_id, $account_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Logs de integração (para debug/admin).
     */
    public function listIntegrationLogs($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $limit = $request['limit'] ?? 50;

        $stmt = $pdo->prepare("
            SELECT * FROM pluggy_integration_logs 
            WHERE system_unit_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $system_unit_id, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // PAINEL ADMINISTRATIVO (GLOBAL)
    // =========================================================================

    /**
     * Listar TODOS os pagadores de todas as unidades
     * Usado na tela global de administração do MRK
     */
    public function listAllPayers(): array
    {
        global $pdo;

        // Fazemos um JOIN para trazer o nome da unidade para o Grid
        $stmt = $pdo->prepare("
            SELECT p.*, u.name as unit_name, u.cnpj as unit_cnpj
            FROM pluggy_payers p
            LEFT JOIN system_unit u ON u.id = p.system_unit_id
            ORDER BY p.active DESC, p.created_at DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar unidades que ainda NÃO possuem Open Finance ativado
     * Usado para popular o <select> do Modal de Novo Pagador
     */
    public function listAvailableUnits(): array
    {
        global $pdo;

        // Traz apenas unidades ativas e com open_finance = 0
        $stmt = $pdo->prepare("
            SELECT id, name, cnpj 
            FROM system_unit 
            WHERE status = 1 AND open_finance = 0
            ORDER BY name ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se existe um pagador ativo para a unidade informada.
     * Útil para o frontend saber se libera a tela de contas ou não.
     */
    public function checkPayerExists($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];

        if (!$system_unit_id) {
            return ['status' => 'error', 'message' => 'ID da unidade não informado.'];
        }

        $stmt = $pdo->prepare("
            SELECT id, name, cpf_cnpj, statement_actived 
            FROM pluggy_payers 
            WHERE system_unit_id = ? AND active = 1 
            LIMIT 1
        ");
        $stmt->execute([$system_unit_id]);
        $payer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payer) {
            return [
                'status' => 'success',
                'exists' => true,
                'payer' => $payer
            ];
        } else {
            return [
                'status' => 'success',
                'exists' => false
            ];
        }
    }

    public function requestStatementFromLastYear($request) {
        global $pdo;
        $system_unit_id = $request['system_unit_id'];
        $account_id = $request['account_id'];
        $user_id = $request['user_id'] ?? null; // Opcional, caso a chamada parta de uma ação manual no painel

        // 1. Buscar a conta e os dados do pagador associado para montar o Header
        $stmt = $pdo->prepare("
            SELECT a.id as account_id, a.account_hash, p.id as payer_id, p.cpf_cnpj 
            FROM pluggy_accounts a
            INNER JOIN pluggy_payers p ON p.id = a.payer_id
            WHERE a.id = ? AND a.system_unit_id = ? AND a.active = 1
        ");
        $stmt->execute([$account_id, $system_unit_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return ['status' => 'error', 'message' => 'Conta bancária não encontrada ou inativa.'];
        }

        $account_hash = $data['account_hash'];
        $payer_cpf_cnpj = $data['cpf_cnpj'];
        $payer_id = $data['payer_id'];

        // 2. Definir o período: Hoje e os últimos 365 dias
        $dateEnd = date('Y-m-d');
        $dateStart = '2026-01-01';
        //$dateStart = date('Y-m-d', strtotime('-365 days'));

        // 3. Montar o payload
        $payload = [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'today' => false,
            'accountHash' => $account_hash,
            'statementType' => 'BANK'
        ];

        try {
            // 4. Dispara a requisição usando o client interno já existente
            $apiResponse = $this->apiRequest(
                'POST',
                '/api/v1/statement/openfinance',
                $payload,
                $payer_cpf_cnpj,
                $system_unit_id
            );

            // A API pode retornar uniqueId ou uniqueid dependendo de variações do JSON
            $uniqueId = $apiResponse['uniqueId'] ?? $apiResponse['uniqueid'] ?? null;

            if (!$uniqueId) {
                throw new Exception("A API da TecnoSpeed não retornou o uniqueId.");
            }

            // 5. Salva na fila de importação (pluggy_statement_imports) com status processing
            $stmtInsert = $pdo->prepare("
                INSERT IGNORE INTO pluggy_statement_imports 
                (system_unit_id, account_id, unique_id, status, date_start, date_end)
                VALUES (?, ?, ?, 'processing', ?, ?)
            ");
            $stmtInsert->execute([
                $system_unit_id,
                $account_id,
                $uniqueId,
                $dateStart,
                $dateEnd
            ]);

            // 6. Opcional: Registra o sucesso na tabela de eventos para fins de auditoria (igual ao worker Node)
            $this->logExtratoEvent($system_unit_id, $payer_id, $account_id, $user_id, 'manual', 'success', "Protocolo {$uniqueId} solicitado com sucesso.");

            return [
                'status' => 'success',
                'message' => "Solicitação enviada com sucesso! Protocolo: {$uniqueId}.",
                'unique_id' => $uniqueId,
                'data' => $apiResponse
            ];

        } catch (Exception $e) {
            // Registra a falha na tabela de eventos
            $this->logExtratoEvent($system_unit_id, $payer_id, $account_id, $user_id, 'manual', 'error', "Falha ao solicitar extrato: " . $e->getMessage());

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Helper privado para replicar o registrarEvento do Node.js
     */
    private function logExtratoEvent($system_unit_id, $payer_id, $account_id, $user_id, $event_type, $status, $message) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pluggy_extrato_events 
                (system_unit_id, payer_id, account_id, user_id, event_type, status, message)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$system_unit_id, $payer_id, $account_id, $user_id, $event_type, $status, $message]);
        } catch (Exception $e) {
            error_log("[OpenFinance] Erro ao registrar evento de extrato: " . $e->getMessage());
        }
    }

    // =========================================================================
    // EXTRATOS BANCÁRIOS (TRANSAÇÕES)
    // =========================================================================

    /**
     * Retorna os extratos (transações) locais de uma conta com filtro de data.
     */
    public function getExtrato($request) {
        global $pdo;

        $system_unit_id = $request['system_unit_id'] ?? null;
        $account_id = $request['account_id'] ?? null;

        if (!$system_unit_id || !$account_id) {
            return [
                'status' => 'error',
                'message' => 'O ID da unidade e o ID da conta são obrigatórios.'
            ];
        }

        // Se a data não for informada, o padrão será os últimos 30 dias
        $date_start = $request['date_start'] ?? date('Y-m-d', strtotime('-30 days'));
        $date_end   = $request['date_end']   ?? date('Y-m-d');

        try {
            // Busca todas as transações da conta no intervalo de datas, ordenadas da mais recente para a mais antiga
            $sql = "SELECT * FROM pluggy_transactions 
                    WHERE system_unit_id = ? 
                      AND account_id = ? 
                      AND date BETWEEN ? AND ? 
                    ORDER BY date DESC, id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $system_unit_id,
                $account_id,
                $date_start,
                $date_end
            ]);

            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Retorna a estrutura padronizada para o frontend consumir facilmente
            return [
                'status'  => 'success',
                'message' => count($transactions) . ' transação(ões) encontrada(s).',
                'data'    => $transactions,
                'filters' => [
                    'date_start' => $date_start,
                    'date_end'   => $date_end
                ]
            ];

        } catch (Exception $e) {
            error_log("[OpenFinance] Erro ao listar extrato: " . $e->getMessage());

            return [
                'status'  => 'error',
                'message' => 'Ocorreu um erro interno ao buscar os extratos no banco de dados.'
            ];
        }
    }
    // =========================================================================
    // LOGS DE INTEGRAÇÃO (DEBUG)
    // =========================================================================

    /**
     * Retorna os logs de integração de um protocolo (unique_id) específico de extrato.
     */
    public function getStatementLogs($request) {
        global $pdo;

        $system_unit_id = $request['system_unit_id'] ?? null;
        $unique_id = $request['unique_id'] ?? null;

        if (!$system_unit_id || !$unique_id) {
            return [
                'status' => 'error',
                'message' => 'O ID da unidade e o ID do protocolo (unique_id) são obrigatórios.'
            ];
        }

        try {
            // Usa o LIKE para buscar qualquer log onde o endpoint contenha o unique_id
            $sql = "SELECT 
                        id, 
                        endpoint, 
                        method, 
                        http_code, 
                        error_message, 
                        execution_time_ms, 
                        created_at, 
                        request_body, 
                        response_body 
                    FROM pluggy_integration_logs 
                    WHERE system_unit_id = ? 
                      AND endpoint LIKE ? 
                    ORDER BY created_at DESC";

            $stmt = $pdo->prepare($sql);

            // Ex: busca por '%U8_TRQ2FhSrjqE%' para garantir que pega a URL independente do formato exato salvo
            $stmt->execute([
                $system_unit_id,
                "%" . $unique_id . "%"
            ]);

            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Tentar decodificar o JSON com validação para evitar erro de depreciação no PHP 8.1+
            foreach ($logs as &$log) {

                // Trata request_body
                if (is_string($log['request_body']) && trim($log['request_body']) !== '') {
                    $reqDecoded = json_decode($log['request_body'], true);
                    $log['request_body_decoded'] = (json_last_error() === JSON_ERROR_NONE) ? $reqDecoded : $log['request_body'];
                } else {
                    $log['request_body_decoded'] = $log['request_body']; // Mantém null
                }

                // Trata response_body
                if (is_string($log['response_body']) && trim($log['response_body']) !== '') {
                    $resDecoded = json_decode($log['response_body'], true);
                    $log['response_body_decoded'] = (json_last_error() === JSON_ERROR_NONE) ? $resDecoded : $log['response_body'];
                } else {
                    $log['response_body_decoded'] = $log['response_body']; // Mantém null
                }
            }

            return [
                'status'  => 'success',
                'message' => count($logs) . ' log(s) encontrado(s).',
                'data'    => $logs
            ];

        } catch (Exception $e) {
            error_log("[OpenFinance] Erro ao buscar logs do extrato: " . $e->getMessage());

            return [
                'status'  => 'error',
                'message' => 'Ocorreu um erro interno ao buscar os logs.'
            ];
        }
    }

}
