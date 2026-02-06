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

    /**
     * RETORNA LISTA DE PRODUTOS COM PROJEÇÕES FORMATADA
     * Formato: [ {codigo: 100, nome: 'Carne', segunda: 10, terca: 0...}, ... ]
     */
    public static function getProjeccoes($system_unit_id): array
    {
        global $pdo;

        // INNER JOIN garante que só retorna produtos que tenham registro na tabela de projeção
        $sql = "
            SELECT 
                p.codigo,
                p.nome,
                proj.day_of_week,
                proj.quantity
            FROM product_daily_projections proj
            INNER JOIN products p 
                ON p.codigo = proj.product_codigo 
                AND p.system_unit_id = proj.system_unit_id
            WHERE proj.system_unit_id = ? 
              AND proj.deleted_at IS NULL
            ORDER BY p.nome ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$system_unit_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];

        // Template para garantir que todos os dias existam no JSON final
        $diasPadrao = [
            'segunda' => 0,
            'terca'   => 0,
            'quarta'  => 0,
            'quinta'  => 0,
            'sexta'   => 0,
            'sabado'  => 0,
            'domingo' => 0
        ];

        foreach ($rows as $row) {
            $codigo = $row['codigo'];
            $dia    = $row['day_of_week'];
            $qtd    = (float)$row['quantity'];

            // Se o produto ainda não está no array, inicializa ele
            if (!isset($resultado[$codigo])) {
                $resultado[$codigo] = array_merge(
                    [
                        'codigo' => $codigo,
                        'nome'   => $row['nome']
                    ],
                    $diasPadrao // Adiciona os dias zerados
                );
            }

            // Atualiza o valor do dia específico que veio do banco
            // Se o dia não vier do banco, ele mantém o 0 do $diasPadrao
            if (array_key_exists($dia, $diasPadrao)) {
                $resultado[$codigo][$dia] = $qtd;
            }
        }

        // Retorna apenas os valores (remove as chaves associativas do código)
        return array_values($resultado);
    }
}