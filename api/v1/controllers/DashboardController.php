<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class DashboardController {

    public static function getLojaIdBySystemUnitId($systemUnitId): array
    {
        global $pdo;

        $sql = "SELECT custom_code,name from system_unit WHERE id = :systemUnitId LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':systemUnitId', $systemUnitId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (isset($result['custom_code'])) {
            $response = [
                'success' => true,
                'lojaId' => $result['custom_code'],
                'name' => $result['name']
            ];
        }
        else {
            $response = [
                'success' => false,
                'name' => $result['name']
            ];
        }

        return $response;
    }

    public static function generateHourlySalesByStore($start_datetime, $end_datetime): array
    {
        global $pdo;

        $result = [
            'hours' => [],
            'lojas' => []
        ];

        // Gera o eixo X: ["00h", ..., "23h"]
        for ($h = 0; $h < 24; $h++) {
            $result['hours'][] = str_pad($h, 2, '0', STR_PAD_LEFT) . 'h';
        }

        try {
            $stmt = $pdo->prepare("
               SELECT 
                    lojaId,
                    ANY_VALUE(loja) AS nome_loja,
                    hora,
                    SUM(vlTotalRecebido) / COUNT(DISTINCT DATE(dataContabil)) AS media_hora
                FROM 
                    movimento_caixa
                WHERE 
                    dataContabil BETWEEN :start AND :end
                    AND cancelado = 0
                    AND vlTotalRecebido > 0
                GROUP BY 
                    lojaId, hora
                ORDER BY 
                    nome_loja, hora;
            ");

            $stmt->bindParam(':start', $start_datetime);
            $stmt->bindParam(':end', $end_datetime);
            $stmt->execute();

            $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupa os dados por loja
            $lojasMap = [];

            foreach ($rawData as $row) {
                $loja = $row['nome_loja'];
                $hora = (int)$row['hora'];
                $valor = (float)$row['media_hora'];

                if (!isset($lojasMap[$loja])) {
                    $lojasMap[$loja] = array_fill(0, 24, 0); // inicia todas as horas com 0
                }

                $lojasMap[$loja][$hora] += $valor;
            }

            // Formata para o formato final
            foreach ($lojasMap as $nome => $valores) {
                $result['lojas'][] = [
                    'nome' => $nome,
                    'valores' => $valores
                ];
            }

            return $result;
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao gerar dados: ' . $e->getMessage()
            ];
        }
    }

    public static function generateResumoFinanceiroPorLoja($lojaId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
            SELECT
                SUM(vlTotalRecebido) AS faturamento_bruto,
                SUM(vlDesconto) AS total_descontos,
                SUM(vlServicoRecebido) AS total_taxa_servico,
                SUM(numPessoas) AS total_clientes,
                count(num_controle) as numero_pedidos
            FROM
                movimento_caixa
            WHERE
                lojaId = :lojaId
                AND dataContabil BETWEEN :dt_inicio AND :dt_fim
                AND cancelado = 0
        ");

            $stmt->bindParam(':lojaId', $lojaId, PDO::PARAM_INT);
            $stmt->bindParam(':dt_inicio', $dt_inicio);
            $stmt->bindParam(':dt_fim', $dt_fim);
            $stmt->execute();

            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            $bruto = (float) $res['faturamento_bruto'] ?? 0;
            $descontos = (float) $res['total_descontos'] ?? 0;
            $taxaServico = (float) $res['total_taxa_servico'] ?? 0;
            $clientes = (int) $res['total_clientes'] ?? 0;

            $liquido = $bruto - $descontos - $taxaServico;
            $ticketMedio = $clientes > 0 ? $bruto / $clientes : 0;

            return [
                'faturamento_bruto' => round($bruto, 2),
                'descontos' => round($descontos, 2),
                'taxa_servico' => round($taxaServico, 2),
                'faturamento_liquido' => round($liquido, 2),
                'numero_clientes' => $clientes,
                'ticket_medio' => round($ticketMedio, 2),
                'numero_pedidos' => (int) $res['numero_pedidos'] ?? 0
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao gerar resumo: ' . $e->getMessage()
            ];
        }
    }

    public static function generateResumoFinanceiroPorLojaDiario($lojaId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $sql = "
            SELECT DISTINCT dataContabil AS data
            FROM movimento_caixa
            WHERE lojaId = :lojaId
              AND dataContabil BETWEEN :dt_inicio AND :dt_fim
              AND cancelado = 0
            ORDER BY data ASC
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':lojaId' => $lojaId,
                ':dt_inicio' => $dt_inicio,
                ':dt_fim' => $dt_fim,
            ]);

            $datas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $resumo = [];

            foreach ($datas as $data) {
                $inicio = $data . ' 00:00:00';
                $fim = $data . ' 23:59:59';

                $info = self::generateResumoFinanceiroPorLoja($lojaId, $inicio, $fim);

                $bruto = round((float) $info['faturamento_bruto'], 2);
                $descontos = round((float) $info['descontos'], 2);
                $taxaServico = round((float) $info['taxa_servico'], 2);
                $liquido = round((float) $info['faturamento_liquido'], 2);

                // Validação com tolerância de centavos
                $esperado = round($bruto - $descontos - $taxaServico, 2);
                $valido = abs($esperado - $liquido) < 0.01;

                $resumo[] = [
                    'dataContabil' => $data,
                    'faturamento_bruto' => $bruto,
                    'descontos' => $descontos,
                    'taxa_servico' => $taxaServico,
                    'faturamento_liquido' => $liquido,
                    'valido' => $valido
                ];
            }

            return [
                'success' => true,
                'data' => $resumo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao gerar resumo diário: ' . $e->getMessage()
            ];
        }
    }

    public static function getResumoModosVenda($lojaId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        // Soma total geral de faturamento
        $sqlTotal = "SELECT SUM(vlTotalRecebido) as total 
                 FROM movimento_caixa 
                 WHERE lojaId = :lojaId
                   AND dataContabil BETWEEN :dt_inicio AND :dt_fim";

        $stmtTotal = $pdo->prepare($sqlTotal);
        $stmtTotal->execute([
            ':lojaId' => $lojaId,
            ':dt_inicio' => $dt_inicio,
            ':dt_fim' => $dt_fim
        ]);

        $total = $stmtTotal->fetchColumn();

        if (!$total || $total == 0) {
            return ['success' => true, 'data' => [], 'total' => 0];
        }

        // Agrupar por modoVenda com soma + contagem
        $sql = "SELECT 
                modoVenda,
                COUNT(*) as quantidade,
                SUM(vlTotalRecebido) as valor,
                ROUND(SUM(vlTotalRecebido) / :total * 100, 2) as percentual
            FROM movimento_caixa
            WHERE lojaId = :lojaId
              AND dataContabil BETWEEN :dt_inicio AND :dt_fim
            GROUP BY modoVenda
            ORDER BY valor DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lojaId' => $lojaId,
            ':dt_inicio' => $dt_inicio,
            ':dt_fim' => $dt_fim,
            ':total' => $total
        ]);

        $resumo = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'total' => round($total, 2),
            'data' => $resumo
        ];
    }

    public static function getResumoMeiosPagamento($lojaId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        // Primeiro, somar o total de valorRecebido no período
        $sqlTotal = "SELECT SUM(valorRecebido) as total 
                 FROM meios_pagamento 
                 WHERE lojaId = :lojaId 
                   AND data_pagamento BETWEEN :dt_inicio AND :dt_fim";

        $stmtTotal = $pdo->prepare($sqlTotal);
        $stmtTotal->execute([
            ':lojaId' => $lojaId,
            ':dt_inicio' => $dt_inicio,
            ':dt_fim' => $dt_fim
        ]);

        $total = $stmtTotal->fetchColumn();

        if (!$total || $total == 0) {
            return ['success' => true, 'data' => [], 'total' => 0];
        }

        // Agora, agrupar por código e nome
        $sqlResumo = "SELECT 
                    codigo,
                    nome,
                    SUM(valorRecebido) as valor,
                    ROUND(SUM(valorRecebido) / :total * 100, 2) as percentual
                  FROM meios_pagamento
                  WHERE lojaId = :lojaId
                    AND data_pagamento BETWEEN :dt_inicio AND :dt_fim
                  GROUP BY codigo, nome
                  ORDER BY valor DESC";

        $stmtResumo = $pdo->prepare($sqlResumo);
        $stmtResumo->execute([
            ':lojaId' => $lojaId,
            ':dt_inicio' => $dt_inicio,
            ':dt_fim' => $dt_fim,
            ':total' => $total
        ]);

        $resumo = $stmtResumo->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'total' => round($total, 2),
            'data' => $resumo
        ];
    }

    public static function getRankingVendasProdutos($lojaId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $sql = "
            SELECT
                p.nome AS nome_produto,
                s.cod_material,
                SUM(s.quantidade) AS total_quantidade,
                SUM(s.valor_liquido) AS total_valor
            FROM _bi_sales s
            INNER JOIN products p
                ON s.cod_material = p.codigo AND s.system_unit_id = p.system_unit_id
            WHERE s.custom_code = :lojaId
              AND s.data_movimento BETWEEN :dt_inicio AND :dt_fim
            GROUP BY s.cod_material, p.nome
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':lojaId' => $lojaId,
                ':dt_inicio' => $dt_inicio,
                ':dt_fim' => $dt_fim
            ]);

            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Organiza os 4 rankings
            // Ordena por quantidade (descendente)
            $produtosQtd = $produtos;
            usort($produtosQtd, fn($a, $b) => $b['total_quantidade'] <=> $a['total_quantidade']);
            $maisVendidosQtd = array_slice($produtosQtd, 0, 10);

            // Ordena por valor (descendente)
            $produtosValor = $produtos;
            usort($produtosValor, fn($a, $b) => $b['total_valor'] <=> $a['total_valor']);
            $maisVendidosValor = array_slice($produtosValor, 0, 10);

            // Ordena por quantidade (ascendente, exclui zeros)
            $produtosQtdAsc = array_filter($produtos, fn($p) => $p['total_quantidade'] > 0);
            usort($produtosQtdAsc, fn($a, $b) => $a['total_quantidade'] <=> $b['total_quantidade']);
            $menosVendidosQtd = array_slice($produtosQtdAsc, 0, 10);

            // Ordena por valor (ascendente, exclui zeros)
            $produtosValorAsc = array_filter($produtos, fn($p) => $p['total_valor'] > 0);
            usort($produtosValorAsc, fn($a, $b) => $a['total_valor'] <=> $b['total_valor']);
            $menosVendidosValor = array_slice($produtosValorAsc, 0, 10);

            $filtrar = fn($item) => !in_array((int) $item['cod_material'], [9000, 9600]);

            return [
                'success' => true,
                'data' => [
                    'mais_vendidos_quantidade' => array_values(array_filter($maisVendidosQtd, $filtrar)),
                    'mais_vendidos_valor' => array_values(array_filter($maisVendidosValor, $filtrar)),
                    'menos_vendidos_quantidade' => array_values(array_filter($menosVendidosQtd, $filtrar)),
                    'menos_vendidos_valor' => array_values(array_filter($menosVendidosValor, $filtrar)),
                ]
            ];


        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao consultar ranking de vendas: ' . $e->getMessage()
            ];
        }
    }

    public static function ListMov($dt_inicio, $dt_fim): false|array
    {
        global $pdo;
        $sql = "SELECT * FROM movimento_caixa WHERE dataContabil BETWEEN :dt_inicio AND :dt_fim AND cancelado = 0";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':dt_inicio', $dt_inicio);
        $stmt->bindParam(':dt_fim', $dt_fim);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getLojasDoGrupo($grupoId): array
    {
        return array_map(
            fn($loja) => (int) $loja['custom_code'],
            BiController::getUnitsByGroup($grupoId)
        );
    }

    public static function generateResumoFinanceiroPorGrupo($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $resumo = [];

            foreach ($lojas as $loja) {
                $lojaId = (int) $loja['custom_code'];

                $stmt = $pdo->prepare("
                SELECT
                    SUM(vlTotalRecebido) AS faturamento_bruto,
                    SUM(vlDesconto) AS total_descontos,
                    SUM(vlServicoRecebido) AS total_taxa_servico,
                    SUM(numPessoas) AS total_clientes
                FROM movimento_caixa
                WHERE lojaId = :lojaId
                  AND dataContabil BETWEEN :dt_inicio AND :dt_fim
                  AND cancelado = 0
            ");
                $stmt->execute([
                    ':lojaId' => $lojaId,
                    ':dt_inicio' => $dt_inicio,
                    ':dt_fim' => $dt_fim
                ]);

                $res = $stmt->fetch(PDO::FETCH_ASSOC);

                $bruto = (float) $res['faturamento_bruto'] ?? 0;
                $descontos = (float) $res['total_descontos'] ?? 0;
                $taxaServico = (float) $res['total_taxa_servico'] ?? 0;
                $clientes = (int) $res['total_clientes'] ?? 0;

                $liquido = $bruto - $descontos - $taxaServico;
                $ticketMedio = $clientes > 0 ? $bruto / $clientes : 0;

                $resumo[] = [
                    'lojaId' => $lojaId,
                    'nomeLoja' => $loja['name'],
                    'faturamento_bruto' => round($bruto, 2),
                    'descontos' => round($descontos, 2),
                    'taxa_servico' => round($taxaServico, 2),
                    'faturamento_liquido' => round($liquido, 2),
                    'numero_clientes' => $clientes,
                    'ticket_medio' => round($ticketMedio, 2)
                ];
            }

            return [
                'success' => true,
                'data' => $resumo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao gerar resumo por grupo: ' . $e->getMessage()
            ];
        }
    }

    public static function generateResumoFinanceiroPorGrupoDiario($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $resumo = [];

            foreach ($lojas as $loja) {
                $lojaId = (int) $loja['custom_code'];
                $stmt = $pdo->prepare("
                SELECT DISTINCT dataContabil AS data
                FROM movimento_caixa
                WHERE lojaId = :lojaId
                  AND dataContabil BETWEEN :dt_inicio AND :dt_fim
                  AND cancelado = 0
                ORDER BY data ASC
            ");
                $stmt->execute([
                    ':lojaId' => $lojaId,
                    ':dt_inicio' => $dt_inicio,
                    ':dt_fim' => $dt_fim
                ]);

                $datas = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $resumoLoja = [];

                foreach ($datas as $data) {
                    $inicio = $data . ' 00:00:00';
                    $fim = $data . ' 23:59:59';

                    $info = self::generateResumoFinanceiroPorLoja($lojaId, $inicio, $fim);

                    $bruto = round((float) $info['faturamento_bruto'], 2);
                    $descontos = round((float) $info['descontos'], 2);
                    $taxaServico = round((float) $info['taxa_servico'], 2);
                    $liquido = round((float) $info['faturamento_liquido'], 2);

                    $esperado = round($bruto - $descontos - $taxaServico, 2);
                    $valido = abs($esperado - $liquido) < 0.01;

                    $resumoLoja[] = [
                        'dataContabil' => $data,
                        'faturamento_bruto' => $bruto,
                        'descontos' => $descontos,
                        'taxa_servico' => $taxaServico,
                        'faturamento_liquido' => $liquido,
                        'valido' => $valido
                    ];
                }

                $resumo[] = [
                    'lojaId' => $lojaId,
                    'nomeLoja' => $loja['name'],
                    'data' => $resumoLoja
                ];
            }

            return ['success' => true, 'data' => $resumo];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao gerar resumo diário por grupo: ' . $e->getMessage()];
        }
    }

    public static function getResumoModosVendaPorGrupo($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $resumo = [];

            foreach ($lojas as $loja) {
                $lojaId = (int) $loja['custom_code'];

                $sqlTotal = "SELECT SUM(vlTotalRecebido) as total 
                         FROM movimento_caixa 
                         WHERE lojaId = :lojaId
                           AND dataContabil BETWEEN :dt_inicio AND :dt_fim";
                $stmtTotal = $pdo->prepare($sqlTotal);
                $stmtTotal->execute([
                    ':lojaId' => $lojaId,
                    ':dt_inicio' => $dt_inicio,
                    ':dt_fim' => $dt_fim
                ]);

                $total = $stmtTotal->fetchColumn();

                if (!$total || $total == 0) {
                    $resumo[] = [
                        'lojaId' => $lojaId,
                        'nomeLoja' => $loja['name'],
                        'total' => 0,
                        'data' => []
                    ];
                    continue;
                }

                $sql = "SELECT 
                        modoVenda,
                        COUNT(*) as quantidade,
                        SUM(vlTotalRecebido) as valor,
                        ROUND(SUM(vlTotalRecebido) / :total * 100, 2) as percentual
                    FROM movimento_caixa
                    WHERE lojaId = :lojaId
                      AND dataContabil BETWEEN :dt_inicio AND :dt_fim
                    GROUP BY modoVenda
                    ORDER BY valor DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':lojaId' => $lojaId,
                    ':dt_inicio' => $dt_inicio,
                    ':dt_fim' => $dt_fim,
                    ':total' => $total
                ]);

                $resumo[] = [
                    'lojaId' => $lojaId,
                    'nomeLoja' => $loja['name'],
                    'total' => round($total, 2),
                    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ];
            }

            return ['success' => true, 'data' => $resumo];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao consultar resumo por modo de venda: ' . $e->getMessage()];
        }
    }

    public static function getResumoMeiosPagamentoPorGrupo($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $resumo = [];

            foreach ($lojas as $loja) {
                $lojaId = (int) $loja['custom_code'];

                $sqlTotal = "SELECT SUM(valorRecebido) as total 
                         FROM meios_pagamento 
                         WHERE lojaId = :lojaId 
                           AND data_pagamento BETWEEN :dt_inicio AND :dt_fim";
                $stmtTotal = $pdo->prepare($sqlTotal);
                $stmtTotal->execute([
                    ':lojaId' => $lojaId,
                    ':dt_inicio' => $dt_inicio,
                    ':dt_fim' => $dt_fim
                ]);

                $total = $stmtTotal->fetchColumn();

                if (!$total || $total == 0) {
                    $resumo[] = [
                        'lojaId' => $lojaId,
                        'nomeLoja' => $loja['name'],
                        'total' => 0,
                        'data' => []
                    ];
                    continue;
                }

                $sqlResumo = "SELECT 
                            codigo,
                            nome,
                            SUM(valorRecebido) as valor,
                            ROUND(SUM(valorRecebido) / :total * 100, 2) as percentual
                          FROM meios_pagamento
                          WHERE lojaId = :lojaId
                            AND data_pagamento BETWEEN :dt_inicio AND :dt_fim
                          GROUP BY codigo, nome
                          ORDER BY valor DESC";

                $stmtResumo = $pdo->prepare($sqlResumo);
                $stmtResumo->execute([
                    ':lojaId' => $lojaId,
                    ':dt_inicio' => $dt_inicio,
                    ':dt_fim' => $dt_fim,
                    ':total' => $total
                ]);

                $resumo[] = [
                    'lojaId' => $lojaId,
                    'nomeLoja' => $loja['name'],
                    'total' => round($total, 2),
                    'data' => $stmtResumo->fetchAll(PDO::FETCH_ASSOC)
                ];
            }

            return ['success' => true, 'data' => $resumo];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao consultar resumo por meios de pagamento: ' . $e->getMessage()];
        }
    }

    public static function getRankingVendasProdutosPorGrupo($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $resumo = [];

            foreach ($lojas as $loja) {
                $lojaId = (int) $loja['custom_code'];

                $sql = "
                SELECT
                    p.nome AS nome_produto,
                    s.cod_material,
                    SUM(s.quantidade) AS total_quantidade,
                    SUM(s.valor_liquido) AS total_valor
                FROM _bi_sales s
                INNER JOIN products p
                    ON s.cod_material = p.codigo AND s.system_unit_id = p.system_unit_id
                WHERE s.custom_code = :lojaId
                  AND s.data_movimento BETWEEN :dt_inicio AND :dt_fim
                GROUP BY s.cod_material, p.nome
            ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':lojaId' => $lojaId,
                    ':dt_inicio' => $dt_inicio,
                    ':dt_fim' => $dt_fim
                ]);

                $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $filtrar = fn($item) => !in_array((int) $item['cod_material'], [9000, 9600]);

                $ordenar = fn($arr, $campo, $ordem) =>
                array_values(array_filter(
                    ($ordem === 'asc'
                        ? array_slice(array_filter($arr, fn($p) => $p[$campo] > 0), 0, 10)
                        : array_slice($arr, 0, 10)
                    ),
                    $filtrar
                ));

                // Ordenações
                usort($produtos, fn($a, $b) => $b['total_quantidade'] <=> $a['total_quantidade']);
                $maisQtd = $ordenar($produtos, 'total_quantidade', 'desc');

                usort($produtos, fn($a, $b) => $b['total_valor'] <=> $a['total_valor']);
                $maisVal = $ordenar($produtos, 'total_valor', 'desc');

                usort($produtos, fn($a, $b) => $a['total_quantidade'] <=> $b['total_quantidade']);
                $menosQtd = $ordenar($produtos, 'total_quantidade', 'asc');

                usort($produtos, fn($a, $b) => $a['total_valor'] <=> $b['total_valor']);
                $menosVal = $ordenar($produtos, 'total_valor', 'asc');

                $resumo[] = [
                    'lojaId' => $lojaId,
                    'nomeLoja' => $loja['name'],
                    'mais_vendidos_quantidade' => $maisQtd,
                    'mais_vendidos_valor' => $maisVal,
                    'menos_vendidos_quantidade' => $menosQtd,
                    'menos_vendidos_valor' => $menosVal
                ];
            }

            return ['success' => true, 'data' => $resumo];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao gerar ranking de produtos por grupo: ' . $e->getMessage()];
        }
    }













}
