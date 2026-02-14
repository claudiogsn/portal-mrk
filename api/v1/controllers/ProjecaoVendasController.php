<?php

require_once __DIR__ . '/../database/db.php';

class ProjecaoVendasController
{
    /**
     * RETORNA A GRID PARA O FRONTEND
     * Retorna: [ 10104 => ['segunda' => ['id'=>1, 'qty'=>5], 'terca' => ...], ... ]
     * Onde 10104 é o CÓDIGO do produto.
     */
    public static function getGrid($system_unit_id, $productCodigos = [])
    {
        global $pdo;

        $sql = "SELECT id, product_codigo, day_of_week, quantity 
                FROM product_daily_projections 
                WHERE system_unit_id = ? 
                AND deleted_at IS NULL";

        $params = [$system_unit_id];

        if (!empty($productCodigos)) {
            // Cria placeholders ?,?,? baseado na quantidade de códigos
            $placeholders = implode(',', array_fill(0, count($productCodigos), '?'));
            $sql .= " AND product_codigo IN ($placeholders)";
            $params = array_merge($params, $productCodigos);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organiza array usando o CÓDIGO como chave
        $grid = [];
        foreach ($rows as $row) {
            $codigo = $row['product_codigo'];
            $dia    = $row['day_of_week'];

            if (!isset($grid[$codigo])) {
                $grid[$codigo] = [];
            }

            $grid[$codigo][$dia] = [
                'id'       => $row['id'],
                'quantity' => (float)$row['quantity']
            ];
        }

        return $grid;
    }

    /**
     * SALVAR EM MASSA (CRIAR OU EDITAR)
     * Transação segura para inserção de múltiplos registros.
     * Espera formato:
     * [
     * ['product_codigo' => 10104, 'day' => 'segunda', 'qty' => 10],
     * ...
     * ]
     */
    public static function saveBatch($system_unit_id, array $dados): array
    {
        global $pdo;

        if (empty($dados)) {
            return ['success' => false, 'message' => 'Nenhum dado enviado.'];
        }

        try {
            $pdo->beginTransaction();

            // UPSERT usando product_codigo
            $sql = "INSERT INTO product_daily_projections 
                    (system_unit_id, product_codigo, day_of_week, quantity, deleted_at) 
                    VALUES (:unit, :codigo, :day, :qtd, NULL)
                    ON DUPLICATE KEY UPDATE 
                        quantity = VALUES(quantity),
                        deleted_at = NULL,
                        updated_at = NOW()";

            $stmt = $pdo->prepare($sql);

            foreach ($dados as $item) {
                // Valida se os campos existem (note o product_codigo)
                if (!isset($item['product_codigo'], $item['day'], $item['qty'])) {
                    continue;
                }

                $stmt->execute([
                    ':unit'   => $system_unit_id,
                    ':codigo' => $item['product_codigo'],
                    ':day'    => $item['day'],
                    ':qtd'    => $item['qty']
                ]);
            }

            $pdo->commit();
            return ['success' => true, 'message' => 'Projeções salvas com sucesso.'];

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("[PROJECAO_VENDAS] Erro Batch: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao salvar dados.'];
        }
    }

    /**
     * SALVAR UM ÚNICO ITEM
     * Wrapper para o saveBatch
     */
    public static function saveItem($system_unit_id, $product_codigo, $day_of_week, $quantity): array
    {
        return self::saveBatch($system_unit_id, [
            [
                'product_codigo' => $product_codigo,
                'day'            => $day_of_week,
                'qty'            => $quantity
            ]
        ]);
    }

    /**
     * SOFT DELETE PELO ID (PK da tabela projeção)
     * Esse método não muda, pois apaga pela ID da linha da projeção
     */
    public static function delete($id): array
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("UPDATE product_daily_projections SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Projeção removida.'];
            } else {
                return ['success' => false, 'message' => 'Item não encontrado.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao deletar: ' . $e->getMessage()];
        }
    }

    /**
     * LIMPAR PROJEÇÕES DE UM PRODUTO ESPECÍFICO (Pelo CÓDIGO)
     */
    public static function clearProductProjections($system_unit_id, $product_codigo): array
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("UPDATE product_daily_projections SET deleted_at = NOW() WHERE system_unit_id = ? AND product_codigo = ?");
            $stmt->execute([$system_unit_id, $product_codigo]);
            return ['success' => true, 'message' => 'Projeções do produto limpas.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao limpar produto.'];
        }
    }

    /**
     * RETORNA LISTA DE PRODUTOS COM PROJEÇÕES FORMATADA
     * Formato: [ {codigo: 100, nome: 'Carne', segunda: 10, terca: 0...}, ... ]
     */
    public static function getProjeccoes($system_unit_id): array
    {
        global $pdo;

        // INNER JOIN garante que só retorna produtos que tenham registro na tabela de projeção
        $sql = "
            SELECT 
                p.codigo,
                p.nome,
                proj.day_of_week,
                proj.quantity
            FROM product_daily_projections proj
            INNER JOIN products p 
                ON p.codigo = proj.product_codigo 
                AND p.system_unit_id = proj.system_unit_id
            WHERE proj.system_unit_id = ? 
              AND proj.deleted_at IS NULL
            ORDER BY p.nome ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$system_unit_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];

        // Template para garantir que todos os dias existam no JSON final
        $diasPadrao = [
            'segunda' => 0,
            'terca'   => 0,
            'quarta'  => 0,
            'quinta'  => 0,
            'sexta'   => 0,
            'sabado'  => 0,
            'domingo' => 0
        ];

        foreach ($rows as $row) {
            $codigo = $row['codigo'];
            $dia    = $row['day_of_week'];
            $qtd    = (float)$row['quantity'];

            // Se o produto ainda não está no array, inicializa ele
            if (!isset($resultado[$codigo])) {
                $resultado[$codigo] = array_merge(
                    [
                        'codigo' => $codigo,
                        'nome'   => $row['nome']
                    ],
                    $diasPadrao // Adiciona os dias zerados
                );
            }

            // Atualiza o valor do dia específico que veio do banco
            // Se o dia não vier do banco, ele mantém o 0 do $diasPadrao
            if (array_key_exists($dia, $diasPadrao)) {
                $resultado[$codigo][$dia] = $qtd;
            }
        }

        // Retorna apenas os valores (remove as chaves associativas do código)
        return array_values($resultado);
    }


    /**
     * PROJEÇÃO: Calcula necessidade baseada na meta definida (Explosão de Materiais)
     * Segue o mesmo padrão de performance do getInsumoConsumption.
     */
    public static function getInsumoProjection($system_unit_id, $daysOfWeek, $insumoIds, $user_id): array
    {
        global $pdo;

        // Busca nomes (Padronizado)
        $unitName = '';
        if ($system_unit_id) {
            $stmt = $pdo->prepare("SELECT name FROM system_unit WHERE id = ?");
            $stmt->execute([$system_unit_id]);
            $unitName = $stmt->fetchColumn() ?: '';
        }

        $userName = '';
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT name FROM system_users WHERE id = ?");
            $stmt->execute([$user_id]);
            $userName = $stmt->fetchColumn() ?: '';
        }

        // Mapeamento de Dias (Inteiro -> String do Banco)
        $mapaDias = [0 => 'domingo', 1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];
        $diasSolicitados = [];
        foreach ($daysOfWeek as $d) {
            if (isset($mapaDias[$d])) $diasSolicitados[] = $mapaDias[$d];
        }

        // Validações
        $diasSolicitados = array_unique($diasSolicitados);
        $insumoIds = array_unique($insumoIds);

        if (empty($diasSolicitados) || empty($insumoIds)) {
            return ['unidade' => $unitName, 'usuario' => $userName, 'consumos' => []];
        }

        // 1. Info Produtos (Batch) - Exatamente igual ao método de consumo
        $placeholdersProds = implode(',', array_fill(0, count($insumoIds), '?'));
        $stmt = $pdo->prepare("
            SELECT p.codigo AS insumo_id, p.nome as nome, cc.nome as categoria, und as unidade
            FROM products p
            INNER JOIN categorias cc ON p.categoria = cc.codigo and cc.system_unit_id = p.system_unit_id
            WHERE p.codigo IN (" . $placeholdersProds . ") 
            AND p.system_unit_id = ?
        ");
        $stmt->execute(array_merge($insumoIds, [$system_unit_id]));
        $productsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Saldo (Batch)
        $saldos = NecessidadesRefactorController::getProductsStockBatch($system_unit_id, $insumoIds);

        // 3. Projeção Total / Explosão (Batch)
        // Aqui está a lógica específica deste método, mas isolada como o "fetchTotalConsumption"
        $projecoesAgregadas = self::fetchTotalProjectionBatch($system_unit_id, $insumoIds, $diasSolicitados);

        $insumoConsumption = [];

        // Mapa para acesso rápido
        $prodMap = [];
        foreach ($productsData as $p) $prodMap[$p['insumo_id']] = $p;

        foreach ($insumoIds as $insumo_id) {
            if (!isset($prodMap[$insumo_id])) continue;

            $pInfo = $prodMap[$insumo_id];

            // Total Projetado (Soma das explosões dos dias solicitados)
            $totalSales = isset($projecoesAgregadas[$insumo_id]) ? (float)$projecoesAgregadas[$insumo_id] : 0;

            // Saldo Atual
            $saldo = isset($saldos[$insumo_id]) ? (float)$saldos[$insumo_id] : 0;

            // Lógica de Cálculo (Direta: Projeção - Estoque)
            // Diferente da média, aqui não dividimos por 4, pois a projeção é absoluta para os dias selecionados
            $calculo = $totalSales - $saldo;

            $recomendado = 0;
            if ($pInfo['unidade'] === 'UND') {
                $recomendado = max(0, ceil($calculo));
            } else {
                $recomendado = max(0, round($calculo, 2));
            }

            $insumoConsumption[] = [
                'codigo' => $insumo_id,
                'sales' => number_format($totalSales, 2, '.', ''), // Mostra o total projetado
                'margem' => '0.00',
                'saldo' => number_format($saldo, 2, '.', ''),
                'necessidade' => 0,
                'unidade' => $pInfo['unidade'],
                'nome' => $pInfo['nome'],
                'categoria' => $pInfo['categoria'],
                'recomendado' => $recomendado
            ];
        }

        return [
            'unidade' => $unitName,
            'usuario' => $userName,
            'consumos' => $insumoConsumption
        ];
    }

    /**
     * METODO AUXILIAR PRIVADO: Calcula a Explosão de Materiais + Venda Direta
     * Isolado para manter o controller principal limpo.
     */
    private static function fetchTotalProjectionBatch($system_unit_id, $insumoIds, $diasSolicitados): array
    {
        global $pdo;

        if (empty($insumoIds) || empty($diasSolicitados)) return [];

        $placeholdersProds = implode(',', array_fill(0, count($insumoIds), '?'));
        $placeholdersDias = implode(',', array_fill(0, count($diasSolicitados), '?'));

        // Parâmetros para a query (usados 2x por causa do UNION)
        // Ordem: unit, dias, insumos
        $paramsBase = array_merge([$system_unit_id], $diasSolicitados, $insumoIds);
        $paramsFinal = array_merge($paramsBase, $paramsBase);

        $sql = "
            SELECT 
                final.insumo_id, 
                SUM(final.total_demandado) as total_geral
            FROM (
                -- PARTE A: Demanda via Composição (Ingrediente dentro do Produto de Venda)
                -- Ex: Carne dentro do Hamburguer
                SELECT 
                    c.insumo_id,
                    SUM(proj.quantity * c.quantity) as total_demandado
                FROM product_daily_projections proj
                JOIN compositions c ON c.product_id = proj.product_codigo AND c.system_unit_id = proj.system_unit_id
                WHERE proj.system_unit_id = ?
                  AND proj.day_of_week IN ($placeholdersDias)
                  AND c.insumo_id IN ($placeholdersProds)
                  AND proj.deleted_at IS NULL
                GROUP BY c.insumo_id

                UNION ALL

                -- PARTE B: Demanda Direta (O próprio produto é vendido e projetado)
                -- Ex: Coca-Cola Lata
                SELECT 
                    proj.product_codigo as insumo_id,
                    SUM(proj.quantity) as total_demandado
                FROM product_daily_projections proj
                WHERE proj.system_unit_id = ?
                  AND proj.day_of_week IN ($placeholdersDias)
                  AND proj.product_codigo IN ($placeholdersProds)
                  AND proj.deleted_at IS NULL
                GROUP BY proj.product_codigo
            ) as final
            GROUP BY final.insumo_id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsFinal);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function getInsumoProjectionMatriz($data): array
    {
        global $pdo;

        // Extração dos parâmetros a partir do array $data
        $matriz_id  = $data['matriz_id'] ?? null;
        $daysOfWeek = $data['daysOfWeek'] ?? []; // Ex: [0, 1, 2]
        $insumoIds  = $data['insumoIds'] ?? [];
        $user_id    = $data['user_id'] ?? null;

        if (!$matriz_id || empty($insumoIds)) {
            return [];
        }

        // Passo 1: Obter filiais vinculadas à matriz
        $stmt = $pdo->prepare("SELECT unit_filial FROM system_unit_rel WHERE unit_matriz = ?");
        $stmt->execute([$matriz_id]);
        $filiais = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($filiais)) {
            return [];
        }

        // Passo 2: Obter metadados dos insumos (Nome/Categoria) priorizando a Matriz
        $all_unit_ids = array_merge([$matriz_id], $filiais);
        $insumoPlaceholders = implode(',', array_fill(0, count($insumoIds), '?'));
        $unitPlaceholders = implode(',', array_fill(0, count($all_unit_ids), '?'));

        $stmt = $pdo->prepare("
        SELECT p.codigo AS insumo_id, p.nome as nome, p.system_unit_id as system_unit_id, cc.nome as categoria
        FROM products p
        INNER JOIN categorias cc ON p.categoria = cc.codigo and cc.system_unit_id = p.system_unit_id
        WHERE p.codigo IN ($insumoPlaceholders) 
        AND p.system_unit_id IN ($unitPlaceholders)
        ORDER BY p.system_unit_id = ? DESC
    ");

        $params = array_merge($insumoIds, $all_unit_ids, [$matriz_id]);
        $stmt->execute($params);

        $produtosInfo = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $codigo = $row['insumo_id'];
            // Garante que pegamos a info da matriz se disponível, senão da primeira filial que aparecer
            if (!isset($produtosInfo[$codigo]) || $row['system_unit_id'] == $matriz_id) {
                $produtosInfo[$codigo] = $row;
            }
        }

        // Busca Saldo atual da Matriz (Batch)
        $saldosMatriz = NecessidadesRefactorController::getProductsStockBatch($matriz_id, $insumoIds);

        // Inicializar a estrutura de retorno
        $insumoConsumption = [];
        foreach ($insumoIds as $insumo_id) {
            $info = $produtosInfo[$insumo_id] ?? ['nome' => 'N/A', 'categoria' => 'N/A'];
            $insumoConsumption[$insumo_id] = [
                'codigo'       => $insumo_id,
                'sales'        => 0, // Representará a Projeção Total (Soma das filiais)
                'saldo_lojas'  => 0,
                'saldo_matriz' => $saldosMatriz[$insumo_id] ?? 0,
                'nome'         => $info['nome'],
                'categoria'    => $info['categoria'],
            ];
        }

        // Passo 3: Consolidar a Projeção (Explosão) de cada filial
        foreach ($filiais as $filial_id) {
            // Chama o método individual que calcula a projeção baseada nos dias da semana
            $dadosFilial = self::getInsumoProjection($filial_id, $daysOfWeek, $insumoIds, $user_id);

            if (empty($dadosFilial['consumos'])) continue;

            foreach ($dadosFilial['consumos'] as $insumo) {
                $id = $insumo['codigo'] ?? null;
                if ($id && isset($insumoConsumption[$id])) {
                    $insumoConsumption[$id]['sales']       += (float)$insumo['sales'];
                    $insumoConsumption[$id]['saldo_lojas'] += (float)$insumo['saldo'];
                }
            }
        }

        // Passo 4: Consolidação e formatação final
        foreach ($insumoConsumption as &$insumo) {
            $projecaoTotal   = $insumo['sales'];
            $saldoTotalGeral = $insumo['saldo_lojas'] + $insumo['saldo_matriz'];

            $insumo['saldo_total'] = number_format($saldoTotalGeral, 2, '.', '');

            // Recomendação: O que falta para cobrir a projeção
            $recomendado = max(0, ceil($projecaoTotal - $saldoTotalGeral));

            $insumo['sales']        = number_format($projecaoTotal, 2, '.', '');
            $insumo['saldo_lojas']  = number_format($insumo['saldo_lojas'], 2, '.', '');
            $insumo['saldo_matriz'] = number_format($insumo['saldo_matriz'], 2, '.', '');
            $insumo['recomendado']  = $recomendado;
            $insumo['margem']       = '0.00'; // Mantido para compatibilidade de colunas no front
        }

        return array_values($insumoConsumption);
    }

}