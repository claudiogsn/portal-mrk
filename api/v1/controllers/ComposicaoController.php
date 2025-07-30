<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class ComposicaoController {

    public static function createComposicao($data) {
        global $pdo;

        $product_id = $data['product_id'];
        $insumo_id = $data['insumo_id'];
        $quantity = $data['quantity'];
        $system_unit_id = $data['system_unit_id'];

        $stmt = $pdo->prepare("INSERT INTO compositions (product_id, insumo_id, quantity, system_unit_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $insumo_id, $quantity, $system_unit_id]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Composição criada com sucesso', 'composicao_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar composição');
        }
    }

    public static function updateComposicao($id, $data) {
        global $pdo;

        $sql = "UPDATE compositions SET ";
        $values = [];
        foreach ($data as $key => $value) {
            $sql .= "$key = :$key, ";
            $values[":$key"] = $value;
        }
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE id = :id";
        $values[':id'] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Detalhes da composição atualizados com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar detalhes da composição');
        }
    }

    public static function getComposicaoById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM compositions WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteComposicao($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM compositions WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Composição excluída com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir composição');
        }
    }

    public static function listComposicoes($system_unit_id) {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM compositions WHERE system_unit_id = $system_unit_id");
            $composicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'composicoes' => $composicoes];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar composições: ' . $e->getMessage()];
        }
    }

    public static function listProdutosComComposicaoStatus($system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("
        SELECT 
            p.codigo, p.nome, p.composicao,
            CASE WHEN EXISTS (
                SELECT 1 FROM compositions c WHERE c.product_id = p.codigo AND c.system_unit_id = p.system_unit_id
            ) THEN 1 ELSE 0 END AS tem_ficha
        FROM products p
        WHERE p.system_unit_id = :unit AND p.codigo < 9999 AND p.composicao = 1
        ORDER BY p.nome
    ");
        $stmt->execute(['unit' => $system_unit_id]);
        return ['success' => true, 'produtos' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public static function getComposicaoByProduto($product_id, $system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("
        SELECT p.codigo AS insumo_id, p.nome AS insumo_nome, c.quantity
        FROM compositions c
        JOIN products p ON p.codigo = c.insumo_id AND p.system_unit_id = c.system_unit_id
        WHERE c.product_id = :product_id AND c.system_unit_id = :system_unit_id
    ");
        $stmt->execute([
            'product_id' => $product_id,
            'system_unit_id' => $system_unit_id
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function saveComposition($data) {
        global $pdo;

        $product_id = $data['product_id'];
        $system_unit_id = $data['system_unit_id'];
        $insumos = $data['insumos'];

        try {
            $pdo->beginTransaction();

            // Deleta as antigas
            $pdo->prepare("DELETE FROM compositions WHERE product_id = ? AND system_unit_id = ?")
                ->execute([$product_id, $system_unit_id]);

            // Insere as novas
            $stmt = $pdo->prepare("
            INSERT INTO compositions (product_id, insumo_id, quantity, system_unit_id)
            VALUES (?, ?, ?, ?)
        ");

            foreach ($insumos as $insumo) {
                $stmt->execute([
                    $product_id,
                    $insumo['insumo_id'],
                    $insumo['quantity'],
                    $system_unit_id
                ]);
            }

            $pdo->commit();
            return ['success' => true, 'message' => 'Composição salva com sucesso.'];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }




    public static function listFichaTecnica($product_codigo, $system_unit_id) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
            SELECT p.codigo AS insumo_codigo, p.nome AS insumo_name, c.quantity
            FROM products p
            JOIN compositions c ON p.codigo = c.insumo_id
            WHERE c.product_id = :product_codigo AND p.system_unit_id = :system_unit_id AND p.insumo = 1
        ");
            $stmt->bindParam(':product_codigo', $product_codigo, PDO::PARAM_INT);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();

            $composicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'composicoes' => $composicoes];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar ficha técnica: ' . $e->getMessage()];
        }
    }

    public static function importCompositions(int $system_unit_id, array $itens): array
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            // Remove todas as composições antigas desse system_unit_id
            $pdo->prepare("DELETE FROM compositions WHERE system_unit_id = ?")
                ->execute([$system_unit_id]);

            // Insere as novas composições
            $insertQuery = "
            INSERT INTO compositions (
                product_id, insumo_id, quantity, system_unit_id
            ) VALUES (?, ?, ?, ?)
        ";
            $insertStmt = $pdo->prepare($insertQuery);

            foreach ($itens as $item) {
                $productId = (int) $item['codigo'];

                foreach ($item['insumos'] as $insumo) {
                    $insumoId = (int) $insumo['codigo'];
                    $quantidade = (float) str_replace(',', '.', $insumo['quantidade']);

                    $insertStmt->execute([
                        $productId,
                        $insumoId,
                        $quantidade,
                        $system_unit_id
                    ]);
                }
            }

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Composições substituídas com sucesso.'
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Erro ao salvar composições: ' . $e->getMessage()
            ];
        }
    }

    public static function importCompositionsZig($system_unit_id, $itens)
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            // Apagar composições existentes da loja
            $stmtDel = $pdo->prepare("DELETE FROM compositions WHERE system_unit_id = :unit_id");
            $stmtDel->execute([':unit_id' => $system_unit_id]);

            $stmtFindProduct = $pdo->prepare("
            SELECT codigo AS product_id 
            FROM products 
            WHERE system_unit_id = :unit_id AND sku_zig = :sku_zig
            LIMIT 1
        ");

            $stmtFindInsumo = $pdo->prepare("
            SELECT codigo AS insumo_id 
            FROM products 
            WHERE system_unit_id = :unit_id AND nome = :nome
            LIMIT 1
        ");

            $stmtInsert = $pdo->prepare("
            INSERT INTO compositions (product_id, insumo_id, quantity, system_unit_id)
            VALUES (:product_id, :insumo_id, :quantity, :system_unit_id)
        ");

            $erros_sku = [];
            $erros_insumo = [];
            $total_inseridos = 0;

            foreach ($itens as $item) {
                $sku_zig = trim($item['sku_zig'] ?? '');
                $insumo_nome = trim($item['insumo'] ?? '');
                $quantidade = floatval($item['quantidade']);

                if (!$sku_zig || !$insumo_nome || $quantidade <= 0) continue;

                // Buscar product_id pelo SKU
                $stmtFindProduct->execute([
                    ':unit_id' => $system_unit_id,
                    ':sku_zig' => $sku_zig
                ]);
                $product = $stmtFindProduct->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    $erros_sku[] = $sku_zig;
                    continue;
                }

                // Buscar insumo_id pelo nome do insumo
                $stmtFindInsumo->execute([
                    ':unit_id' => $system_unit_id,
                    ':nome' => $insumo_nome
                ]);
                $insumo = $stmtFindInsumo->fetch(PDO::FETCH_ASSOC);
                if (!$insumo) {
                    $erros_insumo[] = [
                        'sku_zig' => $sku_zig,
                        'insumo' => $insumo_nome
                    ];
                    continue;
                }

                // Inserir composição
                $stmtInsert->execute([
                    ':product_id' => $product['product_id'],
                    ':insumo_id' => $insumo['insumo_id'],
                    ':quantity' => $quantidade,
                    ':system_unit_id' => $system_unit_id
                ]);

                $total_inseridos++;
            }

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Importação concluída.',
                'total_inseridos' => $total_inseridos,
                'erros_sku_nao_encontrado' => array_unique($erros_sku),
                'erros_insumo_nao_encontrado' => $erros_insumo
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ];
        }
    }


    public static function importProductions(int $system_unit_id, array $itens): array
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            // Remove todas as produções antigas desse system_unit_id
            $pdo->prepare("DELETE FROM productions WHERE system_unit_id = ?")
                ->execute([$system_unit_id]);

            // Insere as novas produções
            $insertQuery = "
            INSERT INTO productions (
                product_id, insumo_id, quantity, rendimento, system_unit_id
            ) VALUES (?, ?, ?, ?, ?)
        ";
            $insertStmt = $pdo->prepare($insertQuery);

            $productIds = [];

            foreach ($itens as $item) {
                $productId = (int) $item['codigo'];
                $rendimento = isset($item['rendimento']) ? (float) str_replace(',', '.', $item['rendimento']) : null;

                $productIds[] = $productId;

                foreach ($item['insumos'] as $insumo) {
                    $insumoId = (int) $insumo['codigo'];
                    $quantidade = (float) str_replace(',', '.', $insumo['quantidade']);

                    $insertStmt->execute([
                        $productId,
                        $insumoId,
                        $quantidade,
                        $rendimento,
                        $system_unit_id
                    ]);
                }
            }

            // Atualiza os produtos principais para não serem compráveis
            if (!empty($productIds)) {
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $updateQuery = "UPDATE products SET compravel = 0 WHERE system_unit_id = ? AND codigo IN ($placeholders)";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute(array_merge([$system_unit_id], $productIds));
            }

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Produções substituídas com sucesso.'
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Erro ao salvar produções: ' . $e->getMessage()
            ];
        }
    }

}
?>
