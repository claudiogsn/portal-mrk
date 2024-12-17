<?php

require_once __DIR__ . '/../database/db.php';

class FinanceiroClienteController {
    public static function createCliente($data) {
        global $pdo;

        $stmt = $pdo->prepare("INSERT INTO financeiro_cliente (system_unit_id, codigo, razao, nome, cnpj_cpf, plano_contas) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['system_unit_id'],
            $data['codigo'],
            $data['razao'],
            $data['nome'],
            $data['cnpj_cpf'],
            $data['plano_contas']
        ]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Cliente criado com sucesso', 'cliente_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar cliente');
        }
    }

    public static function updateCliente($id, $data) {
        global $pdo;

        $sql = "UPDATE financeiro_cliente SET ";
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
            return array('success' => true, 'message' => 'Cliente atualizado com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao atualizar cliente');
        }
    }

    public static function getClienteById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM financeiro_cliente WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteCliente($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM financeiro_cliente WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Cliente excluído com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir cliente');
        }
    }

    public static function listClientes() {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM financeiro_cliente");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}