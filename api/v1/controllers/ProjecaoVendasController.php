<?php

require_once __DIR__ . '/../database/db.php';

class ProjecaoVendasController
{
    /**
     * RETORNA A GRID PARA O FRONTEND
     * Retorna: [ 10104 => ['segunda' => ['id'=>1, 'qty'=>5], 'terca' => ...], ... ]
     * Onde 10104 é o CÓDIGO do produto.
     */
    public static function getGrid($system_unit_id, $productCodigos = [])
    {
        global $pdo;

        $sql = "SELECT id, product_codigo, day_of_week, quantity 
                FROM product_daily_projections 
                WHERE system_unit_id = ? 
                AND deleted_at IS NULL";

        $params = [$system_unit_id];

        if (!empty($productCodigos)) {
            // Cria placeholders ?,?,? baseado na quantidade de códigos
            $placeholders = implode(',', array_fill(0, count($productCodigos), '?'));
            $sql .= " AND product_codigo IN ($placeholders)";
            $params = array_merge($params, $productCodigos);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organiza array usando o CÓDIGO como chave
        $grid = [];
        foreach ($rows as $row) {
            $codigo = $row['product_codigo'];
            $dia    = $row['day_of_week'];

            if (!isset($grid[$codigo])) {
                $grid[$codigo] = [];
            }

            $grid[$codigo][$dia] = [
                'id'       => $row['id'],
                'quantity' => (float)$row['quantity']
            ];
        }

        return $grid;
    }

    /**
     * SALVAR EM MASSA (CRIAR OU EDITAR)
     * Transação segura para inserção de múltiplos registros.
     * Espera formato:
     * [
     * ['product_codigo' => 10104, 'day' => 'segunda', 'qty' => 10],
     * ...
     * ]
     */
    public static function saveBatch($system_unit_id, array $dados): array
    {
        global $pdo;

        if (empty($dados)) {
            return ['success' => false, 'message' => 'Nenhum dado enviado.'];
        }

        try {
            $pdo->beginTransaction();

            // UPSERT usando product_codigo
            $sql = "INSERT INTO product_daily_projections 
                    (system_unit_id, product_codigo, day_of_week, quantity, deleted_at) 
                    VALUES (:unit, :codigo, :day, :qtd, NULL)
                    ON DUPLICATE KEY UPDATE 
                        quantity = VALUES(quantity),
                        deleted_at = NULL,
                        updated_at = NOW()";

            $stmt = $pdo->prepare($sql);

            foreach ($dados as $item) {
                // Valida se os campos existem (note o product_codigo)
                if (!isset($item['product_codigo'], $item['day'], $item['qty'])) {
                    continue;
                }

                $stmt->execute([
                    ':unit'   => $system_unit_id,
                    ':codigo' => $item['product_codigo'],
                    ':day'    => $item['day'],
                    ':qtd'    => $item['qty']
                ]);
            }

            $pdo->commit();
            return ['success' => true, 'message' => 'Projeções salvas com sucesso.'];

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("[PROJECAO_VENDAS] Erro Batch: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao salvar dados.'];
        }
    }

    /**
     * SALVAR UM ÚNICO ITEM
     * Wrapper para o saveBatch
     */
    public static function saveItem($system_unit_id, $product_codigo, $day_of_week, $quantity): array
    {
        return self::saveBatch($system_unit_id, [
            [
                'product_codigo' => $product_codigo,
                'day'            => $day_of_week,
                'qty'            => $quantity
            ]
        ]);
    }

    /**
     * SOFT DELETE PELO ID (PK da tabela projeção)
     * Esse método não muda, pois apaga pela ID da linha da projeção
     */
    public static function delete($id): array
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("UPDATE product_daily_projections SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Projeção removida.'];
            } else {
                return ['success' => false, 'message' => 'Item não encontrado.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao deletar: ' . $e->getMessage()];
        }
    }

    /**
     * LIMPAR PROJEÇÕES DE UM PRODUTO ESPECÍFICO (Pelo CÓDIGO)
     */
    public static function clearProductProjections($system_unit_id, $product_codigo): array
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("UPDATE product_daily_projections SET deleted_at = NOW() WHERE system_unit_id = ? AND product_codigo = ?");
            $stmt->execute([$system_unit_id, $product_codigo]);
            return ['success' => true, 'message' => 'Projeções do produto limpas.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao limpar produto.'];
        }
    }
}