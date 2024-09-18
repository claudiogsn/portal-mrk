<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class TransfersController {

    public static function createTransfer($data) {
        global $pdo;

        $from_unit_id = $data['from_unit_id'];
        $to_unit_id = $data['to_unit_id'];
        $insumo_id = $data['insumo_id'];
        $quantity = $data['quantity'];
        $transfer_date = $data['transfer_date'];

        $stmt = $pdo->prepare("INSERT INTO transfers (from_unit_id, to_unit_id, insumo_id, quantity, transfer_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$from_unit_id, $to_unit_id, $insumo_id, $quantity, $transfer_date]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Transferência criada com sucesso', 'transfer_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar transferência');
        }
    }

    public static function updateTransfer($id, $data) {
        global $pdo;

        $sql = "UPDATE transfers SET ";
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
            return array('success' => true, 'message' => 'Detalhes da transferência atualizados com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar detalhes da transferência');
        }
    }

    public static function getTransferById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteTransfer($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM transfers WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Transferência excluída com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir transferência');
        }
    }

    public static function listTransfers($unit_id) {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM transfers WHERE from_unit_id = $unit_id OR to_unit_id = $unit_id");
            $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'transfers' => $transfers];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar transferências: ' . $e->getMessage()];
        }
    }
}
?>
