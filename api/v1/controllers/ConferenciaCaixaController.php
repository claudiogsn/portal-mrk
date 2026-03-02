<?php

require_once __DIR__ . '/../database/db.php';

class ConferenciaCaixaController
{
    private static function getLojaIdBySystemUnit($systemUnitId)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT custom_code FROM system_unit WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $systemUnitId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) throw new Exception("Unidade não encontrada.");
        return $result['custom_code'];
    }

    public static function saveConferencia($data): array
    {
        global $pdo;

        $systemUnitId = $data['system_unit_id'] ?? null;
        $dataContabil = $data['data'] ?? null;
        $userId       = $data['user_id'] ?? null;
        $items        = $data['items'] ?? [];

        if (!$systemUnitId || !$dataContabil || !$userId || empty($items)) {
            return ['success' => false, 'message' => 'Dados incompletos.'];
        }

        try {
            $pdo->beginTransaction();

            // --- Dados para Mensagem e CNPJ ---
            $stmtUnit = $pdo->prepare("SELECT name, cnpj FROM system_unit WHERE id = ?");
            $stmtUnit->execute([$systemUnitId]);
            $unitData = $stmtUnit->fetch(PDO::FETCH_ASSOC);

            $nomeEmpresa = $unitData['name'] ?? 'Empresa Desconhecida';
            $cnpjEmpresa = !empty($unitData['cnpj']) ? $unitData['cnpj'] : '00.000.000/0000-00';

            $stmtUser = $pdo->prepare("SELECT name, phone FROM system_users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

            $nomeUsuario = $userData['name'] ?? 'Usuário';
            $telefoneUsuario = $userData['phone'] ?? null;

            $dataCaixaFormatada = date('d/m/Y', strtotime($dataContabil));
            $dataRealizacao     = date('d/m/Y H:i');

            $resumoMsg = "🏢 *Conferência de Caixa*\n\n";
            $resumoMsg .= "🏪 *Empresa:* {$nomeEmpresa}\n";
            $resumoMsg .= "👤 *Resp:* {$nomeUsuario}\n";
            $resumoMsg .= "📅 *Data:* {$dataCaixaFormatada}\n";
            $resumoMsg .= "🕒 *Em:* {$dataRealizacao}\n";
            $resumoMsg .= "--------------------------------\n";

            // --- Queries Preparadas (Conferência) ---
            $stmtCheck = $pdo->prepare("SELECT id, valor_adquirente FROM conferencia_caixa WHERE system_unit_id = :system_unit_id AND data_contabil = :data_contabil AND codigo_opcao = :codigo_opcao LIMIT 1");
            $stmtInsert = $pdo->prepare("INSERT INTO conferencia_caixa (system_unit_id, data_contabil, codigo_opcao, nome_opcao, valor_venda, valor_processado, valor_adquirente, diferenca, user_id) VALUES (:system_unit_id, :data_contabil, :codigo_opcao, :nome_opcao, :valor_venda, :valor_processado, :valor_adquirente, :diferenca, :user_id)");
            $stmtUpdate = $pdo->prepare("UPDATE conferencia_caixa SET valor_venda = :valor_venda, valor_processado = :valor_processado, valor_adquirente = :valor_adquirente, diferenca = :diferenca, user_id = :user_id, updated_at = NOW() WHERE id = :id");
            $stmtAudit = $pdo->prepare("INSERT INTO conferencia_caixa_auditoria (conferencia_id, user_id, valor_anterior, valor_novo, motivo) VALUES (:conferencia_id, :user_id, :valor_anterior, :valor_novo, :motivo)");

            $contasParaFinanceiro = []; // Array para passar ao novo método

            foreach ($items as $item) {
                $codigoOpcao = $item['codigo_opcao'] ?? null;
                $nomeOpcao   = strtoupper(trim($item['forma_pagamento']));

                if (empty($codigoOpcao)) continue;

                $venda       = (float) ($item['venda_pdv'] ?? 0);
                $processado  = (float) ($item['processado_pagos'] ?? 0);
                $adquirente  = (float) ($item['valor_adquirente'] ?? 0);
                $diferenca   = $venda - $adquirente;
                $motivo      = $item['motivo'] ?? null;

                // Mensagem WPP
                $icone = abs($diferenca) > 0.01 ? '⚠️' : '✅';
                $resumoMsg .= "💳 *{$nomeOpcao}* {$icone}\n";
                $resumoMsg .= "   Venda: R$ " . number_format($venda, 2, ',', '.') . "\n";
                $resumoMsg .= "   Inf: R$ " . number_format($adquirente, 2, ',', '.') . "\n";
                if (abs($diferenca) > 0.01) $resumoMsg .= "   Dif: R$ " . number_format($diferenca, 2, ',', '.') . "\n";
                $resumoMsg .= "\n";

                // Salva no Banco de Conferência
                $stmtCheck->execute([':system_unit_id' => $systemUnitId, ':data_contabil' => $dataContabil, ':codigo_opcao' => $codigoOpcao]);
                $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existente) {
                    if (abs((float)$existente['valor_adquirente'] - $adquirente) > 0.001) {
                        $stmtAudit->execute([':conferencia_id' => $existente['id'], ':user_id' => $userId, ':valor_anterior' => $existente['valor_adquirente'], ':valor_novo' => $adquirente, ':motivo' => $motivo ?? 'Alteração de valor']);
                    }
                    $stmtUpdate->execute([':valor_venda' => $venda, ':valor_processado' => $processado, ':valor_adquirente' => $adquirente, ':diferenca' => $diferenca, ':user_id' => $userId, ':id' => $existente['id']]);
                } else {
                    $stmtInsert->execute([':system_unit_id' => $systemUnitId, ':data_contabil' => $dataContabil, ':codigo_opcao' => $codigoOpcao, ':nome_opcao' => $nomeOpcao, ':valor_venda' => $venda, ':valor_processado' => $processado, ':valor_adquirente' => $adquirente, ':diferenca' => $diferenca, ':user_id' => $userId]);
                }

                // Acumula para enviar ao módulo financeiro
                if ($adquirente > 0) {
                    $contasParaFinanceiro[] = [
                        'codigo_opcao'  => $codigoOpcao,
                        'nome_original' => $nomeOpcao,
                        'valor'         => $adquirente
                    ];
                }
            }

            // --- Chamada Limpa para o Novo Método Financeiro ---
            if (!empty($contasParaFinanceiro)) {
                $payloadFinanceiro = [
                    'system_unit_id' => $systemUnitId,
                    'data_contabil'  => $dataContabil,
                    'user_id'        => $userId,
                    'cnpj_empresa'   => $cnpjEmpresa,
                    'items'          => $contasParaFinanceiro
                ];

                $resFinanceiro = self::createContaCredito($payloadFinanceiro);

                if (!$resFinanceiro['success']) {
                    throw new Exception("Erro ao gerar contas no financeiro: " . $resFinanceiro['message']);
                }
            }

            $pdo->commit();

            if (!empty($telefoneUsuario)) {
                UtilsController::sendWhatsapp($telefoneUsuario, $resumoMsg);
            }

            return ['success' => true, 'message' => 'Conferência salva e financeiro integrado com sucesso!'];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()];
        }
    }
    public static function createContaCredito($data): array
    {
        global $pdo;

        $systemUnitId = $data['system_unit_id'] ?? null;
        $dataContabil = $data['data_contabil'] ?? null;
        $userId       = $data['user_id'] ?? null;
        $cnpjEmpresa  = $data['cnpj_empresa'] ?? '00.000.000/0000-00';
        $items        = $data['items'] ?? [];

        if (empty($items)) {
            return ['success' => true, 'message' => 'Nenhum item financeiro para processar.'];
        }

        try {
            // 1. Busca Regras
            $stmtOpcaoRec = $pdo->prepare("
            SELECT nome, banco_id, prazo, taxa, plano_contas_id, fornecedor_id 
            FROM financeiro_opcoes_recebimento 
            WHERE system_unit_id = ? AND codigo = ?
            LIMIT 1
        ");

            // 2. Busca Fornecedor pelo nome
            $stmtFindForn = $pdo->prepare("
            SELECT id 
            FROM financeiro_fornecedor 
            WHERE system_unit_id = ? AND razao = ? 
            LIMIT 1
        ");

            // 3. Busca próximo código do fornecedor
            $stmtNextFornCodigo = $pdo->prepare("
    SELECT COALESCE(MAX(CAST(codigo AS UNSIGNED)), 0) + 1
    FROM financeiro_fornecedor
    WHERE system_unit_id = ?
");

            // 4. Insere fornecedor fallback
            $stmtInsertForn = $pdo->prepare("
    INSERT INTO financeiro_fornecedor (system_unit_id, codigo, razao, nome, cnpj_cpf) 
    VALUES (?, ?, ?, ?, ?)
");

            // 5. Salva vínculo do fornecedor
            $stmtLinkForn = $pdo->prepare("
            UPDATE financeiro_opcoes_recebimento 
            SET fornecedor_id = ? 
            WHERE system_unit_id = ? AND codigo = ?
        ");

            // 6. Verifica se a conta já existe
            $stmtCheckConta = $pdo->prepare("
            SELECT id 
            FROM financeiro_conta 
            WHERE system_unit_id = ? AND doc = ? AND tipo = 'c'
            LIMIT 1
        ");

            // 7. Atualiza a conta existente
            $stmtUpdateConta = $pdo->prepare("
            UPDATE financeiro_conta SET
                nome = ?,
                entidade = ?,
                cgc = ?,
                vencimento = ?,
                valor = ?,
                banco = ?,
                plano_contas = ?,
                forma_pagamento = ?,
                obs = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

            // 8. Dados exatos do fornecedor
            $stmtDadosForn = $pdo->prepare("
            SELECT razao, cnpj_cpf 
            FROM financeiro_fornecedor 
            WHERE id = ?
        ");

            $contasParaLote = [];

            foreach ($items as $item) {
                $codigoOpcao  = $item['codigo_opcao'] ?? $item['codigo'] ?? null;
                $valor        = (float)($item['valor'] ?? 0);
                $nomeOriginal = $item['nome_original'] ?? 'Desconhecido';

                if (empty($codigoOpcao) || $valor <= 0) {
                    continue;
                }

                $stmtOpcaoRec->execute([$systemUnitId, $codigoOpcao]);
                $opcaoRecData = $stmtOpcaoRec->fetch(PDO::FETCH_ASSOC);

                $nomeOpcaoDb    = $opcaoRecData ? strtoupper(trim($opcaoRecData['nome'])) : strtoupper(trim($nomeOriginal));
                $bancoId        = $opcaoRecData ? $opcaoRecData['banco_id'] : null;
                $planoContasId  = $opcaoRecData ? $opcaoRecData['plano_contas_id'] : null;
                $prazoDias      = $opcaoRecData ? (int)$opcaoRecData['prazo'] : 0;
                $taxaPercentual = $opcaoRecData ? (float)$opcaoRecData['taxa'] : 0.00;

                $fornecedorVinculado = $opcaoRecData ? $opcaoRecData['fornecedor_id'] : null;

                $valorDesconto = $valor * ($taxaPercentual / 100);
                $valorLiquido  = round($valor - $valorDesconto, 2);

                $vencimentoCalculado = date('Y-m-d', strtotime($dataContabil . " + {$prazoDias} days"));
                $documentoFormatado  = "CONF-" . date('Ymd', strtotime($dataContabil)) . "-" . $codigoOpcao;
                $obsFormatada        = "Criado via Conferência de Caixa ({$nomeOpcaoDb}) - Taxa Deduzida: {$taxaPercentual}%";

                $fornecedorId = null;

                // Resolve fornecedor
                if (!empty($fornecedorVinculado)) {
                    $fornecedorId = $fornecedorVinculado;
                } else {
                    $stmtFindForn->execute([$systemUnitId, $nomeOpcaoDb]);
                    $fornecedorId = $stmtFindForn->fetchColumn();

                    if (!$fornecedorId) {
                        $codigoAjustado = str_pad($codigoOpcao, 2, '0', STR_PAD_LEFT);
                        $cnpjFicticio   = "00.000.000/0000-" . $codigoAjustado;

                        $stmtNextFornCodigo->execute([$systemUnitId]);
                        $novoCodigoFornecedor = (int)$stmtNextFornCodigo->fetchColumn();

                        $stmtInsertForn->execute([
                            $systemUnitId,
                            $novoCodigoFornecedor,
                            $nomeOpcaoDb,   // razao
                            $nomeOpcaoDb,   // nome
                            $cnpjFicticio
                        ]);

                        $fornecedorId = $pdo->lastInsertId();
                    }

                    if ($opcaoRecData) {
                        $stmtLinkForn->execute([$fornecedorId, $systemUnitId, $codigoOpcao]);
                    }
                }

                $stmtDadosForn->execute([$fornecedorId]);
                $fornDb = $stmtDadosForn->fetch(PDO::FETCH_ASSOC);

                $fornRazao = $fornDb['razao'] ?? $nomeOpcaoDb;
                $fornCnpj  = $fornDb['cnpj_cpf'] ?? '';

                $stmtCheckConta->execute([$systemUnitId, $documentoFormatado]);
                $contaExistenteId = $stmtCheckConta->fetchColumn();

                if ($contaExistenteId) {
                    $stmtUpdateConta->execute([
                        $fornRazao,
                        $fornecedorId,
                        $fornCnpj,
                        $vencimentoCalculado,
                        $valorLiquido,
                        $bancoId,
                        $planoContasId,
                        $codigoOpcao,
                        $obsFormatada,
                        $contaExistenteId
                    ]);
                } else {
                    $contasParaLote[] = [
                        'fornecedor_id'   => $fornecedorId,
                        'documento'       => $documentoFormatado,
                        'emissao'         => $dataContabil,
                        'vencimento'      => $vencimentoCalculado,
                        'valor'           => $valorLiquido,
                        'tipo'            => 'c',
                        'forma_pagamento' => $codigoOpcao,
                        'banco'           => $bancoId,
                        'plano_contas'    => $planoContasId,
                        'obs'             => $obsFormatada
                    ];
                }
            }

            if (!empty($contasParaLote)) {
                $payloadLote = [
                    'system_unit_id' => $systemUnitId,
                    'usuario_id'     => $userId,
                    'rateio'         => 0,
                    'contas'         => $contasParaLote
                ];

                $resLote = FinanceiroContaController::createContaLote($payloadLote);

                if (!$resLote['success']) {
                    throw new Exception($resLote['error'] ?? 'Erro desconhecido ao gerar lote de contas.');
                }
            }

            return ['success' => true, 'message' => 'Contas de crédito processadas com sucesso.'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    public static function getPayloadConferencia($systemUnitId, $dataAnalise): array
    {
        global $pdo;

        try {
            $lojaId = self::getLojaIdBySystemUnit($systemUnitId);
            $dataAnalise = date('Y-m-d', strtotime($dataAnalise));

            // PASSO 1: Preparar Mapa de Vínculos
            $stmtVinc = $pdo->prepare("
            SELECT UPPER(TRIM(nome_meio)) as nome_meio, nome_opcao, codigo_opcao 
            FROM financeiro_opcoes_vinculo_meios 
            WHERE system_unit_id = :unit
        ");
            $stmtVinc->execute([':unit' => $systemUnitId]);

            $mapaVinculos = [];
            while ($row = $stmtVinc->fetch(PDO::FETCH_ASSOC)) {
                $mapaVinculos[$row['nome_meio']] = [
                    'codigo' => $row['codigo_opcao'],
                    'nome'   => $row['nome_opcao']
                ];
            }

            // PASSO 2: Consultar vendas usando a MESMA BASE do detalhamento
            $sqlVendas = "
            SELECT
                UPPER(TRIM(descricao)) AS meio_original,
                NULL AS codigo_meio_pdv,
                SUM(valor) AS total_venda
            FROM api_pagamentos
            WHERE id_loja = :loja_id
              AND data_contabil = :data_analise
              AND (status_pagamento IS NULL OR status_pagamento <> 'cancelado')
            GROUP BY UPPER(TRIM(descricao))
        ";

            $stmtRaw = $pdo->prepare($sqlVendas);
            $stmtRaw->execute([
                ':loja_id'      => $lojaId,
                ':data_analise' => $dataAnalise
            ]);

            $vendasPDV = $stmtRaw->fetchAll(PDO::FETCH_ASSOC);

            // PASSO 3: Consultar Conferência Já Realizada
            $stmtSaved = $pdo->prepare("
            SELECT 
                codigo_opcao, nome_opcao, valor_venda, valor_adquirente, 
                diferenca, user_id, updated_at
            FROM conferencia_caixa
            WHERE system_unit_id = :unit AND data_contabil = :data
        ");
            $stmtSaved->execute([':unit' => $systemUnitId, ':data' => $dataAnalise]);

            $dadosSalvos = [];
            while ($row = $stmtSaved->fetch(PDO::FETCH_ASSOC)) {
                $dadosSalvos[$row['codigo_opcao']] = $row;
            }

            // PASSO 4: Processamento Lógico
            $agrupamentoVendas = [];

            foreach ($vendasPDV as $venda) {
                $nomeBruto  = $venda['meio_original'];
                $valorVenda = (float)$venda['total_venda'];
                $codigoPdv  = $venda['codigo_meio_pdv'] ?? null;

                if (isset($mapaVinculos[$nomeBruto])) {
                    $cod = $mapaVinculos[$nomeBruto]['codigo'];
                    $nom = $mapaVinculos[$nomeBruto]['nome'];

                    if (!isset($agrupamentoVendas[$cod])) {
                        $agrupamentoVendas[$cod] = [
                            'nome_opcao' => $nom,
                            'venda_pdv'  => 0.0,
                            'is_linked'  => true
                        ];
                    }

                    $agrupamentoVendas[$cod]['venda_pdv'] += $valorVenda;
                } else {
                    $chave = 'RAW_' . $nomeBruto;
                    $agrupamentoVendas[$chave] = [
                        'nome_opcao'      => null,
                        'forma_pagamento' => $nomeBruto,
                        'codigo_meio_pdv' => $codigoPdv,
                        'venda_pdv'       => $valorVenda,
                        'is_linked'       => false
                    ];
                }
            }

            // PASSO 5: Construção do Retorno Final
            $resultadoFinal = [];

            foreach ($agrupamentoVendas as $chave => $dados) {
                $vendaPDV = $dados['venda_pdv'];

                if ($dados['is_linked']) {
                    $codigoOpcao = $chave;
                    $nomeOpcao   = $dados['nome_opcao'];

                    if (isset($dadosSalvos[$codigoOpcao])) {
                        $salvo = $dadosSalvos[$codigoOpcao];
                        $resultadoFinal[] = [
                            "codigo_opcao"           => $codigoOpcao,
                            "nome_opcao"             => $nomeOpcao,
                            "forma_pagamento"        => null,
                            "venda_pdv"              => round($vendaPDV, 2),
                            "valor_adquirente"       => (float)$salvo['valor_adquirente'],
                            "diferenca"              => round($vendaPDV - $salvo['valor_adquirente'], 2),
                            "tem_vinculo"            => null,
                            "nome_usuario_alteracao" => self::getUserName($salvo['user_id']),
                            "data_ultima_alteracao"  => date('d/m/Y H:i', strtotime($salvo['updated_at']))
                        ];
                        unset($dadosSalvos[$codigoOpcao]);
                    } else {
                        $resultadoFinal[] = [
                            "codigo_opcao"           => $codigoOpcao,
                            "nome_opcao"             => $nomeOpcao,
                            "forma_pagamento"        => $nomeOpcao,
                            "venda_pdv"              => round($vendaPDV, 2),
                            "valor_adquirente"       => 0,
                            "diferenca"              => round($vendaPDV, 2),
                            "tem_vinculo"            => true,
                            "nome_usuario_alteracao" => null,
                            "data_ultima_alteracao"  => null
                        ];
                    }
                } else {
                    $resultadoFinal[] = [
                        "codigo_opcao"           => null,
                        "nome_opcao"             => null,
                        "forma_pagamento"        => $dados['forma_pagamento'],
                        "codigo_meio_pdv"        => $dados['codigo_meio_pdv'] ?? null,
                        "venda_pdv"              => round($vendaPDV, 2),
                        "valor_adquirente"       => 0,
                        "diferenca"              => round($vendaPDV, 2),
                        "tem_vinculo"            => false,
                        "nome_usuario_alteracao" => null,
                        "data_ultima_alteracao"  => null
                    ];
                }
            }

            // Itens conferidos sem venda hoje
            foreach ($dadosSalvos as $cod => $salvo) {
                $resultadoFinal[] = [
                    "codigo_opcao"           => $salvo['codigo_opcao'],
                    "nome_opcao"             => $salvo['nome_opcao'],
                    "forma_pagamento"        => null,
                    "venda_pdv"              => 0,
                    "valor_adquirente"       => (float)$salvo['valor_adquirente'],
                    "diferenca"              => round(0 - $salvo['valor_adquirente'], 2),
                    "tem_vinculo"            => null,
                    "nome_usuario_alteracao" => self::getUserName($salvo['user_id']),
                    "data_ultima_alteracao"  => date('d/m/Y H:i', strtotime($salvo['updated_at']))
                ];
            }

            usort($resultadoFinal, function($a, $b) {
                $aScore = is_null($a['tem_vinculo']) ? 2 : ($a['tem_vinculo'] === true ? 1 : 0);
                $bScore = is_null($b['tem_vinculo']) ? 2 : ($b['tem_vinculo'] === true ? 1 : 0);
                return $bScore <=> $aScore;
            });

            return ['success' => true, 'data' => $resultadoFinal];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    public static function getVendasByForma($systemUnitId, $dataAnalise, $chaveBusca, $tipoBusca): array
    {
        global $pdo;

        if (!$systemUnitId || !$dataAnalise || !$chaveBusca || !$tipoBusca) {
            return ['success' => false, 'message' => 'Parâmetros inválidos.'];
        }

        try {
            $lojaId = self::getLojaIdBySystemUnit($systemUnitId);
            $meiosParaBuscar = [];

            if ($tipoBusca === 'OPCAO') {
                $stmtMeios = $pdo->prepare("
                    SELECT UPPER(TRIM(nome_meio)) 
                    FROM financeiro_opcoes_vinculo_meios 
                    WHERE system_unit_id = :unit 
                      AND codigo_opcao = :cod
                ");
                $stmtMeios->execute([':unit' => $systemUnitId, ':cod' => $chaveBusca]);
                $meiosParaBuscar = $stmtMeios->fetchAll(PDO::FETCH_COLUMN);

                if (empty($meiosParaBuscar)) {
                    return ['success' => true, 'data' => []];
                }

            } elseif ($tipoBusca === 'MEIO') {
                $meiosParaBuscar = [$chaveBusca];
            } else {
                return ['success' => false, 'message' => 'Tipo de busca inválido.'];
            }

            $placeholders = implode(',', array_fill(0, count($meiosParaBuscar), '?'));

            $sql = "
                SELECT 
                    CONCAT(SUBSTRING(hora_lancamento, 1, 2), ':', SUBSTRING(hora_lancamento, 3, 2)) AS hora,
                    COALESCE(nsu, '-') AS nsu,
                    COALESCE(autorizacao, '-') AS autorizacao,
                    COALESCE(adquirente, 'Não Identificado') AS adquirente,
                    COALESCE(bandeira, '') AS bandeira,
                    UPPER(TRIM(descricao)) AS forma_pagamento_original, 
                    valor AS valor_bruto,
                    taxa_comissao AS taxa_percentual,
                    valor_comissao AS valor_taxa,
                    valor_liquido AS valor_liquido,
                    status_pagamento
                FROM api_pagamentos
                WHERE id_loja = ?
                  AND data_contabil = ?
                  AND (status_pagamento IS NULL OR status_pagamento != 'cancelado')
                  AND UPPER(TRIM(descricao)) IN ($placeholders)
                ORDER BY hora_lancamento ASC
            ";

            $params = array_merge([$lojaId, $dataAnalise], $meiosParaBuscar);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar detalhamento: ' . $e->getMessage()];
        }
    }

    public static function sendConferenciaWpp(int $systemUnitId, string $dataContabil, int $userId): array
    {
        global $pdo;

        $stmtItems = $pdo->prepare("
            SELECT nome_opcao, valor_venda, valor_adquirente, diferenca
            FROM conferencia_caixa
            WHERE system_unit_id = :unit_id 
              AND data_contabil = :data
        ");
        $stmtItems->execute([
            ':unit_id' => $systemUnitId,
            ':data'    => $dataContabil
        ]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return ['success' => false, 'message' => 'Nenhuma conferência encontrada.'];
        }

        $stmtUnit = $pdo->prepare("SELECT name FROM system_unit WHERE id = ?");
        $stmtUnit->execute([$systemUnitId]);
        $nomeEmpresa = $stmtUnit->fetchColumn() ?: 'Empresa Desconhecida';

        $stmtUser = $pdo->prepare("SELECT name, phone FROM system_users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userData || empty($userData['phone'])) {
            return ['success' => false, 'message' => 'Usuário sem telefone.'];
        }

        $dataCaixaFmt = date('d/m/Y', strtotime($dataContabil));
        $dataEnvio    = date('d/m/Y H:i');

        $msg  = "🏢 *Resumo de Conferência* (Reenvio)\n\n";
        $msg .= "🏪 *Empresa:* {$nomeEmpresa}\n";
        $msg .= "👤 *Solicitante:* {$userData['name']}\n";
        $msg .= "📅 *Data:* {$dataCaixaFmt}\n";
        $msg .= "🕒 *Envio:* {$dataEnvio}\n";
        $msg .= "--------------------------------\n";

        foreach ($items as $item) {
            $fp          = strtoupper($item['nome_opcao']);
            $venda       = (float) $item['valor_venda'];
            $adquirente  = (float) $item['valor_adquirente'];
            $diferenca   = (float) $item['diferenca'];
            $icone       = abs($diferenca) > 0.01 ? '⚠️' : '✅';

            $msg .= "💳 *{$fp}* {$icone}\n";
            $msg .= "   Venda: R$ " . number_format($venda, 2, ',', '.') . "\n";
            $msg .= "   Inf: R$ " . number_format($adquirente, 2, ',', '.') . "\n";
            if (abs($diferenca) > 0.01) {
                $msg .= "   Dif: R$ " . number_format($diferenca, 2, ',', '.') . "\n";
            }
            $msg .= "\n";
        }

        UtilsController::sendWhatsapp($userData['phone'], $msg);
        return ['success' => true, 'message' => 'Enviado com sucesso!'];
    }

    private static function getUserName($userId) {
        global $pdo;
        if(!$userId) return 'Não conferido';
        $stmt = $pdo->prepare("SELECT name FROM system_users WHERE id = ?");
        $stmt->execute([$userId]);
        $res = $stmt->fetch();
        return $res ? $res['name'] : 'Usuário ' . $userId;
    }
}