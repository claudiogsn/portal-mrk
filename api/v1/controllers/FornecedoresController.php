<?php

require_once __DIR__ . '/../database/db.php';

class FornecedoresController {

    public static function createFornecedor($data) {
        global $pdo;

        $system_unit_id = $data['system_unit_id'];
        $nome = $data['nome'];
        $cnpj = $data['cnpj'] ?? null;
        $telefone = $data['telefone'] ?? null;
        $email = $data['email'] ?? null;
        $endereco = $data['endereco'] ?? null;
        $observacoes = $data['observacoes'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO fornecedores (
                system_unit_id, nome, cnpj, telefone, email, endereco, observacoes
            ) VALUES (
                :system_unit_id, :nome, :cnpj, :telefone, :email, :endereco, :observacoes
            )
        ");
        $stmt->bindParam(':system_unit_id', $system_unit_id);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':cnpj', $cnpj);
        $stmt->bindParam(':telefone', $telefone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':endereco', $endereco);
        $stmt->bindParam(':observacoes', $observacoes);

        if ($stmt->execute()) {
            return ['success' => 'Fornecedor criado com sucesso.'];
        } else {
            throw new Exception('Erro ao criar fornecedor.');
        }
    }

    public static function updateFornecedor($id, $data) {
        global $pdo;

        $system_unit_id = $data['system_unit_id'];
        $nome = $data['nome'];
        $cnpj = $data['cnpj'] ?? null;
        $telefone = $data['telefone'] ?? null;
        $email = $data['email'] ?? null;
        $endereco = $data['endereco'] ?? null;
        $observacoes = $data['observacoes'] ?? null;

        $stmt = $pdo->prepare("
            UPDATE fornecedores 
            SET nome = :nome, cnpj = :cnpj, telefone = :telefone, email = :email,
                endereco = :endereco, observacoes = :observacoes
            WHERE id = :id AND system_unit_id = :system_unit_id
        ");
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':system_unit_id', $system_unit_id);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':cnpj', $cnpj);
        $stmt->bindParam(':telefone', $telefone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':endereco', $endereco);
        $stmt->bindParam(':observacoes', $observacoes);

        if ($stmt->execute()) {
            return ['success' => 'Fornecedor atualizado com sucesso.'];
        } else {
            throw new Exception('Erro ao atualizar fornecedor.');
        }
    }

    public static function getFornecedorById($id, $system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE id = :id AND system_unit_id = :system_unit_id");
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':system_unit_id', $system_unit_id);

        $stmt->execute();
        $fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fornecedor) {
            return $fornecedor;
        } else {
            throw new Exception('Fornecedor não encontrado.');
        }
    }

    public static function listFornecedores($system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE system_unit_id = :system_unit_id ORDER BY nome");
        $stmt->bindParam(':system_unit_id', $system_unit_id);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addItensFornecedor($system_unit_id, $fornecedor_id, array $itens) {
        global $pdo;

        if (empty($itens)) {
            throw new Exception('Lista de itens está vazia.');
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
            INSERT INTO item_fornecedor (
                system_unit_id, produto_codigo, fornecedor_id, preco_unitario, prazo_entrega_dias, observacoes
            ) VALUES (
                :system_unit_id, :produto_codigo, :fornecedor_id, :preco_unitario, :prazo_entrega_dias, :observacoes
            )
        ");

            foreach ($itens as $item) {
                $produto_codigo = $item['produto_codigo'];
                $preco_unitario = isset($item['preco_unitario']) ? $item['preco_unitario'] : null;
                $prazo = isset($item['prazo_entrega_dias']) ? $item['prazo_entrega_dias'] : null;
                $obs = isset($item['observacoes']) ? $item['observacoes'] : null;

                $stmt->bindParam(':system_unit_id', $system_unit_id);
                $stmt->bindParam(':produto_codigo', $produto_codigo);
                $stmt->bindParam(':fornecedor_id', $fornecedor_id);
                $stmt->bindParam(':preco_unitario', $preco_unitario);
                $stmt->bindParam(':prazo_entrega_dias', $prazo);
                $stmt->bindParam(':observacoes', $obs);

                if (!$stmt->execute()) {
                    throw new Exception('Erro ao inserir item de fornecedor.');
                }
            }

            $pdo->commit();
            return ['success' => 'Itens adicionados com sucesso.'];

        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            return ['error' => 'Erro ao adicionar itens: ' . $e->getMessage()];
        }
    }

    public static function removeItemFornecedor($system_unit_id, $fornecedor_id, $produto_codigo) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
            DELETE FROM item_fornecedor
            WHERE system_unit_id = :system_unit_id
              AND fornecedor_id = :fornecedor_id
              AND produto_codigo = :produto_codigo
        ");

            $stmt->bindParam(':system_unit_id', $system_unit_id);
            $stmt->bindParam(':fornecedor_id', $fornecedor_id);
            $stmt->bindParam(':produto_codigo', $produto_codigo);

            if ($stmt->execute()) {
                return ['success' => 'Item removido com sucesso.'];
            } else {
                throw new Exception('Erro ao remover item.');
            }
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Erro: ' . $e->getMessage()];
        }
    }

    public static function editItemFornecedor($system_unit_id, $fornecedor_id, $produto_codigo, $data) {
        global $pdo;

        try {
            $campos = [];
            $params = [];

            if (isset($data['preco_unitario'])) {
                $campos[] = "preco_unitario = :preco_unitario";
                $params[':preco_unitario'] = $data['preco_unitario'];
            }
            if (isset($data['prazo_entrega_dias'])) {
                $campos[] = "prazo_entrega_dias = :prazo_entrega_dias";
                $params[':prazo_entrega_dias'] = $data['prazo_entrega_dias'];
            }
            if (isset($data['observacoes'])) {
                $campos[] = "observacoes = :observacoes";
                $params[':observacoes'] = $data['observacoes'];
            }

            if (empty($campos)) {
                throw new Exception("Nenhum campo para atualizar.");
            }

            $sql = "
            UPDATE item_fornecedor SET " . implode(", ", $campos) . "
            WHERE system_unit_id = :system_unit_id
              AND fornecedor_id = :fornecedor_id
              AND produto_codigo = :produto_codigo
        ";

            $stmt = $pdo->prepare($sql);

            // Bind dos parâmetros dinâmicos
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            // Bind das chaves compostas
            $stmt->bindValue(':system_unit_id', $system_unit_id);
            $stmt->bindValue(':fornecedor_id', $fornecedor_id);
            $stmt->bindValue(':produto_codigo', $produto_codigo);

            if ($stmt->execute()) {
                return ['success' => 'Item atualizado com sucesso.'];
            } else {
                throw new Exception('Erro ao atualizar item.');
            }

        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Erro: ' . $e->getMessage()];
        }
    }

    public static function listItensFornecedor($system_unit_id, $fornecedor_id): array {
        global $pdo;

        try {
            // Primeiro, busca os itens do fornecedor
            $stmt = $pdo->prepare("
            SELECT 
                produto_codigo,
                preco_unitario,
                prazo_entrega_dias,
                observacoes
            FROM item_fornecedor
            WHERE system_unit_id = :system_unit_id
              AND fornecedor_id = :fornecedor_id
        ");
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':fornecedor_id', $fornecedor_id, PDO::PARAM_INT);
            $stmt->execute();

            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agora, para cada item, buscar o nome do produto correspondente
            $stmtNome = $pdo->prepare("
            SELECT nome 
            FROM products 
            WHERE system_unit_id = :system_unit_id 
              AND codigo = :codigo 
            LIMIT 1
        ");

            foreach ($itens as &$item) {
                $stmtNome->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
                $stmtNome->bindParam(':codigo', $item['produto_codigo'], PDO::PARAM_INT);
                $stmtNome->execute();
                $produto = $stmtNome->fetch(PDO::FETCH_ASSOC);
                $item['nome_produto'] = $produto['nome'] ?? 'N/D';
            }

            return ['success' => true, 'data' => $itens];
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => 'Erro ao listar itens do fornecedor: ' . $e->getMessage()];
        }
    }




}
