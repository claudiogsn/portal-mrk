<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class InsumoController {

    public static function createInsumo($data) {
        global $pdo;

        $name = $data['name'];
        $description = $data['description'];
        $system_unit_id = $data['system_unit_id'];

        $stmt = $pdo->prepare("INSERT INTO insumos (name, description, system_unit_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $system_unit_id]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Insumo criado com sucesso', 'insumo_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar insumo');
        }
    }

    public static function updateInsumo($id, $data) {
        global $pdo;

        $sql = "UPDATE insumos SET ";
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
            return array('success' => true, 'message' => 'Detalhes do insumo atualizados com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar detalhes do insumo');
        }
    }

    public static function getInsumoById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM insumos WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteInsumo($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM insumos WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Insumo excluído com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir insumo');
        }
    }

    public static function listInsumos($system_unit_id) {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM insumos WHERE system_unit_id = $system_unit_id");
            $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'insumos' => $insumos];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar insumos: ' . $e->getMessage()];
        }
    }
}
?>
