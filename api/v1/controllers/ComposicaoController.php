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


}
?>
