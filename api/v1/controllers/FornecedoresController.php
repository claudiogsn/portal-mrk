<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class FornecedoresController {

    public static function createFornecedor($data) {
        global $pdo;

        $name = $data['name'];
        $contact_info = $data['contact_info'];

        $stmt = $pdo->prepare("INSERT INTO fornecedores (name, contact_info) VALUES (:name, :contact_info)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':contact_info', $contact_info);

        if ($stmt->execute()) {
            return ['success' => 'Fornecedor criado com sucesso.'];
        } else {
            throw new Exception('Erro ao criar fornecedor.');
        }
    }

    public static function updateFornecedor($id, $data) {
        global $pdo;

        $name = $data['name'];
        $contact_info = $data['contact_info'];

        $stmt = $pdo->prepare("UPDATE fornecedores SET name = :name, contact_info = :contact_info WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':contact_info', $contact_info);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return ['success' => 'Fornecedor atualizado com sucesso.'];
        } else {
            throw new Exception('Erro ao atualizar fornecedor.');
        }
    }

    public static function getFornecedorById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE id = :id");
        $stmt->bindParam(':id', $id);

        $stmt->execute();
        $fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fornecedor) {
            return $fornecedor;
        } else {
            throw new Exception('Fornecedor não encontrado.');
        }
    }

    public static function listFornecedores($unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM fornecedores_insumos WHERE unit_id = :unit_id");
        $stmt->bindParam(':unit_id', $unit_id);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
