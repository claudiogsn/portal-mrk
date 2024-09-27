<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class MovimentacaoController {

    // Criação de nova movimentação
    public static function createMovimentacao($data) {
        global $pdo;

        // Campos obrigatórios para a movimentação
        $requiredFields = ['system_unit_id', 'doc', 'tipo', 'produto', 'seq', 'data', 'quantidade', 'usuario_id'];

        // Verifica se todos os campos obrigatórios estão presentes
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return array('success' => false, 'message' => "O campo '$field' é obrigatório.");
            }
        }

        // Extraindo os dados
        $system_unit_id = $data['system_unit_id'];
        $system_unit_id_destino = isset($data['system_unit_id_destino']) ? $data['system_unit_id_destino'] : null;
        $doc = $data['doc'];
        $tipo = $data['tipo'];
        $produto = $data['produto'];
        $seq = $data['seq'];
        $data_movimentacao = $data['data'];
        $valor = isset($data['valor']) ? $data['valor'] : null;
        $quantidade = $data['quantidade'];
        $usuario_id = $data['usuario_id'];

        // Inserção no banco de dados
        $stmt = $pdo->prepare("INSERT INTO movimentacao (system_unit_id, system_unit_id_destino, doc, tipo, produto, seq, data, valor, quantidade, usuario_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([$system_unit_id, $system_unit_id_destino, $doc, $tipo, $produto, $seq, $data_movimentacao, $valor, $quantidade, $usuario_id]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Movimentação criada com sucesso', 'movimentacao_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar movimentação');
        }
    }


    public static function createMovimentacaoMassa($dataArray) {
        $result = array('success' => true, 'messages' => array());

        foreach ($dataArray as $data) {
            $response = self::createMovimentacao($data);
            if (!$response['success']) {
                $result['success'] = false;
                $result['messages'][] = $response['message'];
            } else {
                $result['messages'][] = $response['message'];
            }
        }

        return $result;
    }


    // Atualização de movimentação
    public static function updateMovimentacao($id, $data, $system_unit_id) {
        global $pdo;

        $sql = "UPDATE movimentacao SET ";
        $values = [];
        foreach ($data as $key => $value) {
            $sql .= "$key = :$key, ";
            $values[":$key"] = $value;
        }
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE id = :id AND system_unit_id = :system_unit_id";
        $values[':id'] = $id;
        $values[':system_unit_id'] = $system_unit_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Movimentação atualizada com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar movimentação');
        }
    }

    // Obter movimentação por ID
    public static function getMovimentacaoById($id, $system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM movimentacao WHERE id = :id AND system_unit_id = :system_unit_id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getMovimentacaoByDoc($system_unit_id, $doc) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM movimentacao WHERE system_unit_id = :system_unit_id AND doc = :doc");
        $stmt->bindParam(':doc', $doc, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Deletar movimentação
    public static function deleteMovimentacao($id, $system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM movimentacao WHERE id = :id AND system_unit_id = :system_unit_id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Movimentação excluída com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir movimentação');
        }
    }

    // Listar movimentações por unidade do sistema
    public static function listMovimentacoes($system_unit_id) {
        try {
            global $pdo;

            $sql = "SELECT * FROM movimentacao WHERE system_unit_id = :system_unit_id ORDER BY created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'movimentacoes' => $movimentacoes];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar movimentações: ' . $e->getMessage()];
        }
    }

    public static function getLastMov ($system_unit_id,$tipo)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM movimentacao WHERE system_unit_id = :system_unit_id AND tipo = :tipo ORDER BY created_at DESC LIMIT 1");
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_INT);
        $stmt->execute();
        $movimentacao = $stmt->fetch(PDO::FETCH_ASSOC);
        return $movimentacao['doc'];

    }
}
?>
