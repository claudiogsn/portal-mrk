<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class EventController {
    
    public static function createEvent($data) {
        global $pdo; 

        // Extrair os dados da requisição
        $nome = $data['nome'];
        $cliente_id = $data['cliente_id'];
        $capacidade = $data['capacidade'];
        $data_inicio = $data['data_inicio'];
        $data_fim = $data['data_fim'];
        $local = $data['local'];
        $cep = $data['cep'];
        $endereco = $data['endereco'];
        $bairro = $data['bairro'];
        $cidade = $data['cidade'];
        $estado = $data['estado'];

        // Preparar e executar a consulta SQL para inserir um novo evento no banco de dados
        $stmt = $pdo->prepare("INSERT INTO evento (nome, cliente_id, capacidade, data_inicio, data_fim, local, cep, endereco, bairro, cidade, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $cliente_id, $capacidade, $data_inicio, $data_fim, $local, $cep, $endereco, $bairro, $cidade, $estado]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Evento criado com sucesso','event_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar evento');
        }
    }

    public static function updateEvent($id, $data) {
        global $pdo;
    
        // Montar a string SQL para atualizar os dados do evento
        $sql = "UPDATE evento SET ";
        $values = [];
        foreach ($data as $key => $value) {
            $sql .= "$key = :$key, ";
            $values[":$key"] = $value;
        }
        $sql = rtrim($sql, ", "); // Remover a vírgula e o espaço em branco extra no final da string SQL
        $sql .= " WHERE id = :id";
        $values[':id'] = $id;
    
        // Preparar e executar a declaração PDO
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    
        // Verificar se a atualização foi bem-sucedida
        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Detalhes do evento atualizados com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar detalhes do evento');
        }
    }
    

    public static function getEventById($id) {
        global $pdo; 
    

        $stmt = $pdo->prepare("SELECT * FROM evento WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    

    public static function deleteEvent($id) {
        global $pdo; 

        // Preparar e executar a consulta SQL para excluir um evento do banco de dados
        $stmt = $pdo->prepare("DELETE FROM evento WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Evento excluído com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir evento');
        }
    }

    public static function listEvents() {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM evento");
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'events' => $events];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar eventos: ' . $e->getMessage()];
        }
    }
}
?>
