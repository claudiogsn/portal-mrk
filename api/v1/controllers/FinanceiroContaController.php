<?php

ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');


require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../controllers/BiController.php';
require_once __DIR__ . '/../controllers/UtilsController.php';


class FinanceiroContaController {

    private static function logAuditoria(
        string $acao,
        string $tabela,
        int $registro_id,
        ?array $dados_antes,
        ?array $dados_depois,
        ?int $system_unit_id = null,
        ?int $usuario_id = null
    ): void {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
            INSERT INTO financeiro_auditoria
            (acao, tabela, registro_id, dados_antes, dados_depois, system_unit_id, usuario_id)
            VALUES (:acao, :tabela, :registro_id, :antes, :depois, :unit, :user)
        ");
            $stmt->execute([
                ':acao'        => $acao,
                ':tabela'      => $tabela,
                ':registro_id' => $registro_id,
                ':antes'       => $dados_antes ? json_encode($dados_antes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
                ':depois'      => $dados_depois ? json_encode($dados_depois, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
                ':unit'        => $system_unit_id,
                ':user'        => $usuario_id
            ]);
        } catch (Exception $e) {
            error_log("[AUDITORIA ERRO] " . $e->getMessage());
        }
    }

    public static function createConta(array $data): array
    {
        global $pdo;

        try {
            // 🧾 Validação obrigatória
            $required = ['system_unit_id', 'codigo', 'nome', 'tipo', 'valor'];
            foreach ($required as $field) {
                if (!array_key_exists($field, $data) || $data[$field] === '') {
                    throw new Exception("Campo obrigatório ausente: {$field}");
                }
            }

            $system_unit_id = (int)$data['system_unit_id'];
            $usuario_id     = $data['usuario_id'] ?? null;

            // 🔹 Rateio (controlado 100% pelo backend)
            $rateio = isset($data['rateio']) ? (int)$data['rateio'] : 0;
            $rateio_id = null;

            if ($rateio === 1) {
                $rateio_id = UtilsController::uuidv4();
            }

            // 🔍 Validação do plano de contas, se informado
            if (!empty($data['plano_contas'])) {
                $stPlano = $pdo->prepare("
                SELECT id
                FROM financeiro_plano
                WHERE system_unit_id = :unit
                  AND codigo = :codigo
                LIMIT 1
            ");
                $stPlano->execute([
                    ':unit'   => $system_unit_id,
                    ':codigo' => $data['plano_contas']
                ]);

                if (!$stPlano->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception(
                        "Plano de contas '{$data['plano_contas']}' não encontrado para esta unidade."
                    );
                }
            }

            // 💾 Inserção
            $sql = "
            INSERT INTO financeiro_conta
            (
                system_unit_id, codigo, nome, entidade, cgc, tipo, doc,
                emissao, vencimento, baixa_dt, valor,
                plano_contas, banco, obs, inc_ope, bax_ope,
                comp_dt, adic, comissao, local, cheque,
                dt_cheque, segmento, rateio, rateio_id
            )
            VALUES
            (
                :system_unit_id, :codigo, :nome, :entidade, :cgc, :tipo, :doc,
                :emissao, :vencimento, :baixa_dt, :valor,
                :plano_contas, :banco, :obs, :inc_ope, :bax_ope,
                :comp_dt, :adic, :comissao, :local, :cheque,
                :dt_cheque, :segmento, :rateio, :rateio_id
            )
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':system_unit_id' => $system_unit_id,
                ':codigo'         => $data['codigo'],
                ':nome'           => $data['nome'],
                ':entidade'       => $data['entidade'] ?? null,
                ':cgc'            => $data['cgc'] ?? null,
                ':tipo'           => $data['tipo'],
                ':doc'            => $data['doc'] ?? null,
                ':emissao'        => $data['emissao'] ?? null,
                ':vencimento'     => $data['vencimento'] ?? null,
                ':baixa_dt'       => $data['baixa_dt'] ?? null,
                ':valor'          => $data['valor'],
                ':plano_contas'   => $data['plano_contas'] ?? null,
                ':banco'          => $data['banco'] ?? null,
                ':obs'            => $data['obs'] ?? null,
                ':inc_ope'        => $data['inc_ope'] ?? null,
                ':bax_ope'        => $data['bax_ope'] ?? null,
                ':comp_dt'        => $data['comp_dt'] ?? null,
                ':adic'           => $data['adic'] ?? 0,
                ':comissao'       => $data['comissao'] ?? 0,
                ':local'          => $data['local'] ?? null,
                ':cheque'         => $data['cheque'] ?? null,
                ':dt_cheque'      => $data['dt_cheque'] ?? null,
                ':segmento'       => $data['segmento'] ?? null,
                ':rateio'         => $rateio,
                ':rateio_id'      => $rateio_id
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Falha ao criar conta.');
            }

            $novoId = (int)$pdo->lastInsertId();

            // 🔍 Busca a conta recém-criada para auditoria
            $stConta = $pdo->prepare("
            SELECT *
            FROM financeiro_conta
            WHERE id = :id
            LIMIT 1
        ");
            $stConta->execute([':id' => $novoId]);
            $novaConta = $stConta->fetch(PDO::FETCH_ASSOC);

            // 🧾 Log de auditoria
            self::logAuditoria(
                'CREATE',
                'financeiro_conta',
                $novoId,
                null,
                $novaConta,
                $system_unit_id,
                $usuario_id
            );

            return [
                'success'   => true,
                'message'   => 'Conta criada com sucesso.',
                'conta_id'  => $novoId,
                'rateio'    => $rateio,
                'rateio_id' => $rateio_id
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar conta: ' . $e->getMessage()
            ];
        }
    }
    public static function createContaLote($data)
    {
        global $pdo;

        $gotLock = false;
        $isTransactionCreator = false; // Flag para controlar transações aninhadas

        try {
            // ===== Validações mínimas =====
            if (empty($data['system_unit_id'])) {
                throw new Exception('system_unit_id é obrigatório.');
            }

            $system_unit_id = (int)$data['system_unit_id'];
            $usuario_id     = isset($data['usuario_id']) ? $data['usuario_id'] : null;

            if (empty($data['contas']) || !is_array($data['contas'])) {
                throw new Exception('contas deve ser um array válido.');
            }

            // ===== Lógica de Rateio =====
            $rateio = isset($data['rateio']) ? (int)$data['rateio'] : 0;
            $rateio_id = null;

            if ($rateio === 1) {
                $rateio_id = method_exists('UtilsController', 'uuidv4') ? UtilsController::uuidv4() : uniqid();
            }

            // ===== Função utilitária de fornecedor =====
            $fornCache = [];
            $getFornecedor = function ($id) use (&$fornCache, $pdo, $system_unit_id) {
                if (!isset($fornCache[$id])) {
                    $st = $pdo->prepare("
                SELECT cnpj_cpf, razao
                FROM financeiro_fornecedor
                WHERE id = :id AND system_unit_id = :unit
                LIMIT 1
            ");
                    $st->execute([':id' => $id, ':unit' => $system_unit_id]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    if (!$row) throw new Exception("Fornecedor {$id} não encontrado.");

                    $fornCache[$id] = [
                        'cgc'   => (string)($row['cnpj_cpf'] ?? ''),
                        'razao' => (string)($row['razao'] ?? ''),
                    ];
                }
                return $fornCache[$id];
            };

            // ===== Controle Inteligente de Transação =====
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $isTransactionCreator = true;
            }

            $lockName = 'financeiro_conta_codigo_local_lock';
            $stLock = $pdo->prepare("SELECT GET_LOCK(:name, 5)");
            $stLock->execute([':name' => $lockName]);
            $gotLock = ((int)$stLock->fetchColumn() === 1);

            $stMin = $pdo->query("SELECT MIN(codigo) FROM financeiro_conta WHERE codigo <= 0");
            $minCodigo = $stMin->fetchColumn();
            $nextCodigo = ($minCodigo !== null) ? ($minCodigo - 1) : 0;

            // ===== INSERT CONTA FINANCEIRA =====
            // Alteração: Mudei "NULL" para ":baixa_dt" para gravar a data que vem da transação
            $stmtIns = $pdo->prepare("
        INSERT INTO financeiro_conta
        (
            system_unit_id, codigo, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt,
            valor, plano_contas, banco, forma_pagamento, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento,
            rateio, rateio_id
        )
        VALUES
        (
            :system_unit_id, :codigo, :nome, :entidade, :cgc, :tipo, :doc, :emissao, :vencimento, :baixa_dt,
            :valor, :plano_contas, :banco, :forma_pagamento, :obs, NULL, NULL, NULL, :adic, 0.00, NULL, NULL, NULL, NULL,
            :rateio, :rateio_id
        )
        ");

            // ===== PREPARE VÍNCULO DA TRANSAÇÃO =====
            $stmtVinculo = $pdo->prepare("
            INSERT INTO financeiro_transacao_vinculo (system_unit_id, financeiro_conta_id, pluggy_transaction_id) 
            VALUES (:system_unit_id, :conta_id, :trans_id)
        ");

            $ids = [];

            foreach ($data['contas'] as $idx => $c) {

                foreach (['fornecedor_id','documento','emissao','vencimento','valor'] as $req) {
                    if (!isset($c[$req]) || $c[$req] === '') {
                        throw new Exception("Conta #".($idx+1).": Campo obrigatório ausente: {$req}");
                    }
                }

                $forn = $getFornecedor($c['fornecedor_id']);
                $nome = !empty($c['nome']) ? $c['nome'] : $forn['razao'];
                $valor = $c['valor'];
                $plano_contas = isset($c['plano_contas']) ? $c['plano_contas'] : null;
                $obs = isset($c['obs']) ? $c['obs'] : (isset($c['obs_extra']) ? $c['obs_extra'] : '');
                $banco = array_key_exists('banco', $c) ? $c['banco'] : null;
                $forma_pagamento = array_key_exists('forma_pagamento', $c) ? $c['forma_pagamento'] : (array_key_exists('forma_pagamento_id', $c) ? $c['forma_pagamento_id'] : null);
                $adic = isset($c['acrescimo']) ? $c['acrescimo'] : (isset($c['adicional']) ? $c['adicional'] : 0);

                // Pega a baixa (Se vier vazia, joga null)
                $baixa_dt = !empty($c['baixa_dt']) ? $c['baixa_dt'] : null;

                // Extrai e normaliza o tipo
                $tipoBruto = isset($c['tipo']) ? strtolower(trim($c['tipo'])) : 'd';
                $tipoFinal = ($tipoBruto === 'r') ? 'c' : (($tipoBruto === 'd') ? 'd' : $tipoBruto);

                if (!is_numeric($valor)) throw new Exception("Conta #".($idx+1).": valor inválido.");
                if ($valor + 0 <= 0) throw new Exception("Conta #".($idx+1).": valor deve ser > 0.");

                $stmtIns->execute([
                    ':system_unit_id'  => $system_unit_id,
                    ':codigo'          => $nextCodigo,
                    ':nome'            => $nome,
                    ':entidade'        => $c['fornecedor_id'],
                    ':cgc'             => $forn['cgc'],
                    ':tipo'            => $tipoFinal,
                    ':doc'             => $c['documento'],
                    ':emissao'         => $c['emissao'],
                    ':vencimento'      => $c['vencimento'],
                    ':baixa_dt'        => $baixa_dt,
                    ':valor'           => $valor,
                    ':plano_contas'    => $plano_contas,
                    ':banco'           => $banco,
                    ':forma_pagamento' => $forma_pagamento,
                    ':obs'             => $obs,
                    ':adic'            => $adic,
                    ':rateio'          => $rateio,
                    ':rateio_id'       => $rateio_id
                ]);

                $id = $pdo->lastInsertId();
                $ids[] = $id;

                if (!empty($c['pluggy_transaction_id'])) {
                    $stmtVinculo->execute([
                        ':system_unit_id' => $system_unit_id,
                        ':conta_id'       => $id,
                        ':trans_id'       => $c['pluggy_transaction_id']
                    ]);
                }

                // Log Auditoria
                if (method_exists(__CLASS__, 'logAuditoria')) {
                    $stConta = $pdo->prepare("SELECT * FROM financeiro_conta WHERE id = :id LIMIT 1");
                    $stConta->execute([':id' => $id]);
                    $novaConta = $stConta->fetch(PDO::FETCH_ASSOC);
                    self::logAuditoria('CREATE', 'financeiro_conta', $id, null, $novaConta, $system_unit_id, $usuario_id);
                }

                $nextCodigo -= 1;
            }

            // Só comita se foi ESTE método que abriu a transação
            if ($isTransactionCreator) {
                $pdo->commit();
            }

            return [
                'success'   => true,
                'inseridos' => count($ids),
                'ids'       => $ids,
                'rateio'    => $rateio,
                'rateio_id' => $rateio_id
            ];

        } catch (Exception $e) {
            // Só dá rollback se foi ESTE método que abriu a transação
            if ($isTransactionCreator && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            if (!empty($gotLock)) {
                try { $pdo->query("SELECT RELEASE_LOCK('financeiro_conta_codigo_local_lock')"); } catch (\Throwable $t) {}
            }
        }
    }
    public static function getContaById($id) {
        global $pdo;

        // 1. Busca a conta solicitada especificamente
        $stmt = $pdo->prepare("SELECT * FROM financeiro_conta WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $conta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conta) return null;

        // 2. Verifica se ela pertence a um grupo de Rateio
        // (rateio = 1 e possui um UUID de rateio_id)
        if (isset($conta['rateio']) && $conta['rateio'] == 1 && !empty($conta['rateio_id'])) {

            // Busca todos os itens que compartilham o mesmo rateio_id
            // Ordena por id para manter consistência visual
            $stRateio = $pdo->prepare("SELECT * FROM financeiro_conta WHERE rateio_id = :rid ORDER BY id ASC");
            $stRateio->execute([':rid' => $conta['rateio_id']]);
            $itensRateio = $stRateio->fetchAll(PDO::FETCH_ASSOC);

            // Anexa a lista de itens ao objeto principal
            $conta['itens_rateio'] = $itensRateio;

            // Opcional: Recalcula o valor total do lote para facilitar o frontend
            $somaTotal = 0;
            foreach ($itensRateio as $item) {
                $somaTotal += $item['valor'];
            }
            $conta['valor_total_rateio'] = $somaTotal;
        } else {
            // Se não for rateio, a lista é vazia ou null
            $conta['itens_rateio'] = null;
            $conta['valor_total_rateio'] = $conta['valor'];
        }

        return $conta;
    }
    public static function deleteConta(int $id, ?int $usuario_id, ?string $motivo): array
    {
        global $pdo;

        try {
            // 🔍 Buscar conta original
            $stmt = $pdo->prepare("SELECT * FROM financeiro_conta WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $conta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$conta) {
                throw new Exception("Conta não encontrada para o ID informado.");
            }

            $system_unit_id = (int)$conta['system_unit_id'];

            // 🔐 Inicia transação
            $pdo->beginTransaction();

            // 💾 Backup para tabela de excluídas
            $stmtBackup = $pdo->prepare("
            INSERT INTO financeiro_conta_excluidas 
            (conta_id, system_unit_id, dados_conta, motivo_exclusao, usuario_exclusao_id)
            VALUES (:conta_id, :unit, :dados, :motivo, :usuario)
        ");
            $stmtBackup->execute([
                ':conta_id' => $id,
                ':unit'     => $system_unit_id,
                ':dados'    => json_encode($conta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                ':motivo'   => $motivo,
                ':usuario'  => $usuario_id
            ]);

            // 🗑️ Remove da tabela original
            $stmtDel = $pdo->prepare("DELETE FROM financeiro_conta WHERE id = :id");
            $stmtDel->execute([':id' => $id]);

            if ($stmtDel->rowCount() === 0) {
                throw new Exception("Falha ao excluir conta.");
            }

            // 🧾 Log de auditoria (com dados antes e null depois)
            self::logAuditoria(
                'DELETE',
                'financeiro_conta',
                $id,
                $conta,
                null,
                $system_unit_id,
                $usuario_id
            );

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Conta excluída e backup registrado com sucesso.',
                'backup_id' => $pdo->lastInsertId()
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => 'Erro ao excluir conta: ' . $e->getMessage()];
        }
    }
    public static function editConta(array $data): array
    {
        global $pdo;

        try {
            if (empty($data['id'])) {
                throw new Exception("Campo obrigatório ausente: id da conta.");
            }

            $id = (int)$data['id'];

            // Compat: se vier forma_pagamento_id, usar como forma_pagamento (quando este não vier)
            if (!isset($data['forma_pagamento']) && isset($data['forma_pagamento_id'])) {
                $data['forma_pagamento'] = $data['forma_pagamento_id'];
            }

            // NOVO: Normaliza o tipo caso venha do frontend (R = Receita/Crédito, D = Despesa/Débito)
            if (isset($data['tipo'])) {
                $t = strtolower(trim($data['tipo']));
                $data['tipo'] = ($t === 'r') ? 'c' : (($t === 'd') ? 'd' : $t);
            }

            // 🔍 Buscar conta atual (para comparação e system_unit_id)
            $stCheck = $pdo->prepare("SELECT * FROM financeiro_conta WHERE id = :id LIMIT 1");
            $stCheck->execute([':id' => $id]);
            $contaAtual = $stCheck->fetch(PDO::FETCH_ASSOC);

            if (!$contaAtual) {
                throw new Exception("Conta não encontrada para o ID informado.");
            }

            $system_unit_id = (int)$contaAtual['system_unit_id'];
            $usuario_id = $data['usuario_id'] ?? null;

            // 🧭 Campos permitidos (inclui forma_pagamento e tipo)
            $camposPermitidos = [
                'nome','entidade','cgc','tipo','doc','emissao','vencimento','baixa_dt',
                'valor','plano_contas','banco','forma_pagamento','obs','inc_ope','bax_ope','comp_dt',
                'adic','comissao','local','cheque','dt_cheque','segmento'
            ];

            $setParts = [];
            $params = [':id' => $id];
            $dadosDepois = $contaAtual;

            foreach ($camposPermitidos as $campo) {
                if (array_key_exists($campo, $data)) {

                    // 🔎 Validação de plano de contas (mantida)
                    if ($campo === 'plano_contas' && !empty($data['plano_contas'])) {
                        $plano = trim($data['plano_contas']);
                        $stPlano = $pdo->prepare("
                    SELECT id FROM financeiro_plano 
                    WHERE system_unit_id = :unit AND codigo = :codigo LIMIT 1
                ");
                        $stPlano->execute([':unit' => $system_unit_id, ':codigo' => $plano]);
                        if (!$stPlano->fetch(PDO::FETCH_ASSOC)) {
                            throw new Exception("Plano de contas '{$plano}' não encontrado para esta unidade.");
                        }
                    }

                    $setParts[] = "$campo = :$campo";
                    $params[":$campo"] = $data[$campo];
                    $dadosDepois[$campo] = $data[$campo];
                }
            }

            if (empty($setParts)) {
                throw new Exception("Nenhum campo válido foi informado para atualização.");
            }

            $sql = "UPDATE financeiro_conta 
            SET " . implode(', ', $setParts) . ", updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() > 0) {
                // ✅ Log de auditoria
                self::logAuditoria(
                    'UPDATE',
                    'financeiro_conta',
                    $id,
                    $contaAtual,
                    $dadosDepois,
                    $system_unit_id,
                    $usuario_id
                );

                return [
                    'success' => true,
                    'message' => 'Conta atualizada com sucesso',
                    'id'      => $id
                ];
            } else {
                return ['success' => false, 'message' => 'Nenhuma alteração detectada ou falha ao atualizar.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao atualizar conta: ' . $e->getMessage()];
        }
    }
    public static function listContas($system_unit_id, $data_inicial, $data_final, $tipoData = 'emissao', $tipo = null) {
        global $pdo;

        // Coluna de filtro de data
        $colunaData = ($tipoData === 'vencimento') ? 'vencimento' : 'emissao';

        $sql = "
        SELECT 
            fc.*,
            fb.nome AS banco_nome
        FROM financeiro_conta fc
        LEFT JOIN financeiro_banco fb
            ON fb.system_unit_id = fc.system_unit_id
           AND fb.codigo = fc.banco  -- se você estiver gravando o ID do banco aqui
        WHERE fc.system_unit_id = :system_unit_id
          AND fc.{$colunaData} BETWEEN :data_inicial AND :data_final
    ";

        // Se o tipo for informado, aplica no WHERE
        if (!empty($tipo)) {
            $sql .= " AND fc.tipo = :tipo";
        }

        // ORDER BY vem por último
        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->bindValue(':data_inicial', $data_inicial);
        $stmt->bindValue(':data_final', $data_final);

        if (!empty($tipo)) {
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getDreGerencial($system_unit_id, $data_inicial, $data_final) {
        global $pdo;

        try {
            // 1. Consultar as contas dentro do período
            $stmt = $pdo->prepare("SELECT codigo, plano_contas, valor, emissao FROM financeiro_conta WHERE system_unit_id = :system_unit_id AND emissao BETWEEN :data_inicial AND :data_final");
            $stmt->execute([
                ':system_unit_id' => $system_unit_id,
                ':data_inicial' => $data_inicial,
                ':data_final' => $data_final
            ]);

            $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $categorias = [];



            foreach ($contas as $conta) {
                // 2. Verificar se existe rateio para a conta
                $stmtRateio = $pdo->prepare("SELECT idconta AS codigo, rateio_plano, rateio_valor, emissao FROM financeiro_rateio WHERE system_unit_id = :system_unit_id AND idconta = :conta");
                $stmtRateio->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':conta' => $conta['codigo']
                ]);

                $rateios = $stmtRateio->fetchAll(PDO::FETCH_ASSOC);



                if (!empty($rateios)) {
                    // Substituir a conta pelo rateio
                    foreach ($rateios as $rateio) {
                        $categorias[] = [
                            'plano_contas' => $rateio['rateio_plano'],
                            'valor' => $rateio['rateio_valor'],
                            'emissao' => $rateio['emissao']
                        ];
                    }
                } else {
                    // Adicionar a conta original se não houver rateio
                    $categorias[] = [
                        'plano_contas' => $conta['plano_contas'],
                        'valor' => $conta['valor'],
                        'emissao' => $conta['emissao']
                    ];
                }
            }

            // 3. Somar valores por plano com base em LIKE
            $stmtPlano = $pdo->prepare("SELECT codigo, descricao FROM financeiro_plano WHERE system_unit_id = :system_unit_id and descricao not like '%INATIVO%'");
            $stmtPlano->execute([':system_unit_id' => $system_unit_id]);
            $planos = $stmtPlano->fetchAll(PDO::FETCH_ASSOC);

            $somaCategorias = [];

            foreach ($planos as $plano) {
                $codigoPlano = $plano['codigo'];
                $descricaoPlano = $plano['descricao'];

                // Inicializar valores mensais para o plano atual
                $valoresMensais = array_fill(1, 12, 0);

                foreach ($categorias as $categoria) {
                    if (strpos($categoria['plano_contas'], $codigoPlano) === 0) {
                        $mesIndex = (int)date('m', strtotime($categoria['emissao']));
                        $valoresMensais[$mesIndex] += $categoria['valor'];
                    }
                }

                $somaCategorias[$codigoPlano] = [
                    'descricao' => $descricaoPlano,
                    'mensal' => $valoresMensais
                ];
            }


            // 4. Formatar o retorno final
            $resultadoFinal = [
                'title' => 'DRE Gerencial',
                'period' => [
                    'start' => $data_inicial,
                    'end' => $data_final,
                    'view' => 'Mensal',
                    'regime' => 'Competência'
                ],
                'categories' => []
            ];

            foreach ($somaCategorias as $codigo => $dados) {
                $resultadoFinal['categories'][] = [
                    'name' => $dados['descricao'],
                    'code' => $codigo,
                    'monthly_values' => array_values($dados['mensal'])
                ];
            }

            return [
                'success' => true,
                'data' => $resultadoFinal
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    public static function getContaByMonth($system_unit_id, $month, $year, $plano_contas) {
        global $pdo;

        try {
            $data_inicial = date("$year-$month-01");
            $data_final = date("Y-m-t", strtotime($data_inicial));

            // Consulta para obter as contas
            $stmtContas = $pdo->prepare(
                "SELECT 
                codigo, 
                nome, 
                entidade, 
                cgc, 
                tipo, 
                doc, 
                emissao, 
                vencimento, 
                baixa_dt, 
                valor, 
                plano_contas
            FROM financeiro_conta
            WHERE system_unit_id = :system_unit_id 
                AND codigo not in (select idconta from financeiro_rateio r where r.system_unit_id = :system_unit_id  AND rateio_plano LIKE :plano_contas AND r.emissao BETWEEN :data_inicial AND :data_final  )
                AND emissao BETWEEN :data_inicial AND :data_final
                AND plano_contas LIKE :plano_contas"
            );

            $stmtContas->execute([
                ':system_unit_id' => $system_unit_id,
                ':data_inicial' => $data_inicial,
                ':data_final' => $data_final,
                ':plano_contas' => $plano_contas . '%'
            ]);

            $contas = $stmtContas->fetchAll(PDO::FETCH_ASSOC);

            // Consulta para obter os rateios
            $stmtRateios = $pdo->prepare(
                "SELECT 
                r.idconta AS codigo, 
                r.nome, 
                r.entidade, 
                r.cgc, 
                r.tipo, 
                c.doc, 
                r.emissao, 
                r.vencimento, 
                r.baixa_dt, 
                r.rateio_valor AS valor, 
                r.rateio_plano AS plano_contas
            FROM financeiro_rateio r
            INNER JOIN financeiro_conta c ON r.idconta = c.codigo AND r.system_unit_id = c.system_unit_id
            WHERE r.system_unit_id = :system_unit_id 
                AND r.emissao BETWEEN :data_inicial AND :data_final
                AND r.rateio_plano LIKE :plano_contas"
            );

            $stmtRateios->execute([
                ':system_unit_id' => $system_unit_id,
                ':data_inicial' => $data_inicial,
                ':data_final' => $data_final,
                ':plano_contas' => $plano_contas . '%'
            ]);

            $rateios = $stmtRateios->fetchAll(PDO::FETCH_ASSOC);

            // Adicionando campo "origem" para distinguir os dados
            $contas = array_map(function ($conta) {
                $conta['origem'] = 'conta';
                return $conta;
            }, $contas);

            $rateios = array_map(function ($rateio) {
                $rateio['origem'] = 'rateio';
                return $rateio;
            }, $rateios);

            // Combina as duas listas
            $resultado = array_merge($contas, $rateios);

            return [
                'success' => true,
                'data' => $resultado
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    public static function lancarNotaNoFinanceiroContaLote(array $data): array
    {
        global $pdo;

        $gotLock = false;

        try {
            // ===== Helpers =====
            $parseDate = function (?string $s) {
                if (!$s) return null;
                $s = trim($s);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
                    [$d, $m, $y] = explode('/', $s);
                    return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                }
                throw new Exception("Data inválida: {$s}");
            };


            // ===== Entrada =====
            if (empty($data['system_unit_id'])) {
                throw new Exception('system_unit_id é obrigatório.');
            }
            $system_unit_id = (int)$data['system_unit_id'];
            $usuario_id = $data['usuario_id'] ?? null;

            if (empty($data['contas']) || !is_array($data['contas'])) {
                throw new Exception('contas deve ser um array com ao menos 1 item.');
            }

            // Normalização + cache
            $norm = [];
            $pairs = [];
            $pairsSeen = [];
            $chaves = [];

            foreach ($data['contas'] as $idx => $c) {
                $required = ['fornecedor_id', 'documento', 'emissao', 'vencimento', 'valor'];
                foreach ($required as $k) {
                    if (!isset($c[$k]) || $c[$k] === '' || $c[$k] === null) {
                        throw new Exception("Conta #" . ($idx + 1) . ": Campo obrigatório ausente: {$k}");
                    }
                }

                $entidade = (int)$c['fornecedor_id'];
                $doc = trim((string)$c['documento']);
                $emissao = $parseDate($c['emissao']);
                $vencimento = $parseDate($c['vencimento']);
                $valorBruto = $c['valor'];
                $adicional = $c['adicional'] ?? 0.0;
                $desconto = $c['desconto'] ?? 0.0;

                // NOVO: Extrai e normaliza o tipo
                $tipoBruto = isset($c['tipo']) ? strtolower(trim($c['tipo'])) : 'd';
                $tipo = ($tipoBruto === 'r') ? 'c' : (($tipoBruto === 'd') ? 'd' : $tipoBruto);

                $planoContas = isset($c['plano_contas']) ? trim((string)$c['plano_contas']) : null;
                $formaPgtoId = isset($c['forma_pagamento_id']) ? (int)$c['forma_pagamento_id'] : null;
                $obsExtra    = isset($c['obs_extra']) ? trim((string)$c['obs_extra']) : '';
                $chaveAcesso = isset($c['chave_acesso']) ? trim((string)$c['chave_acesso']) : null;

                $norm[] = [
                    'entidade'    => $entidade,
                    'doc'         => $doc,
                    'emissao'     => $emissao,
                    'vencimento'  => $vencimento,
                    'valorBruto'  => $valorBruto,
                    'adicional'   => $adicional,
                    'desconto'    => $desconto,
                    'tipo'        => $tipo,
                    'planoContas' => $planoContas,
                    'formaPgtoId' => $formaPgtoId,
                    'obsExtra'    => $obsExtra,
                    'chaveAcesso' => $chaveAcesso,
                ];

                $key = $entidade . '#' . $doc;
                if (!isset($pairsSeen[$key])) {
                    $pairsSeen[$key] = true;
                    $pairs[] = ['entidade' => $entidade, 'doc' => $doc];
                }
                if ($chaveAcesso) $chaves[$chaveAcesso] = true;
            }

            // ===== Verifica duplicidades =====
            $stChk = $pdo->prepare("
        SELECT id FROM financeiro_conta 
        WHERE system_unit_id = :unit AND entidade = :ent AND doc = :doc LIMIT 1
    ");
            foreach ($pairs as $p) {
                $stChk->execute([':unit' => $system_unit_id, ':ent' => $p['entidade'], ':doc' => $p['doc']]);
                if ($stChk->fetchColumn()) {
                    throw new Exception("Já existe lançamento para fornecedor/documento nesta unidade ({$p['entidade']}, {$p['doc']}).");
                }
            }

            // ===== Cache de fornecedor =====
            $fornCache = [];
            $getFornecedor = function (int $ent) use (&$fornCache, $pdo, $system_unit_id) {
                if (!isset($fornCache[$ent])) {
                    $st = $pdo->prepare("
                SELECT cnpj_cpf, razao
                FROM financeiro_fornecedor
                WHERE id = :id AND system_unit_id = :unit
                LIMIT 1
            ");
                    $st->execute([':id' => $ent, ':unit' => $system_unit_id]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);

                    if (!$row) {
                        throw new Exception("Fornecedor {$ent} não encontrado para a unidade informada.");
                    }
                    $fornCache[$ent] = [
                        'cgc'   => (string)($row['cnpj_cpf'] ?? ''),
                        'razao' => (string)($row['razao'] ?? ''),
                    ];
                }
                return $fornCache[$ent];
            };

            // ===== Transação =====
            $pdo->beginTransaction();

            $lockName = 'financeiro_conta_codigo_local_lock';
            $stLock = $pdo->prepare("SELECT GET_LOCK(:name, 5)");
            $stLock->execute([':name' => $lockName]);
            $gotLock = ((int)$stLock->fetchColumn() === 1);

            $stMin = $pdo->query("SELECT MIN(codigo) FROM financeiro_conta WHERE codigo <= 0");
            $minCodigo = $stMin->fetchColumn();
            $nextCodigo = ($minCodigo !== null) ? ((int)$minCodigo - 1) : 0;

            $stmtIns = $pdo->prepare("
        INSERT INTO financeiro_conta
        (system_unit_id, codigo, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt,
         valor, plano_contas, banco, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento)
        VALUES
        (:system_unit_id, :codigo, :nome, :entidade, :cgc, :tipo, :doc, :emissao, :vencimento, NULL,
         :valor, :plano_contas, :banco, :obs, NULL, NULL, NULL, :adic, 0.00, NULL, NULL, NULL, NULL)
    ");

            $ids = [];

            foreach ($norm as $i => $c) {
                $forn = $getFornecedor($c['entidade']);

                // Nome da conta = razão social do fornecedor
                $nome = $forn['razao'];

                $valorFinal = round($c['valorBruto'] + $c['adicional'] - $c['desconto'], 2);
                if ($valorFinal < 0) throw new Exception("Valor final não pode ser negativo (item #" . ($i + 1) . ").");

                $stmtIns->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':codigo'         => $nextCodigo,
                    ':nome'           => $nome,
                    ':entidade'       => $c['entidade'],
                    ':cgc'            => $forn['cgc'],
                    ':tipo'           => $c['tipo'], // NOVO: Manda a variável processada
                    ':doc'            => $c['doc'],
                    ':emissao'        => $c['emissao'],
                    ':vencimento'     => $c['vencimento'],
                    ':valor'          => $valorFinal,
                    ':plano_contas'   => $c['planoContas'],
                    ':banco'          => $c['formaPgtoId'],
                    ':obs'            => $c['obsExtra'],
                    ':adic'           => $c['adicional']
                ]);

                $id = (int)$pdo->lastInsertId();
                $ids[] = $id;

                self::logAuditoria('CREATE','financeiro_conta',$id,null,
                    $pdo->query("SELECT * FROM financeiro_conta WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC),
                    $system_unit_id,
                    $usuario_id
                );

                $nextCodigo -= 1;
            }

            // Marca notas como incluídas
            if (!empty($chaves)) {
                $stU = $pdo->prepare("
            UPDATE estoque_nota
            SET incluida_financeiro = 1, updated_at = CURRENT_TIMESTAMP
            WHERE system_unit_id = :unit AND chave_acesso = :chave
            LIMIT 1
        ");
                foreach (array_keys($chaves) as $ch) {
                    $stU->execute([':unit' => $system_unit_id, ':chave' => $ch]);
                }
            }

            $pdo->commit();

            return [
                'success'   => true,
                'inseridos' => count($ids),
                'ids'       => $ids
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            if (!empty($gotLock)) {
                try { $pdo->query("SELECT RELEASE_LOCK('financeiro_conta_codigo_local_lock')"); } catch (\Throwable $t) {}
            }
        }
    }
    public static function lancarNotaNoFinanceiroConta(array $data): array
    {
        global $pdo;

        $gotLock = false;

        try {
            // ===== Helpers =====
            $parseDate = function (?string $s) {
                if (!$s) return null;
                $s = trim($s);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
                    [$d,$m,$y] = explode('/',$s);
                    return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                }
                throw new Exception("Data inválida: {$s}");
            };

            $required = ['system_unit_id','fornecedor_id','documento','emissao','vencimento','valor'];
            foreach ($required as $k) {
                if (!isset($data[$k]) || $data[$k] === '' || $data[$k] === null) {
                    throw new Exception("Campo obrigatório ausente: {$k}");
                }
            }

            $system_unit_id  = (int)$data['system_unit_id'];
            $usuario_id      = $data['usuario_id'] ?? null;
            $entidade        = (int)$data['fornecedor_id'];
            $doc             = trim((string)$data['documento']);
            $emissao         = $parseDate($data['emissao']);
            $vencimento      = $parseDate($data['vencimento']);
            $valorBruto      = $data['valor'];
            $adicional       = $data['adicional'] ?? 0.0;
            $desconto        = $data['desconto'] ?? 0.0;
            $planoContas     = isset($data['plano_contas']) ? trim((string)$data['plano_contas']) : null;
            $formaPgtoId     = isset($data['forma_pagamento_id']) ? (int)$data['forma_pagamento_id'] : null;
            $chaveAcesso     = isset($data['chave_acesso']) ? trim((string)$data['chave_acesso']) : null;

            $stmtF = $pdo->prepare("
            SELECT cnpj_cpf, nome
            FROM financeiro_fornecedor
            WHERE id = :id AND system_unit_id = :unit
            LIMIT 1
        ");
            $stmtF->execute([':id'=>$entidade, ':unit'=>$system_unit_id]);
            $forn = $stmtF->fetch(PDO::FETCH_ASSOC);
            if (!$forn) throw new Exception("Fornecedor não encontrado.");

            $cgc  = $forn['cnpj_cpf'] ?? '';
            $nome = $forn['razao'];

            $valorFinal = round($valorBruto + $adicional - $desconto, 2);
            if ($valorFinal < 0) throw new Exception("Valor final não pode ser negativo.");

            $obs = $data['obs_extra'] ?? '';

            $pdo->beginTransaction();

            // Verifica duplicidade
            $chk = $pdo->prepare("
            SELECT id FROM financeiro_conta 
            WHERE system_unit_id = :unit AND entidade = :ent AND doc = :doc 
            LIMIT 1
        ");
            $chk->execute([':unit'=>$system_unit_id, ':ent'=>$entidade, ':doc'=>$doc]);
            if ($chk->fetchColumn()) {
                $pdo->rollBack();
                return ['success'=>false, 'error'=>'Já existe lançamento para este fornecedor/documento.'];
            }

            // Lock lógico
            $stLock = $pdo->prepare("SELECT GET_LOCK(:name, 5)");
            $stLock->execute([':name'=>'financeiro_conta_codigo_local_lock']);
            $gotLock = ((int)$stLock->fetchColumn() === 1);

            $stMin = $pdo->query("SELECT MIN(codigo) FROM financeiro_conta WHERE codigo <= 0");
            $minCodigo = $stMin->fetchColumn();
            $nextCodigo = ($minCodigo !== null) ? ((int)$minCodigo - 1) : 0;

            // INSERT
            $stmt = $pdo->prepare("
            INSERT INTO financeiro_conta
            (system_unit_id, codigo, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt,
             valor, plano_contas, banco, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento)
            VALUES
            (:system_unit_id, :codigo, :nome, :entidade, :cgc, :tipo, :doc, :emissao, :vencimento, NULL,
             :valor, :plano_contas, :banco, :obs, NULL, NULL, NULL, :adic, 0.00, NULL, NULL, NULL, NULL)
        ");
            $stmt->execute([
                ':system_unit_id' => $system_unit_id,
                ':codigo'         => $nextCodigo,
                ':nome'           => $nome,
                ':entidade'       => $entidade,
                ':cgc'            => $cgc,
                ':tipo'           => 'd',
                ':doc'            => $doc,
                ':emissao'        => $emissao,
                ':vencimento'     => $vencimento,
                ':valor'          => $valorFinal,
                ':plano_contas'   => $planoContas,
                ':banco'          => $formaPgtoId,
                ':obs'            => $obs,
                ':adic'           => $adicional
            ]);

            $id = (int)$pdo->lastInsertId();

            // 🔍 Busca registro completo p/ log
            $stConta = $pdo->prepare("SELECT * FROM financeiro_conta WHERE id = :id LIMIT 1");
            $stConta->execute([':id' => $id]);
            $novaConta = $stConta->fetch(PDO::FETCH_ASSOC);

            // 🧾 Log
            self::logAuditoria(
                'CREATE',
                'financeiro_conta',
                $id,
                null,
                $novaConta,
                $system_unit_id,
                $usuario_id
            );

            // Marca nota
            if ($chaveAcesso) {
                $stU = $pdo->prepare("
                UPDATE estoque_nota
                SET incluida_financeiro = 1, updated_at = CURRENT_TIMESTAMP
                WHERE system_unit_id = :unit AND chave_acesso = :chave
                LIMIT 1
            ");
                $stU->execute([':unit' => $system_unit_id, ':chave' => $chaveAcesso]);
            }

            $pdo->commit();

            return ['success'=>true,'id'=>$id,'codigo'=>$nextCodigo];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success'=>false,'error'=>$e->getMessage()];
        } finally {
            if (!empty($gotLock)) {
                try { $pdo->query("SELECT RELEASE_LOCK('financeiro_conta_codigo_local_lock')"); } catch (\Throwable $t) {}
            }
        }
    }
    public static function exportContasF360(array $data): array
    {
        global $pdo;

        try {
            if (empty($data['system_unit_id'])) {
                throw new Exception("Campo obrigatório ausente: system_unit_id");
            }
            $unitId = (int)$data['system_unit_id'];

            // helper p/ dd/mm/yyyy
            $br = function (?string $ymd): string {
                if (!$ymd) return '';
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
                    return "{$m[3]}/{$m[2]}/{$m[1]}";
                }
                return $ymd;
            };

            $sql = "
        SELECT
            fc.id,
            fc.doc,
            fc.codigo,
            fc.obs,
            fc.emissao,
            fc.vencimento,
            fc.valor,

            -- Fornecedor/cliente
            COALESCE(NULLIF(fc.cgc,''), NULLIF(ff.cnpj_cpf,''))                           AS cli_doc,
            COALESCE(NULLIF(fc.nome,''), NULLIF(ff.nome,''), NULLIF(ff.razao,''))          AS cli_nome,

            -- Plano de contas (por código)
            fp.descricao AS plano_codigo,

            -- Forma de pagamento via financeiro_banco
            COALESCE(fb.codigo, fc.banco, 0) AS forma_pg_id,
            COALESCE(fb.nome, '')            AS forma_pg_desc,
            COALESCE(fb.descricao, '')       AS forma_pg_cod

        FROM financeiro_conta fc
        LEFT JOIN financeiro_plano fp
               ON fp.system_unit_id = fc.system_unit_id
              AND fp.codigo = fc.plano_contas
        LEFT JOIN financeiro_fornecedor ff
               ON ff.id = fc.entidade
              AND ff.system_unit_id = fc.system_unit_id
        LEFT JOIN financeiro_banco fb
               ON fb.system_unit_id = fc.system_unit_id
              AND fb.codigo = fc.banco
        WHERE fc.system_unit_id = :unit
          AND fc.tipo = 'd'
          AND (fc.baixa_dt IS NULL OR fc.baixa_dt = '0000-00-00')
          AND fc.exportado_f360 = 0
        ORDER BY fc.vencimento ASC, fc.id ASC
        ";

            $st = $pdo->prepare($sql);
            $st->execute([':unit' => $unitId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $out = [];
            foreach ($rows as $r) {
                $numero = trim((string)($r['doc'] ?? ''));
                if ($numero === '') {
                    $numero = (string)($r['codigo'] ?? '');
                }

                $out[] = [
                    'id'                       => (int)$r['id'],                     // para marcar depois
                    'numero'                   => $numero,                            // Nº
                    'observacao'               => (string)($r['obs'] ?? ''),          // Observação
                    'cliente_fornecedor'       => (string)($r['cli_nome'] ?? ''),     // Nome
                    'emissao'                  => $br($r['emissao'] ?? null),         // DD/MM/YYYY
                    'vencimento'               => $br($r['vencimento'] ?? null),      // DD/MM/YYYY
                    'valor'                    => (float)$r['valor'],                 // número
                    'plano_de_conta'           => (string)($r['plano_codigo'] ?? ''),

                    // Campos de forma de pagamento vindo de financeiro_banco
                    'forma_pagamento_id'       => (int)$r['forma_pg_id'],
                    'forma_pagamento'          => (string)$r['forma_pg_desc'],
                    'forma_pagamento_codigo'   => (string)$r['forma_pg_cod'],
                ];
            }

            return [
                'success'         => true,
                'system_unit_id'  => $unitId,
                'columns'         => [
                    'numero',
                    'observacao',
                    'cliente_fornecedor',
                    'emissao',
                    'vencimento',
                    'valor',
                    'plano_de_conta',
                    'forma_pagamento'
                ],
                'rows'            => $out,
                'count'           => count($out)
            ];

        } catch (Exception $e) {
            return ['success'=>false, 'error'=>$e->getMessage()];
        }
    }
    public static function marcarExportadoF360(array $data): array
    {
        global $pdo;

        try {
            if (empty($data['system_unit_id'])) {
                throw new Exception("Campo obrigatório ausente: system_unit_id");
            }
            if (empty($data['ids']) || !is_array($data['ids'])) {
                throw new Exception("Campo obrigatório ausente: ids (array).");
            }

            $unitId = (int)$data['system_unit_id'];

            // normaliza IDs
            $ids = array_values(array_unique(array_map(fn($v) => (int)$v, $data['ids'])));
            $ids = array_values(array_filter($ids, fn($v) => $v > 0));
            if (!$ids) {
                throw new Exception("Lista de ids vazia após normalização.");
            }

            // filtra IDs que realmente pertencem à unidade
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $sel = $pdo->prepare("SELECT id FROM financeiro_conta WHERE system_unit_id = ? AND id IN ($in)");
            $sel->execute(array_merge([$unitId], $ids));
            $validos = $sel->fetchAll(PDO::FETCH_COLUMN, 0);
            $validos = array_map('intval', $validos);

            // quais foram ignorados (não pertencem à unidade)
            $ignorados = array_values(array_diff($ids, $validos));

            $marcados = 0;
            if ($validos) {
                $pdo->beginTransaction();
                $inUpd = implode(',', array_fill(0, count($validos), '?'));
                $upd   = $pdo->prepare("
                UPDATE financeiro_conta
                SET exportado_f360 = 1, updated_at = CURRENT_TIMESTAMP
                WHERE system_unit_id = ? AND id IN ($inUpd)
            ");
                $upd->execute(array_merge([$unitId], $validos));
                $marcados = $upd->rowCount();
                $pdo->commit();
            }

            return [
                'success' => true,
                'system_unit_id' => $unitId,
                'marcados' => $marcados,
                'ignorados' => $ignorados
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success'=>false, 'error'=>$e->getMessage()];
        }
    }
    public static function hasF360Integration($system_unit_id): array
    {
        global $pdo;

        try {

            $st = $pdo->prepare("
            SELECT COALESCE(f360_integration, 0) AS flag
            FROM system_unit
            WHERE id = :id
            LIMIT 1
        ");
            $st->execute([':id' => $system_unit_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return ['success' => false, 'error' => 'Unidade não encontrada.'];
            }

            // retorna 1 ou 0
            $active = ((int)$row['flag'] === 1) ? 1 : 0;

            return ['success' => true, 'active' => $active];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public static function baixarConta(array $data): array
    {
        global $pdo;

        try {
            if (empty($data['id'])) {
                throw new Exception('Campo obrigatório ausente: id da conta.');
            }

            // Nova Validação: Exige o Código do Banco OU a Forma de Pagamento (compatibilidade com telas antigas)
            if (
                empty($data['banco']) &&
                empty($data['forma_pagamento']) &&
                empty($data['forma_pagamento_id'])
            ) {
                throw new Exception('Campo obrigatório ausente: código do banco ou forma_pagamento.');
            }

            $id         = (int)$data['id'];
            $usuario_id = $data['usuario_id'] ?? null;

            // 1) Busca conta
            $stConta = $pdo->prepare("
            SELECT * FROM financeiro_conta 
            WHERE id = :id 
            LIMIT 1
        ");
            $stConta->execute([':id' => $id]);
            $contaAtual = $stConta->fetch(PDO::FETCH_ASSOC);

            if (!$contaAtual) {
                throw new Exception('Conta não encontrada para o ID informado.');
            }

            if (!empty($contaAtual['baixa_dt']) && $contaAtual['baixa_dt'] !== '0000-00-00') {
                throw new Exception('Conta já está baixada (quitada).');
            }

            $system_unit_id = (int)$contaAtual['system_unit_id'];

            // 2) Preparar Campos de Atualização base
            $baixa_dt = !empty($data['baixa_dt']) ? $data['baixa_dt'] : date('Y-m-d');

            $camposUpdate = [
                'baixa_dt' => $baixa_dt
            ];

            // 3) Lógica Inteligente: Define como vamos preencher o Banco
            if (!empty($data['banco'])) {
                // Cenário NOVO: Recebeu o código do banco direto da tela de quitação em lote
                $camposUpdate['banco'] = $data['banco'];

                // Opcional: Se no seu banco a coluna forma_pagamento for obrigatória,
                // você pode replicar o código ou setar nulo. Se não for, basta não incluí-la no update.
                $camposUpdate['forma_pagamento'] = $data['banco'];
            } else {
                // Cenário LEGADO: Recebeu forma de pagamento, vai buscar o banco vinculado
                $formaPagamentoId = (int)($data['forma_pagamento'] ?? $data['forma_pagamento_id']);

                $stFP = $pdo->prepare("
                SELECT id, banco_padrao_id
                FROM financeiro_forma_pagamento
                WHERE id = :id
                  AND system_unit_id = :unit
                  AND ativos = 1
                LIMIT 1
            ");
                $stFP->execute([
                    ':id'   => $formaPagamentoId,
                    ':unit' => $system_unit_id
                ]);

                $formaPagamento = $stFP->fetch(PDO::FETCH_ASSOC);

                if (!$formaPagamento) {
                    throw new Exception('Forma de pagamento inválida ou inativa.');
                }

                $camposUpdate['forma_pagamento'] = $formaPagamentoId;

                if (!empty($formaPagamento['banco_padrao_id'])) {
                    $camposUpdate['banco'] = $formaPagamento['banco_padrao_id'];
                }
            }

            // 4) Executa o Update
            $setParts    = [];
            $params      = [':id' => $id];
            $dadosDepois = $contaAtual;

            foreach ($camposUpdate as $campo => $valor) {
                $setParts[]           = "{$campo} = :{$campo}";
                $params[":{$campo}"]  = $valor;
                $dadosDepois[$campo]  = $valor;
            }

            $sql = "
            UPDATE financeiro_conta
            SET " . implode(', ', $setParts) . ",
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ";

            $stmtUpd = $pdo->prepare($sql);
            $stmtUpd->execute($params);

            if ($stmtUpd->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'Nenhuma alteração realizada.'
                ];
            }

            // 5) Reload para pegar os dados frescos
            $stReload = $pdo->prepare("
            SELECT * FROM financeiro_conta 
            WHERE id = :id 
            LIMIT 1
        ");
            $stReload->execute([':id' => $id]);
            $contaAtualizada = $stReload->fetch(PDO::FETCH_ASSOC);

            // 6) Auditoria
            self::logAuditoria(
                'BAIXA',
                'financeiro_conta',
                $id,
                $contaAtual,
                $dadosDepois,
                $system_unit_id,
                $usuario_id
            );

            return [
                'success' => true,
                'message' => 'Conta baixada (quitada) com sucesso.',
                'data'    => $contaAtualizada
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao baixar conta: ' . $e->getMessage()
            ];
        }
    }
    public static function baixarContasEmLote(array $payload): array
    {
        global $pdo;

        try {
            // Verifica se o roteador passou o payload raiz ou já repassou a chave 'data'
            $contas = isset($payload['data']) ? $payload['data'] : $payload;

            if (!is_array($contas) || empty($contas)) {
                throw new Exception('Nenhuma conta fornecida para baixa em lote.');
            }

            if (count($contas) > 10) {
                throw new Exception('O limite máximo para quitação em lote é de 10 contas por vez.');
            }

            $pdo->beginTransaction();

            $resultados = [];

            foreach ($contas as $index => $contaData) {
                if (!is_array($contaData)) {
                    throw new Exception("O item na posição {$index} é inválido.");
                }

                if (empty($contaData['id'])) {
                    throw new Exception("ID da conta ausente no item {$index}.");
                }

                // NOVA VALIDAÇÃO: Aceita 'banco' ou 'forma_pagamento' para manter a compatibilidade
                if (empty($contaData['baixa_dt'])) {
                    throw new Exception("Data de baixa ausente na conta ID {$contaData['id']}.");
                }

                if (empty($contaData['banco']) && empty($contaData['forma_pagamento'])) {
                    throw new Exception("Banco ou Forma de Pagamento ausente na conta ID {$contaData['id']}.");
                }

                // Chama o método individual
                $res = self::baixarConta($contaData);

                if (!isset($res['success']) || !$res['success']) {
                    $msgErro = $res['message'] ?? 'Erro desconhecido ao processar.';
                    throw new Exception("Erro na conta ID {$contaData['id']}: {$msgErro}");
                }

                $resultados[] = $res['data'] ?? $contaData['id'];
            }

            $pdo->commit();

            return [
                'success' => true,
                'message' => count($resultados) . ' contas foram baixadas com sucesso.',
                'total'   => count($resultados),
                'data'    => $resultados
            ];

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Erro na baixa em lote: ' . $e->getMessage()
            ];
        }
    }
    public static function getExtratoBancario(array $data): array
    {
        global $pdo;

        try {
            // ===== Validações básicas =====
            if (empty($data['system_unit_id'])) {
                throw new Exception("Campo obrigatório ausente: system_unit_id");
            }
            if (!isset($data['banco']) || $data['banco'] === '') {
                throw new Exception("Campo obrigatório ausente: banco (Código da conta)");
            }
            if (empty($data['data_inicial']) || empty($data['data_final'])) {
                throw new Exception("Campos obrigatórios ausentes: data_inicial e data_final");
            }

            $unitId      = (int)$data['system_unit_id'];
            $bancoCodigo = $data['banco']; // Código (ex: '341', '1', '001')

            // ===== Helper para datas =====
            $parseDate = function (string $s): string {
                $s = trim($s);
                if ($s === '') throw new Exception("Data inválida.");
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
                if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
                throw new Exception("Data inválida: {$s}");
            };

            $dtIni = $parseDate($data['data_inicial']);
            $dtFim = $parseDate($data['data_final']);

            if ($dtFim < $dtIni) {
                throw new Exception("Data final não pode ser menor que a data inicial.");
            }

            // ===== Consulta Principal Ajustada =====
            $sql = "
        SELECT
            fc.id,
            fc.baixa_dt,
            fc.doc,
            fc.nome,
            fc.tipo,
            fc.valor,
            fc.obs,
            
            -- Informações Visuais
            fp.nome      AS forma_pagamento_nome,
            
            -- COALESCE: Se achar o nome pelo vínculo da forma, usa ele. Senão, usa o direto.
            COALESCE(fb_fp.nome, fb_dir.nome) AS banco_nome,
            
            pl.descricao AS plano_descricao,
            pl.codigo    AS plano_codigo

        FROM financeiro_conta fc
        
        -- 1. Dados da Forma de Pagamento
        LEFT JOIN financeiro_forma_pagamento fp 
               ON fp.id = fc.forma_pagamento
        
        -- 2. Join Banco via Forma de Pagamento (Pelo CÓDIGO + UNIDADE)
        LEFT JOIN financeiro_banco fb_fp 
               ON fb_fp.codigo = fp.banco_padrao_id 
              AND fb_fp.system_unit_id = fc.system_unit_id
        
        -- 3. Join Banco Direto (Pelo CÓDIGO + UNIDADE)
        LEFT JOIN financeiro_banco fb_dir 
               ON fb_dir.codigo = fc.banco 
              AND fb_dir.system_unit_id = fc.system_unit_id

        -- 4. Dados do Plano de Contas
        LEFT JOIN financeiro_plano pl 
               ON pl.system_unit_id = fc.system_unit_id 
              AND pl.codigo = fc.plano_contas

        WHERE fc.system_unit_id = :unit
          AND fc.baixa_dt IS NOT NULL
          AND fc.baixa_dt BETWEEN :dtIni AND :dtFim
          
          -- LÓGICA DE FILTRO PELO CÓDIGO
          AND (
              fp.banco_padrao_id = :bancoCodigo  -- O código na forma de pagamento bate com o filtro
              OR 
              fc.banco = :bancoCodigo            -- O código na conta bate com o filtro
          )

        ORDER BY fc.baixa_dt ASC, fc.id ASC
        ";

            $st = $pdo->prepare($sql);
            $st->execute([
                ':unit'        => $unitId,
                ':bancoCodigo' => $bancoCodigo,
                ':dtIni'       => $dtIni,
                ':dtFim'       => $dtFim,
            ]);

            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            // ===== Totais =====
            $totalDebitos  = 0.0;
            $totalCreditos = 0.0;

            foreach ($rows as $r) {
                $valor = (float)($r['valor'] ?? 0);

                if ($r['tipo'] === 'd') {
                    $totalDebitos += $valor;
                } elseif ($r['tipo'] === 'c') {
                    $totalCreditos += $valor;
                }
            }

            $saldoPeriodo = $totalCreditos - $totalDebitos;

            return [
                'success' => true,
                'filters' => [
                    'system_unit_id' => $unitId,
                    'banco_codigo'   => $bancoCodigo,
                    'data_inicial'   => $dtIni,
                    'data_final'     => $dtFim,
                ],
                'totals'  => [
                    'total_debitos'  => $totalDebitos,
                    'total_creditos' => $totalCreditos,
                    'saldo_periodo'  => $saldoPeriodo,
                ],
                'rows'    => $rows,
                'count'   => count($rows),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error'   => 'Erro ao gerar extrato: ' . $e->getMessage()
            ];
        }
    }
    public static function getDashboardFinanceiroPorGrupo(array $data): array
    {
        global $pdo;

        $groupId   = isset($data['group_id'])   ? (int)$data['group_id']   : null;
        $dtInicio  = $data['dt_inicio'] ?? null;
        $dtFim     = $data['dt_fim']    ?? null;
        $tipoData  = strtolower($data['tipo_data'] ?? 'vencimento');

        if (!$groupId || !$dtInicio || !$dtFim) {
            return [
                'success' => false,
                'message' => 'Parâmetros obrigatórios: group_id, dt_inicio, dt_fim'
            ];
        }

        if (!in_array($tipoData, ['emissao', 'vencimento'], true)) {
            $tipoData = 'vencimento';
        }
        $campoData = $tipoData === 'emissao' ? 'emissao' : 'vencimento';

        try {
            // 1) Descobrir unidades do grupo
            $units = BiController::getUnitsByGroup($groupId);

            if (empty($units)) {
                return [
                    'success' => false,
                    'message' => 'Nenhuma unidade encontrada para o grupo informado.'
                ];
            }

            $unitIds      = [];
            $mapUnitNames = [];

            foreach ($units as $unit) {
                $id = isset($unit['id']) ? (int)$unit['id'] : (int)$unit['system_unit_id'];
                $unitIds[] = $id;
                $mapUnitNames[$id] = $unit['name'] ?? $unit['nome'] ?? ('Unidade ' . $id);
            }

            $unitIds = array_values(array_unique($unitIds));
            if (empty($unitIds)) {
                return [
                    'success' => false,
                    'message' => 'Nenhum ID de unidade válido encontrado para o grupo.'
                ];
            }

            $unitIdsStr = implode(',', array_map('intval', $unitIds));

            // ===========================
            // Estrutura base do retorno
            // ===========================
            $result = [
                'params' => [
                    'group_id'   => $groupId,
                    'dt_inicio'  => $dtInicio,
                    'dt_fim'     => $dtFim,
                    'tipo_data'  => $tipoData,
                    'campo_data' => $campoData,
                ],

                // Contas a vencer (sempre por VENCIMENTO, tipo D, em aberto)
                'total_contas_vencer_7'  => 0.0,
                'qtd_contas_vencer_7'    => 0,
                'contas_vencer_7'        => [],

                'total_contas_vencer_30' => 0.0,
                'qtd_contas_vencer_30'   => 0,
                'contas_vencer_30'       => [],

                // Totais gerais (grupo inteiro) - AGORA GERAIS, NÃO APENAS EM ABERTO
                'total_debitos_geral'    => 0.0,
                'total_creditos_geral'   => 0.0,

                // Totais e planos por unidade
                'por_unidade'            => [],

                // Planos agregados no grupo (para gráfico geral)
                'planos_geral'           => [],
            ];

            // Inicializar estrutura por unidade
            foreach ($unitIds as $uid) {
                $result['por_unidade'][$uid] = [
                    'system_unit_id' => $uid,
                    'nome_unidade'   => $mapUnitNames[$uid] ?? ('Unidade ' . $uid),
                    'totais' => [
                        'debitos'  => 0.0,
                        'creditos' => 0.0,
                    ],
                    'planos' => [],
                ];
            }

            // ===========================
            // 2) Contas a vencer 7 e 30 dias (tipo D, EM ABERTO)
            // ===========================
            $hoje   = new DateTime();
            $dtHoje = $hoje->format('Y-m-d');
            $dt7    = (clone $hoje)->modify('+6 days')->format('Y-m-d');
            $dt30   = (clone $hoje)->modify('+30 days')->format('Y-m-d');

            $whereBaseAberto = "
            fc.system_unit_id IN ($unitIdsStr)
            AND (fc.baixa_dt IS NULL OR fc.baixa_dt = '0000-00-00')
            AND fc.tipo = 'D'
        ";

            // 2.1) Próximos 7 dias
            $sqlV7 = "
            SELECT 
                fc.id,
                fc.system_unit_id,
                fc.codigo,
                fc.nome,
                fc.entidade,
                fc.cgc,
                fc.tipo,
                fc.doc,
                fc.emissao,
                fc.vencimento,
                fc.valor,
                fc.plano_contas,
                fp.descricao AS plano_descricao
            FROM financeiro_conta fc
            LEFT JOIN financeiro_plano fp
                ON fp.system_unit_id = fc.system_unit_id
               AND fp.codigo = fc.plano_contas
            WHERE $whereBaseAberto
              AND fc.vencimento BETWEEN :dt_hoje AND :dt_limite
            ORDER BY fc.vencimento ASC
        ";

            $stmtV7 = $pdo->prepare($sqlV7);
            $stmtV7->execute([
                ':dt_hoje'   => $dtHoje,
                ':dt_limite' => $dt7,
            ]);
            $rows7 = $stmtV7->fetchAll(PDO::FETCH_ASSOC);

            $total7 = 0.0;
            foreach ($rows7 as $row) {
                $total7 += (float)$row['valor'];
            }

            $result['total_contas_vencer_7'] = $total7;
            $result['qtd_contas_vencer_7']   = count($rows7);
            $result['contas_vencer_7']       = $rows7;

            // 2.2) Próximos 30 dias
            $sqlV30 = "
            SELECT 
                fc.id,
                fc.system_unit_id,
                fc.codigo,
                fc.nome,
                fc.entidade,
                fc.cgc,
                fc.tipo,
                fc.doc,
                fc.emissao,
                fc.vencimento,
                fc.valor,
                fc.plano_contas,
                fp.descricao AS plano_descricao
            FROM financeiro_conta fc
            LEFT JOIN financeiro_plano fp
                ON fp.system_unit_id = fc.system_unit_id
               AND fp.codigo = fc.plano_contas
            WHERE $whereBaseAberto
              AND fc.vencimento BETWEEN :dt_hoje AND :dt_limite
            ORDER BY fc.vencimento ASC
        ";

            $stmtV30 = $pdo->prepare($sqlV30);
            $stmtV30->execute([
                ':dt_hoje'   => $dtHoje,
                ':dt_limite' => $dt30,
            ]);
            $rows30 = $stmtV30->fetchAll(PDO::FETCH_ASSOC);

            $total30 = 0.0;
            foreach ($rows30 as $row) {
                $total30 += (float)$row['valor'];
            }

            $result['total_contas_vencer_30'] = $total30;
            $result['qtd_contas_vencer_30']   = count($rows30);
            $result['contas_vencer_30']       = $rows30;

            // ===========================
            // 3) Totais por unidade (D / C) no período (tipo_data)
            //    AGORA SEM FILTRAR POR baixa_dt (PEGA TUDO)
            // ===========================
            $sqlTotais = "
            SELECT 
                system_unit_id,
                tipo,
                SUM(valor) AS total
            FROM financeiro_conta
            WHERE system_unit_id IN ($unitIdsStr)
              AND $campoData BETWEEN :dt_inicio AND :dt_fim
            GROUP BY system_unit_id, tipo
        ";

            $stmtTotais = $pdo->prepare($sqlTotais);
            $stmtTotais->execute([
                ':dt_inicio' => $dtInicio,
                ':dt_fim'    => $dtFim,
            ]);

            $rowsTotais = $stmtTotais->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rowsTotais as $row) {
                $uid  = (int)$row['system_unit_id'];
                $tipo = strtoupper($row['tipo']);
                $tot  = (float)$row['total'];

                if (!isset($result['por_unidade'][$uid])) {
                    $result['por_unidade'][$uid] = [
                        'system_unit_id' => $uid,
                        'nome_unidade'   => $mapUnitNames[$uid] ?? ('Unidade ' . $uid),
                        'totais' => [
                            'debitos'  => 0.0,
                            'creditos' => 0.0,
                        ],
                        'planos' => [],
                    ];
                }

                if ($tipo === 'D') {
                    $result['por_unidade'][$uid]['totais']['debitos'] += $tot;
                } elseif ($tipo === 'C') {
                    $result['por_unidade'][$uid]['totais']['creditos'] += $tot;
                }
            }

            // ===========================
            // 4) Planos por unidade (para gráfico)
            //    TAMBÉM SEM FILTRAR POR baixa_dt
            // ===========================
            $sqlPlanos = "
            SELECT 
                fc.system_unit_id,
                fc.plano_contas,
                fp.descricao AS plano_descricao,
                fc.tipo,
                SUM(fc.valor) AS total
            FROM financeiro_conta fc
            LEFT JOIN financeiro_plano fp
                ON fp.system_unit_id = fc.system_unit_id
               AND fp.codigo = fc.plano_contas
            WHERE fc.system_unit_id IN ($unitIdsStr)
              AND fc.$campoData BETWEEN :dt_inicio AND :dt_fim
            GROUP BY 
                fc.system_unit_id,
                fc.plano_contas,
                fp.descricao,
                fc.tipo
            ORDER BY 
                fc.system_unit_id,
                fp.descricao,
                fc.plano_contas
        ";

            $stmtPlanos = $pdo->prepare($sqlPlanos);
            $stmtPlanos->execute([
                ':dt_inicio' => $dtInicio,
                ':dt_fim'    => $dtFim,
            ]);

            $rowsPlanos = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);

            // Mapa auxiliar para planos_geral
            $planosGeral = [];

            foreach ($rowsPlanos as $row) {
                $uid        = (int)$row['system_unit_id'];
                $codPlano   = $row['plano_contas'] ?? '';
                $descPlano  = $row['plano_descricao'] ?? $codPlano;
                $tipo       = strtoupper($row['tipo']);
                $totalPlano = (float)$row['total'];

                if (!$codPlano) {
                    $codPlano  = 'SEM_PLANO';
                    $descPlano = $descPlano ?: 'Sem Plano';
                }

                if (!isset($result['por_unidade'][$uid])) {
                    $result['por_unidade'][$uid] = [
                        'system_unit_id' => $uid,
                        'nome_unidade'   => $mapUnitNames[$uid] ?? ('Unidade ' . $uid),
                        'totais' => [
                            'debitos'  => 0.0,
                            'creditos' => 0.0,
                        ],
                        'planos' => [],
                    ];
                }

                if (!isset($result['por_unidade'][$uid]['planos'][$codPlano])) {
                    $result['por_unidade'][$uid]['planos'][$codPlano] = [
                        'plano_codigo'    => $codPlano,
                        'plano_descricao' => $descPlano,
                        'total_debitos'   => 0.0,
                        'total_creditos'  => 0.0,
                        'total_geral'     => 0.0,
                    ];
                }

                if ($tipo === 'D') {
                    $result['por_unidade'][$uid]['planos'][$codPlano]['total_debitos'] += $totalPlano;
                } elseif ($tipo === 'C') {
                    $result['por_unidade'][$uid]['planos'][$codPlano]['total_creditos'] += $totalPlano;
                }

                // Agregado geral por plano (grupo inteiro)
                if (!isset($planosGeral[$codPlano])) {
                    $planosGeral[$codPlano] = [
                        'plano_codigo'    => $codPlano,
                        'plano_descricao' => $descPlano,
                        'total_debitos'   => 0.0,
                        'total_creditos'  => 0.0,
                        'total_geral'     => 0.0,
                    ];
                }

                if ($tipo === 'D') {
                    $planosGeral[$codPlano]['total_debitos'] += $totalPlano;
                } elseif ($tipo === 'C') {
                    $planosGeral[$codPlano]['total_creditos'] += $totalPlano;
                }
            }

            // ===========================
            // 5) Fechar totais gerais e normalizar arrays
            // ===========================
            $totalDebitosGeral  = 0.0;
            $totalCreditosGeral = 0.0;

            foreach ($result['por_unidade'] as $uid => &$uData) {
                $deb = (float)$uData['totais']['debitos'];
                $cre = (float)$uData['totais']['creditos'];

                $totalDebitosGeral  += $deb;
                $totalCreditosGeral += $cre;

                if (!empty($uData['planos']) && is_array($uData['planos'])) {
                    foreach ($uData['planos'] as &$plano) {
                        $plano['total_geral'] = (float)$plano['total_debitos'] + (float)$plano['total_creditos'];
                    }
                    $uData['planos'] = array_values($uData['planos']);
                }
            }
            unset($uData);

            $result['total_debitos_geral']  = $totalDebitosGeral;
            $result['total_creditos_geral'] = $totalCreditosGeral;

            foreach ($planosGeral as &$plano) {
                $plano['total_geral'] = (float)$plano['total_debitos'] + (float)$plano['total_creditos'];
            }
            unset($plano);

            $result['planos_geral'] = array_values($planosGeral);

            return [
                'success' => true,
                'data'    => $result,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao gerar dashboard financeiro por grupo: ' . $e->getMessage(),
            ];
        }
    }
    public static function getMapaDeContas(array $data): array
    {
        global $pdo;

        try {
            // ========= helpers =========
            $normalizeTipo = function($s) {
                $s = trim(mb_strtolower((string)$s));
                $s = str_replace(['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç'],
                    ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c'], $s);
                if (in_array($s, ['emissao','emissao_data','data_emissao','e'])) return 'emissao';
                if (in_array($s, ['vencimento','vcto','data_vencimento','v'])) return 'vencimento';
                throw new Exception("tipo_data inválido. Use 'emissao' ou 'vencimento'.");
            };

            $parseAnoMes = function($s): array {
                $s = trim((string)$s);
                if ($s === '') throw new Exception("ano_mes é obrigatório.");

                // YYYY-MM ou YYYY/MM
                if (preg_match('/^(\d{4})[-\/](\d{2})$/', $s, $m)) {
                    $ano = (int)$m[1];
                    $mes = (int)$m[2];
                }
                // MM/YYYY
                elseif (preg_match('/^(\d{2})[-\/](\d{4})$/', $s, $m)) {
                    $mes = (int)$m[1];
                    $ano = (int)$m[2];
                }
                // YYYYMM
                elseif (preg_match('/^(\d{4})(\d{2})$/', $s, $m)) {
                    $ano = (int)$m[1];
                    $mes = (int)$m[2];
                } else {
                    throw new Exception("ano_mes inválido. Exemplos aceitos: 2025-12, 2025/12, 12/2025, 202512");
                }

                if ($mes < 1 || $mes > 12) throw new Exception("Mês inválido em ano_mes.");

                $inicio = sprintf('%04d-%02d-01', $ano, $mes);
                $dt = new DateTime($inicio);
                $fim = $dt->modify('last day of this month')->format('Y-m-d');

                return [$ano, $mes, $inicio, $fim];
            };

            // ========= validação =========
            $unitId = (int)($data['unit_id'] ?? $data['system_unit_id'] ?? 0);
            if ($unitId <= 0) throw new Exception("unit_id (ou system_unit_id) é obrigatório.");

            $tipoData = $normalizeTipo($data['tipo_data'] ?? '');
            [$ano, $mes, $dtInicio, $dtFim] = $parseAnoMes($data['ano_mes'] ?? '');

            // ========= query =========
            if ($tipoData === 'vencimento') {
                // filtra pelas duplicatas dentro do mês (data_vencimento)
                $sql = "
                SELECT
                    n.id                    AS nota_id,
                    n.fornecedor_id         AS fornecedor_id,
                    n.numero_nf             AS documento,
                    n.data_emissao          AS data_emissao,
                    d.id                    AS duplicata_id,
                    d.numero_duplicata      AS numero_duplicata,
                    d.data_vencimento       AS data_vencimento,
                    d.valor_parcela         AS valor,
                    COALESCE(NULLIF(ff.nome,''), NULLIF(ff.razao,''), 'Fornecedor') AS fornecedor_nome
                FROM estoque_nota_duplicata d
                INNER JOIN estoque_nota n
                        ON n.id = d.nota_id
                       AND n.system_unit_id = d.system_unit_id
                LEFT JOIN financeiro_fornecedor ff
                       ON ff.id = n.fornecedor_id
                      AND ff.system_unit_id = n.system_unit_id
                WHERE d.system_unit_id = :unit
                  AND d.data_vencimento BETWEEN :ini AND :fim
                ORDER BY d.data_vencimento ASC, fornecedor_nome ASC, n.numero_nf ASC, d.numero_duplicata ASC
            ";
            } else {
                // filtra notas pela emissão dentro do mês (data_emissao)
                // - se tiver duplicatas, retorna 1 linha por duplicata
                // - se não tiver duplicata, retorna 1 linha com valor = n.valor_total
                $sql = "
                SELECT
                    n.id                    AS nota_id,
                    n.fornecedor_id         AS fornecedor_id,
                    n.numero_nf             AS documento,
                    n.data_emissao          AS data_emissao,
                    d.id                    AS duplicata_id,
                    d.numero_duplicata      AS numero_duplicata,
                    d.data_vencimento       AS data_vencimento,
                    CASE 
                        WHEN d.id IS NULL THEN n.valor_total
                        ELSE d.valor_parcela
                    END                     AS valor,
                    COALESCE(NULLIF(ff.nome,''), NULLIF(ff.razao,''), 'Fornecedor') AS fornecedor_nome
                FROM estoque_nota n
                LEFT JOIN estoque_nota_duplicata d
                       ON d.nota_id = n.id
                      AND d.system_unit_id = n.system_unit_id
                LEFT JOIN financeiro_fornecedor ff
                       ON ff.id = n.fornecedor_id
                      AND ff.system_unit_id = n.system_unit_id
                WHERE n.system_unit_id = :unit
                  AND n.data_emissao BETWEEN :ini AND :fim
                ORDER BY n.data_emissao ASC, fornecedor_nome ASC, n.numero_nf ASC, d.numero_duplicata ASC
            ";
            }

            $st = $pdo->prepare($sql);
            $st->execute([
                ':unit' => $unitId,
                ':ini'  => $dtInicio,
                ':fim'  => $dtFim,
            ]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            // ========= monta retorno =========
            $itens = [];
            $diasMap = []; // 'YYYY-MM-DD' => ['data'=>..., 'total'=>..., 'itens'=>[]]
            $totalGeral = 0.0;

            foreach ($rows as $r) {
                $emissao = !empty($r['data_emissao']) ? date('Y-m-d', strtotime($r['data_emissao'])) : null;
                $venc    = !empty($r['data_vencimento']) ? date('Y-m-d', strtotime($r['data_vencimento'])) : null;

                $valor = (float)($r['valor'] ?? 0);
                $totalGeral += $valor;

                $dataRef = ($tipoData === 'vencimento') ? $venc : $emissao; // dia do mapa

                $item = [
                    'tipo_data'         => $tipoData,
                    'data_ref'          => $dataRef,
                    'nota_id'           => (int)$r['nota_id'],
                    'duplicata_id'      => $r['duplicata_id'] !== null ? (int)$r['duplicata_id'] : null,
                    'fornecedor_id'     => (int)($r['fornecedor_id'] ?? 0),
                    'fornecedor_nome'   => (string)($r['fornecedor_nome'] ?? ''),
                    'documento'         => (string)($r['documento'] ?? ''),
                    'numero_duplicata'  => $r['numero_duplicata'] !== null ? (string)$r['numero_duplicata'] : null,
                    'emissao'           => $emissao,
                    'vencimento'        => $venc,
                    'valor'             => number_format($valor, 2, '.', ''),
                ];

                $itens[] = $item;

                // agrupa no mapa por dia
                if ($dataRef) {
                    if (!isset($diasMap[$dataRef])) {
                        $diasMap[$dataRef] = [
                            'data'  => $dataRef,
                            'total' => 0.0,
                            'itens' => [],
                        ];
                    }
                    $diasMap[$dataRef]['total'] += $valor;
                    $diasMap[$dataRef]['itens'][] = $item;
                }
            }

            // normaliza dias para array ordenado
            ksort($diasMap);
            $dias = [];
            foreach ($diasMap as $d) {
                $dias[] = [
                    'data'  => $d['data'],
                    'total' => number_format((float)$d['total'], 2, '.', ''),
                    'itens' => $d['itens'],
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'periodo' => [
                        'system_unit_id' => $unitId,
                        'ano'            => $ano,
                        'mes'            => $mes,
                        'dt_inicio'      => $dtInicio,
                        'dt_fim'         => $dtFim,
                        'tipo_data'      => $tipoData,
                    ],
                    'totais' => [
                        'qtd_itens'   => count($itens),
                        'valor_total' => number_format($totalGeral, 2, '.', ''),
                    ],
                    'dias'  => $dias,
                    'itens' => $itens,
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }
    }

    public static function editarContasEmLote($payload) {
        global $pdo;

        $system_unit_id = $payload['system_unit_id'] ?? 0;
        $ids            = $payload['ids'] ?? [];
        $dadosUpdate    = $payload['dados'] ?? [];
        $usuario_id     = $payload['usuario_id'] ?? null; // Captura o usuário do payload

        // Validações básicas de segurança
        if (empty($system_unit_id)) {
            return ['success' => false, 'message' => 'Unidade não informada.'];
        }
        if (empty($ids) || !is_array($ids)) {
            return ['success' => false, 'message' => 'Nenhuma conta foi selecionada.'];
        }
        if (empty($dadosUpdate)) {
            return ['success' => false, 'message' => 'Nenhum dado válido para atualizar.'];
        }

        // Mapeamento seguro: vincula a chave do JSON recebida ao nome exato da coluna no Banco de Dados
        $colunasPermitidas = [
            'plano_contas'    => 'plano_contas',
            'vencimento'      => 'vencimento',
            'forma_pagamento' => 'forma_pagamento',
            'banco'           => 'banco',
            'data_baixa'      => 'baixa_dt'
        ];

        $setQueryParts = [];
        $parametrosQuery = [];

        // Monta a string do SET dinamicamente (Ex: SET vencimento = ?, banco = ?)
        foreach ($dadosUpdate as $key => $value) {
            if (array_key_exists($key, $colunasPermitidas) && $value !== '') {
                $colunaDB = $colunasPermitidas[$key];
                $setQueryParts[] = "{$colunaDB} = ?";
                $parametrosQuery[] = $value;
            }
        }

        // Trava de segurança extra
        if (empty($setQueryParts)) {
            return ['success' => false, 'message' => 'Nenhum campo autorizado para atualização.'];
        }

        $placeholdersIN = implode(',', array_fill(0, count($ids), '?'));

        try {
            $pdo->beginTransaction();

            // 1. Busca os dados de todas as contas ANTES da alteração (Para Auditoria)
            $paramsSelect = array_merge($ids, [$system_unit_id]);
            $sqlSelect = "SELECT * FROM financeiro_conta WHERE id IN ($placeholdersIN) AND system_unit_id = ?";

            $stmtAntes = $pdo->prepare($sqlSelect);
            $stmtAntes->execute($paramsSelect);
            $contasAntes = $stmtAntes->fetchAll(PDO::FETCH_ASSOC);

            $mapAntes = [];
            foreach ($contasAntes as $conta) {
                $mapAntes[$conta['id']] = $conta;
            }

            // 2. Executa o UPDATE em lote
            $parametrosUpdate = array_merge($parametrosQuery, $ids, [$system_unit_id]);

            $sqlUpd = "UPDATE financeiro_conta SET " . implode(', ', $setQueryParts) . ", updated_at = CURRENT_TIMESTAMP 
                       WHERE id IN ($placeholdersIN) AND system_unit_id = ?";

            $stmtUpd = $pdo->prepare($sqlUpd);
            $stmtUpd->execute($parametrosUpdate);
            $linhasAfetadas = $stmtUpd->rowCount();

            // 3. Busca os dados DEPOIS da alteração
            $stmtDepois = $pdo->prepare($sqlSelect);
            $stmtDepois->execute($paramsSelect);
            $contasDepois = $stmtDepois->fetchAll(PDO::FETCH_ASSOC);

            // 4. Registra cada item na Auditoria
            foreach ($contasDepois as $contaDep) {
                $idConta = $contaDep['id'];
                $contaAnt = $mapAntes[$idConta] ?? null;

                self::logAuditoria(
                    'UPDATE_LOTE',
                    'financeiro_conta',
                    $idConta,
                    $contaAnt,
                    $contaDep,
                    $system_unit_id,
                    $usuario_id
                );
            }

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Lote de contas atualizado com sucesso.',
                'linhas_afetadas' => $linhasAfetadas
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Erro SQL ao atualizar em massa: ' . $e->getMessage()
            ];
        }
    }




}
