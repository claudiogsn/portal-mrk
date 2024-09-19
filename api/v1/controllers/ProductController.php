<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class ProductController {

    public static function createProduct($data) {
        global $pdo;

        $name = $data['name'];
        $description = $data['description'];
        $system_unit_id = $data['system_unit_id'];

        $stmt = $pdo->prepare("INSERT INTO products (name, description, system_unit_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $system_unit_id]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Produto criado com sucesso', 'product_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar produto');
        }
    }

    public static function updateProduct($id, $data) {
        global $pdo;

        $sql = "UPDATE products SET ";
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
            return array('success' => true, 'message' => 'Detalhes do produto atualizados com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar detalhes do produto');
        }
    }

    public static function getProductById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteProduct($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Produto excluído com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir produto');
        }
    }

    public static function listProducts($system_unit_id) {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM products WHERE system_unit_id = $system_unit_id");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'products' => $products];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar produtos: ' . $e->getMessage()];
        }
    }
}
?>
