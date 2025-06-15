<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class ProductionController {

    public static function createProduction($data) {
        global $pdo;

        $product_id = $data['product_id'];
        $quantity_produced = $data['quantity_produced'];
        $production_date = $data['production_date'];
        $unit_id = $data['unit_id'];

        $stmt = $pdo->prepare("INSERT INTO productions (product_id, quantity_produced, production_date, unit_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $quantity_produced, $production_date, $unit_id]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Produção criada com sucesso', 'production_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar produção');
        }
    }

    public static function updateProduction($id, $data) {
        global $pdo;

        $sql = "UPDATE productions SET ";
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
            return array('success' => true, 'message' => 'Detalhes da produção atualizados com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar detalhes da produção');
        }
    }

    public static function getProductionById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM productions WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteProduction($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM productions WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Produção excluída com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir produção');
        }
    }

    public static function listProductions($unit_id) {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM productions WHERE unit_id = $unit_id");
            $productions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'productions' => $productions];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar produções: ' . $e->getMessage()];
        }
    }

    public static function updateCompravel($system_unit_id, $itens): array
    {
        global $pdo;

        if (!is_array($itens)) {
            return ['success' => false, 'message' => 'Parâmetro "itens" deve ser um array'];
        }

        $updated = [];

        try {
            $stmt = $pdo->prepare("
            UPDATE products 
            SET compravel = :compravel 
            WHERE system_unit_id = :unit_id AND codigo = :codigo
        ");

            foreach ($itens as $item) {
                if (!isset($item['codigo_produto'], $item['compravel'])) continue;

                $stmt->execute([
                    ':unit_id' => $system_unit_id,
                    ':codigo' => $item['codigo_produto'],
                    ':compravel' => (int)$item['compravel']
                ]);

                $updated[] = $item['codigo_produto'];
            }

            return [
                'success' => true,
                'message' => 'Atualização concluída.',
                'atualizados' => $updated
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ];
        }
    }

    public static function listProdutosCompraveis($system_unit_id): array
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
            SELECT codigo, nome, compravel 
            FROM products 
            WHERE system_unit_id = :unit_id
            and codigo >= 10000
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
                'message' => 'Erro ao listar produtos: ' . $e->getMessage()
            ];
        }
    }
    public static function listProdutosComFichaStatus($system_unit_id): array
    {
        global $pdo;

        try {
            // Busca todos os produtos
            $stmt = $pdo->prepare("
            SELECT p.codigo, p.nome, p.compravel,
                EXISTS (
                    SELECT 1
                    FROM productions f
                    WHERE f.system_unit_id = p.system_unit_id AND f.product_id = p.codigo
                    LIMIT 1
                ) AS tem_ficha
            FROM products p
            WHERE p.system_unit_id = :unit_id
              AND p.codigo >= 10000
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
                'message' => 'Erro ao listar produtos: ' . $e->getMessage()
            ];
        }
    }
    public static function listProdutosComFicha($system_unit_id) {
        global $pdo;
        $stmt = $pdo->prepare("
        SELECT DISTINCT p.codigo, p.nome
        FROM products p
        INNER JOIN productions f ON f.product_id = p.codigo AND f.system_unit_id = p.system_unit_id
        WHERE p.system_unit_id = ? AND p.codigo >= 10000
        ORDER BY p.nome
    ");
        $stmt->execute([$system_unit_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getFichaTecnica($system_unit_id, $codigo_produto) {
        global $pdo;
        $stmt = $pdo->prepare("
        SELECT f.id, f.insumo_id, i.nome AS insumo_nome, f.quantity, f.rendimento
        FROM productions f
        INNER JOIN products i ON i.codigo = f.insumo_id AND i.system_unit_id = f.system_unit_id
        WHERE f.system_unit_id = ? AND f.product_id = ?
        ORDER BY i.nome
    ");
        $stmt->execute([$system_unit_id, $codigo_produto]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function saveFichaTecnica($system_unit_id, $codigo_produto, $insumos) {
        global $pdo;
        $pdo->beginTransaction();

        // Remove ficha antiga
        $del = $pdo->prepare("DELETE FROM productions WHERE system_unit_id = ? AND product_id = ?");
        $del->execute([$system_unit_id, $codigo_produto]);

        // Insere nova ficha
        $insert = $pdo->prepare("
        INSERT INTO productions (system_unit_id, product_id, insumo_id, quantity, rendimento)
        VALUES (?, ?, ?, ?, ?)
    ");

        foreach ($insumos as $insumo) {
            $insert->execute([
                $system_unit_id,
                $codigo_produto,
                $insumo['insumo_id'],
                $insumo['quantity'],
                $insumo['rendimento'] ?? 1
            ]);
        }

        $pdo->commit();
        return ['success' => true];
    }
    public static function listInsumosDisponiveis($system_unit_id) {
        global $pdo;
        $stmt = $pdo->prepare("
        SELECT codigo, nome
        FROM products
        WHERE system_unit_id = ? AND codigo >= 10000 AND insumo = 1
        ORDER BY nome
    ");
        $stmt->execute([$system_unit_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }







}
?>
