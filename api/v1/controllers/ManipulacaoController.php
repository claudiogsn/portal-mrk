<?php

require_once __DIR__ . '/../database/db.php';

class ManipulacaoController
{
    public static function createManipulacao($items)
    {
        global $pdo;

        if (empty($items) || !is_array($items)) {
            return ['success' => false, 'message' => 'Nenhum item enviado para salvar.'];
        }

        try {
            $pdo->beginTransaction();

            // Pega a base pelo primeiro item (já que todos pertencem ao mesmo desmembramento)
            $insumo_id = $items[0]['insumo_id'];
            $system_unit_id = $items[0]['system_unit_id'];

            // 1. WIPE: Apaga toda a ficha atual dessa matéria-prima na unidade
            $stmtDel = $pdo->prepare("DELETE FROM manipulation WHERE insumo_id = :insumo_id AND system_unit_id = :system_unit_id");
            $stmtDel->execute([
                ':insumo_id' => $insumo_id,
                ':system_unit_id' => $system_unit_id
            ]);

            $insertCount = 0;

            // 2. REPLACE: Prepara o insert para os itens que vieram da tela
            $stmtIns = $pdo->prepare("
                INSERT INTO manipulation (product_id, insumo_id, quantity, rendimento, system_unit_id)
                VALUES (:product_id, :insumo_id, :quantity, :rendimento, :system_unit_id)
            ");

            foreach ($items as $index => $item) {
                // Validação de segurança básica
                foreach (['product_id', 'insumo_id', 'quantity', 'rendimento', 'system_unit_id'] as $field) {
                    if (!isset($item[$field])) {
                        throw new Exception("Campo obrigatório '$field' ausente no item $index");
                    }
                }

                $stmtIns->execute([
                    ':product_id' => $item['product_id'],
                    ':insumo_id' => $item['insumo_id'],
                    ':quantity' => $item['quantity'],
                    ':rendimento' => $item['rendimento'],
                    ':system_unit_id' => $item['system_unit_id'],
                ]);

                $insertCount++;
            }

            $pdo->commit();
            return [
                'success' => true,
                'message' => "Ficha de manipulação atualizada! ($insertCount subprodutos registrados)"
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            return [
                'success' => false,
                'message' => 'Erro ao salvar ficha de manipulação',
                'error' => $e->getMessage()
            ];
        }
    }
    public static function updateManipulacao($updates)
    {
        global $pdo;

        $allowedFields = ['quantity', 'rendimento'];

        try {
            $pdo->beginTransaction();
            $totalUpdates = 0;

            foreach ($updates as $index => $item) {
                // Validação obrigatória de chaves
                if (!isset($item['product_id']) || !isset($item['unit_id']) || !isset($item['insumo_id'])) {
                    throw new Exception("Campos obrigatórios ausentes no item $index: product_id, unit_id ou insumo_id");
                }

                $setClause = [];
                $values = [
                    ':product_id' => $item['product_id'],
                    ':system_unit_id' => $item['unit_id'],
                    ':insumo_id' => $item['insumo_id']
                ];

                foreach ($allowedFields as $field) {
                    if (isset($item[$field])) {
                        $setClause[] = "$field = :$field";
                        $values[":$field"] = $item[$field];
                    }
                }

                if (empty($setClause)) {
                    throw new Exception("Nenhum campo válido para atualizar no item $index");
                }

                $sql = "UPDATE manipulation SET " . implode(', ', $setClause) . "
                        WHERE product_id = :product_id AND system_unit_id = :system_unit_id AND insumo_id = :insumo_id";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                if ($stmt->rowCount() > 0) {
                    $totalUpdates += $stmt->rowCount();
                }
            }

            $pdo->commit();
            return ['success' => true, 'message' => "Total de linhas atualizadas: $totalUpdates"];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            return ['success' => false, 'message' => 'Erro ao atualizar ficha de produção', 'error' => $e->getMessage()];
        }
    }

    public static function getManipulacaoById($product_id, $system_unit_id)
    {
        global $pdo;

        try {
            // Busca detalhada da produção específica
            $stmt = $pdo->prepare("
                SELECT 
                    p.product_id,
                    p.insumo_id,
                    p.quantity,
                    p.rendimento,
                    prod.nome AS produto_nome,
                    ins.nome AS insumo_nome
                FROM manipulation p
                JOIN products prod ON prod.codigo = p.product_id AND prod.system_unit_id = p.system_unit_id
                JOIN products ins ON ins.codigo = p.insumo_id AND ins.system_unit_id = p.system_unit_id
                WHERE p.product_id = :product_id AND p.system_unit_id = :system_unit_id
            ");
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return ['success' => false, 'message' => 'Ficha de produção não encontrada'];
            }

            // Busca todos os produtos produzidos da unidade
            $stmt2 = $pdo->prepare("SELECT DISTINCT product_id FROM manipulation WHERE system_unit_id = :system_unit_id");
            $stmt2->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt2->execute();
            $produzidos = $stmt2->fetchAll(PDO::FETCH_COLUMN);

            $ficha = [
                'produto' => $product_id,
                'nome' => $rows[0]['produto_nome'],
                'insumos' => []
            ];

            foreach ($rows as $row) {
                $isProduzido = in_array($row['insumo_id'], $produzidos) ? 1 : 0;

                $ficha['insumos'][] = [
                    'insumo_id' => $row['insumo_id'],
                    'nome' => $row['insumo_nome'],
                    'quantity' => (float)$row['quantity'],
                    'rendimento' => isset($row['rendimento']) ? (float)$row['rendimento'] : null,
                    'produzido' => $isProduzido
                ];
            }

            return ['success' => true, 'producao' => $ficha];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar ficha de produção: ' . $e->getMessage()];
        }
    }

    public static function deleteManipulacao($product_id, $system_unit_id)
    {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM manipulation WHERE product_id = :product_id AND system_unit_id = :system_unit_id");
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Ficha de produção excluída com sucesso'];
        } else {
            return ['success' => false, 'message' => 'Ficha de produção não encontrada'];
        }
    }

    public static function listManipulacoes($system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT 
                    p.product_id,
                    p.insumo_id,
                    p.quantity,
                    p.rendimento,
                    prod.nome AS produto_nome,
                    prod.und  AS produto_unidade,
                    ins.nome  AS insumo_nome,
                    ins.und   AS insumo_unidade
                FROM manipulation p
                JOIN products prod ON prod.codigo = p.product_id AND prod.system_unit_id = p.system_unit_id
                JOIN products ins ON ins.codigo = p.insumo_id AND ins.system_unit_id = p.system_unit_id
                WHERE p.system_unit_id = :system_unit_id
                ORDER BY p.product_id
            ");
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt2 = $pdo->prepare("SELECT DISTINCT product_id FROM manipulation WHERE system_unit_id = :system_unit_id");
            $stmt2->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt2->execute();
            $produzidos = $stmt2->fetchAll(PDO::FETCH_COLUMN);

            $producoesAgrupadas = [];

            foreach ($rows as $row) {
                $product_id = $row['product_id'];

                if (!isset($producoesAgrupadas[$product_id])) {
                    $producoesAgrupadas[$product_id] = [
                        'produto'  => (int)$product_id,
                        'nome'     => $row['produto_nome'],
                        'unidade'  => $row['produto_unidade'],
                        'insumos'  => []
                    ];
                }

                $isProduzido = in_array($row['insumo_id'], $produzidos) ? 1 : 0;

                $producoesAgrupadas[$product_id]['insumos'][] = [
                    'insumo_id' => (int)$row['insumo_id'],
                    'nome'      => $row['insumo_nome'],
                    'unidade'   => $row['insumo_unidade'],
                    'quantity'  => (float)$row['quantity'],
                    'rendimento'=> isset($row['rendimento']) ? (float)$row['rendimento'] : null,
                    'produzido' => $isProduzido
                ];
            }

            return ['success' => true, 'producoes' => array_values($producoesAgrupadas)];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar produções: ' . $e->getMessage()];
        }
    }

    public static function expandFichaManipulacao($product_id, $system_unit_id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT 
                    p.product_id,
                    p.insumo_id,
                    p.quantity AS quantidade_principal,
                    p.rendimento AS rendimento_principal,
                    i.nome AS insumo_nome
                FROM manipulation p
                JOIN products i ON i.codigo = p.insumo_id AND i.system_unit_id = p.system_unit_id
                WHERE p.product_id = :product_id AND p.system_unit_id = :system_unit_id
            ");
            $stmt->execute([':product_id' => $product_id, ':system_unit_id' => $system_unit_id]);
            $insumos_principais = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($insumos_principais)) {
                return ['success' => false, 'message' => 'Ficha técnica não encontrada'];
            }

            $stmtProduzidos = $pdo->prepare("SELECT DISTINCT product_id FROM manipulation WHERE system_unit_id = :system_unit_id");
            $stmtProduzidos->execute([':system_unit_id' => $system_unit_id]);
            $produzidos = $stmtProduzidos->fetchAll(PDO::FETCH_COLUMN);

            $insumos_expandidos = [];

            foreach ($insumos_principais as $item) {
                $insumo_id = $item['insumo_id'];
                $quantidade_usada = $item['quantidade_principal'];

                if (in_array($insumo_id, $produzidos)) {
                    $stmtSub = $pdo->prepare("
                        SELECT p.insumo_id, i.nome, p.quantity, p.rendimento
                        FROM manipulation p
                        JOIN products i ON i.codigo = p.insumo_id AND i.system_unit_id = p.system_unit_id
                        WHERE p.product_id = :produto AND p.system_unit_id = :unit_id
                    ");
                    $stmtSub->execute([':produto' => $insumo_id, ':unit_id' => $system_unit_id]);
                    $subInsumos = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($subInsumos as $sub) {
                        if (!isset($sub['rendimento']) || $sub['rendimento'] <= 0) {
                            throw new Exception("Rendimento inválido na ficha de produção do item $insumo_id");
                        }

                        $fator = $quantidade_usada / $sub['rendimento'];
                        $insumos_expandidos[] = [
                            'insumo_id' => $sub['insumo_id'],
                            'nome' => $sub['nome'],
                            'quantity' => round($sub['quantity'] * $fator, 6)
                        ];
                    }
                } else {
                    $insumos_expandidos[] = [
                        'insumo_id' => $insumo_id,
                        'nome' => $item['insumo_nome'],
                        'quantity' => (float)$quantidade_usada
                    ];
                }
            }

            return ['success' => true, 'produto' => $product_id, 'insumos_expandidos' => $insumos_expandidos];
        } catch (Exception $e) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Erro ao expandir ficha', 'error' => $e->getMessage()];
        }
    }

    public static function executeProduction($data): array
    {
        global $pdo;

        $required = ["system_unit_id", "product_codigo", "quantidade_produzida"];
        foreach ($required as $f) {
            if (!isset($data[$f]) || $data[$f] === "") {
                return ["success" => false, "message" => "O campo '$f' é obrigatório."];
            }
        }

        $system_unit_id  = (int)$data["system_unit_id"];
        $product_codigo  = (int)$data["product_codigo"];
        $qtdProduzida    = (float)str_replace(",", ".", (string)$data["quantidade_produzida"]);

        $usuario_id = $data["user"] ?? $data["usuario_id"] ?? null;
        if (!$usuario_id) {
            return ["success" => false, "message" => "O campo 'user' (ou 'usuario_id') é obrigatório."];
        }
        $usuario_id = (string)$usuario_id;

        $date_producao  = $data["date_producao"] ?? $data["data"] ?? date("Y-m-d");
        $allow_negative = isset($data["allow_negative"]) ? (bool)$data["allow_negative"] : false;

        if ($system_unit_id <= 0) return ["success" => false, "message" => "system_unit_id inválido."];
        if ($product_codigo <= 0) return ["success" => false, "message" => "product_codigo inválido."];
        if ($qtdProduzida <= 0) return ["success" => false, "message" => "quantidade_produzida deve ser > 0."];

        try {
            $stmtUnit = $pdo->prepare("SELECT name FROM system_unit WHERE id = :id LIMIT 1");
            $stmtUnit->execute([":id" => $system_unit_id]);
            $unidade_nome = (string)($stmtUnit->fetchColumn() ?: "Unidade #{$system_unit_id}");

            $usuario_nome = null;
            try {
                $stmtUser = $pdo->prepare("SELECT name FROM system_users WHERE id = :id LIMIT 1");
                $stmtUser->execute([":id" => (int)$usuario_id]);
                $usuario_nome = $stmtUser->fetchColumn();
            } catch (\Throwable $ignore) {
                // Ignore se não tiver a tabela system_users
            }

            $hora_execucao = date("H:i");

            $pdo->beginTransaction();

            // Busca produto final
            $stmt = $pdo->prepare("
                SELECT id, codigo, nome, und, COALESCE(saldo,0) AS saldo
                FROM products
                WHERE system_unit_id = :unit AND codigo = :codigo
                FOR UPDATE
            ");
            $stmt->execute([":unit" => $system_unit_id, ":codigo" => $product_codigo]);
            $produtoFinal = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produtoFinal) {
                $pdo->rollBack();
                return ["success" => false, "message" => "Produto final não encontrado nessa unidade."];
            }

            // Busca Ficha Técnica
            $stmt = $pdo->prepare("
                SELECT
                    p.insumo_id,
                    p.quantity AS ficha_qtd,
                    COALESCE(p.rendimento, NULL) AS rendimento,
                    pr.codigo AS insumo_codigo,
                    pr.nome   AS insumo_nome,
                    pr.und    AS insumo_und,
                    COALESCE(pr.saldo,0) AS insumo_saldo
                FROM manipulation p
                INNER JOIN products pr ON pr.codigo = p.insumo_id AND pr.system_unit_id = p.system_unit_id
                WHERE p.system_unit_id = :unit AND p.product_id = :product_codigo
                FOR UPDATE
            ");
            $stmt->execute([":unit" => $system_unit_id, ":product_codigo" => $product_codigo]);
            $ficha = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$ficha || count($ficha) === 0) {
                $pdo->rollBack();
                return ["success" => false, "message" => "Ficha não encontrada para esse produto."];
            }

            // Geração de Documento MP (Manipulação de Produção)
            $ultimoDoc = MovimentacaoController::getLastMov($system_unit_id, "mp");
            $doc = MovimentacaoController::incrementDoc($ultimoDoc, "mp");
            $tipo = "mp"; // CORRIGIDO PARA MP
            $status = 1;

            $stmtInsMov = $pdo->prepare("
                INSERT INTO movimentacao (system_unit_id, status, doc, tipo, tipo_mov, produto, seq, data, data_original, quantidade, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtUpdSaldoByCodigo = $pdo->prepare("
                UPDATE products
                SET saldo = COALESCE(saldo,0) + :delta, ultimo_doc = :doc, updated_at = CURRENT_TIMESTAMP
                WHERE system_unit_id = :unit AND codigo = :codigo
            ");

            $seq = 1;
            $insumosResumo = [];

            // Saída de Insumos
            foreach ($ficha as $row) {
                $insumoCodigo = (int)$row["insumo_codigo"];
                $fichaQtd = (float)$row["ficha_qtd"];
                $rendimento = ($row["rendimento"] !== null && $row["rendimento"] > 0) ? (float)$row["rendimento"] : 1;

                $consumo = (float)round($fichaQtd * ($qtdProduzida / $rendimento), 4);
                $saldoAtual = (float)$row["insumo_saldo"];

                if (!$allow_negative && $saldoAtual < $consumo) {
                    $pdo->rollBack();
                    return ["success" => false, "message" => "Saldo insuficiente no insumo {$row['insumo_nome']}."];
                }

                $stmtInsMov->execute([$system_unit_id, $status, $doc, $tipo, "saida", $insumoCodigo, $seq, $date_producao, $date_producao, $consumo, $usuario_id]);
                $stmtUpdSaldoByCodigo->execute([":delta" => -1 * $consumo, ":doc" => $doc, ":unit" => $system_unit_id, ":codigo" => $insumoCodigo]);

                $insumosResumo[] = [
                    "codigo" => $insumoCodigo,
                    "nome" => $row["insumo_nome"],
                    "und" => $row["insumo_und"],
                    "consumo" => $consumo
                ];
                $seq++;
            }

            // Entrada do Produto Final
            $stmtInsMov->execute([$system_unit_id, $status, $doc, $tipo, "entrada", $product_codigo, $seq, $date_producao, $date_producao, (float)round($qtdProduzida, 4), $usuario_id]);
            $stmtUpdSaldoByCodigo->execute([":delta" => (float)round($qtdProduzida, 4), ":doc" => $doc, ":unit" => $system_unit_id, ":codigo" => $product_codigo]);

            $pdo->commit();

            return [
                "success" => true,
                "message" => "Produção executada com sucesso!",
                "doc" => $doc,
                "data_producao" => $date_producao,
                "produto_final" => [
                    "codigo" => $product_codigo,
                    "nome" => $produtoFinal["nome"],
                    "quantidade_produzida" => (float)round($qtdProduzida, 4)
                ],
                "insumos" => $insumosResumo
            ];

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            return ["success" => false, "message" => "Erro ao executar produção: " . $e->getMessage()];
        }
    }

    public static function executeProductionBatch($data): array
    {
        global $pdo;

        $required = ["system_unit_id", "items"];
        foreach ($required as $f) {
            if (!isset($data[$f]) || empty($data[$f])) {
                return ["success" => false, "message" => "O campo '$f' é obrigatório."];
            }
        }

        $system_unit_id = (int)$data["system_unit_id"];
        $usuario_id = (string)($data["user"] ?? $data["usuario_id"] ?? "");
        if (!$usuario_id) return ["success" => false, "message" => "O campo 'usuario_id' é obrigatório."];

        $date_producao = $data["date_producao"] ?? date("Y-m-d");
        $allow_negative = isset($data["allow_negative"]) ? (bool)$data["allow_negative"] : false;
        $items = $data["items"];

        $itensNorm = [];
        foreach ($items as $idx => $it) {
            $product_codigo = (int)($it["product_codigo"] ?? 0);
            $q = $it["quantidade_produzida"] ?? $it["quantidade"] ?? $it["qtd"] ?? 0;
            $qtdProduzida = (float)str_replace(",", ".", (string)$q);

            if ($product_codigo <= 0 || $qtdProduzida <= 0) {
                return ["success" => false, "message" => "Dados inválidos no item #" . ($idx + 1)];
            }
            $itensNorm[] = ["product_codigo" => $product_codigo, "quantidade_produzida" => round($qtdProduzida, 4)];
        }

        try {
            $pdo->beginTransaction();

            $ultimoDoc = MovimentacaoController::getLastMov($system_unit_id, "mp");
            $doc = MovimentacaoController::incrementDoc($ultimoDoc, "mp");
            $tipo = "mp"; // CORRIGIDO PARA MP
            $status = 1;
            $seq = 1;

            $stmtInsMov = $pdo->prepare("
                INSERT INTO movimentacao (system_unit_id, status, doc, tipo, tipo_mov, produto, seq, data, data_original, quantidade, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtUpdSaldo = $pdo->prepare("
                UPDATE products
                SET saldo = COALESCE(saldo,0) + :delta, ultimo_doc = :doc, updated_at = CURRENT_TIMESTAMP
                WHERE system_unit_id = :unit AND codigo = :codigo
            ");

            $produtosResumo = [];

            foreach ($itensNorm as $it) {
                $product_codigo = $it["product_codigo"];
                $qtdProduzida = $it["quantidade_produzida"];

                $stmtProd = $pdo->prepare("SELECT nome FROM products WHERE system_unit_id = :unit AND codigo = :codigo FOR UPDATE");
                $stmtProd->execute([":unit" => $system_unit_id, ":codigo" => $product_codigo]);
                $produtoFinal = $stmtProd->fetch(PDO::FETCH_ASSOC);

                if (!$produtoFinal) throw new Exception("Produto final $product_codigo não encontrado.");

                $stmtFicha = $pdo->prepare("
                    SELECT p.insumo_id, p.quantity AS ficha_qtd, p.rendimento, pr.nome, COALESCE(pr.saldo,0) AS insumo_saldo
                    FROM manipulation p
                    INNER JOIN products pr ON pr.codigo = p.insumo_id AND pr.system_unit_id = p.system_unit_id
                    WHERE p.system_unit_id = :unit AND p.product_id = :product_codigo FOR UPDATE
                ");
                $stmtFicha->execute([":unit" => $system_unit_id, ":product_codigo" => $product_codigo]);
                $ficha = $stmtFicha->fetchAll(PDO::FETCH_ASSOC);

                if (!$ficha) throw new Exception("Ficha técnica não encontrada para o produto $product_codigo.");

                $insumosResumo = [];

                foreach ($ficha as $row) {
                    $insumoCodigo = (int)$row["insumo_id"];
                    $rendimento = ($row["rendimento"] > 0) ? (float)$row["rendimento"] : 1;
                    $consumo = (float)round($row["ficha_qtd"] * ($qtdProduzida / $rendimento), 4);

                    if (!$allow_negative && $row["insumo_saldo"] < $consumo) {
                        throw new Exception("Saldo insuficiente no insumo {$row['nome']}.");
                    }

                    $stmtInsMov->execute([$system_unit_id, $status, $doc, $tipo, "saida", $insumoCodigo, $seq++, $date_producao, $date_producao, $consumo, $usuario_id]);
                    $stmtUpdSaldo->execute([":delta" => -1 * $consumo, ":doc" => $doc, ":unit" => $system_unit_id, ":codigo" => $insumoCodigo]);

                    $insumosResumo[] = ["codigo" => $insumoCodigo, "consumo" => $consumo];
                }

                $stmtInsMov->execute([$system_unit_id, $status, $doc, $tipo, "entrada", $product_codigo, $seq++, $date_producao, $date_producao, $qtdProduzida, $usuario_id]);
                $stmtUpdSaldo->execute([":delta" => $qtdProduzida, ":doc" => $doc, ":unit" => $system_unit_id, ":codigo" => $product_codigo]);

                $produtosResumo[] = ["codigo" => $product_codigo, "quantidade_produzida" => $qtdProduzida, "insumos" => $insumosResumo];
            }

            $pdo->commit();
            return ["success" => true, "message" => "Produção em lote executada!", "doc" => $doc, "produtos" => $produtosResumo];

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public static function listManipulacoesRealizadasDetalhado(array $data): array
    {
        global $pdo;

        if (empty($data["system_unit_id"]) || empty($data["data_inicial"]) || empty($data["data_final"])) {
            return ["success" => false, "message" => "Parâmetros incompletos."];
        }

        try {
            // CORRIGIDO O TIPO PARA 'mp' AQUI EMBAIXO:
            $stmt = $pdo->prepare("
                SELECT m.doc, m.data, m.tipo_mov, m.produto AS produto_codigo, SUM(m.quantidade) AS quantidade, p.nome AS produto_nome, p.und AS produto_unidade, m.usuario_id
                FROM movimentacao m
                INNER JOIN products p ON p.codigo = m.produto AND p.system_unit_id = m.system_unit_id
                WHERE m.system_unit_id = :unit AND m.tipo = 'mp' AND DATE(m.data) BETWEEN :dt_ini AND :dt_fim
                GROUP BY m.doc, m.data, m.tipo_mov, m.produto, p.nome, p.und, m.usuario_id
                ORDER BY m.data DESC, m.doc DESC
            ");

            $stmt->execute([":unit" => $data["system_unit_id"], ":dt_ini" => $data["data_inicial"], ":dt_fim" => $data["data_final"]]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $producoes = [];
            foreach ($rows as $row) {
                $doc = $row["doc"];
                if (!isset($producoes[$doc])) {
                    $producoes[$doc] = ["doc" => $doc, "data" => $row["data"], "usuario_id" => $row["usuario_id"], "produto_final" => null, "insumos" => []];
                }

                $itemInfo = [
                    "codigo" => (int)$row["produto_codigo"],
                    "nome" => $row["produto_nome"],
                    "unidade" => $row["produto_unidade"]
                ];

                if ($row["tipo_mov"] === "entrada") {
                    $itemInfo["quantidade_produzida"] = (float)round($row["quantidade"], 4);
                    $producoes[$doc]["produto_final"] = $itemInfo;
                } else {
                    $itemInfo["quantidade_consumida"] = (float)round($row["quantidade"], 4);
                    $producoes[$doc]["insumos"][] = $itemInfo;
                }
            }

            return ["success" => true, "total" => count($producoes), "producoes" => array_values($producoes)];
        } catch (Exception $e) {
            return ["success" => false, "message" => "Erro ao listar: " . $e->getMessage()];
        }
    }

    public static function listManipulacoesRealizadasBasico(array $data): array
    {
        global $pdo;

        if (empty($data["system_unit_id"])) return ["success" => false, "message" => "O campo 'system_unit_id' é obrigatório."];

        try {
            $where = "";
            $params = [":unit" => $data["system_unit_id"]];

            if (!empty($data["data_inicial"]) && !empty($data["data_final"])) {
                $where = " AND DATE(m.data) BETWEEN :dt_ini AND :dt_fim ";
                $params[":dt_ini"] = $data["data_inicial"];
                $params[":dt_fim"] = $data["data_final"];
            }

            // CORRIGIDO O TIPO PARA 'mp' AQUI EMBAIXO:
            $stmt = $pdo->prepare("
                SELECT m.doc, m.data, m.usuario_id, m.produto AS produto_codigo, SUM(m.quantidade) AS quantidade_produzida, p.nome AS produto_nome, p.und AS produto_unidade
                FROM movimentacao m
                INNER JOIN products p ON p.codigo = m.produto AND p.system_unit_id = m.system_unit_id
                WHERE m.system_unit_id = :unit AND m.tipo = 'mp' AND m.tipo_mov = 'entrada' $where
                GROUP BY m.doc, m.data, m.usuario_id, m.produto, p.nome, p.und
                ORDER BY m.data DESC, m.doc DESC
            ");

            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                "success" => true,
                "producoes" => array_map(function ($r) {
                    return [
                        "doc" => $r["doc"], "data" => $r["data"], "usuario_id" => $r["usuario_id"],
                        "produto_final" => ["codigo" => (int)$r["produto_codigo"], "nome" => $r["produto_nome"], "unidade" => $r["produto_unidade"], "quantidade_produzida" => (float)round($r["quantidade_produzida"], 4)]
                    ];
                }, $rows)
            ];

        } catch (Exception $e) {
            return ["success" => false, "message" => "Erro ao listar: " . $e->getMessage()];
        }
    }

    public static function listInsumosComFichaStatus($system_unit_id): array
    {
        global $pdo;

        try {
            // Busca todos os produtos que são insumos (matérias-primas)
            $stmt = $pdo->prepare("
                SELECT p.codigo, p.nome, p.compravel,
                    EXISTS (
                        SELECT 1
                        FROM manipulation m
                        WHERE m.system_unit_id = p.system_unit_id 
                          AND m.insumo_id = p.codigo
                    ) AS tem_ficha
                FROM products p
                WHERE p.system_unit_id = :unit_id
                  AND p.insumo = 1
                ORDER BY p.nome ASC
            ");
            $stmt->execute([':unit_id' => $system_unit_id]);

            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'produtos' => $produtos
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erro ao listar insumos e status da ficha: ' . $e->getMessage()
            ];
        }
    }
}
?>