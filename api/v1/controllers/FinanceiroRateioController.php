<?php

require_once __DIR__ . '/../database/db.php';


class RateioController {
    public static function createRateio($data) {
        global $pdo;

        $stmt = $pdo->prepare("INSERT INTO financeiro_rateio (system_unit_id, idconta, nome, entidade, cgc, tipo, emissao, vencimento, baixa_dt, valor, plano_contas, rateio_doc, rateio_plano, rateio_valor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['system_unit_id'],
            $data['idconta'],
            $data['nome'],
            $data['entidade'],
            $data['cgc'],
            $data['tipo'],
            $data['emissao'],
            $data['vencimento'],
            $data['baixa_dt'],
            $data['valor'],
            $data['plano_contas'],
            $data['rateio_doc'],
            $data['rateio_plano'],
            $data['rateio_valor']
        ]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Rateio criado com sucesso', 'rateio_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar rateio');
        }
    }

    public static function updateRateio($id, $data) {
        global $pdo;

        $sql = "UPDATE financeiro_rateio SET ";
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
            return array('success' => true, 'message' => 'Rateio atualizado com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao atualizar rateio');
        }
    }

    public static function getRateioById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM financeiro_rateio WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteRateio($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM financeiro_rateio WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Rateio excluído com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir rateio');
        }
    }

    public static function listRateios() {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM financeiro_rateio");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
