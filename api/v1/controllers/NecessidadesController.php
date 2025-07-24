<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class NecessidadesController
{

    /**
     * @throws DateMalformedStringException
     */
    public static function getConsumptionBuy($matriz_id, $insumoIds, $dias): array
    {
        $DatasSemana = self::ultimasQuatroDatasPorDiaSemana(); // últimas 4 datas de cada dia da semana
        $QuantidadeDiasSemana = self::contarDiasSemana($dias); // quantas vezes cada dia aparece no período

        $resultadoFinal = [];

        foreach ($DatasSemana as $dia => $datas) {

            // Busca consumo do insumo nas 4 últimas datas desse dia
            $consumo = self::getInsumoConsumptionMatriz($matriz_id, $datas, $insumoIds);

            // Multiplica média de consumo por quantidade de vezes que o dia aparece no período
            $consumo = array_map(function ($item) use ($QuantidadeDiasSemana, $dia) {
                $item['compras'] = $item['sales'] * $QuantidadeDiasSemana[$dia];
                return $item;
            }, $consumo);

            // Remove itens com 0 consumo
            $consumo = array_filter($consumo, fn($item) => $item['compras'] > 0);

            // Agrupa por insumo_id somando
            foreach ($consumo as $item) {
                $id = $item['codigo'];
                if (!isset($resultadoFinal[$id])) {
                    $resultadoFinal[$id] = [
                        'insumo_id' => $id,
                        'compras' => 0
                    ];
                }
                $resultadoFinal[$id]['compras'] += $item['compras'];
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

        // Processar produtos para priorizar o nome da matriz
        $produtos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $codigo = $row['insumo_id'];
            if (!isset($produtos[$codigo])) {
                $produtos[$codigo] = $row;
            } else {
                // Substitui apenas se for da matriz
                if ($row['system_unit_id'] == $matriz_id) {
                    $produtos[$codigo] = $row;
                }
            }
        }

        // Inicializar consumo dos insumos
        $insumoConsumption = [];
        foreach ($insumoIds as $insumo_id) {
            $nome = isset($produtos[$insumo_id]) ? $produtos[$insumo_id]['nome'] : 'Insumo não encontrado';
            $categoria = isset($produtos[$insumo_id]) ? $produtos[$insumo_id]['categoria'] : 'Categoria não encontrada';

            // Obter saldo da matriz
            $saldo_matriz_data = self::getProductStock($matriz_id, $insumo_id);
            $saldo_matriz = $saldo_matriz_data['saldo'] ?? 0;

            $insumoConsumption[$insumo_id] = [
                'codigo' => $insumo_id,
                'sales' => 0,
                'margem' => 0,
                'saldo_lojas' => 0,
                'necessidade' => 0,
                'saldo_matriz' => $saldo_matriz,
                'saldo_total' => 0,
                'nome' => $nome,
                'categoria' => $categoria,
            ];
        }

        // Passo 3: Calcular consumo para cada filial e agregar
        foreach ($filiais as $filial_id) {
            $dadosFilial = self::getInsumoConsumption($filial_id, $dates, $insumoIds, $type);

            if (!isset($dadosFilial['consumos']) || !is_array($dadosFilial['consumos'])) {
                continue;
            }

            foreach ($dadosFilial['consumos'] as $insumo) {

                if (!isset($insumo['codigo'])) {
                    continue;
                }

                $insumo_id = $insumo['codigo'];

                if (!isset($insumoConsumption[$insumo_id])) {
                    continue;
                }

                $sales = (float)$insumo['sales'];
                $saldo = (float)$insumo['saldo'];

                $insumoConsumption[$insumo_id]['sales'] += $sales;
                $insumoConsumption[$insumo_id]['saldo_lojas'] += $saldo;
            }
        }

        // Passo 4: Calcular margem, recomendado e saldo total consolidados
        foreach ($insumoConsumption as &$insumo) {
            $sales = $insumo['sales'];
            $saldo = $insumo['saldo_lojas'];
            $saldo_matriz = $insumo['saldo_matriz'];

            // Calcular saldo total (saldo lojas + saldo matriz)
            $saldo_total = $saldo + $saldo_matriz;
            $insumo['saldo_total'] = number_format($saldo_total, 2, '.', '');

            $margem = 0;
            if ($type === 'media') {
                $recomendado = max(0, ceil($sales - $saldo_total));
            } else {
                // Lógica para outros tipos (se necessário)
                $recomendado = 0;
            }

            // Formatar valores
            $insumo['sales'] = number_format($sales, 2, '.', '');
            $insumo['margem'] = number_format($margem, 2, '.', '');
            $insumo['saldo_lojas'] = number_format($saldo, 2, '.', '');
            $insumo['saldo_matriz'] = number_format($saldo_matriz, 2, '.', '');
            $insumo['recomendado'] = $recomendado;
        }

        return array_values($insumoConsumption);
    }
    public static function getInsumoConsumptionTop3($system_unit_id, $dates, $insumoIds, $user_id): array
    {
        global $pdo;

        // Nome da unidade
        $stmt = $pdo->prepare("SELECT name FROM system_unit WHERE id = ?");
        $stmt->execute([$system_unit_id]);
        $unitName = $stmt->fetchColumn() ?: '';

        // Nome do usuário
        $stmt = $pdo->prepare("SELECT name FROM system_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $userName = $stmt->fetchColumn() ?: '';

        $dates = array_unique($dates);

        // Busca nomes e categorias dos insumos
        $stmt = $pdo->prepare("
        SELECT p.codigo AS insumo_id, p.nome as nome, cc.nome as categoria, und as unidade
        FROM products p
        INNER JOIN categorias cc ON p.categoria = cc.codigo and cc.system_unit_id = p.system_unit_id
        WHERE p.codigo IN (" . implode(',', array_fill(0, count($insumoIds), '?')) . ") 
        AND p.system_unit_id = ?
    ");
        $params = array_merge($insumoIds, [$system_unit_id]);
        $stmt->execute($params);

        // Inicializa consumo com estrutura
        $insumoConsumption = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['insumo_id'];
            $insumoConsumption[$id] = [
                'codigo' => $id,
                'sales' => 0,
                'margem' => 0,
                'saldo' => 0,
                'necessidade' => 0,
                'unidade' => $row['unidade'],
                'nome' => $row['nome'],
                'categoria' => $row['categoria'],
                '__consumos' => [] // ← array para armazenar consumo por data
            ];
        }

        // Itera pelas datas e armazena os consumos individuais
        foreach ($dates as $date) {
            $consumoData = self::fetchTotalConsumption($system_unit_id, $insumoIds, $date);

            foreach ($consumoData as $row) {
                $id = $row['insumo_id'];
                if (isset($insumoConsumption[$id])) {
                    $insumoConsumption[$id]['__consumos'][] = floatval($row['total_consumo']);
                }
            }
        }

        // Processa saldo e média por insumo (média das 3 maiores)
        foreach ($insumoIds as $id) {
            $consumos = $insumoConsumption[$id]['__consumos'];
            sort($consumos); // ordena do menor para maior
            $top3 = array_slice(array_reverse($consumos), 0, 3); // pega as 3 maiores
            $mediaTop3 = count($top3) > 0 ? array_sum($top3) / count($top3) : 0;
            $insumoConsumption[$id]['sales'] = number_format($mediaTop3, 2, '.', '');

            // Saldo atual
            $stockData = self::getProductStock($system_unit_id, $id);
            $insumoConsumption[$id]['saldo'] = number_format($stockData['saldo'] ?? 0, 2, '.', '');

            // Recomendado
            if ($insumoConsumption[$id]['unidade'] === 'UND') {
                $insumoConsumption[$id]['recomendado'] = max(0, ceil($mediaTop3 - $insumoConsumption[$id]['saldo']));
            } else {
                $insumoConsumption[$id]['recomendado'] = max(0, round($mediaTop3 - $insumoConsumption[$id]['saldo'], 2));
            }

            // Remove campo temporário
            unset($insumoConsumption[$id]['__consumos']);
        }

        return [
            'unidade' => $unitName,
            'usuario' => $userName,
            'consumos' => array_values($insumoConsumption)
        ];
    }


    public static function getInsumoConsumption($system_unit_id, $dates, $insumoIds, $user_id, $type = 'media'): array
    {
        global $pdo;

        // Busca o nome da unidade
        $stmt = $pdo->prepare("SELECT name FROM system_unit WHERE id = ?");
        $stmt->execute([$system_unit_id]);
        $unitName = $stmt->fetchColumn() ?: '';

        // Busca o nome da unidade
        $stmt = $pdo->prepare("SELECT name FROM system_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $userName = $stmt->fetchColumn() ?: '';

        // Remove datas duplicadas
        $dates = array_unique($dates);

        // Busca os nomes de todos os insumos independentemente do consumo
        $stmt = $pdo->prepare("
            SELECT p.codigo AS insumo_id, p.nome as nome, cc.nome as categoria, und as unidade
            FROM products p
            INNER JOIN categorias cc ON p.categoria = cc.codigo and cc.system_unit_id = p.system_unit_id
            WHERE p.codigo IN (" . implode(',', array_fill(0, count($insumoIds), '?')) . ") 
            AND p.system_unit_id = ?
        ");

        // Prepara os parâmetros dos insumos
        $params = array_merge($insumoIds, [$system_unit_id]);
        $stmt->execute($params);

        // Inicializa o consumo dos insumos com o nome
        $insumoConsumption = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insumo_id = $row['insumo_id'];
            $insumoConsumption[$insumo_id] = [
                'codigo' => $insumo_id,
                'sales' => 0,
                'margem' => 0,
                'saldo' => 0,
                'necessidade' => 0,
                'unidade' => $row['unidade'],
                'nome' => $row['nome'],
                'categoria' => $row['categoria'],
            ];
        }

        // Itera sobre as datas e calcula o total de consumo usando a nova consulta SQL
        foreach ($dates as $date) {
            // Busca o total de consumo de insumos para uma data específica
            $consumoData = self::fetchTotalConsumption($system_unit_id, $insumoIds, $date);

            // Atualiza o consumo total de insumos
            foreach ($consumoData as $row) {
                $insumo_id = $row['insumo_id'];

                // Verifica se o insumo existe no array e atualiza as vendas
                if (isset($insumoConsumption[$insumo_id])) {
                    $insumoConsumption[$insumo_id]['sales'] += $row['total_consumo'];
                }
            }
        }

        // Atualiza saldo, margem e recomendado
        foreach ($insumoIds as $insumo_id) {
            // Obtém o saldo atual do insumo
            $stockData = self::getProductStock($system_unit_id, $insumo_id);
            $insumoConsumption[$insumo_id]['saldo'] = number_format($stockData['saldo'] ?? 0, 2, '.', '');


            if ($type === 'media') {
                // Calcula margem e recomendado
                $insumoConsumption[$insumo_id]['sales'] = number_format($insumoConsumption[$insumo_id]['sales'] / 4, 2, '.', '');
                if ($insumoConsumption[$insumo_id]['unidade'] === 'UND') {
                    $insumoConsumption[$insumo_id]['recomendado'] = max(0, ceil($insumoConsumption[$insumo_id]['sales'] + $insumoConsumption[$insumo_id]['margem'] - $insumoConsumption[$insumo_id]['saldo']));
                } else {
                    $insumoConsumption[$insumo_id]['recomendado'] = max(0, round(
                        $insumoConsumption[$insumo_id]['sales'] +
                        $insumoConsumption[$insumo_id]['margem'] -
                        $insumoConsumption[$insumo_id]['saldo'],
                        2
                    ));
                }
            }
        }

        return [
            'unidade' => $unitName,
            'usuario' => $userName,
            'consumos' => array_values($insumoConsumption) // Retorna um array indexado
        ]; // Retorna um array indexado
    }

    private static function fetchTotalConsumption($system_unit_id, $insumoIds, $date): array
    {
        global $pdo;

        // Cria uma string de placeholders nomeados para os insumos
        $placeholders = [];
        foreach ($insumoIds as $insumo_id) {
            $placeholders[] = ":insumo_$insumo_id"; // Cria placeholders nomeados
        }
        $insumoIdsPlaceholders = implode(',', $placeholders); // Converte o array em uma string separada por vírgulas

        // Consulta SQL para calcular o total de consumo
        $stmt = $pdo->prepare("
            SELECT 
                c.insumo_id,
                b.data_movimento,
                SUM(b.quantidade * c.quantity) AS total_consumo
            FROM 
                compositions AS c
            JOIN 
                _bi_sales AS b ON c.product_id = b.cod_material AND c.system_unit_id = b.system_unit_id
            WHERE 
                c.insumo_id IN ($insumoIdsPlaceholders)
                AND b.system_unit_id = :system_unit_id
                AND b.data_movimento = :date
            GROUP BY 
                c.insumo_id, b.data_movimento
        ");

        // Bind dos parâmetros para system_unit_id e date
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(':date', $date);

        // Bind dos parâmetros dos insumos
        foreach ($insumoIds as $insumo_id) {
            $stmt->bindValue(":insumo_$insumo_id", $insumo_id, PDO::PARAM_INT);
        }

        $stmt->execute(); // Executa a consulta

        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Retorna todos os resultados
    }

    public static function getFiliaisProduction($user_id): array
    {
        global $pdo;

        $sql = "
            SELECT
                su.id AS filial_id,
                su.name AS filial_nome
            FROM
                system_user_unit sur
            JOIN
                system_unit su ON sur.system_unit_id = su.id
            WHERE
                sur.system_user_id = ?
            AND
                su.name like '%Produção%';
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getFiliaisByMatriz($user_id): array
    {
        global $pdo;

        $sql = "
            SELECT
                su.id AS filial_id,
                su.name AS filial_nome
            FROM
                system_user_unit sur
            JOIN
                system_unit su ON sur.system_unit_id = su.id
            WHERE
                sur.system_user_id = ?;
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getProductStock($system_unit_id, $codigo): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT COALESCE(saldo, 0) AS saldo
            FROM products 
            WHERE system_unit_id = :system_unit_id 
              AND codigo = :codigo
        ");
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(':codigo', $codigo, PDO::PARAM_INT);

        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            return ['success' => true, 'saldo' => $product['saldo']];
        } else {
            return ['success' => false, 'message' => 'Produto não encontrado ou saldo indisponível.'];
        }
    }

    public static function getProductsToBuys($matriz_id, $vendas): array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT unit_filial FROM system_unit_rel WHERE unit_matriz = ?");
        $stmt->execute([$matriz_id]);
        $filiais = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($filiais)) return [];

        $quantidadesPorProduto = [];
        foreach ($vendas as $item) {
            $quantidadesPorProduto[$item['insumo_id']] = floatval($item['compras']);
        }

        $produtosIds = array_keys($quantidadesPorProduto);

        $placeholders = implode(',', array_fill(0, count($produtosIds), '?'));
        $stmt = $pdo->prepare("
        SELECT codigo, producao 
        FROM products 
        WHERE codigo IN ($placeholders) AND system_unit_id = ? AND codigo >= 10000
    ");
        $stmt->execute([...$produtosIds, $matriz_id]);
        $producoes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $compras = [];
        $insumoIds = [];

        foreach ($quantidadesPorProduto as $produto => $qtdVenda) {
            $producao = isset($producoes[$produto]) ? intval($producoes[$produto]) : 0;
            if ($producao === 0) {
                $compras[$produto] = $qtdVenda;
                $insumoIds[$produto] = $produto;
                continue;
            }

            $saldoData = MovimentacaoController::getLastBalanceByMatriz($matriz_id, $produto);
            $saldoAtual = floatval($saldoData['quantidade'] ?? 0);
            $necessario = $qtdVenda - $saldoAtual;
            if ($necessario <= 0) continue;

            $stmtFicha = $pdo->prepare("
            SELECT insumo_id, quantity, rendimento 
            FROM productions 
            WHERE system_unit_id = ? AND product_id = ?
        ");
            $stmtFicha->execute([$matriz_id, $produto]);
            $fichas = $stmtFicha->fetchAll(PDO::FETCH_ASSOC);

            foreach ($fichas as $ficha) {
                $insumo_id = $ficha['insumo_id'];
                $qtd_ficha = floatval($ficha['quantity']);
                $rendimento = floatval($ficha['rendimento']) ?: 1;
                $qtd_compra = ($qtd_ficha * $necessario) / $rendimento;

                if (!isset($compras[$insumo_id])) $compras[$insumo_id] = 0;
                $compras[$insumo_id] += $qtd_compra;
                $insumoIds[$insumo_id] = $insumo_id;
            }
        }

        if (empty($insumoIds)) return [];

        $resultado = [];
        $debugRodadas = [];
        $maxRodadas = 10;
        $rodada = 0;

        do {
            $rodada++;
            if ($rodada > $maxRodadas) {
                // Junta todas as rodadas em um único array e conta as ocorrências
                $todos = [];
                foreach ($debugRodadas as $listaRodada) {
                    foreach ($listaRodada as $insumoId) {
                        if (!isset($todos[$insumoId])) $todos[$insumoId] = 0;
                        $todos[$insumoId]++;
                    }
                }

                arsort($todos);

                $suspeitos = array_filter($todos, fn($v) => $v > 1);
                $detalhes = [];

                // Coleta informações detalhadas sobre os suspeitos
                if (!empty($suspeitos)) {
                    $placeholders = implode(',', array_fill(0, count($suspeitos), '?'));
                    $stmtDebug = $pdo->prepare("
            SELECT p.codigo, p.nome, p.compravel, COUNT(f.insumo_id) AS tem_ficha
            FROM products p
            LEFT JOIN productions f ON f.product_id = p.codigo AND f.system_unit_id = p.system_unit_id
            WHERE p.codigo IN ($placeholders) AND p.system_unit_id = ?
            GROUP BY p.codigo, p.nome, p.compravel
        ");
                    $stmtDebug->execute([...array_keys($suspeitos), $matriz_id]);
                    $detalhes = $stmtDebug->fetchAll(PDO::FETCH_ASSOC);
                }

                $debugFormatado = [
                    'rodadas_executadas' => $rodada - 1,
                    'itens_por_rodada' => $debugRodadas,
                    'repeticoes_detectadas' => $todos,
                    'suspeitos_com_mais_de_1_ocorrencia' => $suspeitos,
                    'detalhes_suspeitos' => $detalhes,
                ];

                //echo "🚨 Loop detectado após {$maxRodadas} rodadas! Análise de insumos:\n";
                //echo json_encode($debugFormatado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                return array_values($resultado);
            }


            $debugRodadas[] = array_keys($insumoIds);

            $placeholders = implode(',', array_fill(0, count($insumoIds), '?'));
            $stmt = $pdo->prepare("
            SELECT p.codigo AS insumo_id, p.nome, p.system_unit_id, cc.nome AS categoria, p.compravel
            FROM products p
            INNER JOIN categorias cc ON p.categoria = cc.codigo AND cc.system_unit_id = p.system_unit_id
            WHERE p.codigo IN ($placeholders) AND p.system_unit_id = ? AND p.codigo >= 10000
        ");
            $stmt->execute([...array_values($insumoIds), $matriz_id]);
            $produtosInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $naoCompraveis = [];

            foreach ($produtosInfo as $produto) {
                $id = $produto['insumo_id'];
                $quantidade = round($compras[$id], 2);

                if ((int)$produto['compravel'] === 1) {
                    $resultado[$id] = [
                        'insumo_id' => $id,
                        'nome' => $produto['nome'],
                        'categoria' => $produto['categoria'],
                        'compras' => $quantidade
                    ];
                } else {
                    $naoCompraveis[$id] = $quantidade;
                    unset($resultado[$id], $compras[$id], $insumoIds[$id]);
                }
            }

            foreach ($naoCompraveis as $produto => $quantidadeNecessaria) {
                $stmtFicha = $pdo->prepare("
                SELECT insumo_id, quantity, rendimento 
                FROM productions 
                WHERE system_unit_id = ? AND product_id = ?
            ");
                $stmtFicha->execute([$matriz_id, $produto]);
                $fichas = $stmtFicha->fetchAll(PDO::FETCH_ASSOC);

                foreach ($fichas as $ficha) {
                    $insumo_id = $ficha['insumo_id'];
                    $qtd_ficha = floatval($ficha['quantity']);
                    $rendimento = floatval($ficha['rendimento']) ?: 1;

                    $qtd_compra = ($qtd_ficha * $quantidadeNecessaria) / $rendimento;

                    if (!isset($compras[$insumo_id])) $compras[$insumo_id] = 0;
                    $compras[$insumo_id] += $qtd_compra;
                    $insumoIds[$insumo_id] = $insumo_id;
                }
            }

        } while (!empty($naoCompraveis));

        return array_values($resultado);
    }


    /**
     * @throws DateMalformedStringException
     */
    public static function contarDiasSemana($dias): array
    {
        $diasSemana = [
            'segunda' => 0,
            'terca' => 0,
            'quarta' => 0,
            'quinta' => 0,
            'sexta' => 0,
            'sabado' => 0,
            'domingo' => 0
        ];

        $mapaSemana = [
            1 => 'segunda',
            2 => 'terca',
            3 => 'quarta',
            4 => 'quinta',
            5 => 'sexta',
            6 => 'sabado',
            0 => 'domingo'
        ];

        $hoje = new DateTime();

        for ($i = 1; $i <= $dias; $i++) { // começa do dia 1 (amanhã)
            $data = clone $hoje;
            $data->modify("+$i day");
            $diaSemana = (int)$data->format('w'); // 0 = domingo, 1 = segunda, ..., 6 = sábado
            $nomeDia = $mapaSemana[$diaSemana];
            $diasSemana[$nomeDia]++;
        }

        return $diasSemana;
    }

    /**
     * @throws DateMalformedStringException
     */
    public static function ultimasQuatroDatasPorDiaSemana(): array
    {
        $diasSemana = [
            'segunda' => [],
            'terca' => [],
            'quarta' => [],
            'quinta' => [],
            'sexta' => [],
            'sabado' => [],
            'domingo' => []
        ];

        $mapaSemana = [
            1 => 'segunda',
            2 => 'terca',
            3 => 'quarta',
            4 => 'quinta',
            5 => 'sexta',
            6 => 'sabado',
            0 => 'domingo'
        ];

        $hoje = new DateTime();

        for ($i = 0; $i < 90; $i++) { // olha até 90 dias para trás
            $data = clone $hoje;
            $data->modify("-$i days");

            $diaSemana = (int)$data->format('w'); // 0 = domingo, 1 = segunda, ..., 6 = sábado
            $nomeDia = $mapaSemana[$diaSemana];

            if (count($diasSemana[$nomeDia]) < 4) {
                $diasSemana[$nomeDia][] = $data->format('Y-m-d');
            }

            // Se todos os dias da semana tiverem 4 datas, pode parar o loop
            $completos = array_filter($diasSemana, fn($d) => count($d) >= 4);
            if (count($completos) === 7) {
                break;
            }
        }

        return $diasSemana;
    }

    public static function calculateInsumosByItens($system_unit_id, $itens): array
    {
        global $pdo;
        $resultado = [];

        foreach ($itens as $item) {
            if (!isset($item['produto']) || !isset($item['quantidade'])) {
                continue;
            }

            $produto_codigo = $item['produto'];
            $quantidade_desejada = $item['quantidade'];

            // Buscar nome do produto principal
            $stmtProduto = $pdo->prepare("
            SELECT nome
            FROM products
            WHERE system_unit_id = :unit_id AND codigo = :codigo
            LIMIT 1
            ");
            $stmtProduto->execute([
                ':unit_id' => $system_unit_id,
                ':codigo' => $produto_codigo
            ]);
            $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                $resultado[] = [
                    'produto' => $produto_codigo,
                    'erro' => 'Produto não encontrado'
                ];
                continue;
            }

            $produto_nome = $produto['nome'];

            // Buscar insumos da composição usando product_id = codigo
            $stmtInsumos = $pdo->prepare("
            SELECT insumo_id, quantity
            FROM compositions
            WHERE system_unit_id = :unit_id AND product_id = :product_id
            ");
            $stmtInsumos->execute([
                ':unit_id' => $system_unit_id,
                ':product_id' => $produto_codigo
            ]);

            $insumos = [];

            while ($row = $stmtInsumos->fetch(PDO::FETCH_ASSOC)) {
                $insumo_id = $row['insumo_id'];
                $quantidade = $row['quantity'];

                // Buscar nome e categoria do insumo
                $stmtProdutoInsumo = $pdo->prepare("
                SELECT nome, categoria
                FROM products
                WHERE system_unit_id = :unit_id AND codigo = :codigo
                LIMIT 1
            ");
                $stmtProdutoInsumo->execute([
                    ':unit_id' => $system_unit_id,
                    ':codigo' => $insumo_id
                ]);
                $produtoInsumo = $stmtProdutoInsumo->fetch(PDO::FETCH_ASSOC);

                $nome_insumo = $produtoInsumo['nome'] ?? '(produto não encontrado)';
                $categoria_nome = null;

                if (isset($produtoInsumo['categoria'])) {
                    $stmtCategoria = $pdo->prepare("
                    SELECT nome
                    FROM categorias
                    WHERE system_unit_id = :unit_id AND codigo = :codigo
                    LIMIT 1
                ");
                    $stmtCategoria->execute([
                        ':unit_id' => $system_unit_id,
                        ':codigo' => $produtoInsumo['categoria']
                    ]);
                    $categoria = $stmtCategoria->fetch(PDO::FETCH_ASSOC);
                    $categoria_nome = $categoria['nome'] ?? null;
                }

                $insumos[] = [
                    'insumo' => $insumo_id,
                    'nome_insumo' => $nome_insumo,
                    'categoria' => $categoria_nome,
                    'quantidade' => $quantidade_desejada * $quantidade
                ];
            }

            $resultado[] = [
                'produto' => $produto_codigo,
                'nome' => $produto_nome,
                'quantidade' => $quantidade_desejada,
                'insumos' => $insumos
            ];

        }

        return [
            'success' => true,
            'consumos' => array_values($resultado)
        ];
    }


}
