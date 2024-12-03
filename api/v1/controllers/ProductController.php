<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class ProductController {

    public static function createProduct($data) {
        global $pdo;

        // Campos da nova estrutura da tabela, exceto 'codigo'
        $requiredFields = ['nome', 'preco', 'und', 'venda', 'composicao', 'insumo', 'system_unit_id', 'categoria'];

        // Verifica se todos os campos obrigatórios estão presentes
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return array('success' => false, 'message' => "O campo '$field' é obrigatório.");
            }
        }

        // Gerar o valor de 'codigo' automaticamente a partir do valor máximo existente
        try {
            $stmt = $pdo->prepare("SELECT MAX(codigo) AS max_codigo FROM products WHERE system_unit_id = :system_unit_id");
            $stmt->bindParam(':system_unit_id', $data['system_unit_id'], PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Incrementar o valor do 'codigo' com base no máximo encontrado, iniciando com 1 se não houver registros
            $codigo = ($result['max_codigo'] !== null) ? $result['max_codigo'] + 1 : 1;

        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erro ao gerar código do produto: ' . $e->getMessage());
        }

        // Obter os demais campos da requisição
        $nome = $data['nome'];
        $categoria = $data['categoria']; // Obtém o valor de categoria
        $preco = $data['preco'];
        $und = $data['und'];
        $venda = $data['venda'];
        $composicao = $data['composicao'];
        $insumo = $data['insumo'];
        $system_unit_id = $data['system_unit_id'];
        $preco_custo = isset($data['preco_custo']) ? $data['preco_custo'] : null; // Novo campo
        $saldo = isset($data['saldo']) ? $data['saldo'] : null; // Novo campo
        $ultimo_doc = isset($data['ultimo_doc']) ? $data['ultimo_doc'] : null; // Novo campo

        // Inserção no banco de dados com os novos campos
        $stmt = $pdo->prepare("INSERT INTO products (codigo, nome, preco, und, venda, composicao, insumo, system_unit_id, categoria, preco_custo, saldo, ultimo_doc) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        try {
            $stmt->execute([$codigo, $nome, $preco, $und, $venda, $composicao, $insumo, $system_unit_id, $categoria, $preco_custo, $saldo, $ultimo_doc]);

            if ($stmt->rowCount() > 0) {
                return array('success' => true, 'message' => 'Produto criado com sucesso', 'product_id' => $pdo->lastInsertId());
            } else {
                return array('success' => false, 'message' => 'Falha ao criar produto');
            }
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erro ao criar produto: ' . $e->getMessage());
        }
    }



    public static function updateProduct($codigo, $data, $system_unit_id) {
        global $pdo;

        $sql = "UPDATE products SET ";
        $values = [];
        foreach ($data as $key => $value) {
            // Excluindo campos que não podem ser atualizados
            if ($key != 'id' && $key != 'system_unit_id') {
                $sql .= "$key = :$key, ";
                $values[":$key"] = $value;
            }
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
        LEFT JOIN categorias c ON c.codigo = p.categoria
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


    public static function updateStockBalance($system_unit_id, $codigo, $saldo, $documento) {
        global $pdo;

        try {
            // Atualiza o saldo do produto
            $stmt = $pdo->prepare("UPDATE products SET saldo = :saldo, ultimo_doc = :documento WHERE system_unit_id = :system_unit_id AND codigo = :codigo");
            $stmt->bindParam(':saldo', $saldo, PDO::PARAM_STR);
            $stmt->bindParam(':documento', $documento, PDO::PARAM_STR);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return array('success' => true, 'message' => 'Saldo atualizado com sucesso');
            } else {
                return array('success' => false, 'message' => 'Falha ao atualizar saldo');
            }
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erro ao atualizar saldo: ' . $e->getMessage());
            
        }
    }

    public static function listProductsByCategory($system_unit_id) {
        try {
            global $pdo;

            // Consulta SQL para listar produtos agrupados por categoria
            $sql = "
        SELECT 
            c.codigo AS categoria_id,
            c.nome AS categoria_nome,
            p.codigo,
            p.nome,
            p.und
        FROM products p
        LEFT JOIN categorias c ON c.codigo = p.categoria AND c.system_unit_id = p.system_unit_id
        WHERE p.system_unit_id = :system_unit_id
        AND p.insumo = 1
        ORDER BY c.nome, p.nome";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();

            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar produtos por categoria
            $groupedProducts = [];
            foreach ($products as $product) {
                $categoria_id = $product['categoria_id'] ?: 0; // Usamos 0 se a categoria não for definida
                $categoria_nome = $product['categoria_nome'] ?: 'Sem Categoria';

                // Se a categoria ainda não foi adicionada ao array, inicializamos
                if (!isset($groupedProducts[$categoria_id])) {
                    $groupedProducts[$categoria_id] = [
                        'id' => $categoria_id,
                        'categoria' => $categoria_nome,
                        'itens' => []
                    ];
                }

                // Adicionamos o produto dentro da chave 'itens' da categoria
                $groupedProducts[$categoria_id]['itens'][] = [
                    'codigo' => $product['codigo'],
                    'nome' => $product['nome'],
                    'und' => $product['und']
                ];
            }

            $groupedProducts = array_values($groupedProducts);

            return ['success' => true, 'products_by_category' => $groupedProducts];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar produtos por categoria: ' . $e->getMessage()];
        }
    }

    public static function getProductCards($system_unit_id) {
        global $pdo;

        try {
            // Consulta para obter as informações dos produtos, incluindo as duas últimas movimentações e as composições
            $sql = "
            SELECT 
                p.id,
                p.codigo,
                p.nome AS produto_nome,
                p.estoque_minimo,
                c.codigo AS categoria_id,
                c.nome AS nome_categoria,
                p.saldo AS quantidade,
                p.insumo,
                p.venda,
                p.composicao,
                p.und,
                p.preco_custo,
                p.saldo * p.preco_custo AS valor_estoque,
                p.system_unit_id,
                (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'data', m.data,
                            'tipo', m.tipo,
                            'tipo_mov', m.tipo_mov,
                            'doc', m.doc,
                            'quantidade', m.quantidade
                        )
                    )
                    FROM movimentacao m 
                    WHERE m.status = 1 AND m.produto = p.codigo AND m.system_unit_id = p.system_unit_id
                    ORDER BY m.data ASC
                ) AS atividade_recente,
                (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'insumo_id', comp.insumo_id,
                            'insumo_nome', prod.nome,
                            'quantity', comp.quantity
                        )
                    )
                    FROM compositions comp
                    LEFT JOIN products prod ON prod.codigo = comp.insumo_id AND prod.system_unit_id = comp.system_unit_id
                    WHERE comp.product_id = p.codigo AND comp.system_unit_id = p.system_unit_id
                ) AS ficha_tecnica
            FROM products p
            LEFT JOIN categorias c ON c.codigo = p.categoria and c.system_unit_id = p.system_unit_id
            WHERE p.system_unit_id = :system_unit_id
            ORDER BY c.nome, p.nome
            
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();

            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatar os dados para o retorno desejado
            $productCards = [];
            foreach ($products as $product) {
                $productCards[] = [
                    'id' => $product['id'],
                    'codigo' => $product['codigo'],
                    'nome' => $product['produto_nome'],
                    'categ' => $product['nome_categoria'],
                    'und' => $product['und'],
                    'categoria_id' => $product['categoria_id'],
                    'insumo' => $product['insumo'],
                    'venda' => $product['venda'],
                    'composicao' => $product['composicao'],
                    'quantidade' => "{$product['quantidade']} {$product['und']}",
                    'estoque_minimo' => isset($product['estoque_minimo']) ? "{$product['estoque_minimo']} {$product['und']}" : 'N/A',
                    'custo_unitario' => 'R$ ' . number_format($product['preco_custo'], 2, ',', '.') . " / {$product['und']}",
                    'valor_estoque' => 'R$ ' . number_format($product['valor_estoque'], 2, ',', '.'),
                    'atividade_recente' => json_decode($product['atividade_recente'], true) ?: [],
                    'ficha_tecnica' => json_decode($product['ficha_tecnica'], true) ?: [],
                ];
            }

            return ['success' => true, 'product_cards' => $productCards];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar produtos: ' . $e->getMessage()];
        }
    }




}
?>
