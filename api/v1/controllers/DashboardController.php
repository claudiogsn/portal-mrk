<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php
require_once __DIR__ . '/../libs/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;


class DashboardController
{

    private static function calcularVariacao($atual, $anterior): float
    {
        if ($anterior == 0) {
            return $atual > 0 ? 100.0 : 0.0;
        }

        return (($atual - $anterior) / abs($anterior)) * 100;
    }


    private static function gerarHtmlComparativoLoja(
        string            $nomeLoja,
        DateTimeInterface $inicioAtual,
        DateTimeInterface $fimAtual,
        DateTimeInterface $inicioAnterior,
        DateTimeInterface $fimAnterior,
        array             $dadosAtual,
        array             $dadosAnterior,
        array             $ranking = [],
        array             $modosVendaAnterior = [],
        array             $modosVendaAtual = []
    ): string
    {
        $campos = [
            'faturamento_bruto' => 'Faturamento Bruto',
            'descontos' => 'Descontos',
            'taxa_servico' => 'Taxa de Serviço',
            'faturamento_liquido' => 'Faturamento Líquido',
            'numero_clientes' => 'Número de Clientes',
            'ticket_medio' => 'Ticket Médio',
        ];

        $html = '<div style="page-break-after: always; font-family: Arial, sans-serif;">';

        // Cabeçalho
        $html .= "<div style='position: relative; margin-bottom: 16px;'>
        <div style='position: absolute; top: 0; right: 0;'>
            <img src='https://portal.mrksolucoes.com.br/external/reports/logo.png' alt='Logo' style='max-height: 80px;' />
        </div>
        <div>
            <h2 style='margin: 0;'>Portal MRK</h2>
            <h3 style='margin: 0;'>Relatório Semanal - {$nomeLoja}</h3>
            <p style='margin: 4px 0 0 0; font-size: 14px;'>
                Comparativo entre <strong>{$inicioAnterior->format('d/m')} a {$fimAnterior->format('d/m')}</strong>
                e <strong>{$inicioAtual->format('d/m')} a {$fimAtual->format('d/m')}</strong>
            </p>
        </div>
    </div><br><br>";

        // Tabela comparativa principal
        $html .= "<table style='width:100%; border-collapse: collapse; font-size: 12px;'>
        <thead>
            <tr style='border-bottom: 1px solid #000000;'>
                <th style='padding: 6px; text-align: left;'> </th>
                <th style='padding: 6px; text-align: right;'>
                    Semana Anterior<br>
                    <strong>{$inicioAnterior->format('d/m')} - {$fimAnterior->format('d/m')}</strong>
                </th>
                <th style='padding: 6px; text-align: right;'>
                    Semana Atual<br>
                    <strong>{$inicioAtual->format('d/m')} - {$fimAtual->format('d/m')}</strong>
                </th>
                <th style='padding: 6px; text-align: right; width: 30px; font-size: 8px;'>Var (%)</th>
            </tr>
        </thead>
        <tbody>";

        foreach ($campos as $chave => $label) {
            $anterior = $dadosAnterior[$chave] ?? 0;
            $atual = $dadosAtual[$chave] ?? 0;

            $variacao = self::calcularVariacao($atual, $anterior);
            $variacaoFormatada = number_format($variacao, 2, ',', '.') . '%';
            $class = $variacao >= 0 ? 'color: green;' : 'color: red;';

            $isMonetario = in_array($chave, ['faturamento_bruto', 'descontos', 'taxa_servico', 'faturamento_liquido', 'ticket_medio']);

            $anteriorFormatado = $isMonetario ? 'R$ ' . number_format($anterior, 2, ',', '.') : number_format($anterior, 0, ',', '.');
            $atualFormatado = $isMonetario ? 'R$ ' . number_format($atual, 2, ',', '.') : number_format($atual, 0, ',', '.');

            $html .= "<tr>
            <td style='padding: 6px; text-align: left;'>{$label}</td>
            <td style='padding: 6px; text-align: right;'>{$anteriorFormatado}</td>
            <td style='padding: 6px; text-align: right;'>{$atualFormatado}</td>
            <td style='padding: 6px; text-align: right; font-size: 8px; {$class}'>{$variacaoFormatada}</td>
        </tr>";
        }

        $html .= "</tbody></table>";

        // Tabela de Modos de Venda
        $html .= "<br><table style='width:100%; border-collapse: collapse; font-size: 12px;'>
        <thead>
            <tr style='border-bottom: 1px solid #000000;'>
                <th style='padding: 6px; text-align: left;'>Comparativo Modo de Venda</th>
                <th style='padding: 6px; text-align: right;'> </th>
                <th style='padding: 6px; text-align: right;'> </th>
                <th style='padding: 6px; text-align: right; font-size: 8px;'> </th>
            </tr>
        </thead>
        <tbody>";

        $modos = ['SALAO', 'DELIVERY', 'BALCAO'];

        $mapAnt = [];
        foreach ($modosVendaAnterior as $mv) {
            $mapAnt[$mv['modoVenda']] = (float)$mv['valor'];
        }

        $mapAtual = [];
        foreach ($modosVendaAtual as $mv) {
            $mapAtual[$mv['modoVenda']] = (float)$mv['valor'];
        }

        foreach ($modos as $modo) {
            $anterior = $mapAnt[$modo] ?? 0;
            $atual = $mapAtual[$modo] ?? 0;

            $variacao = self::calcularVariacao($atual, $anterior);
            $variacaoFormatada = number_format($variacao, 2, ',', '.') . '%';
            $class = $variacao >= 0 ? 'color: green;' : 'color: red;';

            $antFmt = 'R$ ' . number_format($anterior, 2, ',', '.');
            $atualFmt = 'R$ ' . number_format($atual, 2, ',', '.');

            $html .= "<tr>
            <td style='padding: 6px; text-align: left;'>{$modo}</td>
            <td style='padding: 6px; text-align: right;'>{$antFmt}</td>
            <td style='padding: 6px; text-align: right;'>{$atualFmt}</td>
            <td style='padding: 6px; text-align: right; font-size: 8px; {$class}'>{$variacaoFormatada}</td>
        </tr>";
        }

        $html .= "</tbody></table>";

        // Rankings
        $html .= "<br><hr style='margin: 20px 0; border: none;'>";

        $renderRankingBlock = function ($titulo, $anterior, $atual, $formatter) {
            $html = "<table style='width: 100%; font-size: 12px; border-collapse: collapse; margin-bottom: 16px;'>
            <thead>
                <tr style='border-bottom: 1px solid #000;'>
                    <th style='text-align: left; padding: 6px;'>{$titulo}</th>
                    <th style='text-align: right; padding: 6px;'> </th>
                    <th style='text-align: right; padding: 6px;'> </th>
                </tr>
            </thead><tbody>";

            for ($i = 0; $i < 3; $i++) {
                $left = $formatter($anterior[$i] ?? null);
                $right = $formatter($atual[$i] ?? null);
                $html .= "<tr>
                <td style='padding: 4px; text-align: left;'> </td>
                <td style='padding: 4px; text-align: right;'>{$left}</td>
                <td style='padding: 4px; text-align: right;'>{$right}</td>
            </tr>";
            }

            return $html . "</tbody></table>";
        };

        $html .= $renderRankingBlock('Top 3 MAIOR faturamento:', $ranking['anterior']['mais_vendidos_valor'] ?? [], $ranking['atual']['mais_vendidos_valor'] ?? [], fn($i) => $i ? "{$i['nome_produto']} (R$ " . number_format($i['total_valor'], 0, ',', '.') . ")" : '');
        $html .= $renderRankingBlock('Top 3 MENOR faturamento:', $ranking['anterior']['menos_vendidos_valor'] ?? [], $ranking['atual']['menos_vendidos_valor'] ?? [], fn($i) => $i ? "{$i['nome_produto']} (R$ " . number_format($i['total_valor'], 0, ',', '.') . ")" : '');
        $html .= $renderRankingBlock('Top 3 MAIS vendidos:', $ranking['anterior']['mais_vendidos_quantidade'] ?? [], $ranking['atual']['mais_vendidos_quantidade'] ?? [], fn($i) => $i ? "{$i['nome_produto']} ({$i['total_quantidade']})" : '');
        $html .= $renderRankingBlock('Top 3 MENOS vendidos:', $ranking['anterior']['menos_vendidos_quantidade'] ?? [], $ranking['atual']['menos_vendidos_quantidade'] ?? [], fn($i) => $i ? "{$i['nome_produto']} ({$i['total_quantidade']})" : '');

        $html .= "<p style='text-align: right; font-size: 12px; margin-top: 30px; color: #777;'>Gerado em " . date('d/m/Y H:i') . "</p>";
        $html .= "</div>";

        return $html;
    }


    private static function gerarTabelaModosVenda(array $dadosAnteriores, array $dadosAtuais): string
    {
        $modos = ['SALAO', 'DELIVERY', 'BALCAO'];

        // Indexar por modo
        $mapAnteriores = [];
        foreach ($dadosAnteriores as $item) {
            $mapAnteriores[$item['modoVenda']] = (float)$item['valor'];
        }

        $mapAtuais = [];
        foreach ($dadosAtuais as $item) {
            $mapAtuais[$item['modoVenda']] = (float)$item['valor'];
        }

        $html = "<br><table style='width:100%; border-collapse: collapse; font-size: 14px;'>";
        $html .= "<thead><tr style='border-bottom: 1px solid #000000;'>";
        $html .= "<th style='padding: 6px; text-align: left;'>Modo</th>";
        $html .= "<th style='padding: 6px; text-align: right;'></th>";
        $html .= "<th style='padding: 6px; text-align: right;'></th>";
        $html .= "<th style='padding: 6px; text-align: right; width: 30px; font-size: 8px;'>Var</th>";
        $html .= "</tr></thead><tbody>";

        foreach ($modos as $modo) {
            $anterior = $mapAnteriores[$modo] ?? 0.0;
            $atual = $mapAtuais[$modo] ?? 0.0;

            $variacao = self::calcularVariacao($atual, $anterior);
            $variacaoFormatada = number_format($variacao, 2, ',', '.') . '%';
            $cor = $variacao >= 0 ? 'green' : 'red';

            $anteriorFormatado = 'R$ ' . number_format($anterior, 2, ',', '.');
            $atualFormatado = 'R$ ' . number_format($atual, 2, ',', '.');

            $html .= "<tr>";
            $html .= "<td style='padding: 6px; text-align: left;'>{$modo}</td>";
            $html .= "<td style='padding: 6px; text-align: right;'>{$anteriorFormatado}</td>";
            $html .= "<td style='padding: 6px; text-align: right;'>{$atualFormatado}</td>";
            $html .= "<td style='padding: 6px; text-align: right; font-size: 8px; color: {$cor};'>{$variacaoFormatada}</td>";
            $html .= "</tr>";
        }

        $html .= "</tbody></table>";
        return $html;
    }

    public static function gerarRelatorioFinanceiroSemanalPorGrupo($grupoId): array
    {
        $hoje = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));

        $inicioAtual = $hoje->modify('last sunday')->modify('-6 days'); // segunda passada
        $fimAtual = $hoje->modify('last sunday'); // domingo passado

        $inicioAnterior = $inicioAtual->modify('-7 days');
        $fimAnterior = $fimAtual->modify('-7 days');

        // Buscar resumos financeiros
        $resumoAtual = self::generateResumoFinanceiroPorGrupo($grupoId, $inicioAtual->format('Y-m-d 00:00:00'), $fimAtual->format('Y-m-d 23:59:59'));
        $resumoAnterior = self::generateResumoFinanceiroPorGrupo($grupoId, $inicioAnterior->format('Y-m-d 00:00:00'), $fimAnterior->format('Y-m-d 23:59:59'));

        if (!$resumoAtual['success'] || !$resumoAnterior['success']) {
            return ['success' => false, 'message' => 'Erro ao buscar dados das semanas.'];
        }

        // Buscar ranking top 3 para cada semana
        $rankingAtual = self::getRankingTop3ProdutosPorGrupo($grupoId, $inicioAtual->format('Y-m-d'), $fimAtual->format('Y-m-d'));
        $rankingAnterior = self::getRankingTop3ProdutosPorGrupo($grupoId, $inicioAnterior->format('Y-m-d'), $fimAnterior->format('Y-m-d'));

        // Indexar rankings por lojaId
        $rankingsPorLoja = [];

        foreach ($rankingAnterior['data'] ?? [] as $loja) {
            $rankingsPorLoja[$loja['lojaId']]['anterior'] = $loja;
        }

        foreach ($rankingAtual['data'] ?? [] as $loja) {
            $rankingsPorLoja[$loja['lojaId']]['atual'] = $loja;
        }

        // Indexar resumos
        $dadosAtuais = [];
        foreach ($resumoAtual['data'] as $loja) {
            $dadosAtuais[$loja['lojaId']] = $loja;
        }

        $dadosAnteriores = [];
        foreach ($resumoAnterior['data'] as $loja) {
            $dadosAnteriores[$loja['lojaId']] = $loja;
        }

        $modosVendaAtual = self::getResumoModosVendaPorGrupo($grupoId, $inicioAtual->format('Y-m-d'), $fimAtual->format('Y-m-d'));
        $modosVendaAnterior = self::getResumoModosVendaPorGrupo($grupoId, $inicioAnterior->format('Y-m-d'), $fimAnterior->format('Y-m-d'));

        $mvAtual = [];
        foreach ($modosVendaAtual['data'] ?? [] as $item) {
            $mvAtual[$item['lojaId']] = $item['data'];
        }

        $mvAnterior = [];
        foreach ($modosVendaAnterior['data'] ?? [] as $item) {
            $mvAnterior[$item['lojaId']] = $item['data'];
        }

        // Montar HTML por loja
        $html = '';
        foreach ($dadosAtuais as $lojaId => $dadosLojaAtual) {
            $dadosLojaAnterior = $dadosAnteriores[$lojaId] ?? [
                'faturamento_bruto' => 0,
                'descontos' => 0,
                'taxa_servico' => 0,
                'faturamento_liquido' => 0,
                'numero_clientes' => 0,
                'ticket_medio' => 0
            ];

            $ranking = $rankingsPorLoja[$lojaId] ?? [
                'anterior' => [],
                'atual' => []
            ];

            $html .= self::gerarHtmlComparativoLoja(
                $dadosLojaAtual['nomeLoja'],
                $inicioAtual, $fimAtual,
                $inicioAnterior, $fimAnterior,
                $dadosLojaAtual,
                $dadosLojaAnterior,
                $ranking,
                $mvAnterior[$lojaId] ?? [],
                $mvAtual[$lojaId] ?? []
            );

        }

        // Gerar PDF
        $dompdf = new Dompdf((new Options())->set('isRemoteEnabled', true));
        $dompdf->loadHtml('<html><body>' . $html . '</body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $fileName = 'relatorio_semanal_grupo_' . $grupoId . '_' . date('Ymd_His') . '.pdf';
        $filePath = __DIR__ . '/../public/reports/' . $fileName;
        $publicUrl = 'https://portal.mrksolucoes.com.br/api/v1/public/reports/' . $fileName;

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }

        file_put_contents($filePath, $dompdf->output());

        return [
            'success' => true,
            'url' => $publicUrl
        ];
    }

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
        } else {
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

            $bruto = (float)$res['faturamento_bruto'] ?? 0;
            $descontos = (float)$res['total_descontos'] ?? 0;
            $taxaServico = (float)$res['total_taxa_servico'] ?? 0;
            $clientes = (int)$res['total_clientes'] ?? 0;

            $liquido = $bruto - $descontos - $taxaServico;
            $ticketMedio = $clientes > 0 ? $bruto / $clientes : 0;

            return [
                'faturamento_bruto' => round($bruto, 2),
                'descontos' => round($descontos, 2),
                'taxa_servico' => round($taxaServico, 2),
                'faturamento_liquido' => round($liquido, 2),
                'numero_clientes' => $clientes,
                'ticket_medio' => round($ticketMedio, 2),
                'numero_pedidos' => (int)$res['numero_pedidos'] ?? 0
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

                $bruto = round((float)$info['faturamento_bruto'], 2);
                $descontos = round((float)$info['descontos'], 2);
                $taxaServico = round((float)$info['taxa_servico'], 2);
                $liquido = round((float)$info['faturamento_liquido'], 2);

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

            $filtrar = fn($item) => !in_array((int)$item['cod_material'], [9000, 9600]);

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

    public static function generateHourlySalesByGrupo($grupoId, $start_datetime, $end_datetime): array
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
            $lojas = BiController::getUnitsByGroup($grupoId);
            $lojasMap = [];

            foreach ($lojas as $loja) {
                $lojaId = (int)$loja['custom_code'];
                $nomeLoja = $loja['name'];

                $stmt = $pdo->prepare("
                SELECT 
                    hora,
                    SUM(vlTotalRecebido) / COUNT(DISTINCT DATE(dataContabil)) AS media_hora
                FROM 
                    movimento_caixa
                WHERE 
                    lojaId = :lojaId
                    AND dataContabil BETWEEN :start AND :end
                    AND cancelado = 0
                    AND vlTotalRecebido > 0
                GROUP BY 
                    hora
                ORDER BY 
                    hora
            ");

                $stmt->execute([
                    ':lojaId' => $lojaId,
                    ':start' => $start_datetime,
                    ':end' => $end_datetime
                ]);

                $horarios = array_fill(0, 24, 0);

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $hora = (int)$row['hora'];
                    $media = (float)$row['media_hora'];
                    $horarios[$hora] = round($media, 2);
                }

                $result['lojas'][] = [
                    'nome' => $nomeLoja,
                    'valores' => $horarios
                ];
            }

            return $result;
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao gerar dados por grupo: ' . $e->getMessage()
            ];
        }
    }

    public static function generateResumoFinanceiroPorGrupo($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $resumo = [];

            foreach ($lojas as $loja) {
                $lojaId = (int)$loja['custom_code'];

                $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS numero_pedidos,
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

                $bruto = (float)$res['faturamento_bruto'] ?? 0;
                $descontos = (float)$res['total_descontos'] ?? 0;
                $taxaServico = (float)$res['total_taxa_servico'] ?? 0;
                $clientes = (int)$res['total_clientes'] ?? 0;

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
                    'ticket_medio' => round($ticketMedio, 2),
                    'numero_pedidos' => (int)$res['numero_pedidos'] ?? 0
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
                $lojaId = (int)$loja['custom_code'];
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

                    $bruto = round((float)$info['faturamento_bruto'], 2);
                    $descontos = round((float)$info['descontos'], 2);
                    $taxaServico = round((float)$info['taxa_servico'], 2);
                    $liquido = round((float)$info['faturamento_liquido'], 2);

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
                $lojaId = (int)$loja['custom_code'];

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
                $lojaId = (int)$loja['custom_code'];

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
                $lojaId = (int)$loja['custom_code'];

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

                $filtrar = fn($item) => !in_array((int)$item['cod_material'], [9000, 9600]);

                $ordenar = fn($arr, $campo, $ordem) => array_values(array_filter(
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

    public static function getRankingTop3ProdutosPorGrupo($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $resumo = [];

            foreach ($lojas as $loja) {
                $lojaId = (int)$loja['custom_code'];

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

                $filtrar = fn($item) => !in_array((int)$item['cod_material'], [9000, 9600]) &&
                    stripos($item['nome_produto'], 'taxa') === false &&
                    stripos($item['nome_produto'], 'mal passado') === false &&
                    stripos($item['nome_produto'], 'ao ponto') === false &&
                    stripos($item['nome_produto'], 'bem passado') === false &&
                    stripos($item['nome_produto'], 'serviço') === false;

                $ordenar = fn($arr, $campo, $ordem) => array_values(array_filter(
                    ($ordem === 'asc'
                        ? array_slice(array_filter($arr, fn($p) => $p[$campo] > 0), 0, 3) // top 3 crescentes
                        : array_slice($arr, 0, 3) // top 3 decrescentes
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

    //---------------------------------------------------------------------------------------------------------------**

    public static function generateResumoEstoquePorGrupoNAuth($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $financeiros = self::generateResumoFinanceiroPorGrupo($grupoId, $dt_inicio, $dt_fim)['data'];
            $resumo = [];

            foreach ($lojas as $loja) {
                $system_unit_id = $loja['system_unit_id'];

                $stmt = $pdo->prepare("
                SELECT 
                    SUM(entradas * preco_custo) AS total_compras,
                    SUM(saidas * preco_custo) AS total_saidas,
                    SUM(saidas * preco_custo) AS cmv,
                    SUM(CASE 
                        WHEN data = :dt_fim AND contagem_realizada < contagem_ideal 
                        THEN (contagem_ideal - contagem_realizada) * preco_custo
                        ELSE 0
                    END) AS desperdicio
                FROM fluxo_estoque
                WHERE system_unit_id = :unit AND data BETWEEN :dt_inicio AND :dt_fim
            ");
                $stmt->execute([
                    ':unit' => $system_unit_id,
                    ':dt_inicio' => $dt_inicio,
                    ':dt_fim' => $dt_fim
                ]);

                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                $faturamentoBruto = 0;
                foreach ($financeiros as $fin) {
                    if ((int)$fin['lojaId'] === (int)$loja['custom_code']) {
                        $faturamentoBruto = (float)$fin['faturamento_bruto'];
                        break;
                    }
                }

                $percentualCmv = $faturamentoBruto > 0 ? ($res['cmv'] / $faturamentoBruto) * 100 : 0;

                $resumo[] = [
                    'lojaId' => $system_unit_id,
                    'nomeLoja' => $loja['name'],
                    'faturamento_bruto' => round($faturamentoBruto, 2),
                    'cmv' => round($res['cmv'], 2),
                    'percentual_cmv' => round($percentualCmv, 2),
                    'total_compras' => round($res['total_compras'], 2),
                    'total_saidas' => round($res['total_saidas'], 2),
                    'desperdicio' => round($res['desperdicio'], 2)
                ];
            }

            return ['success' => true, 'data' => $resumo];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public static function generateResumoEstoquePorGrupo($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $financeiros = self::generateResumoFinanceiroPorGrupo($grupoId, $dt_inicio, $dt_fim)['data'];
            $resumo = [];

            foreach ($lojas as $loja) {
                $system_unit_id = $loja['system_unit_id'];

                $stmt = $pdo->prepare("
                SELECT 
                    SUM(entradas * preco_custo) AS total_compras,
                    SUM(saidas * preco_custo) AS total_saidas,
                    SUM(saidas * preco_custo) AS cmv,
                    SUM(CASE 
                        WHEN data = :dt_fim AND contagem_realizada < contagem_ideal 
                        THEN (contagem_ideal - contagem_realizada) * preco_custo
                        ELSE 0
                    END) AS desperdicio
                FROM fluxo_estoque
                WHERE system_unit_id = :unit AND data BETWEEN :dt_inicio AND :dt_fim
            ");
                $stmt->execute([
                    ':unit' => $system_unit_id,
                    ':dt_inicio' => $dt_inicio,
                    ':dt_fim' => $dt_fim
                ]);

                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                $faturamentoBruto = 0;
                foreach ($financeiros as $fin) {
                    if ((int)$fin['lojaId'] === (int)$loja['custom_code']) {
                        $faturamentoBruto = (float)$fin['faturamento_bruto'];
                        break;
                    }
                }

                $percentualCmv = $faturamentoBruto > 0 ? ($res['cmv'] / $faturamentoBruto) * 100 : 0;

                $resumo[] = [
                    'lojaId' => $system_unit_id,
                    'nomeLoja' => $loja['name'],
                    'faturamento_bruto' => round($faturamentoBruto, 2),
                    'cmv' => round($res['cmv'], 2),
                    'percentual_cmv' => round($percentualCmv, 2),
                    'total_compras' => round($res['total_compras'], 2),
                    'total_saidas' => round($res['total_saidas'], 2),
                    'desperdicio' => round($res['desperdicio'], 2)
                ];
            }

            return ['success' => true, 'data' => $resumo];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    public static function generateCmvEvolucao($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $dados = [];

            $periodo = new DatePeriod(
                new DateTime($dt_inicio),
                new DateInterval('P1D'),
                (new DateTime($dt_fim))->modify('+1 day')
            );

            foreach ($lojas as $loja) {
                $unitId = $loja['system_unit_id'];
                $valores = [];

                foreach ($periodo as $dia) {
                    $data = $dia->format('Y-m-d');

                    $stmt = $pdo->prepare("
                    SELECT SUM(saidas * preco_custo) AS cmv
                    FROM fluxo_estoque
                    WHERE system_unit_id = :unit AND data = :data
                ");
                    $stmt->execute([':unit' => $unitId, ':data' => $data]);
                    $valor = $stmt->fetchColumn() ?: 0;

                    $valores[] = round($valor, 2);
                }

                $dados[] = ['nome' => $loja['name'], 'valores' => $valores];
            }

            return [
                'success' => true,
                'data' => $dados,
                'labels' => array_map(fn($d) => $d->format('d/m'), iterator_to_array($periodo))
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function generateTopComprasPorProduto($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $saida = [];

            foreach ($lojas as $loja) {
                $unitId = $loja['system_unit_id'];

                $stmt = $pdo->prepare("
                SELECT nome_produto AS name, ROUND(SUM(entradas * preco_custo), 2) AS value
                FROM fluxo_estoque
                WHERE system_unit_id = :unitId AND data BETWEEN :inicio AND :fim
                GROUP BY nome_produto
                ORDER BY value DESC
                LIMIT 5
            ");
                $stmt->execute([
                    ':unitId' => $unitId,
                    ':inicio' => $dt_inicio,
                    ':fim' => $dt_fim
                ]);

                $topProdutos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $saida[] = [
                    'lojaId' => $unitId,
                    'nomeLoja' => $loja['name'],
                    'produtos' => $topProdutos
                ];
            }

            return ['success' => true, 'data' => $saida];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function generateCmvPorProduto($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $saida = [];

            foreach ($lojas as $loja) {
                $unitId = $loja['system_unit_id'];

                $stmt = $pdo->prepare("
                SELECT nome_produto AS name, ROUND(SUM(saidas * preco_custo), 2) AS value
                FROM fluxo_estoque
                WHERE system_unit_id = :unitId AND data BETWEEN :inicio AND :fim
                GROUP BY nome_produto
                ORDER BY value DESC
                LIMIT 5
            ");
                $stmt->execute([
                    ':unitId' => $unitId,
                    ':inicio' => $dt_inicio,
                    ':fim' => $dt_fim
                ]);

                $topCmv = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $saida[] = [
                    'lojaId' => $unitId,
                    'nomeLoja' => $loja['name'],
                    'produtos' => $topCmv
                ];
            }

            return ['success' => true, 'data' => $saida];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function generateCmvPorCategoria($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $saida = [];

            foreach ($lojas as $loja) {
                $unitId = $loja['system_unit_id'];

                $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(cat.nome, 'Sem Categoria') AS name,
                    ROUND(SUM(f.saidas * f.preco_custo), 2) AS value
                FROM fluxo_estoque f
                LEFT JOIN categorias cat 
                    ON f.categoria = cat.codigo AND f.system_unit_id = cat.system_unit_id
                WHERE f.system_unit_id = :unitId
                  AND f.data BETWEEN :inicio AND :fim
                GROUP BY cat.nome
                ORDER BY value DESC
                LIMIT 5
            ");
                $stmt->execute([
                    ':unitId' => $unitId,
                    ':inicio' => $dt_inicio,
                    ':fim' => $dt_fim
                ]);

                $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $saida[] = [
                    'lojaId' => $unitId,
                    'nomeLoja' => $loja['name'],
                    'categorias' => $categorias
                ];
            }

            return ['success' => true, 'data' => $saida];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }


}
