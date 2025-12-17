<?php

require_once __DIR__ . '/../database/db.php';

class ProducaoController
{
    public static function createProducao($items)
    {
        global $pdo;

        try {
            $pdo->beginTransaction();
            $insertCount = 0;
            $rendimentoPorChave = []; // [product_id|system_unit_id => rendimento]

            foreach ($items as $index => $item) {
                // Validação obrigatória
                foreach (['product_id', 'insumo_id', 'quantity', 'rendimento', 'system_unit_id'] as $field) {
                    if (!isset($item[$field])) {
                        throw new Exception("Campo obrigatório '$field' ausente no item $index");
                    }
                }

                $product_id = $item['product_id'];
                $insumo_id = $item['insumo_id'];
                $quantity = $item['quantity'];
                $rendimento = $item['rendimento'];
                $system_unit_id = $item['system_unit_id'];

                $chave = "$product_id|$system_unit_id";

                // Verifica se já existe o registro
                $checkStmt = $pdo->prepare("
                SELECT 1 FROM productions
                WHERE product_id = :product_id AND insumo_id = :insumo_id AND system_unit_id = :system_unit_id
                LIMIT 1
            ");
                $checkStmt->execute([
                    ':product_id' => $product_id,
                    ':insumo_id' => $insumo_id,
                    ':system_unit_id' => $system_unit_id
                ]);

                if ($checkStmt->fetch()) {
                    throw new Exception("Produção já existente com product_id=$product_id, insumo_id=$insumo_id, unit_id=$system_unit_id no item $index");
                }

                // Verifica se o rendimento é consistente para a chave composta
                if (isset($rendimentoPorChave[$chave])) {
                    if ((float)$rendimentoPorChave[$chave] !== (float)$rendimento) {
                        throw new Exception("Rendimento divergente para o produto $product_id na unidade $system_unit_id no item $index");
                    }
                } else {
                    $rendimentoPorChave[$chave] = $rendimento;
                }

                // Inserção
                $stmt = $pdo->prepare("
                INSERT INTO productions (product_id, insumo_id, quantity, rendimento, system_unit_id)
                VALUES (:product_id, :insumo_id, :quantity, :rendimento, :system_unit_id)
            ");
                $stmt->execute([
                    ':product_id' => $product_id,
                    ':insumo_id' => $insumo_id,
                    ':quantity' => $quantity,
                    ':rendimento' => $rendimento,
                    ':system_unit_id' => $system_unit_id,
                ]);

                $insertCount += $stmt->rowCount();
            }

            $pdo->commit();
            return ['success' => true, 'message' => "Total de produções criadas: $insertCount"];
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(400);
            return ['success' => false, 'message' => 'Erro ao criar produções', 'error' => $e->getMessage()];
        }
    }

    public static function updateProducao($updates)
    {
        global $pdo;

        $allowedFields = ['quantity', 'rendimento'];

        try {
            $pdo->beginTransaction();
            $totalUpdates = 0;

            foreach ($updates as $index => $item) {
                // Validação obrigatória de chaves
                if (
                    !isset($item['product_id']) ||
                    !isset($item['unit_id']) ||
                    !isset($item['insumo_id'])
                ) {
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

                $sql = "UPDATE productions SET " . implode(', ', $setClause) . "
                    WHERE product_id = :product_id AND system_unit_id = :system_unit_id AND insumo_id = :insumo_id";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                if ($stmt->rowCount() === 0) {
                    throw new Exception("Registro não encontrado ou sem alteração no item $index");
                }

                $totalUpdates += $stmt->rowCount();
            }

            $pdo->commit();
            return ['success' => true, 'message' => "Total de linhas atualizadas: $totalUpdates"];
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(400);
            return ['success' => false, 'message' => 'Erro ao atualizar ficha de produção', 'error' => $e->getMessage()];
        }
    }


    public static function getProducaoById($product_id, $system_unit_id)
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
            FROM productions p
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

            // Busca todos os produtos produzidos da unidade (para saber se algum insumo é produzido)
            $stmt2 = $pdo->prepare("SELECT DISTINCT product_id FROM productions WHERE system_unit_id = :system_unit_id");
            $stmt2->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt2->execute();
            $produzidos = $stmt2->fetchAll(PDO::FETCH_COLUMN);

            // Monta resposta no formato da listagem
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


    public static function deleteProducao($product_id, $system_unit_id)
    {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM productions WHERE product_id = :product_id AND system_unit_id = :system_unit_id");
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Ficha de produção excluída com sucesso'];
        } else {
            return ['success' => false, 'message' => 'Ficha de produção não encontrada'];
        }
    }

    public static function listProducoes($system_unit_id)
    {
        global $pdo;

        try {
            // Primeiro, buscamos todas as produções da unidade
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
            FROM productions p
            JOIN products prod 
                ON prod.codigo = p.product_id 
               AND prod.system_unit_id = p.system_unit_id
            JOIN products ins  
                ON ins.codigo = p.insumo_id 
               AND ins.system_unit_id = p.system_unit_id
            WHERE p.system_unit_id = :system_unit_id
            ORDER BY p.product_id
        ");
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agora vamos identificar todos os product_id únicos (para saber quais insumos também são produtos)
            $stmt2 = $pdo->prepare("SELECT DISTINCT product_id FROM productions WHERE system_unit_id = :system_unit_id");
            $stmt2->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt2->execute();
            $produzidos = $stmt2->fetchAll(PDO::FETCH_COLUMN); // array de todos os product_id

            $producoesAgrupadas = [];

            foreach ($rows as $row) {
                $product_id = $row['product_id'];

                if (!isset($producoesAgrupadas[$product_id])) {
                    $producoesAgrupadas[$product_id] = [
                        'produto'  => (int)$product_id,
                        'nome'     => $row['produto_nome'],
                        'unidade'  => $row['produto_unidade'], // ✅ unidade do produto produzido
                        'insumos'  => []
                    ];
                }

                $isProduzido = in_array($row['insumo_id'], $produzidos) ? 1 : 0;

                $producoesAgrupadas[$product_id]['insumos'][] = [
                    'insumo_id' => (int)$row['insumo_id'],
                    'nome'      => $row['insumo_nome'],
                    'unidade'   => $row['insumo_unidade'], // ✅ unidade do insumo
                    'quantity'  => (float)$row['quantity'],
                    'rendimento'=> isset($row['rendimento']) ? (float)$row['rendimento'] : null,
                    'produzido' => $isProduzido
                ];
            }

            $producoes = array_values($producoesAgrupadas);

            return ['success' => true, 'producoes' => $producoes];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar produções: ' . $e->getMessage()];
        }
    }

    public static function expandFichaProducao($product_id, $system_unit_id)
    {
        global $pdo;

        try {
            // Busca a ficha técnica do produto principal
            $stmt = $pdo->prepare("
            SELECT 
                p.product_id,
                p.insumo_id,
                p.quantity AS quantidade_principal,
                p.rendimento AS rendimento_principal,
                i.nome AS insumo_nome
            FROM productions p
            JOIN products i ON i.codigo = p.insumo_id AND i.system_unit_id = p.system_unit_id
            WHERE p.product_id = :product_id AND p.system_unit_id = :system_unit_id
        ");
            $stmt->execute([
                ':product_id' => $product_id,
                ':system_unit_id' => $system_unit_id
            ]);

            $insumos_principais = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($insumos_principais)) {
                return ['success' => false, 'message' => 'Ficha técnica não encontrada'];
            }

            // Mapear os produtos que também são produzidos
            $stmtProduzidos = $pdo->prepare("
            SELECT DISTINCT product_id FROM productions WHERE system_unit_id = :system_unit_id
        ");
            $stmtProduzidos->execute([':system_unit_id' => $system_unit_id]);
            $produzidos = $stmtProduzidos->fetchAll(PDO::FETCH_COLUMN);

            $insumos_expandidos = [];

            foreach ($insumos_principais as $item) {
                $insumo_id = $item['insumo_id'];
                $quantidade_usada = $item['quantidade_principal'];
                $rendimento_original = $item['rendimento_principal'];

                if (in_array($insumo_id, $produzidos)) {
                    // Esse insumo também tem ficha: expandir
                    $stmtSub = $pdo->prepare("
                    SELECT 
                        p.insumo_id,
                        i.nome,
                        p.quantity,
                        p.rendimento
                    FROM productions p
                    JOIN products i ON i.codigo = p.insumo_id AND i.system_unit_id = p.system_unit_id
                    WHERE p.product_id = :produto AND p.system_unit_id = :unit_id
                ");
                    $stmtSub->execute([
                        ':produto' => $insumo_id,
                        ':unit_id' => $system_unit_id
                    ]);
                    $subInsumos = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($subInsumos as $sub) {
                        if (!isset($sub['rendimento']) || $sub['rendimento'] == 0) {
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
                    // Insumo final
                    $insumos_expandidos[] = [
                        'insumo_id' => $insumo_id,
                        'nome' => $item['insumo_nome'],
                        'quantity' => (float)$quantidade_usada
                    ];
                }
            }

            return [
                'success' => true,
                'produto' => $product_id,
                'insumos_expandidos' => $insumos_expandidos
            ];
        } catch (Exception $e) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Erro ao expandir ficha', 'error' => $e->getMessage()];
        }
    }

    public static function executeProduction($data): array
    {
        global $pdo;

        // obrigatórios
        $required = ["system_unit_id", "product_codigo", "quantidade_produzida"];
        foreach ($required as $f) {
            if (!isset($data[$f]) || $data[$f] === "") {
                return ["success" => false, "message" => "O campo '$f' é obrigatório."];
            }
        }

        $system_unit_id  = (int)$data["system_unit_id"];
        $product_codigo  = (int)$data["product_codigo"]; // SEMPRE products.codigo
        $qtdProduzida    = (float)str_replace(",", ".", (string)$data["quantidade_produzida"]);

        // usuário
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
            // Nome da unidade (fora do lock FOR UPDATE dos produtos; pode ser antes da transação também)
            $stmtUnit = $pdo->prepare("SELECT name FROM system_unit WHERE id = :id LIMIT 1");
            $stmtUnit->execute([":id" => $system_unit_id]);
            $unidade_nome = (string)($stmtUnit->fetchColumn() ?: "Unidade #{$system_unit_id}");

            // Nome do usuário (ajuste a tabela/colunas se necessário)
            $usuario_nome = null;
            try {
                $stmtUser = $pdo->prepare("SELECT name FROM system_users WHERE id = :id LIMIT 1");
                $stmtUser->execute([":id" => (int)$usuario_id]);
                $usuario_nome = $stmtUser->fetchColumn();
            } catch (\Throwable $ignore) {
                // Se sua base não tiver system_user ou não quiser buscar, deixa null sem quebrar.
                $usuario_nome = null;
            }

            $hora_execucao = date("H:i");

            $pdo->beginTransaction();

            // 1) Buscar produto final por CODIGO (FOR UPDATE)
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
                return ["success" => false, "message" => "Produto final não encontrado (codigo={$product_codigo}) nessa unidade."];
            }

            // 2) Buscar ficha (productions) pelo CODIGO do produto (FOR UPDATE)
            //    OBS: productions.product_id = CODIGO do produto final, productions.insumo_id = CODIGO do insumo
            $stmt = $pdo->prepare("
            SELECT
                p.insumo_id,                           -- CODIGO do insumo (products.codigo)
                p.quantity AS ficha_qtd,
                COALESCE(p.rendimento, NULL) AS rendimento,
                pr.codigo AS insumo_codigo,
                pr.nome   AS insumo_nome,
                pr.und    AS insumo_und,
                COALESCE(pr.saldo,0) AS insumo_saldo
            FROM productions p
            INNER JOIN products pr
                ON pr.codigo = p.insumo_id
               AND pr.system_unit_id = p.system_unit_id
            WHERE p.system_unit_id = :unit
              AND p.product_id = :product_codigo       -- ✅ CODIGO do produto
            FOR UPDATE
        ");
            $stmt->execute([":unit" => $system_unit_id, ":product_codigo" => $product_codigo]);
            $ficha = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$ficha || count($ficha) === 0) {
                $pdo->rollBack();
                return ["success" => false, "message" => "Ficha não encontrada em productions para esse produto (codigo={$product_codigo})."];
            }

            // 3) Gerar doc (pr-000001)
            $ultimoDoc = MovimentacaoController::getLastMov($system_unit_id, "pr");
            $doc = MovimentacaoController::incrementDoc($ultimoDoc, "pr");

            $tipo = "pr";
            $status = 1;

            // 4) INSERT movimentacao e UPDATE saldo (por CODIGO)
            $stmtInsMov = $pdo->prepare("
            INSERT INTO movimentacao
                (system_unit_id, system_unit_id_destino, status, doc, tipo, tipo_mov, produto, seq, data, data_original, quantidade, usuario_id)
            VALUES
                (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

            // ✅ Atualiza por CODIGO (padrão do seu sistema)
            $stmtUpdSaldoByCodigo = $pdo->prepare("
            UPDATE products
               SET saldo = COALESCE(saldo,0) + :delta,
                   ultimo_doc = :doc,
                   updated_at = CURRENT_TIMESTAMP
             WHERE system_unit_id = :unit
               AND codigo = :codigo
        ");

            $seq = 1;
            $insumosResumo = [];

            // 5) Saída dos insumos (produto SEMPRE = products.codigo)
            foreach ($ficha as $row) {
                $insumoCodigo = (int)$row["insumo_codigo"];
                $insumoNome   = (string)$row["insumo_nome"];
                $insumoUnd    = (string)($row["insumo_und"] ?? "");

                $fichaQtd = (float)$row["ficha_qtd"];
                $rend     = $row["rendimento"] !== null ? (float)$row["rendimento"] : null;

                // ✅ Regra correta: consumo = quantity * (qtdProduzida / rendimento). Se rendimento vazio/0 assume 1.
                $rendimento = ($rend !== null && $rend > 0) ? $rend : 1;
                $consumo = $fichaQtd * ($qtdProduzida / $rendimento);
                $consumo = (float)round($consumo, 4);

                $saldoAtual = (float)$row["insumo_saldo"];

                if (!$allow_negative && $saldoAtual < $consumo) {
                    $pdo->rollBack();
                    return [
                        "success" => false,
                        "message" => "Saldo insuficiente no insumo {$insumoNome} (código {$insumoCodigo}). Saldo: {$saldoAtual} | Consumo: {$consumo}"
                    ];
                }

                // movimentacao: SAIDA do insumo
                $stmtInsMov->execute([
                    $system_unit_id,
                    $status,
                    $doc,
                    $tipo,
                    "saida",
                    $insumoCodigo,   // ✅ SEMPRE codigo
                    $seq,
                    $date_producao,
                    $date_producao,
                    $consumo,
                    $usuario_id
                ]);

                // saldo insumo -= consumo (por CODIGO)
                $stmtUpdSaldoByCodigo->execute([
                    ":delta"  => -1 * $consumo,
                    ":doc"    => $doc,
                    ":unit"   => $system_unit_id,
                    ":codigo" => $insumoCodigo
                ]);

                $insumosResumo[] = [
                    "codigo"  => $insumoCodigo,
                    "nome"    => $insumoNome,
                    "und"     => $insumoUnd,
                    "ficha"   => (float)round($fichaQtd, 4),
                    "rendimento" => (float)round($rendimento, 4),
                    "consumo" => $consumo
                ];

                $seq++;
            }

            // 6) Entrada do produto final (produto SEMPRE = products.codigo)
            $stmtInsMov->execute([
                $system_unit_id,
                $status,
                $doc,
                $tipo,
                "entrada",
                $product_codigo,  // ✅ SEMPRE codigo
                $seq,
                $date_producao,
                $date_producao,
                (float)round($qtdProduzida, 4),
                $usuario_id
            ]);

            // saldo produto final += qtdProduzida (por CODIGO)
            $stmtUpdSaldoByCodigo->execute([
                ":delta"  => (float)round($qtdProduzida, 4),
                ":doc"    => $doc,
                ":unit"   => $system_unit_id,
                ":codigo" => $product_codigo
            ]);

            $pdo->commit();

            return [
                "success" => true,
                "message" => "Produção executada com sucesso!",
                "doc" => $doc,
                "data_producao" => $date_producao,
                "hora_execucao" => $hora_execucao,

                "unidade" => [
                    "id" => $system_unit_id,
                    "nome" => $unidade_nome
                ],

                "usuario" => [
                    "id" => $usuario_id,
                    "nome" => $usuario_nome
                ],

                "produto_final" => [
                    "codigo" => $product_codigo,
                    "nome" => $produtoFinal["nome"],
                    "und"  => $produtoFinal["und"] ?? null,
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

        // obrigatórios
        $required = ["system_unit_id", "items"];
        foreach ($required as $f) {
            if (!isset($data[$f]) || $data[$f] === "" || $data[$f] === []) {
                return ["success" => false, "message" => "O campo '$f' é obrigatório."];
            }
        }

        $system_unit_id = (int)$data["system_unit_id"];

        // usuário
        $usuario_id = $data["user"] ?? $data["usuario_id"] ?? null;
        if (!$usuario_id) {
            return ["success" => false, "message" => "O campo 'user' (ou 'usuario_id') é obrigatório."];
        }
        $usuario_id = (string)$usuario_id;

        $date_producao  = $data["date_producao"] ?? $data["data"] ?? date("Y-m-d");
        $allow_negative = isset($data["allow_negative"]) ? (bool)$data["allow_negative"] : false;

        if ($system_unit_id <= 0) return ["success" => false, "message" => "system_unit_id inválido."];

        // items: [{ product_codigo, quantidade_produzida }]
        $items = $data["items"];
        if (!is_array($items) || count($items) === 0) {
            return ["success" => false, "message" => "items deve ser um array com pelo menos 1 item."];
        }

        // normaliza e valida itens
        $itensNorm = [];
        foreach ($items as $idx => $it) {
            $product_codigo = (int)($it["product_codigo"] ?? 0);

            $q = $it["quantidade_produzida"] ?? $it["quantidade"] ?? $it["qtd"] ?? null;
            if ($q === null || $q === "") $qtdProduzida = 0;
            else $qtdProduzida = (float)str_replace(",", ".", (string)$q);

            if ($product_codigo <= 0) {
                return ["success" => false, "message" => "Item #".($idx+1).": product_codigo inválido."];
            }
            if ($qtdProduzida <= 0) {
                return ["success" => false, "message" => "Item #".($idx+1).": quantidade_produzida deve ser > 0."];
            }

            $itensNorm[] = [
                "product_codigo" => $product_codigo,
                "quantidade_produzida" => (float)round($qtdProduzida, 4),
            ];
        }

        try {
            $pdo->beginTransaction();

            // --- pega nome da unidade e usuário (pro retorno)
            $stmtUnit = $pdo->prepare("SELECT id, name AS nome FROM system_unit WHERE id = :id LIMIT 1");
            $stmtUnit->execute([":id" => $system_unit_id]);
            $unidadeRow = $stmtUnit->fetch(PDO::FETCH_ASSOC);

            $stmtUser = $pdo->prepare("SELECT id, name AS nome FROM system_users WHERE id = :id LIMIT 1");
            $stmtUser->execute([":id" => $usuario_id]);
            $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

            $unidadeRet = [
                "id" => $system_unit_id,
                "nome" => $unidadeRow["nome"] ?? ("Unidade ".$system_unit_id),
            ];

            $usuarioRet = [
                "id" => (string)$usuario_id,
                "nome" => $userRow["nome"] ?? ("Usuário ".$usuario_id),
            ];

            // 1) gera UM doc (pr-000001) para tudo
            $ultimoDoc = MovimentacaoController::getLastMov($system_unit_id, "pr");
            $doc = MovimentacaoController::incrementDoc($ultimoDoc, "pr");

            $tipo = "pr";
            $status = 1;
            $seq = 1;

            // 2) statements
            $stmtInsMov = $pdo->prepare("
            INSERT INTO movimentacao
                (system_unit_id, system_unit_id_destino, status, doc, tipo, tipo_mov, produto, seq, data, data_original, quantidade, usuario_id)
            VALUES
                (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

            $stmtUpdSaldoByCodigo = $pdo->prepare("
            UPDATE products
               SET saldo = COALESCE(saldo,0) + :delta,
                   ultimo_doc = :doc,
                   updated_at = CURRENT_TIMESTAMP
             WHERE system_unit_id = :unit
               AND codigo = :codigo
        ");

            // 3) processa cada produto da lista
            $produtosResumo = [];

            foreach ($itensNorm as $it) {
                $product_codigo = (int)$it["product_codigo"];
                $qtdProduzida   = (float)$it["quantidade_produzida"];

                // 3.1) busca produto final por CODIGO (FOR UPDATE)
                $stmtProd = $pdo->prepare("
                SELECT codigo, nome, und, COALESCE(saldo,0) AS saldo
                  FROM products
                 WHERE system_unit_id = :unit
                   AND codigo = :codigo
                 FOR UPDATE
            ");
                $stmtProd->execute([":unit" => $system_unit_id, ":codigo" => $product_codigo]);
                $produtoFinal = $stmtProd->fetch(PDO::FETCH_ASSOC);

                if (!$produtoFinal) {
                    $pdo->rollBack();
                    return ["success" => false, "message" => "Produto final não encontrado (codigo={$product_codigo}) nessa unidade."];
                }

                // 3.2) ficha pelo CODIGO do produto (FOR UPDATE)
                $stmtFicha = $pdo->prepare("
                SELECT
                    p.insumo_id,                           -- CODIGO do insumo (products.codigo)
                    p.quantity AS ficha_qtd,
                    COALESCE(p.rendimento, NULL) AS rendimento,
                    pr.codigo AS insumo_codigo,
                    pr.nome   AS insumo_nome,
                    pr.und AS insumo_und,
                    COALESCE(pr.saldo,0) AS insumo_saldo
                FROM productions p
                INNER JOIN products pr
                    ON pr.codigo = p.insumo_id
                   AND pr.system_unit_id = p.system_unit_id
                WHERE p.system_unit_id = :unit
                  AND p.product_id = :product_codigo       -- ✅ product_id = CODIGO do produto
                FOR UPDATE
            ");
                $stmtFicha->execute([":unit" => $system_unit_id, ":product_codigo" => $product_codigo]);
                $ficha = $stmtFicha->fetchAll(PDO::FETCH_ASSOC);

                if (!$ficha || count($ficha) === 0) {
                    $pdo->rollBack();
                    return ["success" => false, "message" => "Ficha não encontrada em productions para esse produto (codigo={$product_codigo})."];
                }

                $insumosResumo = [];

                // 3.3) SAÍDA dos insumos
                foreach ($ficha as $row) {
                    $insumoCodigo = (int)$row["insumo_codigo"];
                    $insumoNome   = (string)$row["insumo_nome"];
                    $insumoUnd    = (string)($row["insumo_und"] ?? "");

                    $fichaQtd = (float)$row["ficha_qtd"];
                    $rend     = $row["rendimento"] !== null ? (float)$row["rendimento"] : null;

                    $rendimento = ($rend !== null && $rend > 0) ? $rend : 1;

                    // consumo proporcional
                    $consumo = $fichaQtd * ($qtdProduzida / $rendimento);
                    $consumo = (float)round($consumo, 4);

                    $saldoAtual = (float)$row["insumo_saldo"];

                    if (!$allow_negative && $saldoAtual < $consumo) {
                        $pdo->rollBack();
                        return [
                            "success" => false,
                            "message" => "Saldo insuficiente no insumo {$insumoNome} (código {$insumoCodigo}). Saldo: {$saldoAtual} | Consumo: {$consumo}"
                        ];
                    }

                    // movimentacao: SAIDA do insumo
                    $stmtInsMov->execute([
                        $system_unit_id,
                        $status,
                        $doc,
                        $tipo,
                        "saida",
                        $insumoCodigo,
                        $seq,
                        $date_producao,
                        $date_producao,
                        $consumo,
                        $usuario_id
                    ]);
                    $seq++;

                    // saldo insumo -= consumo
                    $stmtUpdSaldoByCodigo->execute([
                        ":delta" => -1 * $consumo,
                        ":doc"   => $doc,
                        ":unit"  => $system_unit_id,
                        ":codigo"=> $insumoCodigo
                    ]);

                    $insumosResumo[] = [
                        "codigo" => $insumoCodigo,
                        "nome" => $insumoNome,
                        "und" => $insumoUnd,
                        "ficha" => (float)round($fichaQtd, 4),
                        "rendimento" => (float)round($rendimento, 4),
                        "consumo" => (float)round($consumo, 4),
                    ];
                }

                // 3.4) ENTRADA do produto final
                $stmtInsMov->execute([
                    $system_unit_id,
                    $status,
                    $doc,
                    $tipo,
                    "entrada",
                    $product_codigo,
                    $seq,
                    $date_producao,
                    $date_producao,
                    (float)round($qtdProduzida, 4),
                    $usuario_id
                ]);
                $seq++;

                // saldo produto final += qtdProduzida
                $stmtUpdSaldoByCodigo->execute([
                    ":delta" => (float)round($qtdProduzida, 4),
                    ":doc"   => $doc,
                    ":unit"  => $system_unit_id,
                    ":codigo"=> $product_codigo
                ]);

                $produtosResumo[] = [
                    "codigo" => (int)$product_codigo,
                    "nome" => (string)$produtoFinal["nome"],
                    "und" => (string)($produtoFinal["unidade"] ?? ""),
                    "quantidade_produzida" => (float)round($qtdProduzida, 4),
                    "insumos" => $insumosResumo
                ];
            }

            $pdo->commit();

            return [
                "success" => true,
                "message" => "Produção em massa executada com sucesso!",
                "doc" => $doc,
                "data_producao" => $date_producao,
                "hora_execucao" => date("H:i"),
                "unidade" => $unidadeRet,
                "usuario" => $usuarioRet,
                "produtos" => $produtosResumo
            ];

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            return ["success" => false, "message" => "Erro ao executar produção em massa: " . $e->getMessage()];
        }
    }





}
?>
