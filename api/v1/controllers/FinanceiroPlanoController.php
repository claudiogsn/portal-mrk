<?php

require_once __DIR__ . '/../database/db.php';

class FinanceiroPlanoController {
    public static function createPlano($data) {
        global $pdo;

        $stmt = $pdo->prepare("INSERT INTO financeiro_plano (codigo, descricao) VALUES (?, ?)");
        $stmt->execute([
            $data['codigo'],
            $data['descricao']
        ]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Plano criado com sucesso', 'plano_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar plano');
        }
    }

    public static function updatePlano($id, $data) {
        global $pdo;

        $sql = "UPDATE financeiro_plano SET ";
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
            return array('success' => true, 'message' => 'Plano atualizado com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao atualizar plano');
        }
    }

    public static function getPlanoById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM financeiro_plano WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deletePlano($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM financeiro_plano WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Plano excluído com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir plano');
        }
    }

    public static function listPlanos() {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM financeiro_plano");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function importarPlanosApi($system_unit_id) {
        global $pdo;

        try {
            // Obtém o custom_code a partir do system_unit_id
            $stmt = $pdo->prepare("SELECT custom_code AS estabelecimento FROM system_unit WHERE id = :id");
            $stmt->bindParam(':id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                throw new Exception("System Unit ID inválido ou não encontrado.");
            }

            $estabelecimento = $result['estabelecimento'];

            // Chama o método da API para buscar os planos
            $planos = FinanceiroApiMenewController::fetchFinanceiroPlano($estabelecimento);

            if (!$planos['success']) {
                throw new Exception("Erro ao buscar planos da API: " . $planos['message']);
            }

            foreach ($planos['planos'] as $plano) {
                $stmt = $pdo->prepare("INSERT INTO financeiro_plano (codigo, descricao) 
                                        VALUES (?, ?) 
                                        ON DUPLICATE KEY UPDATE 
                                            descricao = VALUES(descricao)");

                $stmt->execute([
                    $plano['codigo'],
                    $plano['descricao']
                ]);
            }

            return ["success" => true, "message" => "Planos importados com sucesso"];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }
}
