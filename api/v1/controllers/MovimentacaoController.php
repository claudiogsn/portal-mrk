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
        global $pdo;

        try {
            // 🔎 Busca movimentações do documento informado
            $movimentacoes = self::getMovimentacao($systemUnitId, $doc);

            if (empty($movimentacoes)) {
                return [
                    "success" => false,
                    "message" => "Nenhuma movimentação encontrada para o documento informado.",
                ];
            }

            $pdo->beginTransaction();

            /**
             * 1️⃣ Aprova todas as movimentações do documento atual
             */
            $stmtUpdateAtual = $pdo->prepare("
            UPDATE movimentacao
               SET status = 1,
                   updated_at = CURRENT_TIMESTAMP
             WHERE system_unit_id = :unit
               AND doc = :doc
               AND status = 0
        ");
            $stmtUpdateAtual->execute([
                ':unit' => $systemUnitId,
                ':doc'  => $doc,
            ]);

            /**
             * 2️⃣ Para cada movimentação, aprova o PAR da transferência
             *     usando produto + seq + data + quantidade + usuario
             */
            foreach ($movimentacoes as $mov) {

                $tipo = $mov['tipo'] ?? null;
                if ($tipo !== 'ts' && $tipo !== 'te') {
                    continue;
                }

                $produto    = $mov['produto'];
                $seq        = $mov['seq'];
                $quantidade = (float)$mov['quantidade'];
                $dataMov    = $mov['data'];
                $usuarioId  = $mov['usuario_id'];

                if ($tipo === 'ts') {
                    // 🔴 TS → busca TE
                    $stmtPar = $pdo->prepare("
                    UPDATE movimentacao
                       SET status = 1,
                           updated_at = CURRENT_TIMESTAMP
                     WHERE system_unit_id = :unit_destino
                       AND status = 0
                       AND tipo = 'te'
                       AND produto = :produto
                       AND seq = :seq
                       AND quantidade = :quantidade
                       AND data = :data
                       AND usuario_id = :usuario
                ");

                    $stmtPar->execute([
                        ':unit_destino' => $mov['system_unit_id_destino'],
                        ':produto'      => $produto,
                        ':seq'          => $seq,
                        ':quantidade'   => $quantidade,
                        ':data'         => $dataMov,
                        ':usuario'      => $usuarioId,
                    ]);

                } elseif ($tipo === 'te') {
                    // 🟢 TE → busca TS
                    $stmtPar = $pdo->prepare("
                    UPDATE movimentacao
                       SET status = 1,
                           updated_at = CURRENT_TIMESTAMP
                     WHERE system_unit_id_destino = :unit_te
                       AND status = 0
                       AND tipo = 'ts'
                       AND produto = :produto
                       AND seq = :seq
                       AND quantidade = :quantidade
                       AND data = :data
                       AND usuario_id = :usuario
                ");

                    $stmtPar->execute([
                        ':unit_te'     => $mov['system_unit_id'],
                        ':produto'     => $produto,
                        ':seq'         => $seq,
                        ':quantidade'  => $quantidade,
                        ':data'        => $dataMov,
                        ':usuario'     => $usuarioId,
                    ]);
                }
            }

            /**
             * 3️⃣ Atualiza saldo SOMENTE para BALANÇO
             */
            $stmtSaldo = $pdo->prepare("
            UPDATE products
               SET saldo = :saldo,
                   ultimo_doc = :doc,
                   updated_at = CURRENT_TIMESTAMP
             WHERE system_unit_id = :unit
               AND codigo = :produto
        ");

            foreach ($movimentacoes as $mov) {
                $isBalanco =
                    (($mov['tipo'] ?? '') === 'b') ||
                    (($mov['tipo_mov'] ?? '') === 'balanco');

                if (!$isBalanco) continue;

                $stmtSaldo->execute([
                    ':saldo'   => (float)$mov['quantidade'],
                    ':doc'     => $doc,
                    ':unit'    => $systemUnitId,
                    ':produto' => $mov['produto'],
                ]);
            }

            $pdo->commit();

            return [
                "success" => true,
                "message" => "Transações efetivadas com sucesso.",
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

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
                    WHEN m.tipo = 'pr' THEN 'Produção'
                    ELSE 'Outro'
                END AS tipo_movimentacao,
                us.name as username,
                m.data
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
                m.system_unit_id,
                su_origem.name,
                m.system_unit_id_destino,
                su_destino.name,
                m.doc,
                m.tipo,
                us.name,
                m.data
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
    ): false|array {
        global $pdo;

        $query = "
        WITH ranked_movs AS (
            SELECT
                m.*,
                su_origem.name AS nome_unidade_origem,
                su_destino.name AS nome_unidade_destino,
                us.name AS username,
                ROW_NUMBER() OVER (PARTITION BY m.doc ORDER BY m.created_at DESC) AS rn
            FROM movimentacao m
            LEFT JOIN system_unit su_origem ON m.system_unit_id = su_origem.id
            LEFT JOIN system_unit su_destino ON m.system_unit_id_destino = su_destino.id
            LEFT JOIN system_users us ON m.usuario_id = us.id
            WHERE 
                m.system_unit_id = :system_unit_id
                AND m.status <> 3
                AND m.data BETWEEN :data_inicial AND :data_final
        )
        SELECT
            system_unit_id,
            CASE 
                WHEN status = 0 THEN 'Pendente'
                WHEN status = 1 THEN 'Efetivado'
                WHEN status = 3 THEN 'Rejeitado'
                ELSE 'Outro'
            END AS status,
            nome_unidade_origem,
            system_unit_id_destino,
            nome_unidade_destino,
            doc,
            CASE
                WHEN tipo = 'b' THEN 'Balanço'
                WHEN tipo = 'te' THEN 'Transferência de Entrada'
                WHEN tipo = 'ts' THEN 'Transferência de Saida'
                WHEN tipo = 'v' THEN 'Venda'
                WHEN tipo = 'p' THEN 'Perda'
                WHEN tipo = 'c' THEN 'Compra'
                ELSE 'Outro'
            END AS tipo_movimentacao,
            username,
            data,
            created_at
        FROM ranked_movs
        WHERE rn = 1
        ORDER BY created_at DESC
    ";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(":system_unit_id", $systemUnitId, PDO::PARAM_INT);
        $stmt->bindParam(":data_inicial", $data_inicial);
        $stmt->bindParam(":data_final", $data_final);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


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

    public static function incrementDoc($ultimoDoc, $prefixo): string
    {
        // Supondo que o formato do doc seja algo como "t-000001" ou "b-000001"
        if (preg_match("/^" . $prefixo . '-(\d+)$/', $ultimoDoc, $matches)) {
            $numero = (int) $matches[1] + 1;
            return $prefixo . "-" . str_pad($numero, 6, "0", STR_PAD_LEFT);
        }
        return $prefixo . "-000001";
    }

    // Métodos Específicos para Balanço
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

    public static function getLastBalance($system_unit_id, $produto)
    {
        global $pdo;

        $stmt = $pdo->prepare(
            "SELECT doc, produto, quantidade 
         FROM movimentacao 
         WHERE system_unit_id = :system_unit_id 
         AND tipo = 'b' 
         AND status = 1 
         AND produto = :produto 
         ORDER BY doc DESC 
         LIMIT 1"
        );
        $stmt->bindParam(":system_unit_id", $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(":produto", $produto, PDO::PARAM_STR);
        $stmt->execute();
        $movimentacao = $stmt->fetch(PDO::FETCH_ASSOC);

        return $movimentacao ?: [
            "doc" => "b-000000",
            "produto" => $produto,
            "quantidade" => 0
        ];
    }

    public static function getLastBalanceByMatriz($matriz_id, $produto): array
    {
        global $pdo;

        // Passo 1: Obter todas as unidades filiais da matriz
        $stmt = $pdo->prepare("SELECT unit_filial FROM system_unit_rel WHERE unit_matriz = ?");
        $stmt->execute([$matriz_id]);
        $filiais = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($filiais)) {
            return [
                "produto" => $produto,
                "quantidade" => 0
            ];
        }

        $quantidadeTotal = 0;

        // Passo 2: Buscar o último saldo de cada filial e somar
        foreach ($filiais as $system_unit_id) {
            $stmt = $pdo->prepare(
                "SELECT quantidade 
             FROM movimentacao 
             WHERE system_unit_id = :system_unit_id 
             AND tipo = 'b' 
             AND status = 1 
             AND produto = :produto 
             ORDER BY doc DESC 
             LIMIT 1"
            );
            $stmt->bindParam(":system_unit_id", $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(":produto", $produto, PDO::PARAM_STR);
            $stmt->execute();
            $mov = $stmt->fetch(PDO::FETCH_ASSOC);

            $quantidade = $mov ? floatval($mov["quantidade"]) : 0;
            $quantidadeTotal += $quantidade;
        }

        return [
            "produto" => $produto,
            "quantidade" => $quantidadeTotal
        ];
    }

    public static function getBalanceByDoc(int $system_unit_id, string $doc): array
    {
        global $pdo;

        if (!$system_unit_id || !$doc) {
            return [
                "success" => false,
                "message" => "Parâmetros obrigatórios ausentes."
            ];
        }

        try {
            // Consulta com joins para já trazer:
            // - Dados da unidade (system_unit)
            // - Dados do usuário (system_users)
            // - Dados dos itens (products + categorias)
            $sql = "
            SELECT 
                -- Cabeçalho / metadados
                m.doc,
                DATE(m.created_at) AS date_balance,
                MIN(m.created_at) OVER (PARTITION BY m.system_unit_id, m.doc) AS created_at_first,
                m.usuario_id,
                
                -- Usuário
                u.id              AS user_id,
                u.name            AS user_name,
                u.login           AS user_login,
                u.email           AS user_email,
                u.system_unit_id  AS user_system_unit_id,

                -- Unidade
                su.id                          AS unit_id,
                su.name                        AS unit_name,
                su.cnpj                        AS unit_cnpj,
                su.connection_name             AS unit_connection_name,
                su.custom_code                 AS unit_custom_code,
                su.intg_financeiro             AS unit_intg_financeiro,
                su.token_zig                   AS unit_token_zig,
                su.rede_zig                    AS unit_rede_zig,
                su.zig_integration_faturamento AS unit_zig_integration_faturamento,
                su.zig_integration_estoque     AS unit_zig_integration_estoque,
                su.menew_integration_estoque   AS unit_menew_integration_estoque,
                su.menew_integration_faturamento AS unit_menew_integration_faturamento,
                su.f360_integration            AS unit_f360_integration,
                su.status                      AS unit_status,

                -- Itens
                p.codigo        AS produto_codigo,
                p.nome          AS produto_nome,
                m.quantidade    AS quantidade,
                c.nome          AS categoria_nome

            FROM movimentacao m
            LEFT JOIN products p
                   ON p.codigo = m.produto
                  AND p.system_unit_id = m.system_unit_id
            LEFT JOIN categorias c
                   ON c.codigo = p.categoria
                  AND c.system_unit_id = m.system_unit_id
            LEFT JOIN system_users u
                   ON u.id = m.usuario_id
            LEFT JOIN system_unit su
                   ON su.id = m.system_unit_id
            WHERE m.system_unit_id = :system_unit_id
              AND m.doc = :doc
            ORDER BY p.nome ASC, p.codigo ASC
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindValue(':doc', $doc, PDO::PARAM_STR);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                return [
                    "success" => false,
                    "message" => "Balanço não encontrado."
                ];
            }

            // Cabeçalho (pega da primeira linha)
            $first = $rows[0];

            // Monta objeto da unidade (system_unit)
            $unit = [
                "id"                          => (int)$first["unit_id"],
                "name"                        => $first["unit_name"],
                "cnpj"                        => $first["unit_cnpj"],
                "custom_code"                 => $first["unit_custom_code"],
                "status"                      => isset($first["unit_status"]) ? (int)$first["unit_status"] : null,
            ];

            // Monta objeto do usuário (system_users)
            $user = [
                "id"              => isset($first["user_id"]) ? (int)$first["user_id"] : null,
                "name"            => $first["user_name"] ?? null,
                "login"           => $first["user_login"] ?? null,
                "email"           => $first["user_email"] ?? null,
                "system_unit_id"  => isset($first["user_system_unit_id"]) ? (int)$first["user_system_unit_id"] : null,
            ];

            // Itens
            $itens = [];
            $totalQuantidade = 0;

            foreach ($rows as $r) {
                $qtd = is_numeric($r["quantidade"]) ? (float)$r["quantidade"] : $r["quantidade"];

                // soma apenas quando for numérico
                if (is_numeric($r["quantidade"])) {
                    $totalQuantidade += (float)$r["quantidade"];
                }

                $itens[] = [
                    "codigo"     => isset($r["produto_codigo"]) ? (int)$r["produto_codigo"] : null,
                    "produto"    => $r["produto_nome"],
                    "quantidade" => $qtd,
                    "categoria"  => $r["categoria_nome"],
                ];
            }

            $response = [
                "success" => true,
                "balance" => [
                    "doc"          => $first["doc"],
                    // date_balance: data “lógica” do balanço; usei DATE(created_at) do movimento
                    "date_balance" => $first["date_balance"],
                    // created_at: primeira ocorrência do doc (caso haja várias linhas)
                    "created_at"   => $first["created_at_first"],
                    "unit"         => $unit,
                    "user"         => $user,
                    "itens"        => $itens,
                    "totals"       => [
                        "items_count" => count($itens),
                        "qty_sum"     => $totalQuantidade,
                    ],
                ],
            ];

            return $response;

        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Erro ao buscar balanço: " . $e->getMessage()
            ];
        }
    }

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
                $stmt = $pdo->prepare("INSERT INTO movimentacao (system_unit_id, system_unit_id_destino, doc, tipo, tipo_mov , produto, seq, data,data_original, quantidade, usuario_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ? , ?, ?, ?, ?)");
                $stmt->execute([
                    $system_unit_id,
                    $system_unit_id_destino,
                    $doc,
                    $tipo,
                    $tipo_mov,
                    $produto,
                    $seq,
                    $date_balance,
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

            $transferKey = UtilsController::uuidv4();


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
                $stmt = $pdo->prepare("
                    INSERT INTO movimentacao (
                        system_unit_id,
                        system_unit_id_destino,
                        system_unit_id_remetente,
                        doc,
                        doc_par,
                        transfer_key,
                        tipo,
                        tipo_mov,
                        produto,
                        seq,
                        data,
                        data_original,
                        quantidade,
                        usuario_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $system_unit_id,             // origem
                    $system_unit_id_destino,     // destino
                    $system_unit_id,             // ✅ remetente = a própria origem
                    $docSaida,                   // doc TS
                    $docEntrada,                 // ✅ par = doc TE
                    $transferKey,                // ✅ transfer_key igual
                    $tipo_saida_doc,             // 'ts'
                    $tipo_saida,                 // 'saida'
                    $produto,
                    $seq,
                    $transferDate,
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

                $stmt = $pdo->prepare("
                    INSERT INTO movimentacao (
                        system_unit_id,
                        system_unit_id_destino,
                        system_unit_id_remetente,
                        doc,
                        doc_par,
                        transfer_key,
                        tipo,
                        tipo_mov,
                        produto,
                        seq,
                        data,
                        data_original,
                        quantidade,
                        usuario_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $system_unit_id_destino,     // unit que está recebendo (TE)
                    $system_unit_id_destino,     // ✅ destino = a própria unit do TE
                    $system_unit_id,             // remetente = origem
                    $docEntrada,                 // doc TE
                    $docSaida,                   // ✅ par = doc TS
                    $transferKey,                // ✅ transfer_key igual
                    $tipo_entrada_doc,           // 'te'
                    $tipo_entrada,               // 'entrada'
                    $produto,
                    $seq,
                    $transferDate,
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
            // Rollback em caso de erro
            $pdo->rollBack();
            return [
                "success" => false,
                "message" => "Erro ao criar transferência: " . $e->getMessage(),
            ];
        }
    }

    public static function getTransferenciasComCustos(array $data): array
    {
        global $pdo;

        try {
            $unitId = isset($data['system_unit_id']) ? (int)$data['system_unit_id'] : 0;
            if (!$unitId) {
                return ["success" => false, "message" => "Informe system_unit_id."];
            }

            $dtInicio = self::parseDate($data['dt_inicio'] ?? null);
            $dtFim    = self::parseDate($data['dt_fim'] ?? null);
            if (!$dtInicio || !$dtFim) {
                return ["success" => false, "message" => "Informe dt_inicio e dt_fim (YYYY-MM-DD ou DD/MM/YYYY)."];
            }

            // =========================
            // 1) Buscar DOCs (ts/te) no período
            // =========================
            $where = [];
            $bind  = [
                ':unit_id'   => $unitId,
                ':dt_inicio' => $dtInicio,
                ':dt_fim'    => $dtFim,
            ];

            $where[] = "m.system_unit_id = :unit_id";
            $where[] = "m.tipo IN ('ts','te')";
            $where[] = "m.data BETWEEN :dt_inicio AND :dt_fim";

            if (isset($data['status']) && ($data['status'] === 0 || $data['status'] === '0' || $data['status'] === 1 || $data['status'] === '1')) {
                $where[] = "m.status = :status";
                $bind[':status'] = (int)$data['status'];
            }

            if (!empty($data['doc'])) {
                $where[] = "m.doc LIKE :doc";
                $bind[':doc'] = '%' . trim((string)$data['doc']) . '%';
            }

            if (!empty($data['tipo'])) {
                $tipo = strtolower(trim((string)$data['tipo']));
                if (in_array($tipo, ['ts','te'], true)) {
                    $where[] = "m.tipo = :tipo";
                    $bind[':tipo'] = $tipo;
                }
            }

            $whereSql = implode(" AND ", $where);

            // =========================
            // Cache de nomes das unidades
            // =========================
            $unitNameCache = [];

            $getUnitName = function (?int $id) use (&$unitNameCache, $pdo) {
                if (!$id) return null;
                if (isset($unitNameCache[$id])) return $unitNameCache[$id];

                $st = $pdo->prepare("SELECT name FROM system_unit WHERE id = :id LIMIT 1");
                $st->bindValue(':id', $id, PDO::PARAM_INT);
                $st->execute();

                $name = $st->fetchColumn();
                $unitNameCache[$id] = $name ? (string)$name : (string)$id;

                return $unitNameCache[$id];
            };

            // Aqui pegamos um resumo determinístico por doc.
            $sqlDocs = "
            SELECT
                m.doc,
                m.tipo_mov,
                MIN(m.data) AS data,
                MAX(m.status) AS status,
                MAX(m.system_unit_id_destino) AS system_unit_id_destino,
                MAX(m.system_unit_id_remetente) AS system_unit_id_remetente,
                GROUP_CONCAT(DISTINCT m.tipo ORDER BY m.tipo) AS tipos_presentes
            FROM movimentacao m
            WHERE {$whereSql}
            GROUP BY m.doc
            ORDER BY MIN(m.data) DESC, m.doc DESC
        ";

            $stmtDocs = $pdo->prepare($sqlDocs);
            foreach ($bind as $k => $v) {
                if (in_array($k, [':unit_id', ':status'], true)) $stmtDocs->bindValue($k, (int)$v, PDO::PARAM_INT);
                else $stmtDocs->bindValue($k, $v);
            }
            $stmtDocs->execute();
            $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

            if (!$docs) {
                return ["success" => true, "data" => []];
            }

            // =========================
            // 2) Buscar detalhes por doc (itens) + join em products
            // =========================
            $sqlItens = "
            SELECT
                m.doc,
                m.tipo,
                m.tipo_mov,              -- ✅ ADICIONADO
                m.seq,
                m.produto,
                m.quantidade,
                m.valor,
                m.data,
                m.status,
                m.system_unit_id,
                m.system_unit_id_destino,
                m.system_unit_id_remetente,

                p.nome AS product_nome,
                p.preco_custo AS product_preco_custo,
                p.und AS product_und
            FROM movimentacao m
            LEFT JOIN products p
                ON p.system_unit_id = m.system_unit_id
               AND p.codigo = CAST(m.produto AS UNSIGNED)
            WHERE m.system_unit_id = :unit_id
              AND m.doc = :doc
              AND m.tipo IN ('ts','te')
            ORDER BY m.seq ASC
        ";
            $stmtItens = $pdo->prepare($sqlItens);

            $out = [];

            foreach ($docs as $d) {
                $doc = (string)$d['doc'];

                $stmtItens->bindValue(':unit_id', $unitId, PDO::PARAM_INT);
                $stmtItens->bindValue(':doc', $doc);
                $stmtItens->execute();
                $rows = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

                $itens = [];
                $totalCusto = 0.0;

                foreach ($rows as $r) {
                    $qtd = (float)($r['quantidade'] ?? 0);

                    // custo unitário: products.preco_custo (se não existir, 0)
                    $precoCusto = (float)($r['product_preco_custo'] ?? 0);
                    $subtotalCusto = $qtd * $precoCusto;

                    $totalCusto += $subtotalCusto;

                    $itens[] = [
                        "tipo" => (string)$r["tipo"],
                        "tipo_mov" => (string)($r["tipo_mov"] ?? ""),
                        "seq" => (int)$r["seq"],
                        "produto" => (string)$r["produto"],
                        "product_nome" => (string)($r["product_nome"] ?? ""),
                        "product_und" => (string)($r["product_und"] ?? ""),
                        "quantidade" => $qtd,
                        "preco_custo" => $precoCusto,
                        "subtotal_custo" => $subtotalCusto
                    ];
                }

                // =========================
                // Ajuste: destino para TE
                // =========================
                $tiposPresentesArr = !empty($d["tipos_presentes"])
                    ? explode(",", (string)$d["tipos_presentes"])
                    : [];

                $temTE = in_array('te', $tiposPresentesArr, true);

                $destinoIdRaw   = $d["system_unit_id_destino"] !== null ? (int)$d["system_unit_id_destino"] : null;
                $remetenteId    = $d["system_unit_id_remetente"] !== null ? (int)$d["system_unit_id_remetente"] : null;

                // ✅ Se for TE, destino = a própria unidade do relatório (system_unit_id)
                $destinoId = $temTE ? $unitId : $destinoIdRaw;

                $out[] = [
                    "doc" => $doc,
                    "data" => (string)($d["data"] ?? ""),
                    "status" => (int)($d["status"] ?? 0),

                    "system_unit_id" => $unitId,
                    "system_unit_id_destino" => $destinoId,
                    "system_unit_id_remetente" => $remetenteId,

                    "system_unit_name" => $getUnitName($unitId),
                    "system_unit_destino_name" => $getUnitName($destinoId),
                    "system_unit_remetente_name" => $getUnitName($remetenteId),

                    "tipos_presentes" => $tiposPresentesArr,
                    "tipo_mov" => (string)($d["tipo_mov"] ?? ""),

                    "total_custo" => $totalCusto,
                    "itens" => $itens,
                ];
            }

            return ["success" => true, "data" => $out];

        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    private static function parseDate(?string $s): ?string
    {
        if (!$s) return null;
        $s = trim($s);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
            [$d, $m, $y] = explode('/', $s);
            return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
        }

        return null;
    }

    public static function importCompras($usuarioId, $produtos): array {
        global $pdo;

        try {
            $pdo->beginTransaction();

            $insertQuery = "
            INSERT INTO movimentacao (
                system_unit_id, status, doc, tipo, tipo_mov, produto, seq,
                data, data_emissao, data_original, quantidade, valor, usuario_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                data = VALUES(data),
                data_emissao = VALUES(data_emissao),
                data_original = VALUES(data_original),
                quantidade = VALUES(quantidade),
                valor = VALUES(valor),
                usuario_id = VALUES(usuario_id)
        ";

            $insertStmt = $pdo->prepare($insertQuery);

            foreach ($produtos as $produto) {
                $insertStmt->execute([
                    $produto["system_unit_id"],
                    1,
                    $produto["doc"],
                    "c",
                    "entrada",
                    $produto["produto"],
                    $produto["seq"],
                    $produto["data_entrada"],
                    $produto["data_emissao"] ?? $produto["data_entrada"],
                    $produto["data_entrada"],
                    $produto["qtde"],
                    $produto["valor"],
                    $usuarioId
                ]);
            }

            $pdo->commit();

            return [
                "success" => true,
                "message" => "Movimentações salvas com sucesso."
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public static function importMovBySales($systemUnitId, $data): string
    // NÃO USADA
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

    public static function importMovBySalesCons($systemUnitId, $data): array|string
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            // Passo 1: Obtem dados de consumo por insumo via método já existente
            $consumos = BiController::getSalesByInsumos($systemUnitId, $data, $data);

            if (isset($consumos['error'])) {
                $pdo->rollBack();
                return $consumos['error'];
            }

            // Passo 2: Prepara o insert/update
            $insertStmt = $pdo->prepare("
            INSERT INTO movimentacao (
                system_unit_id, status, doc, tipo, tipo_mov, produto, seq, data, data_original, quantidade, usuario_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                data = VALUES(data),
                data_original = VALUES(data_original),
                quantidade = VALUES(quantidade),
                usuario_id = VALUES(usuario_id)
        ");

            $usuarioId = 5;
            $seq = 1;
            $doc = "v-" . str_replace("-", "", $data);

            // Passo 3: Insere os dados de movimentação com base no consumo
            foreach ($consumos as $insumo) {
                $insertStmt->execute([
                    $systemUnitId,
                    1, // status
                    $doc,
                    "v", // tipo
                    "saida", // tipo_mov
                    $insumo["codigo_insumo"],
                    $seq++,
                    $data,
                    $data,
                    $insumo["sale_insumos"], // quantidade
                    $usuarioId,
                ]);
            }

            $pdo->commit();

            return [
                "status" => "success",
                "message" => "Movimentações de insumos importadas com sucesso.",
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            return [
                "status" => "erro",
                "message" => "Erro ao importar movimentações de insumos: " . $e->getMessage(),
            ];
        }
    }


    public static function getDiferencasEstoque(
        $startDate,
        $endDate,
        array $systemUnitIds,
        $tipo = 'detalhado'
    ): array
    {
        global $pdo;

        $response = [
            "parameters" => [
                "startDate" => $startDate,
                "endDate" => $endDate,
                "systemUnitIds" => $systemUnitIds,
                "tipo" => $tipo,
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
            saldo_anterior,
            entradas,
            saidas,
            contagem_ideal,
            contagem_realizada,
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
        ORDER BY d.system_unit_id, d.produto, d.data
        ";

            // Prepara a consulta uma vez, para reutilização
            $stmt = $pdo->prepare($sql);

            foreach ($systemUnitIds as $systemUnitId) {
                // Executa a consulta para cada loja (system_unit_id)
                $stmt->execute([$systemUnitId, $startDate, $endDate]);

                // Adiciona os resultados ao array de dados
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($results)) {
                    continue;
                }

                if ($tipo === 'resumido') {
                    $resumido = [];

                    // Agrupa por produto
                    foreach ($results as $row) {
                        $produto = $row['produto'];

                        if (!isset($resumido[$produto])) {
                            // Primeiro registro do produto (será o primeiro dia)
                            $resumido[$produto] = [
                                'system_unit_id' => $row['system_unit_id'],
                                'nome_unidade' => $row['nome_unidade'],
                                'produto' => $row['produto'],
                                'nome_produto' => $row['nome_produto'],
                                'saldo_anterior' => $row['saldo_anterior'], // Primeiro dia
                                'entradas' => 0,
                                'saidas' => 0,
                                'preco_custo' => $row['preco_custo'],
                                'ultima_contagem_realizada' => 0,
                            ];
                        }

                        // Soma entradas e saídas
                        $resumido[$produto]['entradas'] += $row['entradas'];
                        $resumido[$produto]['saidas'] += $row['saidas'];

                        // Guarda a última contagem realizada
                        $resumido[$produto]['ultima_contagem_realizada'] = $row['contagem_realizada'];
                    }

                    foreach ($resumido as &$prod) {
                        // Calcula contagem ideal (saldo inicial + entradas - saídas)
                        $prod['contagem_ideal'] = $prod['saldo_anterior'] + $prod['entradas'] - $prod['saidas'];

                        // Calcula diferença (contagem ideal - última contagem realizada)
                        $prod['diferenca'] = $prod['contagem_ideal'] - $prod['ultima_contagem_realizada'];

                        // Calcula perda de custo
                        $prod['perda_custo'] = round($prod['diferenca'] * $prod['preco_custo'], 2);

                        // Novo campo: balanco final (última contagem realizada)
                        $prod['contagem_realizada'] = $prod['ultima_contagem_realizada'];

                        // Remove campo temporário
                        unset($prod['ultima_contagem_realizada']);
                    }


                    $response["data"] = array_merge(
                        $response["data"],
                        array_values($resumido)
                    );
                } else {
                    // Mantém detalhado
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

    public static function ajustarPrecoCustoPorGrupo($grupo_id, $ajuste_date, $itens, $usuario_id): array
    {
        global $pdo;

        try {
            // 1. Obter as unidades do grupo
            $unidades = BiController::getUnitsByGroupMov($grupo_id);
            if (empty($unidades)) {
                throw new Exception('Nenhuma unidade encontrada para o grupo.');
            }

            $system_unit_ids = [];
            $unidadeProducao = null;

            foreach ($unidades as $unidade) {
                $system_unit_ids[] = $unidade['system_unit_id'];

                if (!$unidadeProducao && str_ends_with($unidade['name'], 'Produção')) {
                    $unidadeProducao = $unidade;
                }
            }

            if (!$unidadeProducao) {
                throw new Exception('Unidade com sufixo "Produção" não encontrada.');
            }

            // 2. Iniciar transação
            $pdo->beginTransaction();

            // 3. Gerar documento apenas para a unidade de produção
            $doc = self::gerarDocAjustePreco($unidadeProducao['system_unit_id']);

            // 4. Preparar INSERT para log de ajustes
            $stmtInsert = $pdo->prepare("
            INSERT INTO ajustes_preco_custo 
            (system_unit_id, doc, produto, preco_antigo, preco_atual, usuario_id, data_ajuste) 
            VALUES (:system_unit_id, :doc, :produto, :preco_antigo, :preco_atual, :usuario_id, :data_ajuste)
        ");

            // 5. Preparar UPDATE para todos os system_unit_id
            $placeholders = implode(',', array_fill(0, count($system_unit_ids), '?'));
            $stmtUpdate = $pdo->prepare("
            UPDATE products 
            SET preco_custo = ? 
            WHERE system_unit_id IN ($placeholders) AND codigo = ?
        ");

            // 6. Executar para cada item
            foreach ($itens as $item) {
                $codigo = $item['codigo'];
                $precoAtual = $item['precoAtual'];
                $novoPreco = $item['novoPreco'];

                // INSERT apenas na unidade de produção
                $stmtInsert->execute([
                    ':system_unit_id' => $unidadeProducao['system_unit_id'],
                    ':doc' => $doc,
                    ':produto' => $codigo,
                    ':preco_antigo' => $precoAtual,
                    ':preco_atual' => $novoPreco,
                    ':usuario_id' => $usuario_id,
                    ':data_ajuste' => $ajuste_date
                ]);

                // UPDATE para todas as unidades
                $paramsUpdate = array_merge([$novoPreco], $system_unit_ids, [$codigo]);
                $stmtUpdate->execute($paramsUpdate);
            }

            // 7. Commit
            $pdo->commit();

            return [
                'status' => 'success',
                'message' => 'Ajustes aplicados com sucesso.',
                'doc' => $doc,
                'unidade_producao' => [
                    'id' => $unidadeProducao['system_unit_id'],
                    'nome' => $unidadeProducao['name']
                ]
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            return [
                'status' => 'error',
                'message' => 'Erro ao aplicar ajustes: ' . $e->getMessage()
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

    public static function getDatesByDoc($system_unit_id, $doc): array
    {
        global $pdo;

        $sql = "SELECT data_original 
            FROM movimentacao
            WHERE system_unit_id = :system_unit_id 
              AND doc = :id 
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':system_unit_id', $system_unit_id);
        $stmt->bindParam(':id', $doc);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && isset($row['data_original'])) {
            $dataOriginal = $row['data_original'];
            $date = DateTime::createFromFormat('Y-m-d', $dataOriginal);

            if ($date) {
                $antes = clone $date;
                $depois = clone $date;

                $antes->modify('-1 day');
                $depois->modify('+1 day');

                return [
                    $antes->format('d/m/Y'),
                    $date->format('d/m/Y'),
                    $depois->format('d/m/Y'),
                ];
            }
        }

        return [];
    }

    public static function updateDataByDoc($system_unit_id, $doc, $data): bool
    {
        global $pdo;

        $dateObj = DateTime::createFromFormat('d/m/Y', $data);
        if (!$dateObj) {
            return false; // Data inválida
        }
        $dataFormatada = $dateObj->format('Y-m-d');

        $sql = "UPDATE movimentacao 
            SET data = :data 
            WHERE system_unit_id = :system_unit_id 
              AND doc = :doc";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data', $dataFormatada);
        $stmt->bindParam(':system_unit_id', $system_unit_id);
        $stmt->bindParam(':doc', $doc);

        return $stmt->execute();
    }

//    public static function extratoInsumo($systemUnitId, $produto, $dtInicio, $dtFim): array
//    {
//        try {
//            global $pdo;
//
//            // 1. Buscar saldo inicial (último balanço antes da data de início)
//            $stmt = $pdo->prepare("
//            SELECT doc, quantidade
//            FROM movimentacao
//            WHERE system_unit_id = :unitId
//              AND produto = :produto
//              AND tipo_mov = 'balanco'
//              AND status = 1
//              AND data < :data_inicio
//            ORDER BY data DESC, id DESC
//            LIMIT 1
//        ");
//            $stmt->execute([
//                ':unitId' => $systemUnitId,
//                ':produto' => $produto,
//                ':data_inicio' => $dtInicio
//            ]);
//            $row = $stmt->fetch(PDO::FETCH_ASSOC);
//            $saldoInicial = $row ? (float)$row['quantidade'] : 0;
//            $docInicial = $row ? $row['doc'] : null;
//
//            // 2. Buscar todas as movimentações no período
//            $stmt = $pdo->prepare("
//            SELECT data, tipo_mov, doc, quantidade
//            FROM movimentacao
//            WHERE system_unit_id = :unitId
//              AND produto = :produto
//              AND status = 1
//              AND data BETWEEN :data_inicio AND :data_fim
//            ORDER BY data, id
//        ");
//            $stmt->execute([
//                ':unitId' => $systemUnitId,
//                ':produto' => $produto,
//                ':data_inicio' => $dtInicio,
//                ':data_fim' => $dtFim
//            ]);
//            $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);
//
//            // 3. Agrupar por data
//            $dias = [];
//            foreach ($movs as $mov) {
//                $data = $mov['data'];
//                unset($mov['data']);
//                if (!isset($dias[$data])) {
//                    $dias[$data] = [
//                        'data' => $data,
//                        'movimentacoes' => [],
//                        'balanco' => null
//                    ];
//                }
//
//                if ($mov['tipo_mov'] === 'balanco') {
//                    $dias[$data]['balanco'] = [
//                        'doc' => $mov['doc'],
//                        'quantidade' => (float)$mov['quantidade']
//                    ];
//                } else {
//                    $dias[$data]['movimentacoes'][] = [
//                        'tipo_mov' => $mov['tipo_mov'],
//                        'doc' => $mov['doc'],
//                        'quantidade' => (float)$mov['quantidade']
//                    ];
//                }
//            }
//
//            // 4. Reordenar e calcular saldos
//            $extrato = [];
//            $saldoAtual = $saldoInicial;
//            $docAnterior = $docInicial;
//
//            foreach ($dias as $dia) {
//                $entradaTotal = 0;
//                $saidaTotal = 0;
//
//                foreach ($dia['movimentacoes'] as $m) {
//                    if ($m['tipo_mov'] === 'entrada') {
//                        $entradaTotal += $m['quantidade'];
//                    } elseif ($m['tipo_mov'] === 'saida') {
//                        $saidaTotal += $m['quantidade'];
//                    }
//                }
//
//                $saldoAtual += $entradaTotal - $saidaTotal;
//
//                $extrato[] = [
//                    'data' => $dia['data'],
//                    'saldo_anterior' => $docAnterior ? [
//                        'doc' => $docAnterior,
//                        'quantidade' => $saldoAtual + $saidaTotal - $entradaTotal
//                    ] : null,
//                    'movimentacoes' => $dia['movimentacoes'],
//                    'saldo_estimado' => $saldoAtual,
//                    'balanco' => $dia['balanco']
//                ];
//
//                if ($dia['balanco']) {
//                    $docAnterior = $dia['balanco']['doc'];
//                    $saldoAtual = $dia['balanco']['quantidade'];
//                }
//            }
//
//            return [
//                'saldo_inicial' => $saldoInicial,
//                'extrato' => $extrato
//            ];
//
//        } catch (Exception $e) {
//            return [
//                'error' => 'Erro interno: ' . $e->getMessage()
//            ];
//        }
//    }

    public static function extratoInsumo($systemUnitId, $produto, $dtInicio, $dtFim): array
    {
        try {
            global $pdo;

            // ===============================
            // 1) Último BALANÇO <= data inicial
            // ===============================
            $stmt = $pdo->prepare("
            SELECT data, doc, quantidade
            FROM movimentacao
            WHERE system_unit_id = :unitId
              AND produto       = :produto
              AND tipo_mov      = 'balanco'
              AND status        = 1
              AND data         < :data_inicio
            ORDER BY data DESC, id DESC
            LIMIT 1
        ");
            $stmt->execute([
                ':unitId'      => $systemUnitId,
                ':produto'     => $produto,
                ':data_inicio' => $dtInicio
            ]);
            $balanco = $stmt->fetch(PDO::FETCH_ASSOC);

            $saldoInicialPeriodo = 0.0;
            $dataBalanco         = null;

            if ($balanco) {
                $saldoInicialPeriodo = (float) $balanco['quantidade']; // saldo absoluto do balanço
                $dataBalanco         = $balanco['data'];
            }

            // ===============================
            // 2) Ajustar saldo inicial com movs entre BALANÇO e dtInicio
            //    (apenas para cálculo, não serão retornadas)
            // ===============================
            if ($dataBalanco !== null && $dataBalanco < $dtInicio) {
                $stmt = $pdo->prepare("
                SELECT data, tipo_mov, quantidade
                FROM movimentacao
                WHERE system_unit_id = :unitId
                  AND produto        = :produto
                  AND status         = 1
                  AND data          > :data_balanco
                  AND data          < :data_inicio
                  AND tipo_mov IN ('entrada', 'saida')
                ORDER BY data, id
            ");
                $stmt->execute([
                    ':unitId'       => $systemUnitId,
                    ':produto'      => $produto,
                    ':data_balanco' => $dataBalanco,
                    ':data_inicio'  => $dtInicio
                ]);

                $ajustes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($ajustes as $mov) {
                    $qtd = (float) $mov['quantidade'];
                    if ($mov['tipo_mov'] === 'entrada') {
                        $saldoInicialPeriodo += $qtd;
                    } elseif ($mov['tipo_mov'] === 'saida') {
                        $saldoInicialPeriodo -= $qtd;
                    }
                }
            }

            // ===============================
            // 3) Movimentações SOMENTE do PERÍODO solicitado
            // ===============================
            $stmt = $pdo->prepare("
            SELECT data, tipo_mov, doc, quantidade
            FROM movimentacao
            WHERE system_unit_id = :unitId
              AND produto        = :produto
              AND status         = 1
              AND data BETWEEN :data_inicio AND :data_fim
            ORDER BY data, id
        ");
            $stmt->execute([
                ':unitId'      => $systemUnitId,
                ':produto'     => $produto,
                ':data_inicio' => $dtInicio,
                ':data_fim'    => $dtFim
            ]);
            $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ===============================
            // 4) Agrupar por dia
            // ===============================
            $dias = [];
            foreach ($movs as $mov) {
                $data = $mov['data'];
                unset($mov['data']);

                if (!isset($dias[$data])) {
                    $dias[$data] = [
                        'data'          => $data,
                        'movimentacoes' => [],
                        'balanco'       => null, // último balanço do dia
                    ];
                }

                if ($mov['tipo_mov'] === 'balanco') {
                    // Sempre sobrescreve, ficando com o ÚLTIMO balanço do dia
                    $dias[$data]['balanco'] = [
                        'doc'        => $mov['doc'],
                        'quantidade' => (float) $mov['quantidade'],
                    ];
                } else {
                    $dias[$data]['movimentacoes'][] = [
                        'tipo_mov'   => $mov['tipo_mov'],
                        'doc'        => $mov['doc'],
                        'quantidade' => (float) $mov['quantidade'],
                    ];
                }
            }

            // ===============================
            // 5) Montar EXTRATO apenas do range pedido
            // ===============================
            $extrato    = [];
            $saldoAtual = $saldoInicialPeriodo;

            foreach ($dias as $dia) {
                $entradaTotal = 0.0;
                $saidaTotal   = 0.0;

                foreach ($dia['movimentacoes'] as $m) {
                    if ($m['tipo_mov'] === 'entrada') {
                        $entradaTotal += $m['quantidade'];
                    } elseif ($m['tipo_mov'] === 'saida') {
                        $saidaTotal += $m['quantidade'];
                    }
                }

                // Saldo no INÍCIO do dia (antes das movs)
                $saldoAntesDia = $saldoAtual;

                // Saldo teórico (se não houvesse balanço)
                $saldoTeorico = $saldoAntesDia + $entradaTotal - $saidaTotal;

                // Se houver BALANÇO no dia, o saldo final é o do balanço (ABSOLUTO)
                if ($dia['balanco']) {
                    $saldoFinalDia = (float) $dia['balanco']['quantidade'];
                } else {
                    $saldoFinalDia = $saldoTeorico;
                }

                $extrato[] = [
                    'data'           => $dia['data'],
                    // aqui só o valor numérico, sem doc
                    'saldo_anterior' => $saldoAntesDia,
                    'movimentacoes'  => $dia['movimentacoes'],
                    'saldo_estimado' => $saldoFinalDia,
                    'balanco'        => $dia['balanco'], // aqui você ainda vê o doc do balanço se quiser
                ];

                // Saldo para o próximo dia
                $saldoAtual = $saldoFinalDia;
            }

            return [
                'saldo_inicial' => $saldoInicialPeriodo,
                'extrato'       => $extrato,
            ];

        } catch (Exception $e) {
            return [
                'error' => 'Erro interno: ' . $e->getMessage(),
            ];
        }
    }

    public static function savePerdaItems($data): array
    {
        global $pdo;

        $requiredFields = ["system_unit_id", "itens", "user", "date_perda"];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return [
                    "success" => false,
                    "message" => "O campo '$field' é obrigatório.",
                ];
            }
        }

        if (!is_array($data["itens"]) || count($data["itens"]) === 0) {
            return [
                "success" => false,
                "message" => "É necessário incluir ao menos um item.",
            ];
        }

        $system_unit_id = $data["system_unit_id"];
        $itens = $data["itens"];
        $data_perda = $data["date_perda"];
        $usuario_id = $data["user"];

        $ultimoDoc = self::getLastMov($system_unit_id, "p");
        $doc = self::incrementDoc($ultimoDoc, "p");

        $tipo = "p";
        $tipo_mov = "saida";
        $status = 1;

        $itensSalvos = [];

        try {
            $pdo->beginTransaction();

            foreach ($itens as $item) {
                foreach (["codigo", "seq", "quantidade"] as $field) {
                    if (!isset($item[$field])) {
                        $pdo->rollBack();
                        return [
                            "success" => false,
                            "message" => "O campo '$field' é obrigatório para cada item.",
                        ];
                    }
                }

                $codigo = $item["codigo"];
                $seq = $item["seq"];
                $quantidade = $item["quantidade"];
                $valor = $item["valor"] ?? null;
                $motivo = $item["motivo"] ?? null;
                $foto = $item["foto"] ?? null;

                // Buscar nome e unidade do produto
                $stmtProd = $pdo->prepare("SELECT nome, und FROM products WHERE system_unit_id = :unit_id AND codigo = :codigo LIMIT 1");
                $stmtProd->execute([
                    ':unit_id' => $system_unit_id,
                    ':codigo' => $codigo
                ]);
                $produto = $stmtProd->fetch(PDO::FETCH_ASSOC);

                $nomeProduto = $produto['nome'] ?? 'Produto não encontrado';
                $unidade = $produto['und'] ?? '-';

                // Inserir na movimentação
                $stmt = $pdo->prepare("
                INSERT INTO movimentacao 
                (system_unit_id, system_unit_id_destino, status, doc, tipo, tipo_mov, produto, seq, data, data_original, valor, quantidade, usuario_id) 
                VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
                $stmt->execute([
                    $system_unit_id,
                    $status,
                    $doc,
                    $tipo,
                    $tipo_mov,
                    $codigo,
                    $seq,
                    $data_perda,
                    $data_perda,
                    $valor,
                    $quantidade,
                    $usuario_id,
                ]);

                if ($stmt->rowCount() == 0) {
                    $pdo->rollBack();
                    return [
                        "success" => false,
                        "message" => "Falha ao lançar perda do item $codigo.",
                    ];
                }

                // Salvar anexo se tiver motivo e foto
                if (!empty($motivo) && !empty($foto)) {
                    $stmtAnexo = $pdo->prepare("
                    INSERT INTO perda_anexos (doc, user, motivo, url)
                    VALUES (:doc, :user, :motivo, :url)
                ");
                    $stmtAnexo->execute([
                        ':doc' => $doc,
                        ':user' => $usuario_id,
                        ':motivo' => $motivo,
                        ':url' => $foto
                    ]);
                }

                $itensSalvos[] = [
                    'codigo'     => $codigo,
                    'nome'       => $nomeProduto,
                    'quantidade' => number_format($quantidade, 3, ',', '.'),
                    'unidade'    => $unidade
                ];
            }

            // Buscar nome da loja
            $stmtLoja = $pdo->prepare("SELECT name FROM system_unit WHERE id = :id");
            $stmtLoja->execute([':id' => $system_unit_id]);
            $loja = $stmtLoja->fetch(PDO::FETCH_ASSOC);
            $nomeLoja = $loja['name'] ?? 'Loja não encontrada';

            // Buscar nome do usuário
            $stmtUser = $pdo->prepare("SELECT name FROM system_users WHERE id = :id");
            $stmtUser->execute([':id' => $usuario_id]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $nomeUsuario = $usuario['name'] ?? 'Usuário não encontrado';

            $pdo->commit();

            return [
                "success" => true,
                "message" => "Perdas lançadas com sucesso.",
                "doc" => $doc,
                "unit_name" => $nomeLoja,
                "user_name" => $nomeUsuario,
                "datetime" => date('d/m/Y H:i:s'),
                "itens" => $itensSalvos
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            return [
                "success" => false,
                "message" => "Erro ao lançar perdas: " . $e->getMessage(),
            ];
        }
    }

    public static function getRelatorioPerdas(array $data)
    {
        global $pdo;

        try {
            $system_unit_id = isset($data['system_unit_id']) ? (int)$data['system_unit_id'] : 0;
            $data_inicial   = $data['data_inicial'] ?? null;
            $data_final     = $data['data_final']   ?? null;

            if (!$system_unit_id || !$data_inicial || !$data_final) {
                return [
                    'success' => false,
                    'message' => 'Parâmetros obrigatórios: system_unit_id, data_inicial, data_final'
                ];
            }

            // filtros opcionais
            $produto  = $data['produto']  ?? null; // código do produto (insumo)
            $doc      = $data['doc']      ?? null; // documento específico

            $sql = "
            SELECT
                m.id,
                m.system_unit_id,
                m.doc,
                m.tipo,
                m.tipo_mov,
                m.produto              AS codigo_produto,
                m.seq,
                m.data,
                m.data_emissao,
                m.data_original,
                m.quantidade,
                m.valor,
                m.usuario_id,              -- ainda retorna o ID
                u.name             AS usuario_nome, -- novo campo com o nome do usuário
                p.nome             AS nome_produto,
                p.und              AS unidade,
                p.preco_custo
            FROM movimentacao m
            LEFT JOIN products p
                   ON p.system_unit_id = m.system_unit_id
                  AND p.codigo         = m.produto
            LEFT JOIN system_users u
                   ON u.id             = m.usuario_id
            WHERE m.tipo           = 'p'
              AND m.system_unit_id = :unit_id
              AND m.status         = 1
              AND m.data BETWEEN :data_inicial AND :data_final
        ";

            $params = [
                ':unit_id'      => $system_unit_id,
                ':data_inicial' => $data_inicial,
                ':data_final'   => $data_final,
            ];

            if (!empty($produto)) {
                $sql .= " AND m.produto = :produto";
                $params[':produto'] = $produto;
            }

            if (!empty($doc)) {
                $sql .= " AND m.doc = :doc";
                $params[':doc'] = $doc;
            }

            $sql .= " ORDER BY m.data, m.doc, m.seq";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data'    => $rows,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao gerar relatório de perdas: ' . $e->getMessage(),
            ];
        }
    }

    public static function extratoCopEntreBalancos(int $systemUnitId, string $dtInicio, string $dtFim): array
    {
        try {
            global $pdo;

            // 1) balanços no período
            $sqlBalancos = "
            SELECT m.doc, MAX(m.data) AS data_ref
            FROM movimentacao m
            WHERE m.system_unit_id = :unitId
              AND m.status = 1
              AND (m.tipo_mov = 'balanco' OR m.tipo = 'b')
              AND m.data BETWEEN :ini AND :fim
            GROUP BY m.doc
            ORDER BY data_ref ASC
        ";
            $stmt = $pdo->prepare($sqlBalancos);
            $stmt->execute([
                ':unitId' => $systemUnitId,
                ':ini'    => $dtInicio,
                ':fim'    => $dtFim,
            ]);
            $balancos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($balancos) < 2) {
                return [
                    'error' => 'Período informado precisa conter pelo menos dois balanços.',
                    'detalhe' => [
                        'qtde_balancos_no_periodo' => count($balancos),
                        'periodo' => [$dtInicio, $dtFim],
                    ]
                ];
            }

            $primeiro = $balancos[0];
            $ultimo   = $balancos[count($balancos) - 1];

            $docInicial = $primeiro['doc'];
            $docFinal   = $ultimo['doc'];
            $dataInicialRef = $primeiro['data_ref'];
            $dataFinalRef   = $ultimo['data_ref'];

            // 2) Itens do BALANÇO INICIAL (COP=1)
            $sqlIniItens = "
            SELECT 
                m.produto,
                SUM(m.quantidade) AS quantidade,
                p.nome AS nome_produto,
                c.codigo AS categoria_id,
                c.nome AS nome_categoria,
                p.und AS unidade,
                p.preco_custo AS custo_unitario         -- ADIÇÃO: pegar custo do produto
            FROM movimentacao m
            INNER JOIN products p 
                ON p.codigo = m.produto 
               AND p.system_unit_id = m.system_unit_id
            INNER JOIN categorias c 
                ON c.codigo = p.categoria 
               AND c.system_unit_id = p.system_unit_id
            WHERE m.system_unit_id = :unitId
              AND m.status = 1
              AND (m.tipo_mov = 'balanco' OR m.tipo = 'b')
              AND m.doc = :docIni
              AND p.cop = 1
            GROUP BY m.produto, p.nome, c.codigo, c.nome, p.und, p.preco_custo  -- ADIÇÃO: incluir no GROUP BY
        ";
            $stmt = $pdo->prepare($sqlIniItens);
            $stmt->execute([
                ':unitId' => $systemUnitId,
                ':docIni' => $docInicial,
            ]);
            $iniItens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $mapIni = [];
            foreach ($iniItens as $row) {
                $mapIni[$row['produto']] = [
                    'produto'         => (int)$row['produto'],
                    'nome_produto'    => $row['nome_produto'],
                    'unidade'         => $row['unidade'],
                    'categoria_id'    => (int)$row['categoria_id'],
                    'nome_categoria'  => $row['nome_categoria'],
                    'saldo_inicial'   => (float)$row['quantidade'],
                    'custo_unitario'  => $row['custo_unitario'] !== null ? (float)$row['custo_unitario'] : null,  // ADIÇÃO
                ];
            }

            // 3) Itens do BALANÇO FINAL (COP=1)
            $sqlFimItens = "
            SELECT 
                m.produto,
                SUM(m.quantidade) AS quantidade,
                p.nome AS nome_produto,
                c.codigo AS categoria_id,
                c.nome AS nome_categoria,
                p.und AS unidade,
                p.preco_custo AS custo_unitario         -- ADIÇÃO
            FROM movimentacao m
            INNER JOIN products p 
                ON p.codigo = m.produto 
               AND p.system_unit_id = m.system_unit_id
            INNER JOIN categorias c 
                ON c.codigo = p.categoria 
               AND c.system_unit_id = p.system_unit_id
            WHERE m.system_unit_id = :unitId
              AND m.status = 1
              AND (m.tipo_mov = 'balanco' OR m.tipo = 'b')
              AND m.doc = :docFim
              AND p.cop = 1
            GROUP BY m.produto, p.nome, c.codigo, c.nome, p.und, p.preco_custo  -- ADIÇÃO
        ";
            $stmt = $pdo->prepare($sqlFimItens);
            $stmt->execute([
                ':unitId' => $systemUnitId,
                ':docFim' => $docFinal,
            ]);
            $fimItens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $mapFim = [];
            foreach ($fimItens as $row) {
                $mapFim[$row['produto']] = [
                    'saldo_final_balanco' => (float)$row['quantidade'],
                    'nome_produto'        => $row['nome_produto'],
                    'categoria_id'        => (int)$row['categoria_id'],
                    'nome_categoria'      => $row['nome_categoria'],
                    'unidade'             => $row['unidade'],
                    'custo_unitario'      => $row['custo_unitario'] !== null ? (float)$row['custo_unitario'] : null, // ADIÇÃO
                ];
            }

            // 4) União dos produtos (inicial ∪ final)
            $produtosAlvo = array_values(array_unique(array_merge(
                array_keys($mapIni),
                array_keys($mapFim)
            )));

            if (empty($produtosAlvo)) {
                return [
                    'message' => "Sem itens COP=1 nos balanços selecionados.",
                    'janela' => [
                        'data_inicial_balanco' => $dataInicialRef,
                        'doc_inicial'          => $docInicial,
                        'data_final_balanco'   => $dataFinalRef,
                        'doc_final'            => $docFinal,
                    ],
                    'itens' => []
                ];
            }

            // 5) Somatório de ENTRADAS/SAÍDAS na janela (exclui o balanço inicial)
            $dIniDate = substr($dataInicialRef, 0, 10); // 'YYYY-MM-DD'
            $dFimDate = substr($dataFinalRef,   0, 10); // 'YYYY-MM-DD'

            $placeholders = [];
            $bind = [
                ':unitId'   => $systemUnitId,
                ':dIniDate' => $dIniDate,
                ':dFimDate' => $dFimDate,
            ];
            foreach ($produtosAlvo as $i => $codigoProd) {
                $ph = ":p{$i}";
                $placeholders[] = $ph;
                $bind[$ph] = $codigoProd;
            }

            $sqlMovs = "
                SELECT 
                    m.produto,
                    SUM(CASE WHEN m.tipo_mov = 'entrada' THEN m.quantidade ELSE 0 END) AS entradas,
                    SUM(CASE WHEN m.tipo_mov = 'saida'   THEN m.quantidade ELSE 0 END) AS saidas
                FROM movimentacao m
                WHERE m.system_unit_id = :unitId
                  AND m.status = 1
                  -- ❗ Exclui o dia do balanço inicial e inclui o dia do balanço final
                  AND DATE(m.data) >= DATE_ADD(:dIniDate, INTERVAL 1 DAY)
                  AND DATE(m.data) <= :dFimDate
                  AND m.tipo_mov IN ('entrada','saida')
                  AND m.produto IN (" . implode(',', $placeholders) . ")
                GROUP BY m.produto
            ";
            $stmt = $pdo->prepare($sqlMovs);
            $stmt->execute($bind);
            $movSomas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $mapMov = [];
            foreach ($movSomas as $row) {
                $mapMov[$row['produto']] = [
                    'entradas' => (float)($row['entradas'] ?? 0),
                    'saidas'   => (float)($row['saidas']   ?? 0),
                ];
            }

            // 6) Montagem dos itens (com custo e valor_diferenca)
            $itens = [];
            foreach ($produtosAlvo as $codigoProd) {
                $ini   = $mapIni[$codigoProd] ?? null;
                $fim   = $mapFim[$codigoProd] ?? null;
                $mov   = $mapMov[$codigoProd] ?? ['entradas' => 0.0, 'saidas' => 0.0];

                $nomeProduto   = $ini['nome_produto']   ?? ($fim['nome_produto']   ?? null);
                $categoriaId   = $ini['categoria_id']   ?? ($fim['categoria_id']   ?? null);
                $nomeCategoria = $ini['nome_categoria'] ?? ($fim['nome_categoria'] ?? null);

                $saldoInicial  = $ini['saldo_inicial'] ?? 0.0;
                $entradas      = $mov['entradas'];
                $saidas        = $mov['saidas'];
                $saldoEsperado = $saldoInicial + $entradas - $saidas;
                $saldoFinalBalanco = $fim['saldo_final_balanco'] ?? null;
                $divergencia   = isset($saldoFinalBalanco) ? ($saldoFinalBalanco - $saldoEsperado) : null;

                // ADIÇÃO: custo unitário e valor diferença
                $custoUnit = $ini['custo_unitario'] ?? ($fim['custo_unitario'] ?? null);
                $valorDif  = (isset($divergencia) && isset($custoUnit))
                    ? ($divergencia * (float)$custoUnit)
                    : null;

                $itens[] = [
                    'produto'              => (int)$codigoProd,
                    'nome_produto'         => $nomeProduto,
                    'categoria_id'         => $categoriaId,
                    'nome_categoria'       => $nomeCategoria,
                    'unidade'              => $ini['unidade'] ?? ($fim['unidade'] ?? null),
                    'saldo_inicial'        => $saldoInicial,
                    'entradas'             => $entradas,
                    'saidas'               => $saidas,
                    'saldo_esperado'       => $saldoEsperado,
                    'saldo_final_balanco'  => $saldoFinalBalanco,
                    'divergencia'          => $divergencia,
                    'custo_unitario'       => $custoUnit,   // ADIÇÃO
                    'valor_diferenca'      => $valorDif,    // ADIÇÃO
                ];
            }

            // 7) Mensagem e retorno
            $fmtBR = function(string $isoDate) {
                $d = substr($isoDate, 0, 10);
                [$Y,$M,$D] = explode('-', $d);
                return "{$D}/{$M}/{$Y}";
            };

            $mensagem = sprintf(
                "Usando período %s (%s) à %s (%s).",
                $fmtBR($dataInicialRef), $docInicial,
                $fmtBR($dataFinalRef),   $docFinal
            );

            return [
                'mensagem' => $mensagem,
                'janela' => [
                    'data_inicial_balanco' => $dataInicialRef,
                    'doc_inicial'          => $docInicial,
                    'data_final_balanco'   => $dataFinalRef,
                    'doc_final'            => $docFinal,
                ],
                'itens' => $itens,
            ];

        } catch (Exception $e) {
            return ['error' => 'Erro interno: ' . $e->getMessage()];
        }
    }

    private static function buildCurvaABC(array $rows): array
    {
        // Normaliza e garante tipos
        $itens = [];
        $total = 0.0;

        foreach ($rows as $r) {
            $codigo = isset($r['codigo']) ? (int)$r['codigo'] : 0;
            if ($codigo <= 0) continue;

            $nome = (string)($r['nome'] ?? '');
            $qtd  = (float)($r['quantidade'] ?? 0);
            $val  = (float)($r['valor'] ?? 0);

            $total += $val;

            $itens[] = [
                "codigo" => $codigo,
                "nome" => $nome,
                "quantidade" => $qtd,
                "valor" => (float)round($val, 2),
            ];
        }

        // Ordena por valor desc (maior impacto primeiro)
        usort($itens, fn($a, $b) => $b['valor'] <=> $a['valor']);

        $acum = 0.0;

        $resumo = [
            "A" => ["valor" => 0.0, "itens" => 0],
            "B" => ["valor" => 0.0, "itens" => 0],
            "C" => ["valor" => 0.0, "itens" => 0],
        ];

        foreach ($itens as &$item) {
            $valor = (float)$item["valor"];
            $pct = ($total > 0) ? ($valor / $total) * 100.0 : 0.0;

            $acum += $pct;

            // Regra: A até 80% acumulado, B até 95%, C resto
            $classe = "C";
            if ($acum <= 80.0) $classe = "A";
            else if ($acum <= 95.0) $classe = "B";

            $item["percentual_total"] = (float)round($pct, 4);
            $item["percentual_acumulado"] = (float)round($acum, 4);
            $item["classe"] = $classe;

            $resumo[$classe]["valor"] += $valor;
            $resumo[$classe]["itens"] += 1;
        }
        unset($item);

        // arredonda resumo
        foreach (["A","B","C"] as $k) {
            $resumo[$k]["valor"] = (float)round($resumo[$k]["valor"], 2);
        }

        return [
            "total_valor" => (float)round($total, 2),
            "itens" => $itens,
            "resumo" => $resumo,
        ];
    }

    public static function getCurvaABCFaturamento($data): array
    {
        global $pdo;

        try {
            $system_unit_id = (int)($data['system_unit_id'] ?? $data['unit_id'] ?? 0);
            $dt_inicio = (string)($data['dt_inicio'] ?? '');
            $dt_fim    = (string)($data['dt_fim'] ?? '');

            if ($system_unit_id <= 0) {
                return ["success" => false, "message" => "system_unit_id inválido."];
            }
            if (!$dt_inicio || !$dt_fim) {
                return ["success" => false, "message" => "dt_inicio e dt_fim são obrigatórios (YYYY-MM-DD)."];
            }

            // Busca agregada de vendas
            $stmt = $pdo->prepare("
            SELECT
                s.cod_material AS codigo,
                COALESCE(p.nome, CONCAT('Produto ', s.cod_material)) AS nome,
                SUM(s.quantidade) AS quantidade,
                SUM(s.valor_liquido) AS valor
            FROM _bi_sales s
            LEFT JOIN products p
                   ON p.system_unit_id = s.system_unit_id
                  AND p.codigo = s.cod_material
            WHERE s.system_unit_id = :unit
              AND DATE(s.data_movimento) BETWEEN :dt_inicio AND :dt_fim
            GROUP BY s.cod_material, p.nome
        ");

            $stmt->execute([
                ":unit" => $system_unit_id,
                ":dt_inicio" => $dt_inicio,
                ":dt_fim" => $dt_fim
            ]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // monta curva ABC
            $abc = self::buildCurvaABC($rows);

            return [
                "success" => true,
                "tipo" => "FATURAMENTO",
                "dt_inicio" => $dt_inicio,
                "dt_fim" => $dt_fim,
                "total_valor" => $abc["total_valor"], // total faturamento período
                "itens" => $abc["itens"],             // lista com % e classe
                "resumo" => $abc["resumo"],           // totais por A/B/C
            ];

        } catch (Exception $e) {
            return ["success" => false, "message" => "Erro Curva ABC Faturamento: " . $e->getMessage()];
        }
    }

    public static function getCurvaABCCompras($data): array
    {
        global $pdo;

        try {
            $system_unit_id = (int)($data['system_unit_id'] ?? $data['unit_id'] ?? 0);
            $dt_inicio = (string)($data['dt_inicio'] ?? '');
            $dt_fim    = (string)($data['dt_fim'] ?? '');

            // opcional: permitir filtrar por tipos específicos de compra (se você usa isso)
            $tipos_incluir = $data['tipos_incluir'] ?? null; // ex: ["c","nfe"]
            $tipos_excluir = $data['tipos_excluir'] ?? ["b","t","v","p","pr"]; // default: exclui tipos comuns que NÃO são compra

            if ($system_unit_id <= 0) return ["success" => false, "message" => "system_unit_id inválido."];
            if (!$dt_inicio || !$dt_fim) return ["success" => false, "message" => "dt_inicio e dt_fim são obrigatórios (YYYY-MM-DD)."];

            $whereTipos = "";
            $params = [
                ":unit" => $system_unit_id,
                ":dt_inicio" => $dt_inicio,
                ":dt_fim" => $dt_fim,
            ];

            if (is_array($tipos_incluir) && count($tipos_incluir) > 0) {
                $in = [];
                foreach ($tipos_incluir as $i => $t) {
                    $k = ":tin_$i";
                    $in[] = $k;
                    $params[$k] = (string)$t;
                }
                $whereTipos = " AND m.tipo IN (" . implode(",", $in) . ") ";
            } else if (is_array($tipos_excluir) && count($tipos_excluir) > 0) {
                $notIn = [];
                foreach ($tipos_excluir as $i => $t) {
                    $k = ":tex_$i";
                    $notIn[] = $k;
                    $params[$k] = (string)$t;
                }
                $whereTipos = " AND m.tipo NOT IN (" . implode(",", $notIn) . ") ";
            }

            $stmt = $pdo->prepare("
            SELECT
                CAST(m.produto AS UNSIGNED) AS codigo,
                COALESCE(p.nome, CONCAT('Produto ', m.produto)) AS nome,
                SUM(m.quantidade) AS quantidade,
                SUM(COALESCE(m.valor,0) * m.quantidade) AS valor
            FROM movimentacao m
            LEFT JOIN products p
                   ON p.system_unit_id = m.system_unit_id
                  AND p.codigo = CAST(m.produto AS UNSIGNED)
            WHERE m.system_unit_id = :unit
              AND m.status = 1
              AND m.tipo_mov = 'entrada'
              AND m.data BETWEEN :dt_inicio AND :dt_fim
              AND m.valor IS NOT NULL
              AND m.valor > 0
              AND m.produto REGEXP '^[0-9]+$'
              {$whereTipos}
            GROUP BY CAST(m.produto AS UNSIGNED), p.nome
        ");

            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $abc = self::buildCurvaABC($rows);

            return [
                "success" => true,
                "tipo" => "COMPRAS",
                "dt_inicio" => $dt_inicio,
                "dt_fim" => $dt_fim,
                "total_valor" => $abc["total_valor"],
                "resumo" => $abc["resumo"],
                "itens" => $abc["itens"],
            ];

        } catch (Exception $e) {
            return ["success" => false, "message" => "Erro Curva ABC Compras: " . $e->getMessage()];
        }
    }











}

