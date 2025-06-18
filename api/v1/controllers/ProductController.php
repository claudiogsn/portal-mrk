<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class ProductController {

    public static function createProduct($data) {
        global $pdo;

        // Campos da nova estrutura da tabela, exceto 'codigo'
        $requiredFields = ['nome', 'preco', 'und', 'venda', 'composicao', 'insumo', 'system_unit_id', 'categoria', 'status'];

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


        // Verificar se o código já existe
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE system_unit_id = :system_unit_id AND codigo = :codigo");
        $stmtCheck->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmtCheck->bindParam(':codigo', $codigo, PDO::PARAM_INT);
        $stmtCheck->execute();
        if ($stmtCheck->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Código de produto já está em uso.'];
        }


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
            if ($key != 'id' && $key != 'system_unit_id' && $key != 'codigo') {
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
        $stmt->bindParam(':codigo', $codigo, PDO::PARAM_INT);
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

    public static function listProdutosDetalhado($unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("
        SELECT 
            id, codigo, nome, und, preco, preco_custo, categoria,
            venda, composicao, insumo,compravel, estoque_minimo, saldo, status
        FROM products
        WHERE system_unit_id = :unit_id
        ORDER BY nome
    ");
        $stmt->bindValue(':unit_id', $unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return ['success' => true, 'produtos' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public static function getProximoCodigoProduto($unit_id, $is_insumo)
    {
        global $pdo;

        if ($is_insumo) {
            $stmt = $pdo->prepare("SELECT MAX(codigo) as max_codigo FROM products WHERE system_unit_id = ? AND codigo >= 10000");
            $stmt->execute([$unit_id]);
            $min_start = 10000;
        } else {
            $stmt = $pdo->prepare("SELECT MAX(codigo) as max_codigo FROM products WHERE system_unit_id = ? AND codigo < 9999");
            $stmt->execute([$unit_id]);
            $min_start = 1;
        }

        $max = $stmt->fetch(PDO::FETCH_ASSOC);
        $proximo = ($max && $max['max_codigo']) ? ((int)$max['max_codigo'] + 1) : $min_start;

        return ['proximo_codigo' => $proximo];
    }
    public static function checkCodigoDisponivel($codigo, $unit_id)
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT codigo FROM products WHERE system_unit_id = ? AND codigo = ?");
        $stmt->execute([$unit_id, $codigo]);
        return ['disponivel' => $stmt->rowCount() === 0];
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

            foreach ($insumos as &$insumo) {
                if (is_null($insumo['preco_custo'])) {
                    $insumo['preco_custo'] = 0;
                }
                if (is_null($insumo['saldo'])) {
                    $insumo['saldo'] = 0;
                }
            }

            return ['success' => true, 'insumos' => $insumos];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar insumos: ' . $e->getMessage()];
        }
    }

    public static function listCompraveis($system_unit_id) {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM products WHERE system_unit_id = $system_unit_id and compravel = 1");
            $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($insumos as &$insumo) {
                if (is_null($insumo['preco_custo'])) {
                    $insumo['preco_custo'] = 0;
                }
                if (is_null($insumo['saldo'])) {
                    $insumo['saldo'] = 0;
                }
            }

            return ['success' => true, 'insumos' => $insumos];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar insumos: ' . $e->getMessage()];
        }
    }

    public static function listItemVenda($system_unit_id) {
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT * FROM products WHERE system_unit_id = $system_unit_id and venda = 1");
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($produtos as &$produto) {
                if (is_null($produto['preco_custo'])) {
                    $produto['preco_custo'] = 0;
                }
                if (is_null($produto['saldo'])) {
                    $produto['saldo'] = 0;
                }
            }

            return ['success' => true, 'produtos' => $produtos];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar produtos: ' . $e->getMessage()];
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


    public static function importarProdutosPorLoja($system_unit_id, $produtos, $usuario_id): array
    {
        global $pdo;

        try {
            error_log("### Início da função importarProdutosPorLoja ###");

            if (empty($system_unit_id) || empty($usuario_id) || !is_array($produtos)) {
                throw new Exception('Parâmetros inválidos.');
            }

            error_log("Parâmetros recebidos - system_unit_id: $system_unit_id, usuario_id: $usuario_id, produtos: " . count($produtos));

            $pdo->beginTransaction();
            error_log("Transação iniciada.");

            // Carrega categorias existentes
            $stmtCategoriasExistentes = $pdo->prepare("SELECT id, codigo, nome FROM categorias WHERE system_unit_id = ?");
            $stmtCategoriasExistentes->execute([$system_unit_id]);
            $categoriasExistentes = $stmtCategoriasExistentes->fetchAll(PDO::FETCH_ASSOC);

            $mapCategorias = [];
            $maiorCodigo = 0;
            foreach ($categoriasExistentes as $cat) {
                $nomeNormalizado = strtoupper(trim($cat['nome']));
                $mapCategorias[$nomeNormalizado] = [
                    'id' => $cat['id'],
                    'codigo' => $cat['codigo']
                ];
                if ($cat['codigo'] > $maiorCodigo) {
                    $maiorCodigo = $cat['codigo'];
                }
            }

            $stmtInsertCategoria = $pdo->prepare("
            INSERT INTO categorias (system_unit_id, codigo, nome)
            VALUES (:system_unit_id, :codigo, :nome)
        ");

            $stmtInsertProduto = $pdo->prepare("
            INSERT INTO products (
                system_unit_id, codigo, nome, und, preco_custo, categoria, insumo
            ) VALUES (
                :system_unit_id, :codigo, :nome, :und, :preco_custo, :categoria_codigo, :insumo
            )
            ON DUPLICATE KEY UPDATE 
                nome = VALUES(nome),
                und = VALUES(und),
                preco_custo = VALUES(preco_custo),
                categoria = VALUES(categoria),
                insumo = VALUES(insumo),
                updated_at = CURRENT_TIMESTAMP
        ");

            $produtosImportados = 0;

            foreach ($produtos as $produto) {
                if (!isset($produto['categoria_nome'], $produto['codigo'], $produto['nome'], $produto['und'], $produto['preco_custo'])) {
                    throw new Exception("Produto malformado: " . json_encode($produto));
                }

                $nomeCategoria = strtoupper(trim($produto['categoria_nome']));
                if (in_array($nomeCategoria, ['DESATIVADOS', 'INTEGRADOR PADRAO'])) {
                    continue;
                }

                // Verifica se já temos a categoria no map
                if (!isset($mapCategorias[$nomeCategoria])) {
                    $novoCodigo = $maiorCodigo + 1;

                    $stmtInsertCategoria->execute([
                        ':system_unit_id' => $system_unit_id,
                        ':codigo' => $novoCodigo,
                        ':nome' => $nomeCategoria
                    ]);

                    $mapCategorias[$nomeCategoria] = [
                        'id' => $pdo->lastInsertId(),
                        'codigo' => $novoCodigo
                    ];
                    $maiorCodigo++;
                    error_log("Categoria inserida: $nomeCategoria (COD: $novoCodigo)");
                }

                $categoria_codigo = $mapCategorias[$nomeCategoria]['codigo'];

                $codigo = $produto['codigo'];
                $nome = mb_substr($produto['nome'], 0, 50);
                $und = $produto['und'];
                $preco_custo = str_replace(',', '.', $produto['preco_custo']);

                try {
                    $stmtInsertProduto->execute([
                        ':system_unit_id' => $system_unit_id,
                        ':codigo' => $codigo,
                        ':nome' => $nome,
                        ':und' => $und,
                        ':preco_custo' => $preco_custo,
                        ':categoria_codigo' => $categoria_codigo,
                        ':insumo' => 1
                    ]);
                    $produtosImportados++;
                } catch (Exception $e) {
                    error_log("Erro ao inserir/atualizar produto (codigo: $codigo): " . $e->getMessage());
                    throw $e;
                }
            }

            $pdo->commit();
            error_log("Transação commitada com sucesso.");
            error_log("### Fim da função importarProdutosPorLoja ###");

            return [
                'status' => 'success',
                'message' => 'Importação concluída com sucesso.',
                'categorias_importadas' => count($mapCategorias),
                'produtos_importados' => $produtosImportados
            ];
        } catch (Exception $e) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                    error_log("Rollback executado.");
                }
            } catch (Exception $rollbackError) {
                error_log("Erro ao tentar rollback: " . $rollbackError->getMessage());
            }

            error_log("Erro capturado: " . $e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Erro na importação: ' . $e->getMessage()
            ];
        }
    }


    public static function deleteProduto($codigo, $unit_id)
    {
        global $pdo;

        try {
            // Verificar se o produto existe
            $stmt = $pdo->prepare("SELECT * FROM products WHERE system_unit_id = ? AND codigo = ?");
            $stmt->execute([$unit_id, $codigo]);
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                http_response_code(404);
                return ['success' => false, 'message' => 'Produto não encontrado'];
            }

            // Verificar se existe movimentação
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM movimentacao WHERE system_unit_id = ? AND produto = ?");
            $stmt->execute([$unit_id, $codigo]);
            $temMovimentacao = $stmt->fetchColumn() > 0;

            // Verificar e apagar fichas de composição/ficha técnica
            $msgs = [];

            $composicoesComoProduto = $pdo->prepare("DELETE FROM compositions WHERE system_unit_id = ? AND product_id = ?");
            $composicoesComoProduto->execute([$unit_id, $codigo]);
            if ($composicoesComoProduto->rowCount() > 0) {
                $msgs[] = 'Ficha de Composição removida';
            }

            $composicoesComoInsumo = $pdo->prepare("DELETE FROM compositions WHERE system_unit_id = ? AND insumo_id = ?");
            $composicoesComoInsumo->execute([$unit_id, $codigo]);
            if ($composicoesComoInsumo->rowCount() > 0) {
                $msgs[] = 'Utilização como insumo em composição removida';
            }

            $productionsComoProduto = $pdo->prepare("DELETE FROM productions WHERE system_unit_id = ? AND product_id = ?");
            $productionsComoProduto->execute([$unit_id, $codigo]);
            if ($productionsComoProduto->rowCount() > 0) {
                $msgs[] = 'Ficha de Produção removida';
            }

            $productionsComoInsumo = $pdo->prepare("DELETE FROM productions WHERE system_unit_id = ? AND insumo_id = ?");
            $productionsComoInsumo->execute([$unit_id, $codigo]);
            if ($productionsComoInsumo->rowCount() > 0) {
                $msgs[] = 'Utilização como insumo em ficha de produção removida';
            }

            if ($temMovimentacao) {
                // Apenas inativa o produto
                $update = $pdo->prepare("UPDATE products SET status = 0 WHERE system_unit_id = ? AND codigo = ?");
                $update->execute([$unit_id, $codigo]);
                $msgs[] = 'Produto inativado pois possui movimentações registradas';
            } else {
                // Remove o produto
                $delete = $pdo->prepare("DELETE FROM products WHERE system_unit_id = ? AND codigo = ?");
                $delete->execute([$unit_id, $codigo]);
                $msgs[] = 'Produto excluído com sucesso';
            }

            return ['success' => true, 'message' => implode('. ', $msgs)];
        } catch (Exception $e) {
            http_response_code(500);
            return ['success' => false, 'message' => 'Erro ao excluir produto: ' . $e->getMessage()];
        }
    }

    public static function getUltimasMovimentacoesProduto($system_unit_id, $codigo_produto)
    {
        global $pdo;

        if (!$system_unit_id || !$codigo_produto) {
            return ['success' => false, 'message' => 'Parâmetros obrigatórios ausentes.'];
        }

        $sql = "
        SELECT 
            m.data,
            m.tipo,
            m.tipo_mov,
            m.doc,
            m.quantidade
        FROM movimentacao m
        WHERE m.status = 1
          AND m.produto = :codigo
          AND m.system_unit_id = :unit_id
        ORDER BY m.data DESC
        LIMIT 6
    ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':codigo', $codigo_produto, PDO::PARAM_STR);
        $stmt->bindParam(':unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'movimentacoes' => $result
        ];
    }







}
?>
