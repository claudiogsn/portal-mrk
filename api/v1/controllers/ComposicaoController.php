<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class ComposicaoController {

    public static function createComposicao($data) {
        global $pdo;

        $product_id = $data['product_id'];
        $insumo_id = $data['insumo_id'];
        $quantity = $data['quantity'];
        $unit_id = $data['unit_id'];

        $stmt = $pdo->prepare("INSERT INTO compositions (product_id, insumo_id, quantity, unit_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $insumo_id, $quantity, $unit_id]);

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

    public static function listComposicoes($unit_id) {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM compositions WHERE unit_id = $unit_id");
            $composicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'composicoes' => $composicoes];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar composições: ' . $e->getMessage()];
        }
    }
}
?>
