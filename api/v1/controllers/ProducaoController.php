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
                ins.nome AS insumo_nome
            FROM productions p
            JOIN products prod ON prod.codigo = p.product_id AND prod.system_unit_id = p.system_unit_id
            JOIN products ins ON ins.codigo = p.insumo_id AND ins.system_unit_id = p.system_unit_id
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
                        'produto' => $product_id,
                        'nome' => $row['produto_nome'],
                        'insumos' => []
                    ];
                }

                $isProduzido = in_array($row['insumo_id'], $produzidos) ? 1 : 0;

                $producoesAgrupadas[$product_id]['insumos'][] = [
                    'insumo_id' => $row['insumo_id'],
                    'nome' => $row['insumo_nome'],
                    'quantity' => (float)$row['quantity'],
                    'rendimento' => isset($row['rendimento']) ? (float)$row['rendimento'] : null,
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



}
?>
