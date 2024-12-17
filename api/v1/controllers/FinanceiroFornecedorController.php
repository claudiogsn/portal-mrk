<?php

require_once __DIR__ . '/../database/db.php';

class FinanceiroFornecedorController {
    public static function createFornecedor($data) {
        global $pdo;

        $stmt = $pdo->prepare("INSERT INTO financeiro_fornecedor (system_unit_id, codigo, razao, nome, cnpj_cpf, plano_contas, endereco, cep, insc_estadual, fone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['system_unit_id'],
            $data['codigo'],
            $data['razao'],
            $data['nome'],
            $data['cnpj_cpf'],
            $data['plano_contas'],
            $data['endereco'],
            $data['cep'],
            $data['insc_estadual'],
            $data['fone']
        ]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Fornecedor criado com sucesso', 'fornecedor_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar fornecedor');
        }
    }

    public static function updateFornecedor($id, $data) {
        global $pdo;

        $sql = "UPDATE financeiro_fornecedor SET ";
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
            return array('success' => true, 'message' => 'Fornecedor atualizado com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao atualizar fornecedor');
        }
    }

    public static function getFornecedorById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM financeiro_fornecedor WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteFornecedor($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM financeiro_fornecedor WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Fornecedor excluído com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir fornecedor');
        }
    }

    public static function listFornecedores() {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM financeiro_fornecedor");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}