<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class ProductController {

    public static function createProduct($data) {
        global $pdo;

        // Campos da nova estrutura da tabela
        $requiredFields = ['codigo', 'nome', 'preco', 'und', 'venda', 'composicao', 'insumo', 'system_unit_id'];

        // Verifica se todos os campos obrigatórios estão presentes
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return array('success' => false, 'message' => "O campo '$field' é obrigatório.");
            }
        }

        $codigo = $data['codigo'];
        $nome = $data['nome'];
        $preco = $data['preco'];
        $und = $data['und'];
        $venda = $data['venda'];
        $composicao = $data['composicao'];
        $insumo = $data['insumo'];
        $system_unit_id = $data['system_unit_id'];

        // Inserção no banco de dados com os novos campos
        $stmt = $pdo->prepare("INSERT INTO products (codigo, nome, preco, und, venda, composicao, insumo, system_unit_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([$codigo, $nome, $preco, $und, $venda, $composicao, $insumo, $system_unit_id]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Produto criado com sucesso', 'product_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar produto');
        }
    }


    public static function updateProduct($codigo, $data, $system_unit_id) {
        global $pdo;

        $sql = "UPDATE products SET ";
        $values = [];
        foreach ($data as $key => $value) {
            $sql .= "$key = :$key, ";
            $values[":$key"] = $value;
        }
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE codigo = :codigo AND system_unit_id = :system_unit_id";
        $values[':codigo'] = $codigo;
        $values[':system_unit_id'] = $system_unit_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Detalhes do produto atualizados com sucesso');
        } else {
            return array('error' => 'Falha ao atualizar detalhes do produto');
        }
    }

    public static function getProductById($codigo, $system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM products WHERE codigo = :codigo AND system_unit_id = :system_unit_id");
        $stmt->bindParam('codigo', $codigo, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteProduct($id, $system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id AND system_unit_id = :system_unit_id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Produto excluído com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir produto');
        }
    }

    public static function listProducts($system_unit_id) {
        try {
            global $pdo;

            // Atualiza a consulta SQL para lidar com os novos campos
            $sql = "
        SELECT p.*, 
            c.nome AS nome_categoria,
            CASE 
                WHEN p.venda = 1 THEN 'Venda' 
                ELSE NULL 
            END AS tipo_venda,
            CASE 
                WHEN p.composicao = 1 THEN 'Composição' 
                ELSE NULL 
            END AS tipo_composicao,
            CASE 
                WHEN p.insumo = 1 THEN 'Insumo' 
                ELSE NULL 
            END AS tipo_insumo
        FROM products p
        LEFT JOIN categorias c ON c.id = p.categ
        WHERE p.system_unit_id = :system_unit_id
        GROUP BY p.id
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formata o campo 'tipo'
            foreach ($products as &$product) {
                $tipo = [];
                if ($product['tipo_venda']) $tipo[] = $product['tipo_venda'];
                if ($product['tipo_composicao']) $tipo[] = $product['tipo_composicao'];
                if ($product['tipo_insumo']) $tipo[] = $product['tipo_insumo'];

                $product['tipo'] = implode(' | ', array_filter($tipo));
                unset($product['tipo_venda'], $product['tipo_composicao'], $product['tipo_insumo']); // Remove campos temporários
            }

            return ['success' => true, 'products' => $products];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar produtos: ' . $e->getMessage()];
        }
    }

    public static function listInsumos($system_unit_id) {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM products WHERE system_unit_id = $system_unit_id and insumo = 1");
            $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'insumos' => $insumos];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar insumos: ' . $e->getMessage()];
        }
    }


}
?>
