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
}
?>
