<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

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
            // requer a tabela: estoque_nota_duplicata (nota_id, system_unit_id, numero_duplicata, data_vencimento, valor_parcela, ...)
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
                        'vencimento' => $venc,                                        // "YYYY-MM-DD" ou null
                        'valor'      => number_format($valor, 2, '.', ''),           // "1237.05"
                    ];
                }
            }

            // === Planos de contas (da unidade) ===
            $stPlano = $pdo->prepare("
            SELECT id, codigo, descricao
            FROM financeiro_plano
            WHERE system_unit_id = :unit 
            ORDER BY 
                CASE WHEN codigo = '' THEN 1 ELSE 0 END,
                codigo, descricao
        ");
            $stPlano->execute([':unit' => $system_unit_id]);
            $planos = $stPlano->fetchAll(PDO::FETCH_ASSOC);

            $planosDeConta = array_map(function ($p) {
                return [
                    'id'        => (int)$p['id'],
                    'codigo'    => (string)$p['codigo'],
                    'descricao' => (string)$p['descricao'],
                    'label'     => trim(($p['codigo'] ? "{$p['codigo']} - " : "") . $p['descricao']),
                ];
            }, $planos);

            // === Formas de pagamento padrão (ajuste IDs/códigos se necessário) ===
            $formasPagamento = [
                ['id' => 1, 'codigo' => 'dinheiro',      'descricao' => 'Dinheiro'],
                ['id' => 2, 'codigo' => 'dda',           'descricao' => 'DDA'],
                ['id' => 3, 'codigo' => 'pix',           'descricao' => 'PIX'],
                ['id' => 4, 'codigo' => 'debito',        'descricao' => 'Cartão de Débito'],
                ['id' => 5, 'codigo' => 'credito',       'descricao' => 'Cartão de Crédito'],
                ['id' => 6, 'codigo' => 'boleto',        'descricao' => 'Boleto'],
                ['id' => 7, 'codigo' => 'transferencia', 'descricao' => 'Transferência'],
                ['id' => 8, 'codigo' => 'cheque',        'descricao' => 'Cheque'],
                ['id' => 9, 'codigo' => 'deposito',      'descricao' => 'Depósito']
            ];

            // === Monta payload ===
            $payload = [
                'fornecedor_id'            => (int)$nota['fornecedor_id'],
                'documento'                => (string)$nota['numero_nf'],
                'emissao'                  => $emissao,                        // "YYYY-MM-DD" ou null
                'valor_total'              => $valorTotal,                     // "1290.50" (da NF)
                'valor_total_duplicatas'   => number_format($somaDup, 2, '.', ''), // "soma" das duplicatas
                'duplicatas'               => $duplicatas,                     // <<< AQUI: lista das duplicatas
                'planos_de_conta'          => $planosDeConta,
                'formas_pagamento'         => $formasPagamento
            ];

            return ['success' => true, 'data' => $payload];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function getNotaItensFornecedorPayload(array $data):array
    {
        global $pdo;

        try {
            // ===== validação =====
            $required = ['system_unit_id', 'chave_acesso', 'fornecedor_id'];
            foreach ($required as $k) {
                if (!isset($data[$k]) || $data[$k] === '' || $data[$k] === null) {
                    throw new Exception("Campo obrigatório ausente: {$k}");
                }
            }

            $unitId      = (int)$data['system_unit_id'];
            $chaveAcesso = trim((string)$data['chave_acesso']);
            $fornIdInput = (int)$data['fornecedor_id'];

            // ===== nota (com dados do fornecedor via JOIN) =====
            $stNota = $pdo->prepare("
            SELECT 
                en.*, 
                ff.nome     AS fornecedor_nome,
                ff.razao    AS fornecedor_razao,
                ff.cnpj_cpf AS fornecedor_cnpj_cpf
            FROM estoque_nota AS en
            LEFT JOIN financeiro_fornecedor AS ff
                   ON ff.id = en.fornecedor_id
                  AND ff.system_unit_id = en.system_unit_id
            WHERE en.system_unit_id = :unit
              AND en.chave_acesso  = :chave
            LIMIT 1
        ");
            $stNota->execute([':unit' => $unitId, ':chave' => $chaveAcesso]);
            $nota = $stNota->fetch(PDO::FETCH_ASSOC);
            if (!$nota) {
                return ['success' => false, 'error' => 'Nota não encontrada para a chave informada.'];
            }

            $notaId       = (int)$nota['id'];
            $fornecedorId = (int)$nota['fornecedor_id'];
            if ($fornecedorId !== $fornIdInput) {
                return ['success' => false, 'error' => 'fornecedor_id não confere com a nota.'];
            }

            // ===== itens da nota =====
            $stItens = $pdo->prepare("
            SELECT id, numero_item, codigo_produto, descricao, unidade, quantidade
            FROM estoque_nota_item
            WHERE system_unit_id = :unit AND nota_id = :nota
            ORDER BY numero_item ASC
        ");
            $stItens->execute([':unit' => $unitId, ':nota' => $notaId]);
            $rows = $stItens->fetchAll(PDO::FETCH_ASSOC);

            $itensOut = [];

            // prepareds para reuso
            $qRelPorCodigoUnid = $pdo->prepare("
            SELECT id, produto_codigo, codigo_nota, descricao_nota, unidade_nota, fator_conversao, unidade_item
            FROM item_fornecedor
            WHERE system_unit_id = :unit
              AND fornecedor_id  = :forn
              AND codigo_nota    = :cod
              AND (unidade_nota = :un OR unidade_nota IS NULL)
            ORDER BY (unidade_nota IS NULL), id DESC
            LIMIT 1
        ");
            $qRelPorDescUnid = $pdo->prepare("
            SELECT id, produto_codigo, codigo_nota, descricao_nota, unidade_nota, fator_conversao, unidade_item
            FROM item_fornecedor
            WHERE system_unit_id = :unit
              AND fornecedor_id  = :forn
              AND descricao_nota = :desc
              AND (unidade_nota = :un OR unidade_nota IS NULL)
            ORDER BY (unidade_nota IS NULL), id DESC
            LIMIT 1
        ");
            $qRelPorCodigo = $pdo->prepare("
            SELECT id, produto_codigo, codigo_nota, descricao_nota, unidade_nota, fator_conversao, unidade_item
            FROM item_fornecedor
            WHERE system_unit_id = :unit
              AND fornecedor_id  = :forn
              AND codigo_nota    = :cod
            ORDER BY id DESC
            LIMIT 1
        ");
            $qRelPorDesc = $pdo->prepare("
            SELECT id, produto_codigo, codigo_nota, descricao_nota, unidade_nota, fator_conversao, unidade_item
            FROM item_fornecedor
            WHERE system_unit_id = :unit
              AND fornecedor_id  = :forn
              AND descricao_nota = :desc
            ORDER BY id DESC
            LIMIT 1
        ");
            $qProd = $pdo->prepare("
            SELECT id, codigo, nome, und
            FROM products
            WHERE system_unit_id = :unit AND codigo = :codigo
            LIMIT 1
        ");

            foreach ($rows as $r) {
                $numItem  = (int)$r['numero_item'];
                $codNota  = $r['codigo_produto'] !== null ? (string)$r['codigo_produto'] : null;
                $descNota = $r['descricao']      !== null ? (string)$r['descricao']      : null;
                $uniNota  = $r['unidade']        !== null ? (string)$r['unidade']        : null;
                $qtdNota  = $r['quantidade']     !== null ? (float)$r['quantidade']      : 0.0;

                // === tenta casar relação item_fornecedor (prioridade) ===
                $rel = null;

                if ($codNota !== null) {
                    $qRelPorCodigoUnid->execute([':unit'=>$unitId, ':forn'=>$fornecedorId, ':cod'=>$codNota, ':un'=>$uniNota]);
                    $rel = $qRelPorCodigoUnid->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                if (!$rel && $descNota !== null) {
                    $qRelPorDescUnid->execute([':unit'=>$unitId, ':forn'=>$fornecedorId, ':desc'=>$descNota, ':un'=>$uniNota]);
                    $rel = $qRelPorDescUnid->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                if (!$rel && $codNota !== null) {
                    $qRelPorCodigo->execute([':unit'=>$unitId, ':forn'=>$fornecedorId, ':cod'=>$codNota]);
                    $rel = $qRelPorCodigo->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                if (!$rel && $descNota !== null) {
                    $qRelPorDesc->execute([':unit'=>$unitId, ':forn'=>$fornecedorId, ':desc'=>$descNota]);
                    $rel = $qRelPorDesc->fetch(PDO::FETCH_ASSOC) ?: null;
                }

                $fator             = 1.0;
                $prodCodigoInterno = null;
                $prodNomeInterno   = null;
                $prodUndInterno    = null;

                if ($rel) {
                    $fator = isset($rel['fator_conversao']) ? (float)$rel['fator_conversao'] : 1.0;

                    if (!empty($rel['produto_codigo'])) {
                        $qProd->execute([':unit'=>$unitId, ':codigo'=>(int)$rel['produto_codigo']]);
                        if ($p = $qProd->fetch(PDO::FETCH_ASSOC)) {
                            $prodCodigoInterno = (int)$p['codigo'];
                            $prodNomeInterno   = (string)$p['nome'];
                            $prodUndInterno    = !empty($rel['unidade_item']) ? (string)$rel['unidade_item'] : ($p['und'] ?? null);
                        } else {
                            $prodUndInterno    = !empty($rel['unidade_item']) ? (string)$rel['unidade_item'] : null;
                        }
                    } else {
                        $prodUndInterno = !empty($rel['unidade_item']) ? (string)$rel['unidade_item'] : null;
                    }
                }

                $qtdInterno = round($qtdNota * $fator, 4);

                $itensOut[] = [
                    // dados da nota
                    'numero_item_nota'       => $numItem,
                    'codigo_produto_nota'    => $codNota,
                    'descricao_nota'         => $descNota,
                    'unidade_nota'           => $uniNota,
                    'quantidade_nota'        => $qtdNota,

                    // mapeamento interno (se houver)
                    'codigo_produto_interno' => $prodCodigoInterno,
                    'descricao_interno'      => $prodNomeInterno,
                    'unidade_interno'        => $prodUndInterno,
                    'quantidade_interno'     => $qtdInterno,
                    'fator_conversao'        => $fator,

                    // metadados opcionais
                    'relacao_encontrada'     => (bool)$rel,
                    'relacao_id'             => $rel ? (int)$rel['id'] : null,
                ];
            }

            // ===== retorno =====
            return [
                'success' => true,
                'data' => [
                    'nota'  => $nota,     // já contém fornecedor_nome/razao/cnpj_cpf
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
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;               // YYYY-MM-DD
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {                      // DD/MM/YYYY
                    [$d,$m,$y] = explode('/',$s);
                    return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                }
                // tenta normalizar qualquer coisa que strtotime aceite
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
            $chaveAcess  = trim((string)$data['chave_acesso']);
            $dataEntrada = $parseDate((string)$data['data_entrada']); // <- agora vindo do payload (obrigatória)
            $dataEmissaoOverride = isset($data['data_emissao']) && $data['data_emissao'] !== ''
                ? $parseDate((string)$data['data_emissao'])
                : null;

            // ===== busca nota (doc/datas) =====
            $stNota = $pdo->prepare("
            SELECT id, numero_nf, data_emissao, data_entrada, fornecedor_id
            FROM estoque_nota
            WHERE system_unit_id = :unit AND chave_acesso = :chave
            LIMIT 1
        ");
            $stNota->execute([':unit'=>$unitId, ':chave'=>$chaveAcess]);
            $nota = $stNota->fetch(PDO::FETCH_ASSOC);
            if (!$nota) throw new Exception("Nota não encontrada para a chave de acesso informada.");

            $notaId      = (int)$nota['id'];
            $docNumero   = (string)$nota['numero_nf'];
            $dataEmissaoNota = $nota['data_emissao'] ? date('Y-m-d', strtotime($nota['data_emissao'])) : null;

            // Emissão usada: override > emissão da nota > data_entrada (payload)
            $dataEmissao = $dataEmissaoOverride ?? ($dataEmissaoNota ?? $dataEntrada);

            // ===== prepareds auxiliares =====
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

            // mesma query base do seu importCompras (com upsert)
            $insertQuery = "
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
        ";
            $stInsert = $pdo->prepare($insertQuery);

            $pdo->beginTransaction();

            $countIns = 0;
            $detalhes = [];

            foreach ($data['itens'] as $idx => $item) {
                // ===== validação por item =====
                foreach (['numero_item_nota','codigo_produto_interno','unidade_interno','quantidade_interno'] as $k) {
                    if (!isset($item[$k]) || $item[$k] === '' || $item[$k] === null) {
                        throw new Exception("Item {$idx}: campo obrigatório ausente: {$k}");
                    }
                }

                $seq        = (int)$item['numero_item_nota'];
                $prodCodigo = (int)$item['codigo_produto_interno'];
                $undInterna = strtoupper(trim((string)$item['unidade_interno']));
                $qtdInterna = (float)$item['quantidade_interno'];
                if ($qtdInterna <= 0) throw new Exception("Item {$idx}: quantidade_interno deve ser > 0.");

                // valida produto
                $stCheckProd->execute([':unit'=>$unitId, ':cod'=>$prodCodigo]);
                if (!$stCheckProd->fetchColumn()) {
                    throw new Exception("Item {$idx}: produto interno {$prodCodigo} não encontrado nesta unidade.");
                }

                // calcula valor (unitário) se não foi enviado
                $valorUnit = null;
                if (isset($item['valor_unitario_interno']) && $item['valor_unitario_interno'] !== '' && $item['valor_unitario_interno'] !== null) {
                    $v = (string)$item['valor_unitario_interno'];
                    $v = str_replace(['.', ' '], ['', ''], $v);
                    $v = str_replace(',', '.', $v);
                    if (!is_numeric($v)) throw new Exception("Item {$idx}: valor_unitario_interno inválido.");
                    $valorUnit = (float)$v;
                } else {
                    // tenta a partir do item da nota
                    $stItemNota->execute([':unit'=>$unitId, ':nota'=>$notaId, ':ni'=>$seq]);
                    $infNota = $stItemNota->fetch(PDO::FETCH_ASSOC);

                    $fator  = isset($item['fator_conversao']) ? (float)$item['fator_conversao'] : null; // se vier
                    $vUnitN = $infNota && $infNota['valor_unitario'] !== null ? (float)$infNota['valor_unitario'] : null;
                    $vTotN  = $infNota && $infNota['valor_total']    !== null ? (float)$infNota['valor_total']    : null;

                    if ($vTotN !== null && $qtdInterna > 0) {
                        $valorUnit = $vTotN / $qtdInterna; // total da nota / qtd interna
                    } elseif ($vUnitN !== null && $fator && $fator > 0) {
                        $valorUnit = $vUnitN / $fator;     // unit da nota (ex.: 1 FD) / fator = unit interna (ex.: UN)
                    } else {
                        $valorUnit = 0.00;
                    }
                }

                // monta linha p/ movimentacao
                $row = [
                    $unitId,            // system_unit_id
                    1,                  // status
                    $docNumero,         // doc (numero da NF)
                    'c',                // tipo (compra)
                    'entrada',          // tipo_mov
                    $prodCodigo,        // produto (products.codigo)
                    $seq,               // seq (nº item da nota)
                    $dataEntrada,       // data (OBRIGATÓRIA e vinda do payload)
                    $dataEmissao,       // data_emissao (override > nota > data_entrada)
                    $dataEntrada,       // data_original
                    $qtdInterna,        // quantidade (já convertida)
                    $valorUnit,         // valor (unitário interno)
                    $usuarioId          // usuario_id
                ];

                $stInsert->execute($row);
                $countIns++;

                $detalhes[] = [
                    'seq'        => $seq,
                    'produto'    => $prodCodigo,
                    'qtd'        => $qtdInterna,
                    'valor_unit' => round($valorUnit, 6),
                    'und'        => $undInterna
                ];
            }

            // marca a nota como incluída no estoque
            try {
                $stU = $pdo->prepare("
                UPDATE estoque_nota
                SET incluida_estoque = 1, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND system_unit_id = :unit
                LIMIT 1
            ");
                $stU->execute([':id'=>$notaId, ':unit'=>$unitId]);

                if ($stU->rowCount() === 0) {
                    // fallback: alguns schemas usam só 'incluida'
                    $stU2 = $pdo->prepare("
                    UPDATE estoque_nota
                    SET incluida_estoque = 1, updated_at = CURRENT_TIMESTAMP, data_entrada = :data_entrada
                    WHERE id = :id AND system_unit_id = :unit
                    LIMIT 1
                ");
                    $stU2->execute([':id'=>$notaId, ':unit'=>$unitId, ':data_entrada'=>$dataEntrada]);
                }
            } catch (\Throwable $e) {
                // se a coluna não existir, apenas ignora
            }

            $pdo->commit();

            return [
                'success'   => true,
                'message'   => 'Movimentações salvas com sucesso.',
                'inseridos' => $countIns,
                'doc'       => $docNumero,
                'data'      => [
                    'data_entrada' => $dataEntrada,
                    'data_emissao' => $dataEmissao
                ],
                'detalhes'  => $detalhes
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }



}
?>