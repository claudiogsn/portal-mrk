<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class ProductController {

    public static function createProduto($data) {
        global $pdo;

        // Campos esperados no JSON (data):
        // id, sku_zig, unit_id, codigo, nome, und, categoria, venda, composicao, insumo

        // Verifica obrigatórios presentes no JSON
        $requiredFields = ['nome', 'und', 'categoria', 'venda', 'composicao', 'insumo', 'unit_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return ['success' => false, 'message' => "O campo '$field' é obrigatório."];
            }
        }

        // De/Para
        $system_unit_id = (int)$data['unit_id'];
        $codigoInput    = isset($data['codigo']) ? trim((string)$data['codigo']) : '';
        $nome           = (string)$data['nome'];
        $und            = (string)$data['und'];
        $categoria      = (string)$data['categoria'];
        $venda          = (int)($data['venda'] ? 1 : 0);
        $composicao     = (int)($data['composicao'] ? 1 : 0);
        $insumo         = (int)($data['insumo'] ? 1 : 0);
        $sku_zig        = isset($data['sku_zig']) ? (string)$data['sku_zig'] : null;

        // Geração/validação do código
        try {
            if ($codigoInput === '') {
                $stmt = $pdo->prepare("SELECT MAX(codigo) AS max_codigo FROM products WHERE system_unit_id = :system_unit_id");
                $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $codigo = ($result && $result['max_codigo'] !== null) ? ((int)$result['max_codigo'] + 1) : 1;
            } else {
                $codigo = (int)$codigoInput;
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE system_unit_id = :system_unit_id AND codigo = :codigo");
                $stmtCheck->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
                $stmtCheck->bindParam(':codigo', $codigo, PDO::PARAM_INT);
                $stmtCheck->execute();
                if ((int)$stmtCheck->fetchColumn() > 0) {
                    return ['success' => false, 'message' => 'Código de produto já está em uso.'];
                }
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao gerar código do produto: ' . $e->getMessage()];
        }

        // INSERT somente com os campos do JSON (mapeados)
        // Ajuste as colunas conforme seu schema (removemos tudo que não existe no JSON original)
        $sql = "INSERT INTO products (
                codigo, nome, und, venda, composicao, insumo, system_unit_id, categoria, sku_zig
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $codigo,
                $nome,
                $und,
                $venda,
                $composicao,
                $insumo,
                $system_unit_id,
                $categoria,
                $sku_zig
            ]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Produto criado com sucesso', 'product_id' => $pdo->lastInsertId(), 'codigo' => $codigo];
            }

            return ['success' => false, 'message' => 'Falha ao criar produto'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao criar produto: ' . $e->getMessage()];
        }
    }

    public static function replicarProdutosEComposicoes($codigosProdutos, $system_unit_id_origem, $system_units_destino) {
        global $pdo;

        try {
            $pdo->beginTransaction();

            foreach ($codigosProdutos as $codigo) {
                // 1. Buscar produto origem
                $stmt = $pdo->prepare("SELECT * FROM products WHERE system_unit_id = :origem AND codigo = :codigo");
                $stmt->execute([':origem' => $system_unit_id_origem, ':codigo' => $codigo]);
                $produtoOrigem = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$produtoOrigem) {
                    continue;
                }

                foreach ($system_units_destino as $destino) {
                    // 2. Inserir ou atualizar produto no destino (upsert)
                    $stmtInsert = $pdo->prepare("
                    INSERT INTO products (system_unit_id, codigo, nome, preco, categoria, und, venda, composicao, insumo, producao, compravel, preco_custo, estoque_minimo, saldo, status, ultimo_doc, sku_zig)
                    VALUES (:system_unit_id, :codigo, :nome, :preco, :categoria, :und, :venda, :composicao, :insumo, :producao, :compravel, :preco_custo, :estoque_minimo, :saldo, :status, :ultimo_doc, :sku_zig)
                    ON DUPLICATE KEY UPDATE
                        nome = VALUES(nome),
                        preco = VALUES(preco),
                        categoria = VALUES(categoria),
                        und = VALUES(und),
                        venda = VALUES(venda),
                        composicao = VALUES(composicao),
                        insumo = VALUES(insumo),
                        producao = VALUES(producao),
                        compravel = VALUES(compravel),
                        preco_custo = VALUES(preco_custo),
                        estoque_minimo = VALUES(estoque_minimo),
                        saldo = VALUES(saldo),
                        status = VALUES(status),
                        ultimo_doc = VALUES(ultimo_doc),
                        sku_zig = VALUES(sku_zig)
                ");
                    $stmtInsert->execute([
                        ':system_unit_id' => $destino,
                        ':codigo' => $produtoOrigem['codigo'],
                        ':nome' => $produtoOrigem['nome'],
                        ':preco' => $produtoOrigem['preco'],
                        ':categoria' => $produtoOrigem['categoria'],
                        ':und' => $produtoOrigem['und'],
                        ':venda' => $produtoOrigem['venda'],
                        ':composicao' => $produtoOrigem['composicao'],
                        ':insumo' => $produtoOrigem['insumo'],
                        ':producao' => $produtoOrigem['producao'],
                        ':compravel' => $produtoOrigem['compravel'],
                        ':preco_custo' => $produtoOrigem['preco_custo'],
                        ':estoque_minimo' => $produtoOrigem['estoque_minimo'],
                        ':saldo' => $produtoOrigem['saldo'],
                        ':status' => $produtoOrigem['status'],
                        ':ultimo_doc' => $produtoOrigem['ultimo_doc'],
                        ':sku_zig' => $produtoOrigem['sku_zig']
                    ]);

                    // Recupera o id do produto no destino
                    $stmtGetId = $pdo->prepare("SELECT id FROM products WHERE system_unit_id = :destino AND codigo = :codigo");
                    $stmtGetId->execute([':destino' => $destino, ':codigo' => $codigo]);
                    $produtoDestino = $stmtGetId->fetch(PDO::FETCH_ASSOC);
                    $novoProductId = $produtoDestino['id'];

                    // 3. Buscar composições da origem
                    $stmtComp = $pdo->prepare("SELECT * FROM compositions WHERE system_unit_id = :origem AND product_id = :product_id");
                    $stmtComp->execute([':origem' => $system_unit_id_origem, ':product_id' => $codigo]);
                    $composicoes = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($composicoes as $comp) {
                        // 4. Inserir ou atualizar composições (upsert)
                        $stmtInsertComp = $pdo->prepare("
                        INSERT INTO compositions (product_id, insumo_id, quantity, system_unit_id)
                        VALUES (:product_id, :insumo_id, :quantity, :system_unit_id)
                        ON DUPLICATE KEY UPDATE
                            quantity = VALUES(quantity)
                    ");
                        $stmtInsertComp->execute([
                            ':product_id' => $novoProductId,
                            ':insumo_id' => $comp['insumo_id'],
                            ':quantity' => $comp['quantity'],
                            ':system_unit_id' => $destino
                        ]);
                    }
                }
            }

            $pdo->commit();
            return ['success' => true, 'message' => 'Produtos e composições replicados/atualizados com sucesso.'];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Erro ao replicar: ' . $e->getMessage()];
        }
    }

    public static function updateProduto($data) {
        global $pdo;

        // Extrai os campos obrigatórios
        $codigo = $data['codigo'] ?? null;
        $system_unit_id = $data['unit_id'] ?? null;

        if (!$codigo || !$system_unit_id) {
            return ['error' => 'Código do produto e unidade são obrigatórios.'];
        }

        // Monta a query dinamicamente, ignorando campos não atualizáveis
        $sql = "UPDATE products SET ";
        $values = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, ['id', 'unit_id', 'codigo'])) {
                $sql .= "$key = :$key, ";
                $values[":$key"] = $value;
            }
        }

        // Remove última vírgula
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE codigo = :codigo AND system_unit_id = :system_unit_id";

        $values[':codigo'] = $codigo;
        $values[':system_unit_id'] = $system_unit_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Detalhes do produto atualizados com sucesso.'];
        } else {
            return ['error' => 'Falha ao atualizar ou nenhum campo foi alterado.'];
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

    public static function deleteProduct($codigo, $system_unit_id, $user_id) {
        global $pdo;

        try {
            // Garanta que erros do PDO virem exceções
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $pdo->beginTransaction();

            // 1) Carrega o produto completo
            $sel = $pdo->prepare("
            SELECT *
            FROM products
            WHERE codigo = :codigo AND system_unit_id = :system_unit_id
            LIMIT 1
        ");
            $sel->bindValue(':codigo', $codigo, PDO::PARAM_INT);
            $sel->bindValue(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $sel->execute();

            $product = $sel->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Produto não encontrado'];
            }

            // 2) Prepara snapshot JSON com todas as colunas do produto
            //    (mantém acentos e barras sem escape)
            $snapshot = json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // 3) Escreve no log (inclui id/codigo/unidade/usuário + snapshot completo)
            $ins = $pdo->prepare("
            INSERT INTO product_delete_log
                (system_unit_id, codigo, user_id, product_id, product_snapshot, deleted_at)
            VALUES
                (:system_unit_id, :codigo, :user_id, :product_id, CAST(:snapshot AS JSON), NOW())
        ");
            $ins->bindValue(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $ins->bindValue(':codigo', $codigo, PDO::PARAM_INT);
            $ins->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $ins->bindValue(':product_id', $product['id'] ?? null, $product['id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);

            // Se sua coluna for LONGTEXT em vez de JSON, use apenas ':snapshot' sem CAST
            $ins->bindValue(':snapshot', $snapshot, PDO::PARAM_STR);

            $ins->execute();

            // 4) Deleta o produto
            $del = $pdo->prepare("
            DELETE FROM products
            WHERE codigo = :codigo AND system_unit_id = :system_unit_id
        ");
            $del->bindValue(':codigo', $codigo, PDO::PARAM_INT);
            $del->bindValue(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $del->execute();

            if ($del->rowCount() > 0) {
                $pdo->commit();
                return ['success' => true, 'message' => 'Produto excluído com sucesso'];
            } else {
                // Caso raríssimo (produto sumiu entre SELECT e DELETE)
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Falha ao excluir produto'];
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Erro ao excluir produto: ' . $e->getMessage()];
        }
    }



    public static function listProdutosDetalhado($unit_id) {
        global $pdo;

        // 1. Query SQL: Garante 0 em vez de NULL para preco_custo e saldo
        $stmt = $pdo->prepare("
    SELECT 
        id, codigo, nome, und, preco,
        IFNULL(preco_custo, 0) AS preco_custo,
        categoria,
        venda, composicao, insumo, compravel, estoque_minimo, 
        IFNULL(saldo, 0.00) AS saldo,
        status,
        sku_zig AS codigo_pdv
    FROM products
    WHERE system_unit_id = :unit_id
    ORDER BY nome
");
        $stmt->bindValue(':unit_id', $unit_id, PDO::PARAM_INT);
        $stmt->execute();

        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Lógica de Formatação Condicional (Ajuste do Frontend levado ao Backend)
        $produtosFormatados = array_map(function($p) {
            // O valor do INSUMO vem como string '1' ou '0' do banco
            if (isset($p['insumo']) && $p['insumo'] == 1) {

                // --- Lógica para preco_custo ---
                // Converte para float para garantir precisão e usa number_format para R$
                $preco = (float)$p['preco_custo'];
                // number_format(valor, decimais, separador_decimal, separador_milhar)
                $p['preco_custo'] = 'R$ ' . number_format($preco, 2, '.', '');

                // --- Lógica para saldo ---
                $saldo = (float)$p['saldo'];
                // Se o saldo for 0, formatamos para 0.00. Se for um número, formatamos com 2 decimais.
                $p['saldo'] = number_format($saldo, 2, '.', '');

            } else {
                // Se não for insumo, define como traço
                $p['preco_custo'] = '-';
                $p['saldo'] = '-';
            }

            return $p;
        }, $produtos);

        // 3. Retorna o array de produtos formatados
        return ['success' => true, 'produtos' => $produtosFormatados];
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

    public static function importarProdutosVenda($system_unit_id, $produtos, $usuario_id): array
    {
        global $pdo;

        try {
            error_log("### Início da função importarProdutosVenda ###");

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
                system_unit_id, codigo, nome, preco, categoria, venda, composicao
            ) VALUES (
                :system_unit_id, :codigo, :nome, :preco_venda, :categoria_codigo, :venda, :composicao
            )
            ON DUPLICATE KEY UPDATE 
                nome = VALUES(nome),
                preco = VALUES(preco),
                categoria = VALUES(categoria),
                venda = VALUES(venda),
                composicao = VALUES(composicao),
                updated_at = CURRENT_TIMESTAMP
        ");

            $produtosImportados = 0;

            foreach ($produtos as $produto) {
                if (!isset($produto['categoria_nome'], $produto['codigo'], $produto['nome'], $produto['preco_venda'])) {
                    throw new Exception("Produto malformado: " . json_encode($produto));
                }

                $nomeCategoria = strtoupper(trim($produto['categoria_nome']));
                if (in_array($nomeCategoria, ['DESATIVADOS', 'INTEGRADOR PADRAO'])) {
                    continue;
                }

                // Cria categoria se não existir
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
                $preco_venda = str_replace(',', '.', $produto['preco_venda']);
                $venda = !empty($produto['venda']) ? 1 : 0;
                $composicao = !empty($produto['composicao']) ? 1 : 0;

                try {
                    $stmtInsertProduto->execute([
                        ':system_unit_id' => $system_unit_id,
                        ':codigo' => $codigo,
                        ':nome' => $nome,
                        ':preco_venda' => $preco_venda,
                        ':categoria_codigo' => $categoria_codigo,
                        ':venda' => $venda,
                        ':composicao' => $composicao
                    ]);
                    $produtosImportados++;
                } catch (Exception $e) {
                    error_log("Erro ao inserir/atualizar produto (codigo: $codigo): " . $e->getMessage());
                    throw $e;
                }
            }

            $pdo->commit();
            error_log("Transação commitada com sucesso.");
            error_log("### Fim da função importarProdutosVenda ###");

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


    public static function importarProdutosZig($system_unit_id, $produtos, $usuario_id): array

    {
        global $pdo;

        error_log("### Início da função importarProdutosZig ###");
        error_log("Parâmetros recebidos - system_unit_id: $system_unit_id, usuario_id: $usuario_id, produtos: " . count($produtos));
        // Verifica se os parâmetros necessários estão presentes


        try {
            if (empty($system_unit_id) || empty($usuario_id) || !is_array($produtos)) {
                throw new Exception('Parâmetros inválidos.');
            }

            $pdo->beginTransaction();

            // Buscar categorias
            $stmtCat = $pdo->prepare("SELECT id, codigo, nome FROM categorias WHERE system_unit_id = ?");
            $stmtCat->execute([$system_unit_id]);
            $categoriasExistentes = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

            $mapCategorias = [];
            $maiorCodigoCategoria = 0;
            foreach ($categoriasExistentes as $cat) {
                $nomeNorm = strtoupper(trim($cat['nome']));
                $mapCategorias[$nomeNorm] = [
                    'id' => $cat['id'],
                    'codigo' => $cat['codigo']
                ];
                if ($cat['codigo'] > $maiorCodigoCategoria) {
                    $maiorCodigoCategoria = $cat['codigo'];
                }
            }

            // Buscar maior código de produto já existente (com até 4 dígitos)
            $stmtMaxCodigo = $pdo->prepare("SELECT MAX(codigo) as max_codigo FROM products WHERE system_unit_id = ? AND LENGTH(codigo) <= 4");
            $stmtMaxCodigo->execute([$system_unit_id]);
            $maxCodigoRow = $stmtMaxCodigo->fetch(PDO::FETCH_ASSOC);
            $proximoCodigo = max(1, intval($maxCodigoRow['max_codigo']) + 1);

            // Inserção de categoria
            $stmtInsertCategoria = $pdo->prepare("
            INSERT INTO categorias (system_unit_id, codigo, nome)
            VALUES (:system_unit_id, :codigo, :nome)
        ");

            // Inserção de produto
            $stmtInsertProduto = $pdo->prepare("
            INSERT INTO products (
                system_unit_id, codigo, nome, und, preco, categoria, insumo, venda, sku_zig
            ) VALUES (
                :system_unit_id, :codigo, :nome, :und, :preco_venda, :categoria_codigo, :insumo, :venda, :sku_zig
            )
        ");

            $produtosImportados = 0;

            foreach ($produtos as $p) {
                if (!isset($p['categoria_nome'], $p['nome'], $p['sku_zig'], $p['preco_venda'])) {
                    continue;
                }

                $nomeCategoria = strtoupper(trim($p['categoria_nome']));
                if (in_array($nomeCategoria, ['DESATIVADOS', 'INTEGRADOR PADRAO'])) {
                    continue;
                }

                // Verifica/cria categoria
                if (!isset($mapCategorias[$nomeCategoria])) {
                    $novoCodigo = ++$maiorCodigoCategoria;
                    $stmtInsertCategoria->execute([
                        ':system_unit_id' => $system_unit_id,
                        ':codigo' => $novoCodigo,
                        ':nome' => $nomeCategoria
                    ]);
                    $mapCategorias[$nomeCategoria] = [
                        'id' => $pdo->lastInsertId(),
                        'codigo' => $novoCodigo
                    ];
                }

                $stmtInsertProduto->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':codigo' => $proximoCodigo,
                    ':nome' => mb_substr($p['nome'], 0, 50),
                    ':und' => 'UND',
                    ':preco_venda' => floatval($p['preco_venda']) / 100,
                    ':categoria_codigo' => $mapCategorias[$nomeCategoria]['codigo'],
                    ':insumo' => 0,
                    ':venda' => 1,
                    ':sku_zig' => $p['sku_zig']
                ]);

                $proximoCodigo++;
                $produtosImportados++;
            }

            $pdo->commit();

            return [
                'status' => 'success',
                'message' => 'Importação Zig concluída com sucesso.',
                'categorias_importadas' => count($mapCategorias),
                'produtos_importados' => $produtosImportados
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function getProdutosComSkuZig($params)
    {
        global $pdo;

        if (!isset($params['system_unit_id'])) {
            return ['success' => false, 'message' => 'Parâmetro system_unit_id obrigatório'];
        }

        $stmt = $pdo->prepare("
        SELECT codigo, sku_zig
        FROM products
        WHERE sku_zig IS NOT NULL
        AND system_unit_id = :system_unit_id
    ");
        $stmt->execute([
            ':system_unit_id' => $params['system_unit_id']
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function deleteProduto($codigo, $unit_id, $user_id)
    {
        global $pdo;

        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->beginTransaction();

            // 1) Carrega o produto completo
            $sel = $pdo->prepare("
            SELECT *
            FROM products
            WHERE system_unit_id = :unit_id AND codigo = :codigo
            LIMIT 1
        ");
            $sel->bindValue(':unit_id', $unit_id, PDO::PARAM_INT);
            $sel->bindValue(':codigo', $codigo, PDO::PARAM_INT);
            $sel->execute();

            $product = $sel->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $pdo->rollBack();
                http_response_code(404);
                return ['success' => false, 'message' => 'Produto não encontrado'];
            }

            // 2) Snapshot JSON do produto (antes de qualquer alteração)
            $snapshot = json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // 3) Verifica movimentações
            $stmtMov = $pdo->prepare("
            SELECT COUNT(*) 
            FROM movimentacao 
            WHERE system_unit_id = :unit_id AND produto = :codigo
        ");
            $stmtMov->bindValue(':unit_id', $unit_id, PDO::PARAM_INT);
            $stmtMov->bindValue(':codigo', $codigo, PDO::PARAM_INT);
            $stmtMov->execute();
            $temMovimentacao = $stmtMov->fetchColumn() > 0;

            // 4) Apaga fichas de composição / produção (como produto e como insumo)
            $msgs = [];
            $extra = [
                'had_movements' => $temMovimentacao,
                'deleted'       => [],   // contagens de linhas removidas em relações
                'action'        => null  // 'inactivated' | 'deleted'
            ];

            // compositions como produto
            $delCompProd = $pdo->prepare("
            DELETE FROM compositions 
            WHERE system_unit_id = :unit_id AND product_id = :codigo
        ");
            $delCompProd->execute([':unit_id' => $unit_id, ':codigo' => $codigo]);
            $rcCompProd = $delCompProd->rowCount();
            if ($rcCompProd > 0) {
                $msgs[] = 'Ficha de Composição removida';
            }
            $extra['deleted']['compositions_as_product'] = $rcCompProd;

            // compositions como insumo
            $delCompIns = $pdo->prepare("
            DELETE FROM compositions 
            WHERE system_unit_id = :unit_id AND insumo_id = :codigo
        ");
            $delCompIns->execute([':unit_id' => $unit_id, ':codigo' => $codigo]);
            $rcCompIns = $delCompIns->rowCount();
            if ($rcCompIns > 0) {
                $msgs[] = 'Utilização como insumo em composição removida';
            }
            $extra['deleted']['compositions_as_insumo'] = $rcCompIns;

            // productions como produto
            $delProdProd = $pdo->prepare("
            DELETE FROM productions 
            WHERE system_unit_id = :unit_id AND product_id = :codigo
        ");
            $delProdProd->execute([':unit_id' => $unit_id, ':codigo' => $codigo]);
            $rcProdProd = $delProdProd->rowCount();
            if ($rcProdProd > 0) {
                $msgs[] = 'Ficha de Produção removida';
            }
            $extra['deleted']['productions_as_product'] = $rcProdProd;

            // productions como insumo
            $delProdIns = $pdo->prepare("
            DELETE FROM productions 
            WHERE system_unit_id = :unit_id AND insumo_id = :codigo
        ");
            $delProdIns->execute([':unit_id' => $unit_id, ':codigo' => $codigo]);
            $rcProdIns = $delProdIns->rowCount();
            if ($rcProdIns > 0) {
                $msgs[] = 'Utilização como insumo em ficha de produção removida';
            }
            $extra['deleted']['productions_as_insumo'] = $rcProdIns;

            // 5) Decide: inativar (se tem movimentação) ou excluir (se não tem)
            if ($temMovimentacao) {
                $upd = $pdo->prepare("
                UPDATE products 
                SET status = 0 
                WHERE system_unit_id = :unit_id AND codigo = :codigo
            ");
                $upd->execute([':unit_id' => $unit_id, ':codigo' => $codigo]);

                $msgs[] = 'Produto inativado pois possui movimentações registradas';
                $extra['action'] = 'inactivated';

                // Loga a inativação (mantém o snapshot do estado antigo)
                $insLog = $pdo->prepare("
                INSERT INTO product_delete_log
                    (system_unit_id, codigo, user_id, product_id, product_snapshot, deleted_at, extra)
                VALUES
                    (:unit_id, :codigo, :user_id, :product_id, CAST(:snapshot AS JSON), NOW(), CAST(:extra AS JSON))
            ");
                $insLog->bindValue(':unit_id', $unit_id, PDO::PARAM_INT);
                $insLog->bindValue(':codigo', $codigo, PDO::PARAM_INT);
                $insLog->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                $insLog->bindValue(':product_id', $product['id'], PDO::PARAM_INT);
                $insLog->bindValue(':snapshot', $snapshot, PDO::PARAM_STR);
                $insLog->bindValue(':extra', json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
                $insLog->execute();

                $pdo->commit();
                return ['success' => true, 'message' => implode('. ', $msgs)];
            } else {
                // Excluir o produto
                $del = $pdo->prepare("
                DELETE FROM products
                WHERE system_unit_id = :unit_id AND codigo = :codigo
            ");
                $del->execute([':unit_id' => $unit_id, ':codigo' => $codigo]);

                if ($del->rowCount() > 0) {
                    $msgs[] = 'Produto excluído com sucesso';
                    $extra['action'] = 'deleted';

                    // Loga a exclusão
                    $insLog = $pdo->prepare("
                    INSERT INTO product_delete_log
                        (system_unit_id, codigo, user_id, product_id, product_snapshot, deleted_at, extra)
                    VALUES
                        (:unit_id, :codigo, :user_id, :product_id, CAST(:snapshot AS JSON), NOW(), CAST(:extra AS JSON))
                ");
                    $insLog->bindValue(':unit_id', $unit_id, PDO::PARAM_INT);
                    $insLog->bindValue(':codigo', $codigo, PDO::PARAM_INT);
                    $insLog->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                    $insLog->bindValue(':product_id', $product['id'], PDO::PARAM_INT);
                    $insLog->bindValue(':snapshot', $snapshot, PDO::PARAM_STR);
                    $insLog->bindValue(':extra', json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
                    $insLog->execute();

                    $pdo->commit();
                    return ['success' => true, 'message' => implode('. ', $msgs)];
                } else {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Falha ao excluir produto'];
                }
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
