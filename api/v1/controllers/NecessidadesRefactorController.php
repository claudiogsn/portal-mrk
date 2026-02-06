<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class NecessidadesRefactorController
{
    /**
     * OTIMIZADO: Batch processing para evitar N+1 queries.
     */
    public static function getConsumptionBuy($matriz_id, $insumoIds, $dias): array
    {
        global $pdo;

        // últimas 4 datas de cada dia da semana
        $DatasSemana = self::ultimasQuatroDatasPorDiaSemana();

        // quantas vezes cada dia aparece no período
        $QuantidadeDiasSemana = self::contarDiasSemana($dias);

        $resultadoFinal = [];

        // Descobre se esse ID é uma MATRIZ (tem filiais cadastradas)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_unit_rel WHERE unit_matriz = ?");
        $stmt->execute([$matriz_id]);
        $isMatriz = $stmt->fetchColumn() > 0;

        foreach ($DatasSemana as $dia => $datas) {

            if (empty($datas)) continue;

            if ($isMatriz) {
                // MATRIZ → usa método que já soma todas as filiais
                $consumo = self::getInsumoConsumptionMatriz($matriz_id, $datas, $insumoIds, 'media');
            } else {
                // UNIDADE SIMPLES
                $dadosUnidade = self::getInsumoConsumption($matriz_id, $datas, $insumoIds, null, 'media');
                $consumo      = $dadosUnidade['consumos'] ?? [];
            }

            // Otimização: processamento em memória
            foreach ($consumo as $item) {
                if (!isset($item['sales']) || (float)$item['sales'] <= 0) continue;

                // Multiplica pela frequencia do dia na semana
                $qtdComprar = (float)$item['sales'] * ($QuantidadeDiasSemana[$dia] ?? 0);

                if ($qtdComprar <= 0) continue;

                $id = $item['codigo'];

                if (!isset($resultadoFinal[$id])) {
                    $resultadoFinal[$id] = [
                        'insumo_id' => $id,
                        'compras'   => 0
                    ];
                }
                $resultadoFinal[$id]['compras'] += $qtdComprar;
            }
        }

        // Reorganiza o array para ter índice numérico
        return array_values($resultadoFinal);
    }

    public static function getInsumoConsumptionMatriz($matriz_id, $dates, $insumoIds, $type = 'media'): array
    {
        global $pdo;

        // Passo 1: Obter todas as unidades filiais da matriz
        $stmt = $pdo->prepare("SELECT unit_filial FROM system_unit_rel WHERE unit_matriz = ?");
        $stmt->execute([$matriz_id]);
        $filiais = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($filiais)) {
            return [];
        }

        // Passo 2: Obter nomes dos insumos da matriz e filiais, priorizando a matriz
        $all_unit_ids = array_merge([$matriz_id], $filiais);
        $insumoPlaceholders = implode(',', array_fill(0, count($insumoIds), '?'));
        $unitPlaceholders = implode(',', array_fill(0, count($all_unit_ids), '?'));

        $stmt = $pdo->prepare("
        SELECT p.codigo AS insumo_id, p.nome as nome, p.system_unit_id as system_unit_id, cc.nome as categoria
        FROM products p
        INNER JOIN categorias cc ON p.categoria = cc.codigo and cc.system_unit_id = p.system_unit_id
        WHERE p.codigo IN ($insumoPlaceholders) 
        AND p.system_unit_id IN ($unitPlaceholders)
        ORDER BY p.system_unit_id = ? DESC");

        $params = array_merge($insumoIds, $all_unit_ids, [$matriz_id]);
        $stmt->execute($params);

        $produtosInfo = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $codigo = $row['insumo_id'];
            if (!isset($produtosInfo[$codigo])) {
                $produtosInfo[$codigo] = $row;
            } elseif ($row['system_unit_id'] == $matriz_id) {
                $produtosInfo[$codigo] = $row;
            }
        }

        // Batch: Saldo da Matriz de uma vez só
        $saldosMatriz = self::getProductsStockBatch($matriz_id, $insumoIds);

        // Inicializar consumo dos insumos
        $insumoConsumption = [];
        foreach ($insumoIds as $insumo_id) {
            $info = $produtosInfo[$insumo_id] ?? ['nome' => 'N/A', 'categoria' => 'N/A'];

            $insumoConsumption[$insumo_id] = [
                'codigo' => $insumo_id,
                'sales' => 0,
                'margem' => 0,
                'saldo_lojas' => 0,
                'necessidade' => 0,
                'saldo_matriz' => $saldosMatriz[$insumo_id] ?? 0,
                'saldo_total' => 0,
                'nome' => $info['nome'],
                'categoria' => $info['categoria'],
            ];
        }

        // Passo 3: Calcular consumo para cada filial e agregar
        foreach ($filiais as $filial_id) {
            $dadosFilial = self::getInsumoConsumption($filial_id, $dates, $insumoIds, null, $type);

            if (empty($dadosFilial['consumos'])) continue;

            foreach ($dadosFilial['consumos'] as $insumo) {
                $id = $insumo['codigo'] ?? null;
                if ($id && isset($insumoConsumption[$id])) {
                    $insumoConsumption[$id]['sales'] += (float)$insumo['sales'];
                    $insumoConsumption[$id]['saldo_lojas'] += (float)$insumo['saldo'];
                }
            }
        }

        // Passo 4: Consolidação
        foreach ($insumoConsumption as &$insumo) {
            $sales = $insumo['sales'];
            $saldo_lojas = $insumo['saldo_lojas'];
            $saldo_matriz = $insumo['saldo_matriz'];

            // Calcular saldo total (saldo lojas + saldo matriz)
            $saldo_total = $saldo_lojas + $saldo_matriz;
            $insumo['saldo_total'] = number_format($saldo_total, 2, '.', '');

            $recomendado = 0;
            if ($type === 'media') {
                $recomendado = max(0, ceil($sales - $saldo_total));
            }

            // Formatar valores
            $insumo['sales'] = number_format($sales, 2, '.', '');
            $insumo['margem'] = '0.00';
            $insumo['saldo_lojas'] = number_format($saldo_lojas, 2, '.', '');
            $insumo['saldo_matriz'] = number_format($saldo_matriz, 2, '.', '');
            $insumo['recomendado'] = $recomendado;
        }

        return array_values($insumoConsumption);
    }

    /**
     * OTIMIZADO: Consome dados em lote (1 query em vez de N queries)
     */
    public static function getInsumoConsumption($system_unit_id, $dates, $insumoIds, $user_id, $type = 'media'): array
    {
        global $pdo;

        // Busca nomes
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

        $dates = array_unique($dates);
        if (empty($dates) || empty($insumoIds)) {
            return ['unidade' => $unitName, 'usuario' => $userName, 'consumos' => []];
        }

        // 1. Info Produtos (Batch)
        $placeholders = implode(',', array_fill(0, count($insumoIds), '?'));
        $stmt = $pdo->prepare("
            SELECT p.codigo AS insumo_id, p.nome as nome, cc.nome as categoria, und as unidade
            FROM products p
            INNER JOIN categorias cc ON p.categoria = cc.codigo and cc.system_unit_id = p.system_unit_id
            WHERE p.codigo IN (" . $placeholders . ") 
            AND p.system_unit_id = ?
        ");
        $stmt->execute(array_merge($insumoIds, [$system_unit_id]));
        $productsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Saldo (Batch)
        $saldos = self::getProductsStockBatch($system_unit_id, $insumoIds);

        // 3. Consumo Total (Batch - soma todas as datas de uma vez)
        $consumosAgregados = self::fetchTotalConsumptionBatch($system_unit_id, $insumoIds, $dates);

        $insumoConsumption = [];

        // Mapa para acesso rápido
        $prodMap = [];
        foreach ($productsData as $p) $prodMap[$p['insumo_id']] = $p;

        foreach ($insumoIds as $insumo_id) {
            if (!isset($prodMap[$insumo_id])) continue;

            $pInfo = $prodMap[$insumo_id];
            $totalSales = isset($consumosAgregados[$insumo_id]) ? (float)$consumosAgregados[$insumo_id] : 0;
            $saldo = isset($saldos[$insumo_id]) ? (float)$saldos[$insumo_id] : 0;

            $salesFinal = 0;
            $recomendado = 0;

            if ($type === 'media') {
                $salesFinal = $totalSales / 4; // Média de 4 semanas

                $calculo = $salesFinal - $saldo;
                if ($pInfo['unidade'] === 'UND') {
                    $recomendado = max(0, ceil($calculo));
                } else {
                    $recomendado = max(0, round($calculo, 2));
                }
            }

            $insumoConsumption[] = [
                'codigo' => $insumo_id,
                'sales' => number_format($salesFinal, 2, '.', ''),
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
     * OTIMIZADO: Versão Top 3 otimizada (Busca dados diários em lote)
     */
    public static function getInsumoConsumptionTop3($system_unit_id, $dates, $insumoIds, $user_id): array
    {
        global $pdo;

        // Busca nomes
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

        $dates = array_unique($dates);
        if (empty($dates) || empty($insumoIds)) {
            return ['unidade' => $unitName, 'usuario' => $userName, 'consumos' => []];
        }

        // 1. Info Produtos
        $placeholders = implode(',', array_fill(0, count($insumoIds), '?'));
        $stmt = $pdo->prepare("
            SELECT p.codigo AS insumo_id, p.nome, cc.nome as categoria, und as unidade
            FROM products p
            INNER JOIN categorias cc ON p.categoria = cc.codigo and cc.system_unit_id = p.system_unit_id
            WHERE p.codigo IN (" . $placeholders . ") 
            AND p.system_unit_id = ?
        ");
        $stmt->execute(array_merge($insumoIds, [$system_unit_id]));
        $productsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Saldos
        $saldos = self::getProductsStockBatch($system_unit_id, $insumoIds);

        // 3. Consumo Diário (Batch - Retorna dados agrupados por insumo e data)
        // Precisamos dos dados separados por data para ordenar e pegar os top 3
        $dadosDiarios = self::fetchDailyConsumptionBatch($system_unit_id, $insumoIds, $dates);

        $insumoConsumption = [];
        $prodMap = [];
        foreach ($productsData as $p) $prodMap[$p['insumo_id']] = $p;

        foreach ($insumoIds as $id) {
            if (!isset($prodMap[$id])) continue;

            $pInfo = $prodMap[$id];

            // Pega array de consumos diários desse insumo
            $consumos = $dadosDiarios[$id] ?? [];

            // Lógica Top 3
            rsort($consumos); // Ordena do maior para o menor
            $top3 = array_slice($consumos, 0, 3); // Pega os 3 maiores
            $mediaTop3 = count($top3) > 0 ? array_sum($top3) / count($top3) : 0;

            $saldo = isset($saldos[$id]) ? (float)$saldos[$id] : 0;

            $recomendado = 0;
            if ($pInfo['unidade'] === 'UND') {
                $recomendado = max(0, ceil($mediaTop3 - $saldo));
            } else {
                $recomendado = max(0, round($mediaTop3 - $saldo, 2));
            }

            $insumoConsumption[] = [
                'codigo' => $id,
                'sales' => number_format($mediaTop3, 2, '.', ''),
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
     * OTIMIZADO: Explosão de fichas técnicas em lote.
     */
    public static function getProductsToBuys($matriz_id, $vendas): array
    {
        global $pdo;

        // Verifica Matriz
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_unit_rel WHERE unit_matriz = ?");
        $stmt->execute([$matriz_id]);
        $isMatriz = $stmt->fetchColumn() > 0;

        $unitBase = $matriz_id;
        if (!$isMatriz) {
            $stmt = $pdo->prepare("SELECT unit_matriz FROM system_unit_rel WHERE unit_filial = ? LIMIT 1");
            $stmt->execute([$matriz_id]);
            $matrizVinculada = $stmt->fetchColumn();
            if ($matrizVinculada) $unitBase = (int)$matrizVinculada;
        }

        // Filtra vendas relevantes
        $quantidadesPorProduto = [];
        foreach ($vendas as $item) {
            if (!isset($item['insumo_id'], $item['compras']) || (int)$item['insumo_id'] < 10000) continue;
            $quantidadesPorProduto[$item['insumo_id']] = (float)$item['compras'];
        }

        if (empty($quantidadesPorProduto)) {
            return ['success' => true, 'compras' => [], 'alertas' => null];
        }

        // 1. Busca Produtos Base (Batch)
        $produtosIds  = array_keys($quantidadesPorProduto);
        $placeholders = implode(',', array_fill(0, count($produtosIds), '?'));

        $stmt = $pdo->prepare("SELECT codigo, producao, compravel, nome FROM products WHERE codigo IN ($placeholders) AND system_unit_id = ?");
        $stmt->execute([...$produtosIds, $unitBase]);
        $produtosBase = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $produtosMap = [];
        $idsParaProducao = [];

        foreach ($produtosBase as $p) {
            $produtosMap[$p['codigo']] = $p;
            if ((int)$p['producao'] === 1) {
                $idsParaProducao[] = $p['codigo'];
            }
        }

        // 2. Busca Fichas Técnicas (Batch)
        $fichasMap = [];
        if (!empty($idsParaProducao)) {
            $placeholdersProd = implode(',', array_fill(0, count($idsParaProducao), '?'));
            $stmtFicha = $pdo->prepare("
                SELECT product_id, insumo_id, quantity, rendimento
                FROM productions
                WHERE system_unit_id = ? AND product_id IN ($placeholdersProd)
            ");
            $stmtFicha->execute(array_merge([$unitBase], $idsParaProducao));
            $fichasRaw = $stmtFicha->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fichasRaw as $f) {
                $fichasMap[$f['product_id']][] = $f;
            }
        }

        $compras   = [];
        $insumoIds = [];
        $alertas   = [];

        // 3. Processa
        foreach ($quantidadesPorProduto as $produto => $qtdVenda) {
            if (!isset($produtosMap[$produto])) continue;

            $pInfo = $produtosMap[$produto];
            $producao  = (int)$pInfo['producao'];
            $compravel = (int)$pInfo['compravel'];
            $nome      = $pInfo['nome'];

            // Compra Direta
            if ($producao === 0) {
                if ($compravel === 1) {
                    $compras[$produto]   = ($compras[$produto] ?? 0) + $qtdVenda;
                    $insumoIds[$produto] = $produto;
                } else {
                    $alertas[$produto] = ['codigo' => $produto, 'nome' => $nome, 'motivo' => 'Não comprável/sem ficha'];
                }
                continue;
            }

            // Produção
            if (isset($fichasMap[$produto])) {
                foreach ($fichasMap[$produto] as $ficha) {
                    $insumo_id  = $ficha['insumo_id'];
                    $qtd_ficha  = (float)$ficha['quantity'];
                    $rendimento = (float)$ficha['rendimento'] ?: 1;
                    $qtd_compra = ($qtd_ficha * $qtdVenda) / $rendimento;

                    $compras[$insumo_id]   = ($compras[$insumo_id] ?? 0) + $qtd_compra;
                    $insumoIds[$insumo_id] = $insumo_id;
                }
            } elseif ($compravel === 0) {
                $alertas[$produto] = ['codigo' => $produto, 'nome' => $nome, 'motivo' => 'Sem ficha técnica'];
            }
        }

        // 4. Detalhes finais dos insumos (Batch)
        $resultado = [];
        if (!empty($insumoIds)) {
            $idsFinais = array_values($insumoIds);
            $placeholders = implode(',', array_fill(0, count($idsFinais), '?'));

            $stmt = $pdo->prepare("
                SELECT p.codigo AS insumo_id, p.nome, cc.nome AS categoria
                FROM products p
                INNER JOIN categorias cc ON cc.codigo = p.categoria AND cc.system_unit_id = p.system_unit_id
                WHERE p.codigo IN ($placeholders) AND p.system_unit_id = ? AND p.compravel = 1
            ");
            $stmt->execute([...$idsFinais, $unitBase]);
            $produtosInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($produtosInfo as $p) {
                $id = $p['insumo_id'];
                $resultado[] = [
                    'insumo_id' => $id,
                    'nome'      => $p['nome'],
                    'categoria' => $p['categoria'],
                    'compras'   => round($compras[$id] ?? 0, 2)
                ];
            }
        }

        return [
            'success' => true,
            'compras' => $resultado,
            'alertas' => !empty($alertas) ? ['tipo' => 'falha_configuracao', 'itens' => array_values($alertas)] : null
        ];
    }

    public static function contarDiasSemana($dias): array
    {
        $diasSemana = array_fill_keys(['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'], 0);
        $mapaSemana = [0 => 'domingo', 1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];

        $hoje = new DateTime();
        for ($i = 1; $i <= $dias; $i++) {
            $data = clone $hoje;
            $data->modify("+$i day");
            $diasSemana[$mapaSemana[(int)$data->format('w')]]++;
        }
        return $diasSemana;
    }

    public static function ultimasQuatroDatasPorDiaSemana(): array
    {
        $diasSemana = array_fill_keys(['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'], []);
        $mapaSemana = [0 => 'domingo', 1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];

        $hoje = new DateTime();
        for ($i = 0; $i < 90; $i++) {
            $data = clone $hoje;
            $data->modify("-$i days");
            $nomeDia = $mapaSemana[(int)$data->format('w')];

            if (count($diasSemana[$nomeDia]) < 4) {
                $diasSemana[$nomeDia][] = $data->format('Y-m-d');
            }

            // Check se completou todos
            $prontos = 0;
            foreach($diasSemana as $arr) if(count($arr) >= 4) $prontos++;
            if ($prontos === 7) break;
        }
        return $diasSemana;
    }

    /**
     * OTIMIZADO: Cálculo em lote (JOIN e GROUP BY eficientes)
     */
    public static function calculateInsumosByItens($system_unit_id, $itens): array
    {
        global $pdo;

        $productIds = [];
        foreach ($itens as $item) {
            if (isset($item['produto'])) $productIds[] = $item['produto'];
        }
        $productIds = array_unique($productIds);

        if (empty($productIds)) return ['success' => true, 'consumos' => []];

        // 1. Produtos (Batch)
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmtProds = $pdo->prepare("SELECT codigo, nome FROM products WHERE system_unit_id = ? AND codigo IN ($placeholders)");
        $stmtProds->execute(array_merge([$system_unit_id], $productIds));
        $produtosMap = $stmtProds->fetchAll(PDO::FETCH_KEY_PAIR);

        // 2. Composição Completa (Batch)
        $stmtComp = $pdo->prepare("
            SELECT 
                c.product_id, 
                c.insumo_id, 
                c.quantity,
                p.nome as nome_insumo,
                cat.nome as categoria_nome
            FROM compositions c
            LEFT JOIN products p ON p.codigo = c.insumo_id AND p.system_unit_id = c.system_unit_id
            LEFT JOIN categorias cat ON cat.codigo = p.categoria AND cat.system_unit_id = p.system_unit_id
            WHERE c.system_unit_id = ? AND c.product_id IN ($placeholders)
        ");
        $stmtComp->execute(array_merge([$system_unit_id], $productIds));
        $allCompositions = $stmtComp->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

        $resultado = [];

        foreach ($itens as $item) {
            $cod = $item['produto'];
            $qtd = $item['quantidade'];

            if (!isset($produtosMap[$cod])) {
                $resultado[] = ['produto' => $cod, 'erro' => 'Produto não encontrado'];
                continue;
            }

            $insumosProcessados = [];
            if (isset($allCompositions[$cod])) {
                foreach ($allCompositions[$cod] as $comp) {
                    $insumosProcessados[] = [
                        'insumo'      => $comp['insumo_id'],
                        'nome_insumo' => $comp['nome_insumo'] ?? '(produto não encontrado)',
                        'categoria'   => $comp['categoria_nome'],
                        'quantidade'  => $qtd * $comp['quantity']
                    ];
                }
            }

            $resultado[] = [
                'produto'    => $cod,
                'nome'       => $produtosMap[$cod],
                'quantidade' => $qtd,
                'insumos'    => $insumosProcessados
            ];
        }

        return ['success' => true, 'consumos' => $resultado];
    }

    // --- MÉTODOS AUXILIARES ---

    public static function getFiliaisProduction($user_id): array {
        global $pdo;
        $stmt = $pdo->prepare("SELECT su.id AS filial_id, su.name AS filial_nome FROM system_user_unit sur JOIN system_unit su ON sur.system_unit_id = su.id WHERE sur.system_user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getFiliaisByMatriz($user_id): array {
        return self::getFiliaisProduction($user_id);
    }

    public static function getProductStock($system_unit_id, $codigo): array {
        $res = self::getProductsStockBatch($system_unit_id, [$codigo]);
        if (isset($res[$codigo])) return ['success' => true, 'saldo' => $res[$codigo]];
        return ['success' => false, 'message' => 'Produto não encontrado'];
    }

    // -- NOVOS MÉTODOS PRIVADOS PARA OTIMIZAÇÃO (BATCH) --

    public static function getProductsStockBatch($system_unit_id, array $codigos): array
    {
        global $pdo;
        if (empty($codigos)) return [];
        $codigos = array_unique($codigos);
        $placeholders = implode(',', array_fill(0, count($codigos), '?'));

        $stmt = $pdo->prepare("
            SELECT codigo, COALESCE(saldo, 0) as saldo 
            FROM products 
            WHERE system_unit_id = ? AND codigo IN ($placeholders)
        ");
        $stmt->execute(array_merge([$system_unit_id], $codigos));
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private static function fetchTotalConsumptionBatch($system_unit_id, $insumoIds, $dates): array
    {
        global $pdo;
        if (empty($insumoIds) || empty($dates)) return [];

        $insumoPlaceholders = implode(',', array_fill(0, count($insumoIds), '?'));
        $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));

        $sql = "
            SELECT 
                c.insumo_id,
                SUM(b.quantidade * c.quantity) AS total_consumo
            FROM compositions AS c
            JOIN _bi_sales AS b ON c.product_id = b.cod_material AND c.system_unit_id = b.system_unit_id
            WHERE 
                c.insumo_id IN ($insumoPlaceholders)
                AND b.system_unit_id = ?
                AND b.data_movimento IN ($datePlaceholders)
            GROUP BY c.insumo_id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($insumoIds, [$system_unit_id], $dates));
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Traz os consumos separadinhos por data (usado no Top3)
     * Retorna: [insumo_id => [consumo_dia1, consumo_dia2...]]
     */
    private static function fetchDailyConsumptionBatch($system_unit_id, $insumoIds, $dates): array
    {
        global $pdo;
        if (empty($insumoIds) || empty($dates)) return [];

        $insumoPlaceholders = implode(',', array_fill(0, count($insumoIds), '?'));
        $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));

        $sql = "
            SELECT 
                c.insumo_id,
                b.data_movimento,
                SUM(b.quantidade * c.quantity) AS total_consumo
            FROM compositions AS c
            JOIN _bi_sales AS b ON c.product_id = b.cod_material AND c.system_unit_id = b.system_unit_id
            WHERE 
                c.insumo_id IN ($insumoPlaceholders)
                AND b.system_unit_id = ?
                AND b.data_movimento IN ($datePlaceholders)
            GROUP BY c.insumo_id, b.data_movimento
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($insumoIds, [$system_unit_id], $dates));
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($raw as $row) {
            $result[$row['insumo_id']][] = (float)$row['total_consumo'];
        }
        return $result;
    }
}