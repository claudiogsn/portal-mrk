<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class EstoqueController {

    public static function createEstoque($data) {
        global $pdo;

        $insumo_id = $data['insumo_id'];
        $quantity_available = $data['quantity_available'];
        $last_updated = $data['last_updated'];
        $unit_id = $data['unit_id'];

        $stmt = $pdo->prepare("
            INSERT INTO estoque (insumo_id, quantity_available, last_updated, unit_id)
            VALUES (:insumo_id, :quantity_available, :last_updated, :unit_id)
        ");
        $stmt->bindParam(':insumo_id', $insumo_id);
        $stmt->bindParam(':quantity_available', $quantity_available);
        $stmt->bindParam(':last_updated', $last_updated);
        $stmt->bindParam(':unit_id', $unit_id);

        if ($stmt->execute()) {
            return ['success' => 'Estoque criado com sucesso.'];
        } else {
            throw new Exception('Erro ao criar estoque.');
        }
    }

    public static function updateEstoque($id, $data) {
        global $pdo;

        $insumo_id = $data['insumo_id'];
        $quantity_available = $data['quantity_available'];
        $last_updated = $data['last_updated'];
        $unit_id = $data['unit_id'];

        $stmt = $pdo->prepare("
            UPDATE estoque
            SET insumo_id = :insumo_id, quantity_available = :quantity_available, last_updated = :last_updated, unit_id = :unit_id
            WHERE id = :id
        ");
        $stmt->bindParam(':insumo_id', $insumo_id);
        $stmt->bindParam(':quantity_available', $quantity_available);
        $stmt->bindParam(':last_updated', $last_updated);
        $stmt->bindParam(':unit_id', $unit_id);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return ['success' => 'Estoque atualizado com sucesso.'];
        } else {
            throw new Exception('Erro ao atualizar estoque.');
        }
    }

    public static function getEstoqueById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM estoque WHERE id = :id");
        $stmt->bindParam(':id', $id);

        $stmt->execute();
        $estoque = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($estoque) {
            return $estoque;
        } else {
            throw new Exception('Estoque não encontrado.');
        }
    }

    public static function listEstoque($unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT * FROM estoque WHERE unit_id = :unit_id
        ");
        $stmt->bindParam(':unit_id', $unit_id);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

