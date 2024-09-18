<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class SalesController {

    public static function createSale($data) {
        global $pdo;

        $insumo_id = $data['insumo_id'];
        $quantity_sold = $data['quantity_sold'];
        $sale_date = $data['sale_date'];
        $unit_id = $data['unit_id'];

        $stmt = $pdo->prepare("INSERT INTO sales (insumo_id, quantity_sold, sale_date, unit_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$insumo_id, $quantity_sold, $sale_date, $unit_id]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Venda criada com sucesso', 'sale_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar venda');
        }
    }

    public static function updateSale($id, $data) {
        global $pdo;

        $sql = "UPDATE sales SET ";
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
            return array('success' => true, 'message' => 'Detalhes da venda atualizados com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar detalhes da venda');
        }
    }

    public static function getSaleById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteSale($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM sales WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Venda excluída com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir venda');
        }
    }

    public static function listSales($unit_id) {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM sales WHERE unit_id = $unit_id");
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'sales' => $sales];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar vendas: ' . $e->getMessage()];
        }
    }
}
?>
