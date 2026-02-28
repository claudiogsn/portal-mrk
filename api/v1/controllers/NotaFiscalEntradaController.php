<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php
require_once __DIR__ . '/../controllers/FinanceiroPlanoController.php';

class NotaFiscalEntradaController {
    public static function importarNotasFiscaisEntrada($system_unit_id, $notas, $usuario_id): array
    {
        global $pdo;

        try {
            error_log("### Início da função importarNotasFiscaisEntrada ###");

            if (empty($system_unit_id) || empty($usuario_id) || !is_array($notas)) {
                throw new Exception('Parâmetros inválidos.');
            }

            $pdo->beginTransaction();
            error_log("Transação iniciada.");

            $stmtInsertNota = $pdo->prepare("
            INSERT INTO nota_fiscal_entrada (
                system_unit_id, documento, data_entrada, data_emissao, fornecedor, valor_total
            ) VALUES (
                :system_unit_id, :documento, :data_entrada, :data_emissao, :fornecedor, :valor_total
            )
            ON DUPLICATE KEY UPDATE 
                data_entrada = VALUES(data_entrada),
                data_emissao = VALUES(data_emissao),
                valor_total = VALUES(valor_total)
        ");

            $notasImportadas = 0;

            foreach ($notas as $nota) {
                if (!isset($nota['documento'], $nota['data_entrada'], $nota['fornecedor'], $nota['valor_total'])) {
                    throw new Exception("Nota malformada: " . json_encode($nota));
                }

                $documento = trim($nota['documento']);
                $dataEntrada = $nota['data_entrada']; // formato 'YYYY-MM-DD'
                $dataEmissao = $nota['data_emissao'] ?? $dataEntrada; // se não houver data de emissão, usa a data de entrada
                $fornecedor = mb_substr(trim($nota['fornecedor']), 0, 100);
                $valorTotal = str_replace(',', '.', $nota['valor_total']);

                $stmtInsertNota->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':documento' => $documento,
                    ':data_entrada' => $dataEntrada,
                    ':data_emissao' => $dataEmissao,
                    ':fornecedor' => $fornecedor,
                    ':valor_total' => $valorTotal
                ]);

                $notasImportadas++;
            }

            $pdo->commit();
            error_log("Transação concluída com sucesso.");
            error_log("### Fim da função importarNotasFiscaisEntrada ###");




            return [
                'status' => 'success',
                'message' => 'Importação concluída com sucesso.',
                'notas_importadas' => $notasImportadas
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
                error_log("Rollback executado.");
            }

            error_log("Erro capturado: " . $e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Erro na importação: ' . $e->getMessage()
            ];
        }
    }

    public static function getNotaFinanceiroPayload(array $data): array
    {
        global $pdo;

        try {
            // === Validação de entrada ===
            if (empty($data['system_unit_id'])) {
                throw new Exception('system_unit_id é obrigatório.');
            }
            $system_unit_id = (int)$data['system_unit_id'];

            // pelo menos um identificador da nota
            $hasId      = !empty($data['nota_id']);
            $hasChave   = !empty($data['chave_acesso']);
            $hasNumero  = !empty($data['numero_nf']);

            if (!$hasId && !$hasChave && !$hasNumero) {
                throw new Exception('Informe nota_id, chave_acesso ou numero_nf.');
            }

            // === Monta WHERE dinamicamente ===
            $where  = ['system_unit_id = :unit'];
            $params = [':unit' => $system_unit_id];

            if ($hasId) {
                $where[]       = 'id = :id';
                $params[':id'] = (int)$data['nota_id'];
            } elseif ($hasChave) {
                $where[]          = 'chave_acesso = :chave';
                $params[':chave'] = trim((string)$data['chave_acesso']);
            } else { // numero_nf (+ opcional série)
                $where[]             = 'numero_nf = :numero';
                $params[':numero']   = trim((string)$data['numero_nf']);
                if (!empty($data['serie'])) {
                    $where[]            = 'serie = :serie';
                    $params[':serie']   = trim((string)$data['serie']);
                }
            }

            // inclui 'id' para buscar duplicatas depois
            $sqlNota = "
            SELECT 
                id,
                fornecedor_id,
                numero_nf,
                data_emissao,
                valor_total
            FROM estoque_nota
            WHERE " . implode(' AND ', $where) . "
            LIMIT 1
        ";
            $st = $pdo->prepare($sqlNota);
            $st->execute($params);
            $nota = $st->fetch(PDO::FETCH_ASSOC);

            if (!$nota) {
                return ['success' => false, 'error' => 'Nota não encontrada para os parâmetros informados.'];
            }

            // Emissão em YYYY-MM-DD (se vier null mantém null)
            $emissao = null;
            if (!empty($nota['data_emissao'])) {
                $ts = strtotime($nota['data_emissao']);
                if ($ts !== false) {
                    $emissao = date('Y-m-d', $ts);
                }
            }

            // Valor total normalizado com ponto decimal
            $valorTotal = isset($nota['valor_total'])
                ? number_format((float)$nota['valor_total'], 2, '.', '')
                : '0.00';

            // === Duplicatas da nota ===
            $sqlDup = "
            SELECT id, numero_duplicata, data_vencimento, valor_parcela
            FROM estoque_nota_duplicata
            WHERE system_unit_id = :unit AND nota_id = :nid
            ORDER BY 
                CASE WHEN data_vencimento IS NULL THEN 1 ELSE 0 END,
                data_vencimento,
                numero_duplicata
        ";
            $stDup = $pdo->prepare($sqlDup);
            $stDup->execute([
                ':unit' => $system_unit_id,
                ':nid'  => (int)$nota['id']
            ]);
            $rowsDup = $stDup->fetchAll(PDO::FETCH_ASSOC);

            $duplicatas = [];
            $somaDup    = 0.0;

            if ($rowsDup) {
                foreach ($rowsDup as $r) {
                    $venc = null;
                    if (!empty($r['data_vencimento'])) {
                        $ts = strtotime($r['data_vencimento']);
                        if ($ts !== false) {
                            $venc = date('Y-m-d', $ts);
                        }
                    }
                    $valor = (float)$r['valor_parcela'];
                    $somaDup += $valor;

                    $duplicatas[] = [
                        'id'         => (int)$r['id'],
                        'numero'     => (string)$r['numero_duplicata'],
                        'vencimento' => $venc,
                        'valor'      => number_format($valor, 2, '.', ''),
                    ];
                }
            }

            // === Planos de contas (da unidade) ===
            $planosDeConta = FinanceiroPlanoController::listPlanos($system_unit_id);
            error_log("DEBUG payload planos: " . json_encode($planosDeConta));

            // === Formas de pagamento (bancos) da unidade ===
            $sqlBancos = "
            SELECT id, codigo, nome, ativos
            FROM financeiro_forma_pagamento
            WHERE system_unit_id = :unit
              AND ativos = 1
            ORDER BY codigo, nome
        ";
            $stBancos = $pdo->prepare($sqlBancos);
            $stBancos->execute([':unit' => $system_unit_id]);
            $rowsBancos = $stBancos->fetchAll(PDO::FETCH_ASSOC);

            $formasPagamento = [];
            if ($rowsBancos) {
                foreach ($rowsBancos as $b) {
                    $formasPagamento[] = [
                        'id'        => (int)$b['id'],
                        'codigo'    => (string)$b['codigo'],                  // aqui você usa o código numérico (1..9 etc.)
                        'descricao' => $b['nome'] ?? $b['descricao'] ?? '',   // label pra exibir
                    ];
                }
            }
            error_log("DEBUG payload bancos: " . json_encode($formasPagamento));

            // === Monta payload ===
            $payload = [
                'fornecedor_id'            => (int)$nota['fornecedor_id'],
                'documento'                => (string)$nota['numero_nf'],
                'emissao'                  => $emissao,
                'valor_total'              => $valorTotal,
                'valor_total_duplicatas'   => number_format($somaDup, 2, '.', ''),
                'duplicatas'               => $duplicatas,
                'planos_de_conta'          => $planosDeConta,
                'formas_pagamento'         => $formasPagamento,
            ];

            return ['success' => true, 'data' => $payload];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function getNotaItensFornecedorPayload(array $data): array
    {
        global $pdo;

        try {
            // ===== 1. VALIDAÇÃO DE ENTRADA =====
            $required = ['system_unit_id', 'chave_acesso', 'fornecedor_id'];
            foreach ($required as $k) {
                if (!isset($data[$k]) || $data[$k] === '' || $data[$k] === null) {
                    throw new Exception("Campo obrigatório ausente: {$k}");
                }
            }

            $unitId      = (int)$data['system_unit_id'];
            $chaveAcesso = trim((string)$data['chave_acesso']);
            $fornIdInput = (int)$data['fornecedor_id'];

            // ===== 2. BUSCA DA NOTA =====
            $stNota = $pdo->prepare("
            SELECT 
                en.*, 
                ff.nome     AS fornecedor_nome,
                ff.razao    AS fornecedor_razao,
                ff.cnpj_cpf AS fornecedor_cnpj_cpf,
                dest.name   AS nome_unidade_destino
            FROM estoque_nota AS en
            LEFT JOIN financeiro_fornecedor AS ff
                   ON ff.id = en.fornecedor_id
                  AND ff.system_unit_id = en.system_unit_id
            LEFT JOIN system_unit AS dest 
                   ON dest.id = en.transferido_para_unit_id
            WHERE en.system_unit_id = :unit
              AND en.chave_acesso  = :chave
            LIMIT 1
        ");

            $stNota->execute([':unit' => $unitId, ':chave' => $chaveAcesso]);
            $nota = $stNota->fetch(PDO::FETCH_ASSOC);

            if (!$nota) {
                return ['success' => false, 'error' => 'Nota não encontrada.'];
            }

            if ((int)$nota['fornecedor_id'] !== $fornIdInput) {
                return ['success' => false, 'error' => 'fornecedor_id não confere.'];
            }

            // ===== 3. MONTA OBJETO DE TRANSFERÊNCIA COM DOCS =====
            $transfInfo = [
                'foi_transferida' => false,
                'destino_id'      => null,
                'destino_nome'    => null,
                'transfer_key'    => null,
                'doc_saida'       => null, // Novo campo
                'doc_entrada'     => null  // Novo campo
            ];

            if (!empty($nota['transferido_para_unit_id'])) {
                $transfInfo['foi_transferida'] = true;
                $transfInfo['destino_id']      = (int)$nota['transferido_para_unit_id'];
                $transfInfo['destino_nome']    = $nota['nome_unidade_destino'];
                $transfInfo['transfer_key']    = $nota['transfer_key'] ?? null;

                // --- BUSCA OS DOCS NA MOVIMENTAÇÃO SE TIVER KEY ---
                if (!empty($nota['transfer_key'])) {
                    // Usamos DISTINCT para não trazer linhas repetidas de cada item
                    $stMov = $pdo->prepare("
                    SELECT DISTINCT system_unit_id, doc, tipo 
                    FROM movimentacao 
                    WHERE transfer_key = :key
                ");
                    $stMov->execute([':key' => $nota['transfer_key']]);
                    $movs = $stMov->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($movs as $m) {
                        // Se o system_unit_id for o da origem (atual), é o Doc de Saída (ts)
                        if ((int)$m['system_unit_id'] === $unitId) {
                            $transfInfo['doc_saida'] = $m['doc'];
                        }
                        // Se o system_unit_id for o do destino, é o Doc de Entrada (te)
                        elseif ((int)$m['system_unit_id'] === (int)$nota['transferido_para_unit_id']) {
                            $transfInfo['doc_entrada'] = $m['doc'];
                        }
                    }
                }
            }

            // Injeta o objeto atualizado na nota
            $nota['transferencia_info'] = $transfInfo;

            // ===== 4. ITENS DA NOTA (Mantido igual) =====
            $notaId = (int)$nota['id'];
            $fornecedorId = (int)$nota['fornecedor_id'];

            $stItens = $pdo->prepare("
            SELECT id, numero_item, codigo_produto, descricao, unidade, quantidade, valor_unitario
            FROM estoque_nota_item
            WHERE system_unit_id = :unit AND nota_id = :nota
            ORDER BY numero_item ASC
        ");
            $stItens->execute([':unit' => $unitId, ':nota' => $notaId]);
            $rows = $stItens->fetchAll(PDO::FETCH_ASSOC);

            $itensOut = [];

            // Prepara queries de relacionamento (mantidas do original para performance)
            $qRelPorCodigoUnid = $pdo->prepare("SELECT id, produto_codigo, codigo_nota, descricao_nota, unidade_nota, fator_conversao, unidade_item FROM item_fornecedor WHERE system_unit_id = :unit AND fornecedor_id = :forn AND codigo_nota = :cod AND (unidade_nota = :un OR unidade_nota IS NULL) ORDER BY (unidade_nota IS NULL), id DESC LIMIT 1");
            $qRelPorDescUnid   = $pdo->prepare("SELECT id, produto_codigo, codigo_nota, descricao_nota, unidade_nota, fator_conversao, unidade_item FROM item_fornecedor WHERE system_unit_id = :unit AND fornecedor_id = :forn AND descricao_nota = :desc AND (unidade_nota = :un OR unidade_nota IS NULL) ORDER BY (unidade_nota IS NULL), id DESC LIMIT 1");
            $qRelPorCodigo     = $pdo->prepare("SELECT id, produto_codigo, codigo_nota, descricao_nota, unidade_nota, fator_conversao, unidade_item FROM item_fornecedor WHERE system_unit_id = :unit AND fornecedor_id = :forn AND codigo_nota = :cod ORDER BY id DESC LIMIT 1");
            $qRelPorDesc       = $pdo->prepare("SELECT id, produto_codigo, codigo_nota, descricao_nota, unidade_nota, fator_conversao, unidade_item FROM item_fornecedor WHERE system_unit_id = :unit AND fornecedor_id = :forn AND descricao_nota = :desc ORDER BY id DESC LIMIT 1");
            $qProd             = $pdo->prepare("SELECT id, codigo, nome, und FROM products WHERE system_unit_id = :unit AND codigo = :codigo LIMIT 1");

            foreach ($rows as $r) {
                // ... (Processamento dos itens idêntico ao anterior) ...
                $numItem  = (int)$r['numero_item'];
                $codNota  = $r['codigo_produto'] ?? null;
                $descNota = $r['descricao'] ?? null;
                $uniNota  = $r['unidade'] ?? null;
                $qtdNota  = (float)($r['quantidade'] ?? 0);
                $valUnit  = (float)($r['valor_unitario'] ?? 0);

                // Logica de Match
                $rel = null;
                if ($codNota) {
                    $qRelPorCodigoUnid->execute([':unit'=>$unitId, ':forn'=>$fornecedorId, ':cod'=>$codNota, ':un'=>$uniNota]);
                    $rel = $qRelPorCodigoUnid->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                if (!$rel && $descNota) {
                    $qRelPorDescUnid->execute([':unit'=>$unitId, ':forn'=>$fornecedorId, ':desc'=>$descNota, ':un'=>$uniNota]);
                    $rel = $qRelPorDescUnid->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                if (!$rel && $codNota) {
                    $qRelPorCodigo->execute([':unit'=>$unitId, ':forn'=>$fornecedorId, ':cod'=>$codNota]);
                    $rel = $qRelPorCodigo->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                if (!$rel && $descNota) {
                    $qRelPorDesc->execute([':unit'=>$unitId, ':forn'=>$fornecedorId, ':desc'=>$descNota]);
                    $rel = $qRelPorDesc->fetch(PDO::FETCH_ASSOC) ?: null;
                }

                $fator = 1.0;
                $prodCodigoInterno = null;
                $prodNomeInterno = null;
                $prodUndInterno = null;

                if ($rel) {
                    $fator = (float)($rel['fator_conversao'] ?? 1.0);
                    if (!empty($rel['produto_codigo'])) {
                        $qProd->execute([':unit'=>$unitId, ':codigo'=>(int)$rel['produto_codigo']]);
                        if ($p = $qProd->fetch(PDO::FETCH_ASSOC)) {
                            $prodCodigoInterno = (int)$p['codigo'];
                            $prodNomeInterno = (string)$p['nome'];
                            $prodUndInterno = !empty($rel['unidade_item']) ? (string)$rel['unidade_item'] : ($p['und'] ?? null);
                        }
                    }
                    if (!$prodUndInterno) $prodUndInterno = $rel['unidade_item'] ?? null;
                }

                $itensOut[] = [
                    'numero_item_nota'       => $numItem,
                    'codigo_produto_nota'    => $codNota,
                    'descricao_nota'         => $descNota,
                    'unidade_nota'           => $uniNota,
                    'quantidade_nota'        => $qtdNota,
                    'valor_unitario_nota'    => $valUnit,
                    'codigo_produto_interno' => $prodCodigoInterno,
                    'descricao_interno'      => $prodNomeInterno,
                    'unidade_interno'        => $prodUndInterno,
                    'quantidade_interno'     => round($qtdNota * $fator, 4),
                    'fator_conversao'        => $fator,
                    'relacao_encontrada'     => (bool)$rel,
                    'relacao_id'             => $rel ? (int)$rel['id'] : null,
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'nota'  => $nota,
                    'itens' => $itensOut
                ]
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public static function vincularItemNotaFornecedor(array $data):array
    {
        global $pdo;

        try {
            // ===== helpers =====
            $parseDecimal = function ($v) {
                if ($v === null || $v === '') throw new Exception("fator_conversao é obrigatório.");
                if (is_string($v)) {
                    $v = str_replace(['.', ' '], ['', ''], $v);
                    $v = str_replace(',', '.', $v);
                }
                if (!is_numeric($v)) throw new Exception("fator_conversao inválido.");
                return (float)$v;
            };
            $reqStr = function($v, $nome){
                $s = trim((string)$v);
                if ($s === '') throw new Exception("{$nome} é obrigatório.");
                return $s;
            };
            $reqInt = function($v, $nome){
                if ($v === null || $v === '' || !is_numeric($v)) throw new Exception("{$nome} é obrigatório.");
                return (int)$v;
            };

            // ===== obrigatórios (TODOS os campos da relação) =====
            $system_unit_id  = $reqInt($data['system_unit_id']  ?? null, 'system_unit_id');
            $fornecedor_id   = $reqInt($data['fornecedor_id']   ?? null, 'fornecedor_id');
            $produto_codigo  = $reqInt($data['produto_codigo']  ?? null, 'produto_codigo');
            $codigo_nota     = $reqStr($data['codigo_nota']     ?? null, 'codigo_nota');
            $descricao_nota  = $reqStr($data['descricao_nota']  ?? null, 'descricao_nota');
            $unidade_nota    = $reqStr($data['unidade_nota']    ?? null, 'unidade_nota');
            $fator_conversao = $parseDecimal($data['fator_conversao'] ?? null);
            $unidade_item    = $reqStr($data['unidade_item']    ?? null, 'unidade_item');

            if ($fator_conversao <= 0) throw new Exception("fator_conversao deve ser > 0.");

            // normaliza unidades
            $unidade_nota = strtoupper($unidade_nota);
            $unidade_item = strtoupper($unidade_item);

            // ===== opcionais para validação com a nota =====
            $chave_acesso = isset($data['chave_acesso']) ? trim((string)$data['chave_acesso']) : null;
            $numero_item  = isset($data['numero_item'])  ? (int)$data['numero_item'] : null;

            // ===== valida fornecedor existe =====
            $stForn = $pdo->prepare("
            SELECT id FROM financeiro_fornecedor
            WHERE id = :id AND system_unit_id = :unit
            LIMIT 1
        ");
            $stForn->execute([':id'=>$fornecedor_id, ':unit'=>$system_unit_id]);
            if (!$stForn->fetchColumn()) {
                throw new Exception("Fornecedor não encontrado nesta unidade.");
            }

            // ===== valida produto interno existe =====
            $stProd = $pdo->prepare("
            SELECT id FROM products
            WHERE system_unit_id = :unit AND codigo = :cod
            LIMIT 1
        ");
            $stProd->execute([':unit'=>$system_unit_id, ':cod'=>$produto_codigo]);
            if (!$stProd->fetchColumn()) {
                throw new Exception("Produto interno (products.codigo) não encontrado nesta unidade.");
            }

            // ===== validação cruzada com a nota (se informada) =====
            if ($chave_acesso && $numero_item !== null) {
                // acha a nota
                $stNota = $pdo->prepare("
                SELECT id, fornecedor_id
                FROM estoque_nota
                WHERE system_unit_id = :unit AND chave_acesso = :chave
                LIMIT 1
            ");
                $stNota->execute([':unit'=>$system_unit_id, ':chave'=>$chave_acesso]);
                $nota = $stNota->fetch(PDO::FETCH_ASSOC);
                if (!$nota) throw new Exception("Nota não encontrada para a chave de acesso informada.");

                if ((int)$nota['fornecedor_id'] !== $fornecedor_id) {
                    throw new Exception("fornecedor_id não confere com o da nota.");
                }

                // carrega o item da nota para conferir código/descrição/unidade
                $stItem = $pdo->prepare("
                SELECT codigo_produto, descricao, unidade
                FROM estoque_nota_item
                WHERE system_unit_id = :unit AND nota_id = :nota AND numero_item = :ni
                LIMIT 1
            ");
                $stItem->execute([
                    ':unit'=>$system_unit_id,
                    ':nota'=>(int)$nota['id'],
                    ':ni'=>$numero_item
                ]);
                $it = $stItem->fetch(PDO::FETCH_ASSOC);
                if (!$it) throw new Exception("Item da nota não encontrado para o número informado.");

                // validações estritas
                if ((string)$it['codigo_produto'] !== $codigo_nota) {
                    throw new Exception("codigo_nota não confere com o item da nota.");
                }
                if ((string)$it['descricao'] !== $descricao_nota) {
                    throw new Exception("descricao_nota não confere com o item da nota.");
                }
                if (strtoupper((string)$it['unidade']) !== $unidade_nota) {
                    throw new Exception("unidade_nota não confere com o item da nota.");
                }
            }

            // ===== upsert =====
                        $stmt = $pdo->prepare("
                INSERT INTO item_fornecedor
                    (system_unit_id, fornecedor_id, produto_codigo, codigo_nota, descricao_nota, unidade_nota, fator_conversao, unidade_item)
                VALUES
                    (:unit, :forn, :pc, :cod, :desc, :un_nota, :fator, :un_item)
                ON DUPLICATE KEY UPDATE
                    produto_codigo  = VALUES(produto_codigo),
                    codigo_nota     = VALUES(codigo_nota),
                    fator_conversao = VALUES(fator_conversao),
                    unidade_item    = VALUES(unidade_item),
                    id = LAST_INSERT_ID(id)
            ");

            $stmt->execute([
                ':unit'    => $system_unit_id,
                ':forn'    => $fornecedor_id,
                ':pc'      => $produto_codigo,
                ':cod'     => $codigo_nota,
                ':desc'    => $descricao_nota,
                ':un_nota' => $unidade_nota,
                ':fator'   => $fator_conversao,
                ':un_item' => $unidade_item
            ]);

            $idRel   = (int)$pdo->lastInsertId();
            $changed = $stmt->rowCount(); // 1 = insert, 2 = update (se mudou), 0 = update sem mudança
            $acao    = ($changed === 1) ? 'inserted' : (($changed >= 2) ? 'updated' : 'noop');

            // preview de conversão (se veio dado da nota)
            $preview = null;
            if ($chave_acesso && $numero_item !== null) {
                $stQtd = $pdo->prepare("
                SELECT quantidade FROM estoque_nota_item
                WHERE system_unit_id = :unit
                  AND nota_id = (SELECT id FROM estoque_nota WHERE system_unit_id = :unit AND chave_acesso = :chave LIMIT 1)
                  AND numero_item = :ni
                LIMIT 1
            ");
                $stQtd->execute([':unit'=>$system_unit_id, ':chave'=>$chave_acesso, ':ni'=>$numero_item]);
                $qtdNota = (float)($stQtd->fetchColumn() ?: 0);
                $preview = [
                    'quantidade_nota'     => $qtdNota,
                    'fator_conversao'     => $fator_conversao,
                    'quantidade_interno'  => round($qtdNota * $fator_conversao, 4),
                    'acao' => $acao
                ];
            }

            return ['success'=>true, 'id'=>$idRel, 'preview'=>$preview];

        } catch (Exception $e) {
            return ['success'=>false, 'error'=>$e->getMessage()];
        }
    }

    public static function lancarItensNotaNoEstoque(array $data): array
    {
        global $pdo;

        try {
            // ===== helpers =====
            $parseDate = function (?string $s) {
                if (!$s) throw new Exception("Data inválida.");
                $s = trim($s);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
                    [$d,$m,$y] = explode('/',$s);
                    return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                }
                $t = strtotime($s);
                if ($t !== false) return date('Y-m-d', $t);
                throw new Exception("Data inválida: {$s}");
            };

            // ===== validação de topo =====
            foreach (['system_unit_id','usuario_id','chave_acesso','itens','data_entrada'] as $k) {
                if (!isset($data[$k]) || $data[$k] === '' || $data[$k] === null) {
                    throw new Exception("Campo obrigatório ausente: {$k}");
                }
            }
            if (!is_array($data['itens']) || count($data['itens']) === 0) {
                throw new Exception("Lista de itens está vazia.");
            }

            $unitId      = (int)$data['system_unit_id'];
            $usuarioId   = (int)$data['usuario_id'];
            $chaveAcesso = trim((string)$data['chave_acesso']);
            $dataEntrada = $parseDate((string)$data['data_entrada']);

            $dataEmissaoOverride = isset($data['data_emissao']) && $data['data_emissao'] !== ''
                ? $parseDate((string)$data['data_emissao'])
                : null;

            // ===== parâmetros opcionais de transferência =====
            $transferirDestino = isset($data['transferir_destino']) && $data['transferir_destino'] === true;
            $unitDestino = isset($data['system_unit_id_destino'])
                ? (int)$data['system_unit_id_destino']
                : null;

            if ($transferirDestino && !$unitDestino) {
                throw new Exception("system_unit_id_destino é obrigatório quando transferir_destino = true");
            }

            // ===== busca nota (ALTERADO: buscando transfer_key também) =====
            $stNota = $pdo->prepare("
            SELECT id, numero_nf, data_emissao, transfer_key 
            FROM estoque_nota 
            WHERE system_unit_id = :unit AND chave_acesso = :chave 
            LIMIT 1
        ");
            $stNota->execute([':unit'=>$unitId, ':chave'=>$chaveAcesso]);
            $nota = $stNota->fetch(PDO::FETCH_ASSOC);
            if (!$nota) throw new Exception("Nota não encontrada para a chave de acesso informada.");

            $notaId    = (int)$nota['id'];
            $docNumero = (string)$nota['numero_nf'];
            $oldTransferKey = $nota['transfer_key'] ?? null; // Pega a chave antiga se existir

            $dataEmissaoNota = $nota['data_emissao']
                ? date('Y-m-d', strtotime($nota['data_emissao']))
                : null;

            $dataEmissao = $dataEmissaoOverride ?? ($dataEmissaoNota ?? $dataEntrada);

            // ===== prepareds =====
            $stCheckProd = $pdo->prepare("
            SELECT id FROM products
            WHERE system_unit_id = :unit AND codigo = :cod
            LIMIT 1
        ");

            $stItemNota = $pdo->prepare("
            SELECT numero_item, valor_unitario, valor_total
            FROM estoque_nota_item
            WHERE system_unit_id = :unit AND nota_id = :nota AND numero_item = :ni
            LIMIT 1
        ");

            $stInsert = $pdo->prepare("
            INSERT INTO movimentacao (
                system_unit_id, status, doc, tipo, tipo_mov, produto, seq,
                data, data_emissao, data_original, quantidade, valor, usuario_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                data = VALUES(data),
                data_emissao = VALUES(data_emissao),
                data_original = VALUES(data_original),
                quantidade = VALUES(quantidade),
                valor = VALUES(valor),
                usuario_id = VALUES(usuario_id)
        ");

            $pdo->beginTransaction();

            $detalhes = [];
            $countIns = 0;

            foreach ($data['itens'] as $idx => $item) {

                foreach (['numero_item_nota','codigo_produto_interno','quantidade_interno'] as $k) {
                    if (!isset($item[$k])) {
                        throw new Exception("Item {$idx}: campo obrigatório ausente: {$k}");
                    }
                }

                $seq        = (int)$item['numero_item_nota'];
                $prodCodigo = (int)$item['codigo_produto_interno'];
                $qtd        = (float)$item['quantidade_interno'];

                if ($qtd <= 0) throw new Exception("Item {$idx}: quantidade inválida.");

                $stCheckProd->execute([':unit'=>$unitId, ':cod'=>$prodCodigo]);
                if (!$stCheckProd->fetchColumn()) {
                    throw new Exception("Item {$idx}: produto {$prodCodigo} não encontrado.");
                }

                $valorUnit = 0.00;

                $stItemNota->execute([':unit'=>$unitId, ':nota'=>$notaId, ':ni'=>$seq]);
                if ($inf = $stItemNota->fetch(PDO::FETCH_ASSOC)) {
                    if ($inf['valor_total'] !== null && $qtd > 0) {
                        $valorUnit = (float)$inf['valor_total'] / $qtd;
                    }
                }

                $stInsert->execute([
                    $unitId,
                    1,
                    $docNumero,
                    'c',
                    'entrada',
                    $prodCodigo,
                    $seq,
                    $dataEntrada,
                    $dataEmissao,
                    $dataEntrada,
                    $qtd,
                    $valorUnit,
                    $usuarioId
                ]);

                $detalhes[] = [
                    'seq'     => $seq,
                    'produto' => $prodCodigo,
                    'qtd'     => $qtd
                ];

                $countIns++;
            }

            // marca nota como incluída (Update inicial)
            $pdo->prepare("
            UPDATE estoque_nota
            SET incluida_estoque = 1, data_entrada = :dt, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND system_unit_id = :unit
        ")->execute([
                ':dt'   => $dataEntrada,
                ':id'   => $notaId,
                ':unit' => $unitId
            ]);

            $pdo->commit();

            // ===== TRANSFERÊNCIA AUTOMÁTICA =====
            $transferResult = null;

            if ($transferirDestino === true) {

                // 1. Verifica se precisa cancelar transferência anterior (Correção de destino)
                if (!empty($oldTransferKey)) {
                    try {
                        $stCancel = $pdo->prepare("UPDATE movimentacao SET status = 3 WHERE transfer_key = :tk");
                        $stCancel->execute([':tk' => $oldTransferKey]);
                    } catch (Exception $e) {
                        // Log silencioso ou ignorar se não afetar o fluxo principal
                    }
                }

                // 2. Prepara nova transferência com a data do payload
                $payloadTransfer = [
                    'system_unit_id'         => $unitId,
                    'system_unit_id_destino' => $unitDestino,
                    'usuario_id'             => $usuarioId,
                    'transfer_date'          => $dataEntrada, // Usa a data que veio no payload
                    'itens'                  => array_map(fn($d) => [
                        'codigo'     => $d['produto'],
                        'seq'        => $d['seq'],
                        'quantidade' => $d['qtd'],
                    ], $detalhes)
                ];

                $transferResult = MovimentacaoController::createTransferItems($payloadTransfer);

                if (!$transferResult['success']) {
                    return [
                        'success' => false,
                        'message' => 'Entrada concluída, mas a transferência falhou: ' . $transferResult['message']
                    ];
                } else {
                    // 3. Sucesso: Grava ID do destino E a nova transfer_key na nota

                    // Tenta pegar a key retornada pelo controller.
                    // Se não vier, assume que o controller gravou no banco e precisamos pegar via doc ou similar,
                    // mas idealmente o createTransferItems deve retornar a 'transfer_key'.
                    $newKey = $transferResult['transfer_key'] ?? null;

                    try {
                        $pdo->prepare("
                        UPDATE estoque_nota 
                        SET 
                            transferido_para_unit_id = :destId,
                            transfer_key = :newKey
                        WHERE id = :id
                    ")->execute([
                            ':destId' => $unitDestino,
                            ':newKey' => $newKey,
                            ':id'     => $notaId
                        ]);
                    } catch (Exception $e) {
                        error_log("Erro ao atualizar dados de transferência na nota: " . $e->getMessage());
                    }
                }
            }

            return [
                'success'        => true,
                'message'        => 'Entrada realizada com sucesso.',
                'doc'            => $docNumero,
                'inseridos'      => $countIns,
                'transferencia'  => $transferResult,
                'detalhes'       => $detalhes
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    public static function getItensCompradosPorPeriodo(array $data): array
    {
        global $pdo;

        try {
            // ===== validação básica =====
            $required = ['system_unit_id', 'data_inicio', 'data_fim'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                    throw new Exception("Campo obrigatório ausente: {$field}");
                }
            }

            $unitId    = (int)$data['system_unit_id'];
            $dtInicio  = trim((string)$data['data_inicio']); // esperado: 'YYYY-MM-DD'
            $dtFim     = trim((string)$data['data_fim']);    // esperado: 'YYYY-MM-DD'

            // filtros opcionais
            $numeroNf      = !empty($data['numero_nf'])      ? trim((string)$data['numero_nf']) : null;
            $produtoCodigo = !empty($data['produto_codigo']) ? (int)$data['produto_codigo'] : null;
            $fornecedorId  = !empty($data['fornecedor_id'])  ? (int)$data['fornecedor_id'] : null;

            // ===== 1) busca movimentações (base) =====
            $whereMov = [
                'system_unit_id = :unit',
                "tipo = 'c'",            // compras
                "tipo_mov = 'entrada'",  // entradas
                'data_emissao BETWEEN :dt_inicio AND :dt_fim' // Mudou de data para data_emissao
            ];
            $paramsMov = [
                ':unit'      => $unitId,
                ':dt_inicio' => $dtInicio,
                ':dt_fim'    => $dtFim,
            ];

            if ($numeroNf) {
                $whereMov[]          = 'doc = :doc';
                $paramsMov[':doc']   = $numeroNf;
            }
            if ($produtoCodigo) {
                $whereMov[]            = 'produto = :prod';
                $paramsMov[':prod']    = $produtoCodigo;
            }

            $sqlMov = "
            SELECT 
                id,
                system_unit_id,
                doc,
                tipo,
                tipo_mov,
                produto,
                seq,
                data,
                data_emissao,
                data_original,
                valor,
                quantidade,
                usuario_id
            FROM movimentacao
            WHERE " . implode(' AND ', $whereMov) . "
            ORDER BY data ASC, doc ASC, seq ASC
        ";

            $stMov = $pdo->prepare($sqlMov);
            $stMov->execute($paramsMov);
            $movRows = $stMov->fetchAll(PDO::FETCH_ASSOC);

            if (!$movRows) {
                return [
                    'success' => true,
                    'data'    => [
                        'resumo' => [
                            'total_itens'      => 0,
                            'total_quantidade' => 0,
                            'total_valor'      => 0.0,
                        ],
                        'itens'  => []
                    ]
                ];
            }

            // ===== 2) notas fiscais (estoque_nota) =====
            $docs        = [];
            $prodCodigos = [];
            foreach ($movRows as $row) {
                $docs[$row['doc']]          = true;
                $prodCodigos[$row['produto']] = true;
            }

            $docsList = array_keys($docs);
            $prodList = array_keys($prodCodigos);

            if (empty($docsList)) {
                return [
                    'success' => true,
                    'data'    => [
                        'resumo' => [
                            'total_itens'      => 0,
                            'total_quantidade' => 0,
                            'total_valor'      => 0.0,
                        ],
                        'itens'  => []
                    ]
                ];
            }

            $placeDocs = implode(',', array_fill(0, count($docsList), '?'));
            $sqlNotas = "
            SELECT 
                id,
                system_unit_id,
                fornecedor_id,
                numero_nf,
                serie,
                data_emissao,
                data_entrada,
                valor_total,
                valor_produtos,
                valor_frete
            FROM estoque_nota
            WHERE system_unit_id = ?
              AND numero_nf IN ($placeDocs)
        ";
            $paramsNotas = array_merge([$unitId], $docsList);

            $stNotas = $pdo->prepare($sqlNotas);
            $stNotas->execute($paramsNotas);
            $notaRows = $stNotas->fetchAll(PDO::FETCH_ASSOC);

            $mapNotaByDoc = [];
            $notaIds      = [];
            foreach ($notaRows as $n) {
                $mapNotaByDoc[$n['numero_nf']] = $n;
                $notaIds[$n['id']]             = true;
            }

            // filtro por fornecedor, se informado
            if ($fornecedorId) {
                $movRowsFiltradas = [];
                foreach ($movRows as $r) {
                    $doc = $r['doc'];
                    if (!isset($mapNotaByDoc[$doc])) {
                        continue;
                    }
                    if ((int)$mapNotaByDoc[$doc]['fornecedor_id'] === $fornecedorId) {
                        $movRowsFiltradas[] = $r;
                    }
                }
                $movRows = $movRowsFiltradas;

                if (!$movRows) {
                    return [
                        'success' => true,
                        'data'    => [
                            'resumo' => [
                                'total_itens'      => 0,
                                'total_quantidade' => 0,
                                'total_valor'      => 0.0,
                            ],
                            'itens'  => []
                        ]
                    ];
                }

                // recalcula docs/notaIds após filtro
                $docs    = [];
                $notaIds = [];
                foreach ($movRows as $r) {
                    $docs[$r['doc']] = true;
                    if (isset($mapNotaByDoc[$r['doc']])) {
                        $notaIds[$mapNotaByDoc[$r['doc']]['id']] = true;
                    }
                }
                $docsList   = array_keys($docs);
            }

            $notaIdList = array_keys($notaIds);

            // ===== 3) itens de nota (estoque_nota_item) =====
            $mapItemByNotaSeq = [];
            if (!empty($notaIdList)) {
                $placeNota = implode(',', array_fill(0, count($notaIdList), '?'));
                $sqlItens = "
                SELECT 
                    id,
                    system_unit_id,
                    nota_id,
                    numero_item,
                    codigo_produto,
                    descricao,
                    unidade,
                    quantidade,
                    valor_unitario,
                    valor_total
                FROM estoque_nota_item
                WHERE system_unit_id = ?
                  AND nota_id IN ($placeNota)
            ";
                $paramsItens = array_merge([$unitId], $notaIdList);

                $stItens = $pdo->prepare($sqlItens);
                $stItens->execute($paramsItens);
                $itensNotaRows = $stItens->fetchAll(PDO::FETCH_ASSOC);

                foreach ($itensNotaRows as $it) {
                    $key = $it['nota_id'] . ':' . $it['numero_item'];
                    $mapItemByNotaSeq[$key] = $it;
                }
            }

            // ===== 4) produtos internos (products) =====
            $mapProdByCodigo = [];
            if (!empty($prodList)) {
                $placeProd = implode(',', array_fill(0, count($prodList), '?'));
                $sqlProd = "
                SELECT 
                    system_unit_id,
                    codigo,
                    nome,
                    und,
                    preco_custo
                FROM products
                WHERE system_unit_id = ?
                  AND codigo IN ($placeProd)
            ";
                $paramsProd = array_merge([$unitId], $prodList);

                $stProd = $pdo->prepare($sqlProd);
                $stProd->execute($paramsProd);
                $prodRows = $stProd->fetchAll(PDO::FETCH_ASSOC);

                foreach ($prodRows as $p) {
                    $mapProdByCodigo[(string)$p['codigo']] = $p;
                }
            }

            // ===== 5) relações item_fornecedor (por fornecedor + produto_codigo) =====
            $combos = [];
            foreach ($movRows as $r) {
                $doc = $r['doc'];
                if (!isset($mapNotaByDoc[$doc])) {
                    continue;
                }
                $fornId  = (int)$mapNotaByDoc[$doc]['fornecedor_id'];
                $prodCod = (int)$r['produto'];
                $key     = $fornId . ':' . $prodCod;
                $combos[$key] = ['fornecedor_id' => $fornId, 'produto_codigo' => $prodCod];
            }

            $mapRelByFornProd = [];
            if (!empty($combos)) {
                $fornIds = [];
                $prodIds = [];
                foreach ($combos as $c) {
                    $fornIds[$c['fornecedor_id']]  = true;
                    $prodIds[$c['produto_codigo']] = true;
                }
                $fornList    = array_keys($fornIds);
                $prodCodList = array_keys($prodIds);

                $placeForn = implode(',', array_fill(0, count($fornList), '?'));
                $placeProd = implode(',', array_fill(0, count($prodCodList), '?'));

                $sqlRel = "
                SELECT 
                    id,
                    system_unit_id,
                    fornecedor_id,
                    produto_codigo,
                    codigo_nota,
                    descricao_nota,
                    unidade_nota,
                    fator_conversao,
                    unidade_item
                FROM item_fornecedor
                WHERE system_unit_id = ?
                  AND fornecedor_id IN ($placeForn)
                  AND produto_codigo IN ($placeProd)
            ";
                $paramsRel = array_merge([$unitId], $fornList, $prodCodList);

                $stRel = $pdo->prepare($sqlRel);
                $stRel->execute($paramsRel);
                $relRows = $stRel->fetchAll(PDO::FETCH_ASSOC);

                foreach ($relRows as $rel) {
                    $key = $rel['fornecedor_id'] . ':' . $rel['produto_codigo'];
                    if (
                        !isset($mapRelByFornProd[$key]) ||
                        (int)$rel['id'] > (int)$mapRelByFornProd[$key]['id']
                    ) {
                        $mapRelByFornProd[$key] = $rel;
                    }
                }
            }

            // ===== 6) monta saída FINAL (achatado) =====
            $itensOut = [];
            $totalQtd = 0.0;
            $totalVal = 0.0;

            foreach ($movRows as $r) {
                $doc        = $r['doc'];
                $prodCodigo = (int)$r['produto'];
                $seq        = (int)$r['seq'];

                $nota    = $mapNotaByDoc[$doc] ?? null;
                $notaId  = $nota ? (int)$nota['id'] : null;
                $produto = $mapProdByCodigo[(string)$prodCodigo] ?? null;

                $notaItem = null;
                if ($notaId !== null) {
                    $keyItem  = $notaId . ':' . $seq;
                    $notaItem = $mapItemByNotaSeq[$keyItem] ?? null;
                }

                $relacao = null;
                if ($nota) {
                    $fornId = (int)$nota['fornecedor_id'];
                    $keyRel = $fornId . ':' . $prodCodigo;
                    $relacao = $mapRelByFornProd[$keyRel] ?? null;
                }

                $qtdInterna    = (float)$r['quantidade'];
                $valorUnitInt  = $r['valor'] !== null ? (float)$r['valor'] : 0.0;
                $valorTotalInt = $qtdInterna * $valorUnitInt;

                $totalQtd += $qtdInterna;
                $totalVal += $valorTotalInt;

                $itensOut[] = [
                    // ===== MOVIMENTAÇÃO (mv_) =====
                    'mv_id'                     => (int)$r['id'],
                    'mv_system_unit_id'         => (int)$r['system_unit_id'],
                    'mv_doc'                    => $r['doc'],
                    'mv_tipo'                   => $r['tipo'],
                    'mv_tipo_mov'               => $r['tipo_mov'],
                    'mv_produto'                => (int)$r['produto'],
                    'mv_seq'                    => $seq,
                    'mv_data'                   => $r['data'],
                    'mv_data_emissao'           => $r['data_emissao'],
                    'mv_data_original'          => $r['data_original'],
                    'mv_quantidade_interna'     => $qtdInterna,
                    'mv_valor_unitario_interno' => $valorUnitInt,
                    'mv_valor_total_interno'    => $valorTotalInt,
                    'mv_usuario_id'             => $r['usuario_id'],

                    // ===== NOTA (nt_) =====
                    'nt_id'             => $nota ? (int)$nota['id'] : null,
                    'nt_fornecedor_id'  => $nota ? (int)$nota['fornecedor_id'] : null,
                    'nt_numero_nf'      => $nota['numero_nf']   ?? null,
                    'nt_serie'          => $nota['serie']       ?? null,
                    'nt_data_emissao'   => $nota['data_emissao'] ?? null,
                    'nt_data_entrada'   => $nota['data_entrada'] ?? null,
                    'nt_valor_total'    => $nota['valor_total']    ?? null,
                    'nt_valor_produtos' => $nota['valor_produtos'] ?? null,
                    'nt_valor_frete'    => $nota['valor_frete']    ?? null,

                    // ===== PRODUTO INTERNO (pi_) =====
                    'pi_codigo'      => $produto ? (int)$produto['codigo'] : null,
                    'pi_nome'        => $produto['nome']       ?? null,
                    'pi_unidade'     => $produto['und']        ?? null,
                    'pi_preco_custo' => $produto['preco_custo'] ?? null,

                    // ===== ITEM DA NOTA (in_) =====
                    'in_id'                  => $notaItem ? (int)$notaItem['id'] : null,
                    'in_nota_id'             => $notaItem ? (int)$notaItem['nota_id'] : null,
                    'in_numero_item'         => $notaItem ? (int)$notaItem['numero_item'] : null,
                    'in_codigo_produto_nota' => $notaItem['codigo_produto'] ?? null,
                    'in_descricao_nota'      => $notaItem['descricao']      ?? null,
                    'in_unidade_nota'        => $notaItem['unidade']        ?? null,
                    'in_quantidade_nota'     => $notaItem['quantidade']     ?? null,
                    'in_valor_unitario_nota' => $notaItem['valor_unitario'] ?? null,
                    'in_valor_total_nota'    => $notaItem['valor_total']    ?? null,

                    // ===== RELAÇÃO FORNECEDOR (rf_) =====
                    'rf_id'              => $relacao ? (int)$relacao['id'] : null,
                    'rf_produto_codigo'  => $relacao ? (int)$relacao['produto_codigo'] : null,
                    'rf_codigo_nota'     => $relacao['codigo_nota']    ?? null,
                    'rf_descricao_nota'  => $relacao['descricao_nota'] ?? null,
                    'rf_unidade_nota'    => $relacao['unidade_nota']   ?? null,
                    'rf_fator_conversao' => $relacao ? (float)$relacao['fator_conversao'] : null,
                    'rf_unidade_item'    => $relacao['unidade_item']   ?? null,
                ];
            }

            return [
                'success' => true,
                'data'    => [
                    'resumo' => [
                        'total_itens'      => count($itensOut),
                        'total_quantidade' => $totalQtd,
                        'total_valor'      => $totalVal,
                    ],
                    'itens'  => $itensOut
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }
    }

    public static function lancarItensNotaAvulsaNoEstoque(array $data): array
    {
        global $pdo;

        try {
            // ===== helpers =====
            $parseDate = function (?string $s) {
                if (!$s) {
                    throw new Exception("Data inválida.");
                }
                $s = trim($s);

                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                    return $s;
                }
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
                    [$d, $m, $y] = explode('/', $s);
                    return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                }

                $t = strtotime($s);
                if ($t !== false) {
                    return date('Y-m-d', $t);
                }
                throw new Exception("Data inválida: {$s}");
            };

            // ===== validações topo =====
            foreach (['system_unit_id','usuario_id','nota_id','data_entrada','itens'] as $k) {
                if (!isset($data[$k]) || $data[$k] === '' || $data[$k] === null) {
                    throw new Exception("Campo obrigatório ausente: {$k}");
                }
            }

            if (!is_array($data['itens']) || count($data['itens']) === 0) {
                throw new Exception("Lista de itens está vazia.");
            }

            $unitId        = (int)$data['system_unit_id'];
            $usuarioId     = (int)$data['usuario_id'];
            $notaId        = (int)$data['nota_id'];
            $dataEntrada   = $parseDate((string)$data['data_entrada']);
            $dataEmissaoOv = isset($data['data_emissao']) && $data['data_emissao'] !== ''
                ? $parseDate((string)$data['data_emissao'])
                : null;

            // ===== busca cabeçalho da nota =====
            $stNota = $pdo->prepare("
            SELECT id, numero_nf, data_emissao
            FROM estoque_nota
            WHERE id = :id AND system_unit_id = :unit
            LIMIT 1
        ");
            $stNota->execute([
                ':id'   => $notaId,
                ':unit' => $unitId
            ]);
            $nota = $stNota->fetch(PDO::FETCH_ASSOC);
            if (!$nota) {
                throw new Exception("Nota avulsa não encontrada para o ID informado.");
            }

            $docNumero       = (string)$nota['numero_nf'];
            $dataEmissaoNota = $nota['data_emissao'] ? date('Y-m-d', strtotime($nota['data_emissao'])) : null;

            $dataEmissao = $dataEmissaoOv ?? ($dataEmissaoNota ?? $dataEntrada);

            // ===== prepareds auxiliares =====
            $stCheckProd = $pdo->prepare("
            SELECT id 
            FROM products
            WHERE system_unit_id = :unit 
              AND codigo = :cod
            LIMIT 1
        ");

            $insertQuery = "
            INSERT INTO movimentacao (
                system_unit_id, status, doc, tipo, tipo_mov, produto, seq,
                data, data_emissao, data_original, quantidade, valor, usuario_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status       = VALUES(status),
                data         = VALUES(data),
                data_emissao = VALUES(data_emissao),
                data_original= VALUES(data_original),
                quantidade   = VALUES(quantidade),
                valor        = VALUES(valor),
                usuario_id   = VALUES(usuario_id)
        ";
            $stInsert = $pdo->prepare($insertQuery);

            // <<< AQUI: só abre transaction se ainda não tiver uma ativa
            $startedTx = false;
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTx = true;
            }

            $countIns = 0;
            $detalhes = [];

            foreach ($data['itens'] as $idx => $item) {
                foreach (['numero_item','codigo_produto','unidade','quantidade','valor_unitario'] as $campo) {
                    if (!isset($item[$campo]) || $item[$campo] === '' || $item[$campo] === null) {
                        throw new Exception("Item {$idx}: campo obrigatório ausente: {$campo}");
                    }
                }

                $seq        = (int)$item['numero_item'];
                $prodCodigo = (int)$item['codigo_produto'];
                $unidade    = strtoupper(trim((string)$item['unidade']));
                $qtd        = (float)$item['quantidade'];
                $vUnit      = (float)$item['valor_unitario'];

                if ($qtd <= 0) {
                    throw new Exception("Item {$idx}: quantidade deve ser > 0.");
                }

                // valida produto
                $stCheckProd->execute([
                    ':unit' => $unitId,
                    ':cod'  => $prodCodigo
                ]);
                if (!$stCheckProd->fetchColumn()) {
                    throw new Exception("Item {$idx}: produto {$prodCodigo} não encontrado nesta unidade.");
                }

                // protege contra estouro do tipo (float(7,2) ~ 99999.99)
                $vSafe = round($vUnit, 4);
                if ($vSafe > 99999.99)  $vSafe = 99999.99;
                if ($vSafe < -99999.99) $vSafe = -99999.99;

                $row = [
                    $unitId,
                    1,
                    $docNumero,
                    'c',
                    'entrada',
                    $prodCodigo,
                    $seq,
                    $dataEntrada,
                    $dataEmissao,
                    $dataEntrada,
                    $qtd,
                    $vSafe,
                    $usuarioId
                ];

                $stInsert->execute($row);
                $countIns++;

                $detalhes[] = [
                    'seq'         => $seq,
                    'produto'     => $prodCodigo,
                    'unidade'     => $unidade,
                    'qtd'         => $qtd,
                    'valor_unit'  => round($vSafe, 4),
                    'valor_total' => round($qtd * $vSafe, 2)
                ];
            }

            // marca nota como incluída no estoque
            try {
                $stU = $pdo->prepare("
                UPDATE estoque_nota
                SET incluida_estoque = 1,
                    data_entrada     = :data_entrada,
                    updated_at       = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND system_unit_id = :unit
                LIMIT 1
            ");
                $stU->execute([
                    ':data_entrada' => $dataEntrada,
                    ':id'           => $notaId,
                    ':unit'         => $unitId
                ]);
            } catch (\Throwable $e) {
                // se faltar coluna, ignora
            }

            if ($startedTx && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return [
                'success'   => true,
                'message'   => 'Itens da nota avulsa lançados no estoque com sucesso.',
                'inseridos' => $countIns,
                'doc'       => $docNumero,
                'data'      => [
                    'data_entrada' => $dataEntrada,
                    'data_emissao' => $dataEmissao
                ],
                'detalhes'  => $detalhes
            ];

        } catch (Exception $e) {
            if (isset($startedTx) && $startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public static function getComprasPorPeriodo($data): array
    {
        try {
            global $pdo;

            $system_unit_id = (int)($data['system_unit_id'] ?? 0);
            $dt_inicio      = $data['dt_inicio'] ?? null;
            $dt_fim         = $data['dt_fim'] ?? null;
            $tipoData       = strtolower(trim($data['tipoData'] ?? 'entrada'));

            if (!$system_unit_id || !$dt_inicio || !$dt_fim) {
                return ["success" => false, "message" => "Parâmetros obrigatórios."];
            }

            $campoData = ($tipoData === 'emissao') ? 'n.data_emissao' : 'n.data_entrada';

            // Ajuste o joinDoc conforme a realidade da sua movimentacao (se doc é NF ou Chave)
            $joinDoc   = "m.doc = n.numero_nf";

            // 1. PREPARA QUERY PARA BUSCAR DOCS NA MOVIMENTACAO (Pela transfer_key)
            $sqlMov = "SELECT DISTINCT system_unit_id, doc 
                   FROM movimentacao 
                   WHERE transfer_key = :key";
            $stmtMov = $pdo->prepare($sqlMov);

            // 2. QUERY PRINCIPAL CORRIGIDA
            $sql = "
        SELECT
            n.id               AS nota_id,
            n.system_unit_id,
            n.fornecedor_id,
            f.razao            AS fornecedor_nome,
            n.chave_acesso,
            n.numero_nf,
            n.serie,
            n.data_emissao,
            n.data_entrada,
            n.valor_total,
            
            -- ✅ CAMPOS CORRIGIDOS CONFORME DDL
            n.transferido_para_unit_id, -- ID do destino (Se não nulo, foi transferido)
            n.transfer_key,             -- Chave UUID da transferência
            
            ud.name            AS nome_destino,
            uo.name            AS nome_origem, -- Nome da unidade atual (origem)

            -- ITENS DA MOVIMENTAÇÃO
            m.id               AS mov_id,
            m.seq              AS item_seq,
            m.produto          AS item_produto,
            m.quantidade       AS item_quantidade,
            m.valor            AS item_valor_unitario,
            m.data             AS item_data_mov,
            
            p.nome             AS produto_nome,
            p.und              AS produto_unidade

        FROM estoque_nota n

        LEFT JOIN financeiro_fornecedor f
            ON f.id = n.fornecedor_id
           AND f.system_unit_id = n.system_unit_id

        -- JOIN para pegar o nome do destino (baseado no transferido_para_unit_id)
        LEFT JOIN system_unit ud 
            ON ud.id = n.transferido_para_unit_id 

        -- JOIN para pegar o nome da origem (baseado na unidade da nota)
        LEFT JOIN system_unit uo 
            ON uo.id = n.system_unit_id 

        LEFT JOIN movimentacao m
            ON m.system_unit_id = n.system_unit_id
           AND {$joinDoc}
           AND m.tipo = 'c' -- Compra
           AND m.status = 1

        LEFT JOIN products p
            ON p.codigo = m.produto
           AND p.system_unit_id = m.system_unit_id

        WHERE n.system_unit_id = :system_unit_id
          AND n.incluida_estoque = 1
          AND DATE($campoData) BETWEEN :dt_inicio AND :dt_fim

        ORDER BY $campoData DESC, n.numero_nf DESC, m.seq ASC
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':system_unit_id' => $system_unit_id,
                ':dt_inicio'      => $dt_inicio,
                ':dt_fim'         => $dt_fim,
            ]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $notasMap = [];
            $totNotas = 0;
            $sumValorNotas = 0.0;
            $sumQtdTotal = 0.0;
            $sumValorItens = 0.0;
            $countItens = 0;

            foreach ($rows as $r) {
                $notaKey = $r['chave_acesso'] ?: ($r['numero_nf'] . '|' . ($r['serie'] ?? ''));

                if (!isset($notasMap[$notaKey])) {
                    $totNotas++;
                    $valorNota = (float)($r['valor_total'] ?? 0);
                    $sumValorNotas += $valorNota;

                    // ===== LÓGICA DE TRANSFERÊNCIA CORRIGIDA =====
                    $transfInfo = [
                        'foi_transferida' => false,
                        'transfer_key'    => null,
                        'origem_id'       => null,
                        'origem_nome'     => null,
                        'destino_id'      => null,
                        'destino_nome'    => null,
                        'doc_saida'       => null,
                        'doc_entrada'     => null,
                        'descricao'       => null
                    ];

                    // Verifica se existe ID de destino
                    if (!empty($r['transferido_para_unit_id'])) {
                        $transfInfo['foi_transferida'] = true;
                        $transfInfo['transfer_key']    = $r['transfer_key'];

                        // A origem é a própria unidade da nota
                        $transfInfo['origem_id']       = (int)$r['system_unit_id'];
                        $transfInfo['origem_nome']     = $r['nome_origem'];

                        // O destino é para onde foi transferido
                        $transfInfo['destino_id']      = (int)$r['transferido_para_unit_id'];
                        $transfInfo['destino_nome']    = $r['nome_destino'];

                        // Descrição
                        if (!empty($r['nome_destino'])) {
                            $transfInfo['descricao'] = "Enviado para: " . $r['nome_destino'];
                        } else {
                            $transfInfo['descricao'] = "Transferência realizada";
                        }

                        // --- BUSCA DOCS NA MOVIMENTAÇÃO ---
                        if (!empty($r['transfer_key'])) {
                            $stmtMov->execute([':key' => $r['transfer_key']]);
                            $movsDocs = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($movsDocs as $md) {
                                $mdUnitId = (int)$md['system_unit_id'];

                                // Se system_unit_id da mov for igual a ORIGEM (Unidade atual) -> Doc Saída (TS)
                                if ($mdUnitId === (int)$r['system_unit_id']) {
                                    $transfInfo['doc_saida'] = $md['doc'];
                                }

                                // Se system_unit_id da mov for igual ao DESTINO -> Doc Entrada (TE)
                                if ($mdUnitId === (int)$r['transferido_para_unit_id']) {
                                    $transfInfo['doc_entrada'] = $md['doc'];
                                }
                            }
                        }
                    }
                    // ==============================================================

                    $notasMap[$notaKey] = [
                        "fornecedor_id"     => (int)$r['fornecedor_id'],
                        "fornecedor_nome"   => $r['fornecedor_nome'] ?? null,

                        "transferencia_info" => $transfInfo,

                        "chave_acesso"      => $r['chave_acesso'],
                        "numero_nf"         => $r['numero_nf'],
                        "serie"             => $r['serie'],
                        "data_emissao"      => $r['data_emissao'],
                        "data_entrada"      => $r['data_entrada'],
                        "valor_total"       => $valorNota,
                        "itens"             => []
                    ];
                }

                if (!empty($r['mov_id'])) {
                    $qtd = (float)($r['item_quantidade'] ?? 0);
                    $vu  = (float)($r['item_valor_unitario'] ?? 0);
                    $vt  = $qtd * $vu;

                    $notasMap[$notaKey]["itens"][] = [
                        "mov_id"         => (int)$r['mov_id'],
                        "item_seq"       => (int)$r['item_seq'],
                        "produto_id"     => $r['item_produto'],
                        "produto_nome"   => $r['produto_nome'],
                        "produto_unidade"=> $r['produto_unidade'],
                        "quantidade"     => $qtd,
                        "valor_unitario" => $vu,
                        "valor_total"    => (float)round($vt, 2),
                        "data_mov"       => $r['item_data_mov']
                    ];

                    $countItens++;
                    $sumQtdTotal += $qtd;
                    $sumValorItens += $vt;
                }
            }

            return [
                "success" => true,
                "system_unit_id" => $system_unit_id,
                "dt_inicio"      => $dt_inicio,
                "dt_fim"         => $dt_fim,
                "totais" => [
                    "notas"       => $totNotas,
                    "valor_notas" => (float)round($sumValorNotas, 2),
                    "qtd_itens"   => $countItens,
                    "qtd_total"   => (float)round($sumQtdTotal, 4),
                    "valor_itens" => (float)round($sumValorItens, 2)
                ],
                "notas" => array_values($notasMap)
            ];

        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Erro ao listar compras.",
                "error" => $e->getMessage()
            ];
        }
    }

}
?>