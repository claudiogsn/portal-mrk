<?php
require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class ClienteController {
    
    // Função para criar um novo cliente
    public static function createCliente($data) {
        global $pdo; 

        // Extrai os dados da requisição
        $nome = $data['nome'];
        $telefone = $data['telefone'];
        $email = $data['email'];
        $cpf_cnpj = $data['cpf_cnpj'];
        $status = $data['status'];
        $endereco = $data['endereco'];
        $bairro = $data['bairro'];
        $cidade = $data['cidade'];
        $estado = $data['estado'];
        $cep = $data['cep'];

        // Prepara e executa a consulta SQL para inserir um novo cliente no banco de dados
        $stmt = $pdo->prepare("INSERT INTO cliente (nome, telefone, email, cpf_cnpj, status, endereco, bairro, cidade, estado, cep) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $telefone, $email, $cpf_cnpj, $status, $endereco, $bairro, $cidade, $estado, $cep]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Cliente criado com sucesso','client_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar cliente');
        }
    }

    // Função para atualizar os detalhes de um cliente
    public static function updateCliente($id, $data) {
        global $pdo; 

        $sql = "UPDATE cliente SET ";
        $values = [];
        foreach ($data as $key => $value) {
            $sql .= "$key = ?, ";
            $values[] = $value;
        }
        $sql = rtrim($sql, ", "); // Remove a vírgula e o espaço em branco extra no final da string SQL
        $sql .= " WHERE id = ?";
        $values[] = $id;

        // Prepara e executa a consulta SQL para atualizar os detalhes do cliente
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

        // Prepara e executa a consulta SQL para obter os detalhes de um cliente pelo seu ID
        $stmt = $pdo->prepare("SELECT * FROM cliente WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $stmt->execute();

        // Retorna os detalhes do cliente encontrado
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cliente) {
            return array('success' => true, 'data' => $cliente);
        } else {
            return array('success' => false, 'message' => 'Cliente não encontrado');
        }
    }

//    public static function listClients() {
//        global $pdo;
//
//        // Prepara e executa a consulta SQL para listar todas as ordens de serviço
//        $stmt = $pdo->query("SELECT id,nome,cpf_cnpj FROM cliente ORDER BY nome ASC");
//
//        // Retorna um array contendo todas as ordens de serviço encontradas
//        return $stmt->fetchAll(PDO::FETCH_ASSOC);
//    }

    public static function listClients() {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM cliente ORDER BY nome ASC");
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'clients' => $clientes];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar clientes: ' . $e->getMessage()];
        }
    }

    public static function validateCPF($cpf)
    {
        global $pdo;
        try {
            // Verifica se já existe um cliente com esse CPF
            $stmt = $pdo->prepare("SELECT id FROM cliente WHERE cpf_cnpj = :cpf");
            $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
            $stmt->execute();
            $existingClient = $stmt->fetch(PDO::FETCH_ASSOC);

            // Se já existir um cliente com esse CPF, retorna uma mensagem de erro
            if ($existingClient) {
                return array('success' => false, 'message' => 'CPF já cadastrado');
            }

            // Consulta a API para obter os dados do CPF
            $url = 'https://api.gw.cellereit.com.br/bg-check/cpf-completo?cpf=' . $cpf;
            $headers = [
                'accept: application/json',
                'authorization: ' . 'Bearer eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICIzS1dxVWt4U2pTSDc5OUxnc3cyX0htRFozZDlkVzZoNmtsVGx2Q2t2dkdzIn0.eyJleHAiOjE3MTY1MDg0MDEsImlhdCI6MTcxNjUwODEwMSwianRpIjoiZWEzNjYwOWYtZjcxOS00NTEyLTgxZWMtOWYzNzdmODliZTQ1IiwiaXNzIjoiaHR0cHM6Ly9sb2dpbi5jZWxsZXJlaXQuY29tLmJyL2F1dGgvcmVhbG1zL3BvcnRhbC1jbGllbnRlcy1hcGkiLCJhdWQiOiJhY2NvdW50Iiwic3ViIjoiM2U4OGE3YzktYWJjNS00MjEwLTk3YjgtMTc1M2Y1NjgwZmYyIiwidHlwIjoiQmVhcmVyIiwiYXpwIjoicGRjYS1hcGkiLCJzZXNzaW9uX3N0YXRlIjoiYTgzZDFkMWEtMmQ4NS00MTNlLTgzZjMtNTMwODIyMWM3NjAyIiwiYWNyIjoiMSIsInJlYWxtX2FjY2VzcyI6eyJyb2xlcyI6WyJvZmZsaW5lX2FjY2VzcyIsImRlZmF1bHQtcm9sZXMtcG9ydGFsLWNsaWVudGVzLWFwaSIsInVtYV9hdXRob3JpemF0aW9uIl19LCJyZXNvdXJjZV9hY2Nlc3MiOnsiYWNjb3VudCI6eyJyb2xlcyI6WyJtYW5hZ2UtYWNjb3VudCIsIm1hbmFnZS1hY2NvdW50LWxpbmtzIiwidmlldy1wcm9maWxlIl19fSwic2NvcGUiOiJlbWFpbCBwbGFucyBwcm9maWxlIiwic2lkIjoiYTgzZDFkMWEtMmQ4NS00MTNlLTgzZjMtNTMwODIyMWM3NjAyIiwiZW1haWxfdmVyaWZpZWQiOnRydWUsImdyb3VwcyI6WyJhY2NvdW50QWRtaW5zIiwiaW5kaXZpZHVhbHMiXSwiYmlsbGluZ0FjY291bnRJZCI6IjY2NGZkNWM1MWZiZTQ0MjdiMTdlYmYyZiIsInByZWZlcnJlZF91c2VybmFtZSI6ImNsYXVkaW9nc24yQGdtYWlsLmNvbSIsImdpdmVuX25hbWUiOiIiLCJsb2NhbGUiOiJwdC1CUiIsImZhbWlseV9uYW1lIjoiIiwiZW1haWwiOiJjbGF1ZGlvZ3NuMkBnbWFpbC5jb20ifQ.iciOQJIa4TvmNuDI-7Sht3uAGpYesx2Y_hJSEVx5ewxfsp-USqnK6Hg2UwgNzX_qvH7ibJ8soN79yre2IPGEtuP9F8L6EC6Y4WHq8uA4b29yc6I2vSodqjEAF50y6BjSwnIIEuEZD7ojLTDZXLO4-CPEB-_ckJBf6X5hsZC1jEhysYrPi_5v2WsktmgrC7JW5wiRXCwIN5woW5sZFy1j9DfHrs07LVgKbBQeDKYadNTRryECdwn5Lp_DkRPyygM-VyzCMcLq9Hb94MW1TvyuM9SDpD9CsjyolCOvFg5MYTqEuagitwAuUHvqDcY6pqnsNX1SJ9f149cqVGuVTSLwRQ'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            curl_close($ch);

            $cpf_data = json_decode($response);

            // Se o CPF for encontrado na API, retorna os dados
            if (isset($cpf_data->CadastroPessoaFisica->Nome)) {
                $data = new stdClass;
                $data->nome = $cpf_data->CadastroPessoaFisica->Nome;
                $data->email = $cpf_data->CadastroPessoaFisica->Emails[0]->EnderecoEmail ?? '';
                $data->telefone = $cpf_data->CadastroPessoaFisica->Telefones[0]->TelefoneComDDD ?? '';
                return array('success' => true, 'message' => 'CPF válido', 'data' => $data);
            } else {
                // Caso contrário, retorna uma mensagem de CPF inválido
                return array('success' => false, 'message' => 'CPF inválido');
            }
        } catch (Exception $e) {
            // Em caso de erro, retorna a mensagem de erro
            return array('success' => false, 'message' => 'Erro ao validar CPF: ' . $e->getMessage());
        }
    }

public static function validateCNPJ($cnpj)
{
    global $pdo;
    try {
        // Verifica se já existe um cliente com esse CNPJ
        $stmt = $pdo->prepare("SELECT id FROM cliente WHERE cpf_cnpj = :cnpj");
        $stmt->bindParam(':cnpj', $cnpj, PDO::PARAM_STR);
        $stmt->execute();
        $existingClient = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se já existir um cliente com esse CNPJ, retorna uma mensagem de erro
        if ($existingClient) {
            return array('success' => false, 'message' => 'CNPJ já cadastrado');
        }

        // Consulta a API para obter os dados do CNPJ
        $url = "https://www.receitaws.com.br/v1/cnpj/{$cnpj}";
        $content = file_get_contents($url);
        $cnpj_data = json_decode($content);

        // Se o CNPJ for encontrado na API, retorna os dados
        if (isset($cnpj_data->nome)) {
            $data = new stdClass;
            $data->nome = $cnpj_data->fantasia;
            $data->telefone = $cnpj_data->telefone;
            $data->email = $cnpj_data->email;
            return array('success' => true, 'message' => 'CNPJ válido', 'data' => $data);
        } else {
            // Caso contrário, retorna uma mensagem de CNPJ inválido
            return array('success' => false, 'message' => 'CNPJ inválido');
        }
    } catch (Exception $e) {
        // Em caso de erro, retorna a mensagem de erro
        return array('success' => false, 'message' => 'Erro ao validar CNPJ: ' . $e->getMessage());
    }
}

}
?>
