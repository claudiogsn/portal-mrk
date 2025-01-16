<?php

ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');


require_once __DIR__ . '/../database/db.php';


class FinanceiroRateioController {
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

    public static function importarRateiosApi($system_unit_id) {
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

            // Chama o método da API para buscar os rateios
            $rateios = FinanceiroApiMenewController::fetchFinanceiroRateio($estabelecimento);

            if (!$rateios['success']) {
                throw new Exception("Erro ao buscar rateios da API: " . $rateios['message']);
            }

            foreach ($rateios['rateios'] as $rateio) {
                $stmt = $pdo->prepare("INSERT INTO financeiro_rateio (system_unit_id, idconta, nome, entidade, cgc, tipo, emissao, vencimento, baixa_dt, valor, plano_contas, rateio_doc, rateio_plano, rateio_valor) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                                            ON DUPLICATE KEY UPDATE 
                                                nome = VALUES(nome),
                                                entidade = VALUES(entidade),
                                                cgc = VALUES(cgc),
                                                tipo = VALUES(tipo),
                                                emissao = VALUES(emissao),
                                                vencimento = VALUES(vencimento),
                                                baixa_dt = VALUES(baixa_dt),
                                                valor = VALUES(valor),
                                                plano_contas = VALUES(plano_contas),
                                                rateio_doc = VALUES(rateio_doc),
                                                rateio_plano = VALUES(rateio_plano),
                                                rateio_valor = VALUES(rateio_valor)");

                $plano_contas = '0'.$rateio['plano_contas'];
                $rateio_plano = '0'.$rateio['rateio_plano'];

                $stmt->execute([
                    $system_unit_id,
                    $rateio['idconta'],
                    $rateio['nome'],
                    $rateio['entidade'],
                    $rateio['cgc'] ?? '',
                    $rateio['tipo'],
                    $rateio['emissao'],
                    $rateio['vencimento'],
                    $rateio['baixa_dt'],
                    $rateio['valor'],
                    $plano_contas,
                    $rateio['rateio_doc'],
                    $rateio_plano,
                    $rateio['rateio_valor']
                ]);
            }

            return ["success" => true, "message" => "Rateios importados com sucesso"];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }


}
