<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class NecessidadesController {

    public static function createNecessidade($data) {
        global $pdo;

        $insumo_id = $data['insumo_id'];
        $estimated_need = $data['estimated_need'];
        $sobras = $data['sobras'];
        $date = $data['date'];
        $unit_id = $data['unit_id'];

        $stmt = $pdo->prepare("
            INSERT INTO necessidades (insumo_id, estimated_need, sobras, date, unit_id)
            VALUES (:insumo_id, :estimated_need, :sobras, :date, :unit_id)
        ");
        $stmt->bindParam(':insumo_id', $insumo_id);
        $stmt->bindParam(':estimated_need', $estimated_need);
        $stmt->bindParam(':sobras', $sobras);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':unit_id', $unit_id);

        if ($stmt->execute()) {
            return ['success' => 'Necessidade criada com sucesso.'];
        } else {
            throw new Exception('Erro ao criar necessidade.');
        }
    }

    public static function updateNecessidade($id, $data) {
        global $pdo;

        $insumo_id = $data['insumo_id'];
        $estimated_need = $data['estimated_need'];
        $sobras = $data['sobras'];
        $date = $data['date'];
        $unit_id = $data['unit_id'];

        $stmt = $pdo->prepare("
            UPDATE necessidades
            SET insumo_id = :insumo_id, estimated_need = :estimated_need, sobras = :sobras, date = :date, unit_id = :unit_id
            WHERE id = :id
        ");
        $stmt->bindParam(':insumo_id', $insumo_id);
        $stmt->bindParam(':estimated_need', $estimated_need);
        $stmt->bindParam(':sobras', $sobras);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':unit_id', $unit_id);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return ['success' => 'Necessidade atualizada com sucesso.'];
        } else {
            throw new Exception('Erro ao atualizar necessidade.');
        }
    }

    public static function getNecessidadeById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM necessidades WHERE id = :id");
        $stmt->bindParam(':id', $id);

        $stmt->execute();
        $necessidade = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($necessidade) {
            return $necessidade;
        } else {
            throw new Exception('Necessidade não encontrada.');
        }
    }

    public static function listNecessidades($unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT * FROM necessidades WHERE unit_id = :unit_id
        ");
        $stmt->bindParam(':unit_id', $unit_id);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
