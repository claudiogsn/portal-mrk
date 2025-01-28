<?php

date_default_timezone_set("America/Recife");

require_once __DIR__ . "/../database/db.php";

class MovimentacaoController
{
    public static function getMovimentacao($system_unit_id, $doc): false|array
    {
        global $pdo;

        $stmt = $pdo->prepare("
        SELECT 
            movimentacao.*, 
            products.nome AS product_name
        FROM 
            movimentacao
        INNER JOIN 
            products 
        ON 
            movimentacao.produto = products.codigo AND movimentacao.system_unit_id = products.system_unit_id
        WHERE 
            movimentacao.system_unit_id = :system_unit_id 
        AND 
            movimentacao.doc = :doc
    ");
        $stmt->bindParam(":system_unit_id", $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(":doc", $doc);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getMovsByProd($unit_id, $data, $produto): false|array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT 
            m.system_unit_id,
            
            COALESCE((select name from system_unit where id = m.system_unit_id_destino), 'Destino Não Informado') AS system_unit_id_destino,
            m.doc,
            m.tipo,
            m.tipo_mov,
            m.produto,
            p.nome AS nome_produto,
            ROUND(m.quantidade, 2) AS quantidade
        FROM 
            movimentacao m
        JOIN 
            products p
        ON 
            m.produto = p.codigo
            AND m.system_unit_id = p.system_unit_id
        WHERE 
            m.system_unit_id = :unit_id
            AND m.status in (1,2)
            AND m.data = :data
            AND m.produto = :produto;");

        $stmt->bindParam(":unit_id", $unit_id, PDO::PARAM_INT);
        $stmt->bindParam(":data", $data);
        $stmt->bindParam(":produto", $produto, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function efetivarTransacoes($systemUnitId, $doc): array
    {
        try {
            // Buscar todas as movimentações associadas ao `doc` e `system_unit_id`
            $movimentacoes = self::getMovimentacao($systemUnitId, $doc);

            // Verificar se as movimentações foram encontradas
            if (empty($movimentacoes)) {
                return [
                    "success" => false,
                    "message" => "Nenhuma movimentação encontrada para o documento especificado.",
                ];
            }

            // Atualizar o status de todas as movimentações do `doc`
            $updateResult = self::atualizarStatusMovimentacoes($systemUnitId, $doc);


            if ($updateResult > 0) {
                return [
                    "success" => true,
                    "message" => "Transações efetivadas com sucesso!",
                ];
            } else {
                return [
                    "success" => false,
                    "message" => "Falha ao efetivar transações. Nenhuma movimentação foi atualizada.",
                ];
            }

        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Erro ao efetivar transações: " . $e->getMessage(),
            ];
        }
    }

    private static function atualizarStatusMovimentacoes($systemUnitId, $doc): array
    {
        global $pdo;

        try {
            $query = "UPDATE movimentacao SET status = 1 WHERE system_unit_id = :system_unit_id AND doc = :doc";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(":system_unit_id", $systemUnitId, PDO::PARAM_INT);
            $stmt->bindParam(":doc", $doc);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return [
                    "success" => true,
                    "message" => "Movimentação rejeitada com sucesso!",
                ];
            } else {
                return [
                    "success" => false,
                    "message" => "Nenhuma movimentação encontrada para rejeitar.",
                ];
            }
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Erro ao rejeitar movimentação: " . $e->getMessage(),
            ];
        }
    }


    public static function listarMovimentacoesPendentes($systemUnitId): false|array
    {
        global $pdo;

        $query = "
            SELECT
                m.system_unit_id,
                su_origem.name AS nome_unidade_origem,
                m.system_unit_id_destino,
                su_destino.name AS nome_unidade_destino,
                m.doc,
                CASE
                    WHEN m.tipo = 'b' THEN 'Balanço'
                    WHEN m.tipo = 'te' THEN 'Transferência de Entrada'
                    WHEN m.tipo = 'ts' THEN 'Transferência de Saida'
                    WHEN m.tipo = 'v' THEN 'Venda'
                    WHEN m.tipo = 'p' THEN 'Perda'
                    WHEN m.tipo = 'c' THEN 'Compra'
                    ELSE 'Outro'
                END AS tipo_movimentacao,
                us.name as username,
                m.data,
                m.created_at
            FROM
                movimentacao m
            LEFT JOIN
                system_unit su_origem ON m.system_unit_id = su_origem.id
            LEFT JOIN
                system_unit su_destino ON m.system_unit_id_destino = su_destino.id
            LEFT JOIN
                system_users us ON m.usuario_id = us.id
            WHERE 
                m.system_unit_id = :system_unit_id
                AND m.status = 0
            GROUP BY
                m.doc;
        ";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(":system_unit_id", $systemUnitId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function rejeitarMovimentacao($systemUnitId, $doc, $usuario_id): array
    {
        global $pdo;

        try {
            // Atualiza o status da movimentação para rejeitado
            $query =
                "UPDATE movimentacao SET status = 3,usuario_id = :usuario_id WHERE system_unit_id = :system_unit_id AND doc = :doc";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(":system_unit_id", $systemUnitId, PDO::PARAM_INT);
            $stmt->bindParam(":usuario_id", $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(":doc", $doc);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return [
                    "success" => true,
                    "message" => "Movimentação rejeitada com sucesso!",
                ];
            } else {
                return [
                    "success" => false,
                    "message" =>
                        "Nenhuma movimentação encontrada para rejeitar.",
                ];
            }
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" =>
                    "Erro ao rejeitar movimentação: " . $e->getMessage(),
            ];
        }
    }

    public static function listarMovimentacoesPorData(
        $systemUnitId,
        $data_inicial,
        $data_final
    ): false|array
    {
        global $pdo;

        $query = "
            SELECT
                m.system_unit_id,
                case 
                    when m.status = 0 then 'Pendente'
                    when m.status = 1 then 'Efetivado'
                    when m.status = 3 then 'Rejeitado'
                    else 'Outro'
                end as status,
                su_origem.name AS nome_unidade_origem,
                m.system_unit_id_destino,
                su_destino.name AS nome_unidade_destino,
                m.doc,
                CASE
                    WHEN m.tipo = 'b' THEN 'Balanço'
                     WHEN m.tipo = 'te' THEN 'Transferência de Entrada'
                    WHEN m.tipo = 'ts' THEN 'Transferência de Saida'
                    WHEN m.tipo = 'v' THEN 'Venda'
                    WHEN m.tipo = 'p' THEN 'Perda'
                    WHEN m.tipo = 'c' THEN 'Compra'
                    ELSE 'Outro'
                END AS tipo_movimentacao,
                us.name as username,
                m.data,
                m.created_at
            FROM
                movimentacao m
            LEFT JOIN
                system_unit su_origem ON m.system_unit_id = su_origem.id
            LEFT JOIN
                system_unit su_destino ON m.system_unit_id_destino = su_destino.id
           LEFT JOIN
                system_users us ON m.usuario_id = us.id
            WHERE 
                m.system_unit_id = :system_unit_id
                AND m.status <> 3
                AND m.data BETWEEN :data_inicial AND :data_final
            GROUP BY
                m.doc;
        ";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(":system_unit_id", $systemUnitId, PDO::PARAM_INT);
        $stmt->bindParam(":data_inicial", $data_inicial);
        $stmt->bindParam(":data_final", $data_final);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obter última movimentação de determinado tipo
    public static function getLastMov($system_unit_id, $tipo)
    {
        global $pdo;

        $stmt = $pdo->prepare(
            "SELECT * FROM movimentacao WHERE system_unit_id = :system_unit_id AND tipo = :tipo ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->bindParam(":system_unit_id", $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(":tipo", $tipo);
        $stmt->execute();
        $movimentacao = $stmt->fetch(PDO::FETCH_ASSOC);

        return $movimentacao ? $movimentacao["doc"] : $tipo . "-000000";
    }

    // Função para incrementar o documento (doc)
    private static function incrementDoc($ultimoDoc, $prefixo): string
    {
        // Supondo que o formato do doc seja algo como "t-000001" ou "b-000001"
        if (preg_match("/^" . $prefixo . '-(\d+)$/', $ultimoDoc, $matches)) {
            $numero = (int) $matches[1] + 1;
            return $prefixo . "-" . str_pad($numero, 6, "0", STR_PAD_LEFT);
        }
        return $prefixo . "-000001";
    }

    // Métodos Específicos para Balanço

    // Listar balanços agrupados por 'doc' com mais informações e filtro de data
    public static function listBalance(
        $system_unit_id,
        $data_inicial = null,
        $data_final = null
    ): array
    {
        global $pdo;

        try {
            // Validação das datas
            if (
                !empty($data_inicial) &&
                !empty($data_final) &&
                $data_inicial > $data_final
            ) {
                http_response_code(400); // Código HTTP 400 para Bad Request
                return [
                    "success" => false,
                    "message" =>
                        "A data inicial não pode ser maior que a data final.",
                ];
            }

            // Constrói a base da consulta
            $query = "
            SELECT 
                m.doc, 
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'produto', m.produto, 
                        'quantidade', m.quantidade, 
                        'nome_produto', p.nome, 
                        'categoria_id', c.codigo,
                        'nome_categoria', c.nome
                    )
                ) AS itens, 
                MAX(m.created_at) AS created_at
            FROM movimentacao m
            INNER JOIN products p ON p.codigo = m.produto AND p.system_unit_id = m.system_unit_id
            INNER JOIN categorias c ON c.codigo = p.categoria AND c.system_unit_id = p.system_unit_id
            WHERE m.system_unit_id = :system_unit_id 
            AND m.tipo = 'b'";

            // Adiciona as condições de data, se fornecidas
            if (!empty($data_inicial) && !empty($data_final)) {
                $query .=
                    " AND m.created_at BETWEEN :data_inicial AND :data_final";
            } elseif (!empty($data_inicial)) {
                $query .= " AND m.created_at >= :data_inicial";
            } elseif (!empty($data_final)) {
                $query .= " AND m.created_at <= :data_final";
            }

            $query .= " GROUP BY m.doc ORDER BY MAX(m.created_at) DESC";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(
                ":system_unit_id",
                $system_unit_id,
                PDO::PARAM_INT
            );

            // Bind das datas se fornecidas
            if (!empty($data_inicial)) {
                $stmt->bindParam(":data_inicial", $data_inicial);
            }
            if (!empty($data_final)) {
                $stmt->bindParam(":data_final", $data_final);
            }

            $stmt->execute();
            $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decodifica os itens de JSON para array/objeto
            foreach ($balances as &$balance) {
                $balance["itens"] = json_decode($balance["itens"], true); // Converte o JSON em array associativo
            }

            return ["success" => true, "balances" => $balances];
        } catch (Exception $e) {
            http_response_code(500); // Código HTTP 500 para erro interno
            return [
                "success" => false,
                "message" => "Erro ao listar balanços: " . $e->getMessage(),
            ];
        }
    }

    public static function getBalanceByDoc($system_unit_id, $doc): array
    {
        global $pdo;

        // Validação de parâmetros obrigatórios
        if (!$system_unit_id || !$doc) {
            return [
                "success" => false,
                "message" => "Parâmetros obrigatórios ausentes.",
            ];
        }

        try {
            // Consulta os detalhes do balanço, incluindo nome do produto, categoria e nome do usuário
            $stmt = $pdo->prepare("
            SELECT 
                m.doc,
                p.codigo as produto_codigo,
                p.nome AS produto_nome,
                m.quantidade,
                c.nome AS categoria_nome,
                u.login AS usuario_nome,
                m.created_at
            FROM movimentacao m
            LEFT JOIN products p ON m.produto = p.codigo AND m.system_unit_id = p.system_unit_id
            LEFT JOIN categorias c ON p.categoria = c.codigo AND m.system_unit_id = c.system_unit_id
            LEFT JOIN system_users u ON m.usuario_id = u.id
            WHERE m.system_unit_id = :system_unit_id AND m.doc = :doc
        ");
            $stmt->bindParam(
                ":system_unit_id",
                $system_unit_id,
                PDO::PARAM_INT
            );
            $stmt->bindParam(":doc", $doc);
            $stmt->execute();

            $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Verifica se encontrou o balanço
            if ($movimentacoes) {
                // Agrupa os dados do balanço e dos itens
                $response = [
                    "success" => true,
                    "balance" => [
                        "doc" => $movimentacoes[0]["doc"],
                        "usuario_nome" => $movimentacoes[0]["usuario_nome"],
                        "created_at" => $movimentacoes[0]["created_at"],
                        "itens" => [],
                    ],
                ];

                // Adiciona os itens ao array de itens
                foreach ($movimentacoes as $movimentacao) {
                    $response["balance"]["itens"][] = [
                        "codigo" => $movimentacao["produto_codigo"],
                        "produto" => $movimentacao["produto_nome"],
                        "quantidade" => $movimentacao["quantidade"],
                        "categoria" => $movimentacao["categoria_nome"],
                    ];
                }

                return $response;
            } else {
                return [
                    "success" => false,
                    "message" => "Balanço não encontrado.",
                ];
            }
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Erro ao buscar balanço: " . $e->getMessage(),
            ];
        }
    }

    // Criação de movimentação de balanço (tipo 'b')
    public static function saveBalanceItems($data): array
    {
        global $pdo;

        // Campos obrigatórios para a movimentação
        $requiredFields = ["system_unit_id", "itens"];

        // Verifica se todos os campos obrigatórios estão presentes
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return [
                    "success" => false,
                    "message" => "O campo '$field' é obrigatório.",
                ];
            }
        }

        // Verifica se 'itens' é um array e possui ao menos um item
        if (!is_array($data["itens"]) || count($data["itens"]) == 0) {
            return [
                "success" => false,
                "message" => "É necessário incluir ao menos um item.",
            ];
        }

        // Extraindo os dados
        $system_unit_id = $data["system_unit_id"];
        $system_unit_id_destino = $data["system_unit_id_destino"] ?? null;
        $itens = $data["itens"];
        $date_balance = $data["date_balance"];

        // Gera o valor de 'doc' chamando o metodo getLastMov e incrementa para obter um novo valor
        $ultimoDoc = self::getLastMov($system_unit_id, "b");
        $doc = self::incrementDoc($ultimoDoc, "b");

        // Definindo valores fixos
        $tipo = "b";
        $tipo_mov = "balanco";
        $usuario_id = $data["user"];

        try {
            // Inicia a transação
            $pdo->beginTransaction();

            foreach ($itens as $item) {
                // Verifica se cada item possui os campos obrigatórios
                $itemRequiredFields = ["codigo", "seq", "quantidade"];
                foreach ($itemRequiredFields as $field) {
                    if (!isset($item[$field])) {
                        // Se algum campo obrigatório faltar, faz rollback e retorna o erro
                        $pdo->rollBack();
                        return [
                            "success" => false,
                            "message" => "O campo '$field' é obrigatório para cada item.",
                        ];
                    }
                }

                // Extraindo os dados do item
                $produto = $item["codigo"];
                $seq = $item["seq"];
                $quantidade = $item["quantidade"];

                // Inserção no banco de dados
                $stmt = $pdo->prepare("INSERT INTO movimentacao (system_unit_id, system_unit_id_destino, doc, tipo, tipo_mov , produto, seq, data, quantidade, usuario_id) 
                                   VALUES (?, ?, ?, ?, ?, ? , ?, ?, ?, ?)");
                $stmt->execute([
                    $system_unit_id,
                    $system_unit_id_destino,
                    $doc,
                    $tipo,
                    $tipo_mov,
                    $produto,
                    $seq,
                    $date_balance,
                    $quantidade,
                    $usuario_id,
                ]);

                if ($stmt->rowCount() == 0) {
                    // Se a inserção do item falhar, faz rollback e retorna o erro
                    $pdo->rollBack();
                    return [
                        "success" => false,
                        "message" => "Falha ao criar movimentação para o item com código " . $produto,
                    ];
                }
            }

            // Se todas as inserções forem bem-sucedidas, faz o commit da transação
            $pdo->commit();

            return [
                "success" => true,
                "message" => "Movimentação criada com sucesso",
                "balanco" => $doc,
            ];
        } catch (Exception $e) {
            // Rollback em caso de erro
            $pdo->rollBack();
            return [
                "success" => false,
                "message" => "Erro ao criar movimentação: " . $e->getMessage(),
            ];
        }
    }


    // Métodos Específicos para Transferências

    // Criação de transferência

    public static function createTransferItems($data): array
    {
        global $pdo;

        // Verifica se todos os campos obrigatórios estão presentes
        $requiredFields = [
            "system_unit_id",
            "system_unit_id_destino",
            "itens",
            "usuario_id",
        ];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return [
                    "success" => false,
                    "message" => "O campo '$field' é obrigatório.",
                ];
            }
        }

        // Verifica se 'itens' é um array e possui ao menos um item
        if (!is_array($data["itens"]) || count($data["itens"]) == 0) {
            return [
                "success" => false,
                "message" => "É necessário incluir ao menos um item.",
            ];
        }

        // Extraindo os dados
        $system_unit_id = $data["system_unit_id"];
        $system_unit_id_destino = $data["system_unit_id_destino"];
        $itens = $data["itens"];
        $usuario_id = $data["usuario_id"];
        $transferDate = $data["transfer_date"];

        // Gera o valor de 'doc' chamando o metodo getLastMov e incrementa para obter novos valores para entrada e saída
        $ultimoDocSaida = self::getLastMov($system_unit_id, "ts"); // Tipo para saída
        $docSaida = self::incrementDoc($ultimoDocSaida, "ts"); // Incrementa para saída

        $ultimoDocEntrada = self::getLastMov($system_unit_id_destino, "te"); // Tipo para entrada
        $docEntrada = self::incrementDoc($ultimoDocEntrada, "te"); // Incrementa para entrada

        // Definindo valores fixos
        $tipo_saida = "saida";
        $tipo_entrada = "entrada";
        $tipo_saida_doc = "ts"; // Tipo para saída
        $tipo_entrada_doc = "te"; // Tipo para entrada

        try {
            // Inicia a transação
            $pdo->beginTransaction();

            // Criação dos movimentos de saída
            foreach ($itens as $item) {
                // Verifica se cada item possui os campos obrigatórios
                $itemRequiredFields = ["codigo", "seq", "quantidade"];
                foreach ($itemRequiredFields as $field) {
                    if (!isset($item[$field])) {
                        return [
                            "success" => false,
                            "message" => "O campo '$field' é obrigatório para cada item.",
                        ];
                    }
                }

                // Extraindo os dados do item
                $produto = $item["codigo"];
                $seq = $item["seq"];
                $quantidade = str_replace(",", ".", $item["quantidade"]);

                // Inserção no banco de dados para o movimento de saída
                $stmt = $pdo->prepare("INSERT INTO movimentacao (system_unit_id, system_unit_id_destino, doc, tipo, tipo_mov, produto, seq, data, quantidade, usuario_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $system_unit_id,
                    $system_unit_id_destino,
                    $docSaida,
                    $tipo_saida_doc,
                    $tipo_saida,
                    $produto,
                    $seq,
                    $transferDate,
                    $quantidade,
                    $usuario_id,
                ]);
            }

            // Criação dos movimentos de entrada
            foreach ($itens as $item) {
                // Extraindo os dados do item
                $produto = $item["codigo"];
                $seq = $item["seq"];
                $quantidade = str_replace(",", ".", $item["quantidade"]);

                // Inserção no banco de dados para o movimento de entrada
                $stmt = $pdo->prepare("INSERT INTO movimentacao (system_unit_id, doc, tipo, tipo_mov, produto, seq, data, quantidade, usuario_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $system_unit_id_destino,
                    $docEntrada,
                    $tipo_entrada_doc,
                    $tipo_entrada,
                    $produto,
                    $seq,
                    $transferDate,
                    $quantidade,
                    $usuario_id,
                ]);
            }

            // Consulta o nome da unidade de destino
            $stmt = $pdo->prepare("SELECT name FROM system_unit WHERE id = ?");
            $stmt->execute([$system_unit_id_destino]);
            $unidade_destino = $stmt->fetch();

            // Se a consulta for bem-sucedida, inclui o nome da unidade de destino na resposta
            if ($unidade_destino) {
                $nome_unidade_destino = $unidade_destino["name"];
            } else {
                $pdo->rollBack();
                return [
                    "success" => false,
                    "message" =>
                        "Falha ao recuperar o nome da unidade de destino.",
                ];
            }

            $stmt = $pdo->prepare("SELECT name FROM system_unit WHERE id = ?");
            $stmt->execute([$system_unit_id]);
            $unidade_origem = $stmt->fetch();

            // Se a consulta for bem-sucedida, inclui o nome da unidade de destino na resposta
            if ($unidade_origem) {
                $nome_unidade_origem = $unidade_origem["name"];
            } else {
                $pdo->rollBack();
                return [
                    "success" => false,
                    "message" =>
                        "Falha ao recuperar o nome da unidade de destino.",
                ];
            }

            $stmt = $pdo->prepare("SELECT name FROM system_users WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $username = $stmt->fetch();

            // Se a consulta for bem-sucedida, inclui o nome da unidade de destino na resposta
            if ($username) {
                $nome_user = $username["name"];
            } else {
                $pdo->rollBack();
                return [
                    "success" => false,
                    "message" =>
                        "Falha ao recuperar o nome da unidade de destino.",
                ];
            }

            // Cria a estrutura dos itens com nome do produto
            $itensComDetalhes = [];
            foreach ($itens as $item) {
                // Obter o nome do produto
                $stmt = $pdo->prepare(
                    "SELECT nome as name FROM products WHERE codigo = ?"
                );
                $stmt->execute([$item["codigo"]]);
                $produtoData = $stmt->fetch();
                $nomeProduto = $produtoData
                    ? $produtoData["name"]
                    : "Desconhecido";

                // Adiciona os detalhes do item
                $itensComDetalhes[] = [
                    "seq" => $item["seq"],
                    "codigo" => $item["codigo"],
                    "nome_produto" => $nomeProduto,
                    "quantidade" => $item["quantidade"],
                ];
            }

            // Commit da transação
            $pdo->commit();
            return [
                "success" => true,
                "message" => "Transferência criada com sucesso",
                "transfer_doc" => $docEntrada,
                "nome_unidade_destino" => $nome_unidade_destino,
                "nome_unidade_origem" => $nome_unidade_origem,
                "data_hora" => date("d/m/Y H:i:s"),
                "usuario" => $nome_user,
                "itens" => $itensComDetalhes,
            ];
        } catch (Exception $e) {
            // Rollback em caso de erro
            $pdo->rollBack();
            return [
                "success" => false,
                "message" => "Erro ao criar transferência: " . $e->getMessage(),
            ];
        }
    }

    public static function importComprasCSV(
        $usuarioId,
        $produtos,
        $data_importacao
    ): array
    {
        global $pdo;

        try {
            // Inicia uma transação para melhorar a performance
            $pdo->beginTransaction();

            // Mapeia todos os códigos de estabelecimento para system_unit_id
            $estabelecimentos = array_column($produtos, "estabelecimento");
            $placeholders = rtrim(
                str_repeat("?,", count($estabelecimentos)),
                ","
            );

            $query = "SELECT custom_code, id as system_unit_id
                  FROM system_unit 
                  WHERE custom_code IN ($placeholders)";
            $stmt = $pdo->prepare($query);
            $stmt->execute($estabelecimentos);

            $unitMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Prepara a query de inserção com atualização em caso de duplicidade
            $insertQuery = "
        INSERT INTO movimentacao (
            system_unit_id, status, doc, tipo, tipo_mov, produto, seq, data, quantidade, usuario_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            status = VALUES(status),
            data = VALUES(data),
            quantidade = VALUES(quantidade),
            usuario_id = VALUES(usuario_id)
        ";
            $insertStmt = $pdo->prepare($insertQuery);

            foreach ($produtos as $produto) {
                $systemUnitId = $unitMap[$produto["estabelecimento"]] ?? null;

                if (!$systemUnitId) {
                    throw new Exception(
                        "Estabelecimento não encontrado: {$produto["estabelecimento"]}"
                    );
                }

                // Insere ou atualiza a movimentação
                $insertStmt->execute([
                    $systemUnitId,
                    1, // Status = Concluído
                    $produto["doc"],
                    $produto["tipo"],
                    "entrada", // Tipo de movimento
                    $produto["produto"],
                    $produto["seq"],
                    $data_importacao,
                    $produto["qtde"],
                    $usuarioId,
                ]);
            }

            // Confirma a transação
            $pdo->commit();
            return [
                "success" => true,
                "message" =>
                    "Movimentações salvas e estoque atualizado com sucesso!",
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public static function importMovBySales($systemUnitId, $data): string
    {
        global $pdo;

        try {
            // Inicia uma transação
            $pdo->beginTransaction();

            // Passo 1: Consulta os produtos vendidos (apenas para identificar os produtos)
            $stmt = $pdo->prepare("
            SELECT 
                system_unit_id,
                cod_material AS produto,
                quantidade AS qtde,
                data_movimento AS data
            FROM 
                _bi_sales
            WHERE 
                system_unit_id = :systemUnitId 
                AND data_movimento = :data
        ");
            $stmt->execute([":systemUnitId" => $systemUnitId, ":data" => $data]);

            $produtosVendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Se nenhum produto for encontrado, não faz nada
            if (empty($produtosVendas)) {
                $pdo->rollBack();
                return "Nenhuma movimentação encontrada para a unidade e data informadas.";
            }

            // Passo 2: Consulta os insumos relacionados aos produtos vendidos
            // Buscamos os insumos para cada produto vendido
            $produtosVendidosIds = array_map(function ($produto) {
                return $produto["produto"]; // coleta o código do produto
            }, $produtosVendas);

            // Gerar os placeholders para o IN
            $placeholders = implode(
                ",",
                array_fill(0, count($produtosVendidosIds), "?")
            );

            // Passagem de parâmetros posicionais, agora usando apenas parâmetros posicionais
            $stmtInsumos = $pdo->prepare("
            SELECT product_id, insumo_id, quantity AS quantidade_insumo
            FROM compositions
            WHERE system_unit_id = ? 
            AND product_id IN ($placeholders)
        ");

            // Passando os parâmetros corretamente
            $stmtInsumos->execute(
                array_merge([$systemUnitId], $produtosVendidosIds)
            );

            $insumos = $stmtInsumos->fetchAll(PDO::FETCH_ASSOC);

            // Array para armazenar a quantidade total de cada insumo
            $insumosTotais = [];

            // Passo 3: Processa os insumos com base nas vendas dos produtos
            foreach ($produtosVendas as $produtoVenda) {
                // Para cada produto vendido, consulte os insumos
                foreach ($insumos as $insumo) {
                    if ($insumo["product_id"] == $produtoVenda["produto"]) {
                        // Cálculo da quantidade de insumo a ser baixada
                        $qtdeInsumo =
                            $produtoVenda["qtde"] *
                            $insumo["quantidade_insumo"];

                        // Armazena a quantidade total de cada insumo
                        if (isset($insumosTotais[$insumo["insumo_id"]])) {
                            $insumosTotais[$insumo["insumo_id"]] += $qtdeInsumo;
                        } else {
                            $insumosTotais[$insumo["insumo_id"]] = $qtdeInsumo;
                        }
                    }
                }
            }

            // Passo 4: Prepara o statement para inserir ou atualizar a tabela movimentacao
            $insertStmt = $pdo->prepare("
            INSERT INTO movimentacao (
                system_unit_id, status, doc, tipo, tipo_mov, produto, seq, data, quantidade, usuario_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                data = VALUES(data),
                quantidade = VALUES(quantidade),
                usuario_id = VALUES(usuario_id)
        ");

            // Usuário fictício para inserção
            $usuarioId = 5;
            $seq = 1;

            // Passo 5: Agora, inserimos os insumos agrupados por insumo_id
            foreach ($insumosTotais as $insumoId => $totalQuantidade) {
                // Gera o documento
                $doc = "v-" . str_replace("-", "", $data);

                // Insere ou atualiza a movimentação do insumo
                $insertStmt->execute([
                    $systemUnitId,
                    1, // status
                    $doc, // documento
                    "v", // Tipo "v" de venda
                    "saida", // Tipo de movimentação "saida"
                    $insumoId, // Insumo ID
                    $seq++, // Incrementa o seq
                    $data, // Data da movimentação
                    $totalQuantidade, // Quantidade total do insumo
                    $usuarioId, // ID do usuário (ajustar conforme necessário)
                ]);
            }

            // Confirma a transação
            $pdo->commit();
            return "Movimentações de insumos importadas com sucesso.";
        } catch (Exception $e) {
            // Reverte a transação em caso de erro
            $pdo->rollBack();
            return "Erro ao importar movimentações de insumos: " .
                $e->getMessage();
        }
    }

    public static function importMovBySalesCons($systemUnitId, $data): string
    {
        global $pdo;

        try {
            // Inicia uma transação
            $pdo->beginTransaction();

            // Passo 1: Consulta os produtos vendidos (apenas para identificar os produtos)
            $stmt = $pdo->prepare("
                SELECT 
                    system_unit_id,
                    cod_material AS produto,
                    quantidade AS qtde,
                    data_movimento AS data
                FROM 
                    _bi_sales
                WHERE 
                    system_unit_id = :systemUnitId 
                    AND data_movimento = :data
            ");
            $stmt->execute([
                ":systemUnitId" => $systemUnitId,
                ":data" => $data,
            ]);

            $produtosVendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Se nenhum produto for encontrado, não faz nada
            if (empty($produtosVendas)) {
                $pdo->rollBack();
                return "Nenhuma movimentação encontrada para a unidade e data informadas.";
            }

            // Passo 2: Obter os IDs dos produtos vendidos
            $produtosVendidosIds = array_map(function ($produto) {
                return $produto["produto"]; // coleta o código do produto
            }, $produtosVendas);

            // Passo 3: Converter os produtos em insumos
            $placeholders = implode(
                ",",
                array_fill(0, count($produtosVendidosIds), "?")
            );
            $stmtInsumos = $pdo->prepare("
                SELECT DISTINCT insumo_id 
                FROM compositions 
                WHERE system_unit_id = ? 
                AND product_id IN ($placeholders)
            ");
            $stmtInsumos->execute(
                array_merge([$systemUnitId], $produtosVendidosIds)
            );

            $insumoIds = $stmtInsumos->fetchAll(PDO::FETCH_COLUMN);

            // Se nenhum insumo for encontrado, não faz nada
            if (empty($insumoIds)) {
                $pdo->rollBack();
                return "Nenhum insumo relacionado encontrado para os produtos vendidos.";
            }

            // Passo 4: Chamar a função `getInsumoConsumption` para calcular o consumo dos insumos
            $consumoInsumos = NecessidadesController::getInsumoConsumption(
                $systemUnitId,
                [$data],
                $insumoIds,
                "total"
            );
            //print_r($consumoInsumos);
            //exit;

            // Passo 5: Prepara o statement para inserir ou atualizar a tabela movimentacao
            $insertStmt = $pdo->prepare("
                INSERT INTO movimentacao (
                    system_unit_id, status, doc, tipo, tipo_mov, produto, seq, data, quantidade, usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status),
                    data = VALUES(data),
                    quantidade = VALUES(quantidade),
                    usuario_id = VALUES(usuario_id)
            ");

            // Usuário fictício para inserção
            $usuarioId = 5;
            $seq = 1;

            // Passo 6: Agora, insere os insumos com os dados calculados
            foreach ($consumoInsumos as $insumo) {
                // Gera o documento
                $doc = "v-" . str_replace("-", "", $data);

                // Insere ou atualiza a movimentação do insumo
                $insertStmt->execute([
                    $systemUnitId,
                    1, // status
                    $doc, // documento
                    "v", // Tipo "v" de venda
                    "saida", // Tipo de movimentação "saida"
                    $insumo["codigo"], // Insumo ID
                    $seq++, // Incrementa o seq
                    $data, // Data da movimentação
                    $insumo["sales"], // Quantidade calculada
                    $usuarioId, // ID do usuário
                ]);
            }

            // Confirma a transação
            $pdo->commit();
            return "Movimentações de insumos importadas com sucesso.";
        } catch (Exception $e) {
            // Reverte a transação em caso de erro
            $pdo->rollBack();
            return "Erro ao importar movimentações de insumos: " .
                $e->getMessage();
        }
    }

    public static function getDiferencasEstoque(
        $startDate,
        $endDate,
        array $systemUnitIds
    ): array
    {
        global $pdo;

        $response = [
            "parameters" => [
                "startDate" => $startDate,
                "endDate" => $endDate,
                "systemUnitIds" => $systemUnitIds,
            ],
            "status" => "success",
            "data" => [],
        ];

        try {
            // Query base para buscar as diferenças de estoque com as condições especificadas
            $sql = "
            SELECT
                d.system_unit_id,
                su.name AS nome_unidade,
                d.produto,
                d.nome_produto,
                (d.diferenca) * -1 AS diferenca,
                d.data,
                COALESCE(p.preco_custo, 0) AS preco_custo,
                ROUND(((d.diferenca * -1) * COALESCE(p.preco_custo, 0)), 2) AS perda_custo
            FROM
                diferencas_estoque d
            JOIN
                products p
            ON
                d.produto = p.codigo AND d.system_unit_id = p.system_unit_id
            JOIN
                system_unit su
            ON
                d.system_unit_id = su.id
            WHERE
                d.diferenca < 0
                AND d.system_unit_id = ?
                AND d.data BETWEEN ? AND ?
        ";

            // Prepara a consulta uma vez, para reutilização
            $stmt = $pdo->prepare($sql);

            foreach ($systemUnitIds as $systemUnitId) {
                // Executa a consulta para cada loja (system_unit_id)
                $stmt->execute([$systemUnitId, $startDate, $endDate]);

                // Adiciona os resultados ao array de dados
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($results)) {
                    $response["data"] = array_merge(
                        $response["data"],
                        $results
                    );
                }
            }
        } catch (Exception $e) {
            // Define o código de resposta HTTP para erro
            http_response_code(500);

            // Atualiza o status e adiciona a mensagem de erro no response
            $response["status"] = "error";
            $response["message"] = $e->getMessage();
        }

        return $response;
    }

    public static function gerarDocAjustePreco($system_unit_id): string
    {
        global $pdo;

        // Prefixo para os documentos
        $prefixo = 'ap';

        // Consulta o último documento gerado
        $sql = "SELECT doc FROM ajustes_preco_custo WHERE system_unit_id = :system_unit_id ORDER BY created_at DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        $ultimoDoc = $stmt->fetchColumn();

        // Gera o próximo documento
        if (preg_match("/^" . $prefixo . "-(\d+)$/", $ultimoDoc, $matches)) {
            $numero = (int) $matches[1] + 1;
            return $prefixo . "-" . str_pad($numero, 6, "0", STR_PAD_LEFT);
        }

        // Caso não haja registros anteriores, inicia com o primeiro documento
        return $prefixo . "-000001";
    }

    public static function ajustarPrecoCusto($system_unit_id, $ajuste_date, $itens, $usuario_id): array
    {
        global $pdo;

        try {

            $pdo->beginTransaction();

            // Gerar o documento de ajuste
            $doc = self::gerarDocAjustePreco($system_unit_id);

            foreach ($itens as $item) {
                $codigo = $item['codigo'];
                $precoAtual = $item['precoAtual'];
                $novoPreco = $item['novoPreco'];

                $sqlInsert = "INSERT INTO ajustes_preco_custo 
            (system_unit_id, doc, produto, preco_antigo, preco_atual, usuario_id, data_ajuste) 
            VALUES (:system_unit_id, :doc, :produto, :preco_antigo, :preco_atual, :usuario_id, :data_ajuste)";

                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':doc' => $doc,
                    ':produto' => $codigo,
                    ':preco_antigo' => $precoAtual,
                    ':preco_atual' => $novoPreco,
                    ':usuario_id' => $usuario_id,
                    ':data_ajuste' => $ajuste_date
                ]);

                $sqlUpdate = "UPDATE products 
            SET preco_custo = :preco_atual 
            WHERE system_unit_id = :system_unit_id AND codigo = :produto";

                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':preco_atual' => $novoPreco,
                    ':system_unit_id' => $system_unit_id,
                    ':produto' => $codigo
                ]);
            }

            $pdo->commit();

            // Retornar status e o documento gerado
            return [
                'status' => 'success',
                'message' => 'Ajustes de preço realizados com sucesso.',
                'doc' => $doc
            ];
        } catch (Exception $e) {

            $pdo->rollBack();

            return [
                'status' => 'error',
                'message' => 'Erro ao realizar ajustes de preço: ' . $e->getMessage()
            ];
        }
    }
    public static function gerarDocAjusteSaldo($system_unit_id): string
    {
        global $pdo;

        // Prefixo para os documentos
        $prefixo = 'as';

        // Consulta o último documento gerado
        $sql = "SELECT doc FROM ajustes_saldo WHERE system_unit_id = :system_unit_id ORDER BY created_at DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        $ultimoDoc = $stmt->fetchColumn();

        // Gera o próximo documento
        if (preg_match("/^" . $prefixo . "-(\d+)$/", $ultimoDoc, $matches)) {
            $numero = (int) $matches[1] + 1;
            return $prefixo . "-" . str_pad($numero, 6, "0", STR_PAD_LEFT);
        }

        // Caso não haja registros anteriores, inicia com o primeiro documento
        return $prefixo . "-000001";
    }

    public static function ajustarSaldo($system_unit_id, $ajuste_date, $itens, $usuario_id): array
    {
        global $pdo;

        try {

            $pdo->beginTransaction();

            // Gerar o documento de ajuste
            $doc = self::gerarDocAjusteSaldo($system_unit_id);

            foreach ($itens as $item) {
                $codigo = $item['codigo'];
                $saldoAtual = $item['saldoAtual'];
                $novoSaldo = $item['novoSaldo'];

                $sqlInsert = "INSERT INTO ajustes_saldo 
            (system_unit_id, doc, produto, saldo_antigo, saldo_atual, usuario_id, data_ajuste) 
            VALUES (:system_unit_id, :doc, :produto, :saldo_antigo, :saldo_atual, :usuario_id, :data_ajuste)";

                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':doc' => $doc,
                    ':produto' => $codigo,
                    ':saldo_antigo' => $saldoAtual,
                    ':saldo_atual' => $novoSaldo,
                    ':usuario_id' => $usuario_id,
                    ':data_ajuste' => $ajuste_date
                ]);

                $sqlUpdate = "UPDATE products 
            SET saldo = :saldo_atual, ultimo_doc = :doc
            WHERE system_unit_id = :system_unit_id AND codigo = :produto";

                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':saldo_atual' => $novoSaldo,
                    ':doc' => $doc,
                    ':system_unit_id' => $system_unit_id,
                    ':produto' => $codigo
                ]);
            }

            $pdo->commit();

            // Retornar status e o documento gerado
            return [
                'status' => 'success',
                'message' => 'Ajustes de saldo realizados com sucesso.',
                'doc' => $doc
            ];
        } catch (Exception $e) {

            $pdo->rollBack();

            return [
                'status' => 'error',
                'message' => 'Erro ao realizar ajustes de saldo: ' . $e->getMessage()
            ];
        }
    }




}

