<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class CategoriesController {

    public static function createCategoria($data) {
        global $pdo;

        $codigo = $data['codigo'];
        $nome = $data['nome'];
        $system_unit_id = $data['system_unit_id'];

        $stmt = $pdo->prepare("INSERT INTO categorias (codigo, nome, system_unit_id) VALUES (?, ?, ?)");
        $stmt->execute([$codigo, $nome, $system_unit_id]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Categoria criada com sucesso', 'categoria_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar categoria');
        }
    }

    public static function updateCategoria($id, $data, $system_unit_id) {
        global $pdo;

        $sql = "UPDATE categorias SET ";
        $values = [];
        foreach ($data as $key => $value) {
            $sql .= "$key = :$key, ";
            $values[":$key"] = $value;
        }
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE id = :id AND system_unit_id = :system_unit_id";
        $values[':id'] = $id;
        $values[':system_unit_id'] = $system_unit_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Detalhes da categoria atualizados com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar detalhes da categoria');
        }
    }

    public static function getCategoriaById($id, $system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = :id AND system_unit_id = :system_unit_id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteCategoria($id, $system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = :id AND system_unit_id = :system_unit_id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Categoria excluída com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir categoria');
        }
    }

    public static function listCategorias($system_unit_id) {
        try {
            global $pdo;

            $sql = "
            SELECT * 
            FROM categorias 
            WHERE system_unit_id = :system_unit_id
            ORDER BY nome ASC
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'categorias' => $categorias];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar categorias: ' . $e->getMessage()];
        }
    }

}
?>
