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

    public static function importarClientesApi($system_unit_id) {
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

            // Chama o metodo da API para buscar os clientes
            $clientes = FinanceiroApiMenewController::fetchFinanceiroCliente($estabelecimento);

            if (!$clientes['success']) {
                throw new Exception("Erro ao buscar clientes da API: " . $clientes['message']);
            }

            foreach ($clientes['clientes'] as $cliente) {
                $stmt = $pdo->prepare("INSERT INTO financeiro_cliente (system_unit_id, codigo, razao, nome, cnpj_cpf, plano_contas) 
                                    VALUES (?, ?, ?, ?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE 
                                        razao = VALUES(razao), 
                                        nome = VALUES(nome), 
                                        cnpj_cpf = VALUES(cnpj_cpf), 
                                        plano_contas = VALUES(plano_contas)");

                $stmt->execute([
                    $system_unit_id,
                    $cliente['codigo'], // Código do cliente vindo da API
                    $cliente['razao'],
                    $cliente['nome'],
                    $cliente['cnpj_cpf'],
                    $cliente['plano_contas']
                ]);
            }

            return ["success" => true, "message" => "Clientes importados com sucesso"];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

}