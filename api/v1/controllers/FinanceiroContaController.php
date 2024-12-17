<?php

require_once __DIR__ . '/../database/db.php';


class FinanceiroContaController {
    public static function createConta($data) {
        global $pdo;

        $stmt = $pdo->prepare("INSERT INTO financeiro_conta (system_unit_id, codigo, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt, valor, plano_contas, banco, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['system_unit_id'],
            $data['codigo'],
            $data['nome'],
            $data['entidade'],
            $data['cgc'],
            $data['tipo'],
            $data['doc'],
            $data['emissao'],
            $data['vencimento'],
            $data['baixa_dt'],
            $data['valor'],
            $data['plano_contas'],
            $data['banco'],
            $data['obs'],
            $data['inc_ope'],
            $data['bax_ope'],
            $data['comp_dt'],
            $data['adic'],
            $data['comissao'],
            $data['local'],
            $data['cheque'],
            $data['dt_cheque'],
            $data['segmento']
        ]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Conta criada com sucesso', 'conta_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar conta');
        }
    }

    public static function updateConta($id, $data) {
        global $pdo;

        $sql = "UPDATE financeiro_conta SET ";
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
            return array('success' => true, 'message' => 'Conta atualizada com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao atualizar conta');
        }
    }

    public static function getContaById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM financeiro_conta WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteConta($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM financeiro_conta WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Conta excluída com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir conta');
        }
    }

    public static function listContas() {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM financeiro_conta");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function importarContaApi($system_unit_id) {
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

            // Chama o método da API para buscar as contas
            $contas = FinanceiroApiMenewController::fetchFinanceiroConta($estabelecimento, 'd');

            if (!$contas['success']) {
                throw new Exception("Erro ao buscar contas da API: " . $contas['message']);
            }

            foreach ($contas['contas'] as $conta) {
                $stmt = $pdo->prepare("INSERT INTO financeiro_conta (system_unit_id, codigo, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt, valor, plano_contas, banco, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $system_unit_id,
                    $conta['id'], // Usando o ID da API como código
                    $conta['nome'],
                    $conta['entidade'],
                    $conta['cgc'],
                    $conta['tipo'],
                    $conta['doc'],
                    $conta['emissao'],
                    $conta['vencimento'],
                    $conta['baixa_dt'],
                    $conta['valor'],
                    $conta['plano_contas'],
                    $conta['banco'],
                    $conta['obs'],
                    $conta['inc_ope'],
                    $conta['bax_ope'],
                    $conta['comp_dt'],
                    $conta['adic'],
                    $conta['comissao'],
                    $conta['local'],
                    $conta['cheque'],
                    $conta['dt_cheque'],
                    $conta['segmento']
                ]);
            }

            return ["success" => true, "message" => "Contas importadas com sucesso"];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }
}
