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

    private static function gerarGraficoBase64(array $labels, array $fats, array $compras): string
    {


        $chart = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Faturamento',
                        'data' => $fats,
                        'backgroundColor' => '#007bff'
                    ],
                    [
                        'label' => 'Compras',
                        'data' => $compras,
                        'backgroundColor' => '#dc3545'
                    ]
                ]
            ],
            'options' => [
                'plugins' => [
                    'legend' => ['position' => 'top'],
                    'datalabels' => [
                        'anchor' => 'end',
                        'align' => 'end',
                        'color' => '#000',
                        'font' => ['weight' => 'bold'],
                        'display' => true
                    ]
                ],
                'title' => [
                    'display' => true,
                    'text' => 'Comparativo Faturamento vs Compras'
                ],
                'scales' => [
                    'y' => ['beginAtZero' => true],
                    'x' => [
                        'ticks' => ['autoSkip' => false]
                    ]
                ]
            ]
        ];

        $payload = json_encode([
            'chart' => $chart,
            'width' => 800,
            'height' => 400,
            'format' => 'png',
            'backgroundColor' => 'white',
            'plugins' => ['datalabels']
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://quickchart.io/chart');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $imageData = curl_exec($ch);

        if ($imageData === false || strlen($imageData) < 1000) {
            error_log('Erro ao gerar imagem do gráfico com QuickChart: ' . curl_error($ch));
            error_log('Resposta recebida: ' . substr($imageData ?? '', 0, 500));
            error_log('Payload enviado (JSON): ' . $payload);
            curl_close($ch);
            return '';
        } else {
            error_log('Imagem do gráfico gerada com sucesso.');
            error_log('Payload enviado (JSON): ' . $payload);
        }

        curl_close($ch);
        return 'data:image/png;base64,' . base64_encode($imageData);
    }



    private static function gerarHtmlComparativoComprasPorLoja(
        DateTimeInterface $inicioAtual,
        DateTimeInterface $fimAtual,
        DateTimeInterface $inicioAnterior,
        DateTimeInterface $fimAnterior,
        array $dadosAtuais,
        array $dadosAnteriores,
        array $comprasAtual,
        array $comprasAnterior
    ): string {
        $labels = [];
        $fatValues = [];
        $compraValues = [];
        // Indexar faturamento atual por nome da loja
        $mapFaturamentoAtual = [];
        foreach ($dadosAtuais as $item) {
            $nome = strtoupper($item['nomeLoja']);
            $mapFaturamentoAtual[$nome] = (float)($item['faturamento_liquido'] ?? 0);
        }

        // Indexar faturamento anterior por nome da loja
        $mapFaturamentoAnterior = [];
        foreach ($dadosAnteriores as $item) {
            $nome = strtoupper($item['nomeLoja']);
            $mapFaturamentoAnterior[$nome] = (float)($item['faturamento_liquido'] ?? 0);
        }

        // Indexar compras atuais por nome da loja
        $mapComprasAtual = [];
        foreach ($comprasAtual as $item) {
            $nome = strtoupper($item['nomeLoja']);
            $mapComprasAtual[$nome] = array_sum(array_column($item['notas'], 'valor_total'));
        }

        // Indexar compras anteriores por nome da loja
        $mapComprasAnterior = [];
        foreach ($comprasAnterior as $item) {
            $nome = strtoupper($item['nomeLoja']);
            $mapComprasAnterior[$nome] = array_sum(array_column($item['notas'], 'valor_total'));
        }

        // Unificar nomes
        $nomesLojas = array_unique(array_merge(
            array_keys($mapFaturamentoAtual),
            array_keys($mapFaturamentoAnterior),
            array_keys($mapComprasAtual),
            array_keys($mapComprasAnterior)
        ));

        // Iniciar HTML
        $html = "<div style='font-family: Arial, sans-serif;'>";
        $html .= "<div style='position: relative; margin-bottom: 16px;'>
        <div style='position: absolute; top: 0; right: 0;'>
            <img src='https://portal.mrksolucoes.com.br/external/reports/logo.png' alt='Logo' style='max-height: 80px;' />
        </div>
        <div>
            <h2 style='margin: 0;'>Portal MRK</h2>
            <h3 style='margin: 0;'>Relatório Semanal - Faturamento X Compras</h3>
            <p style='margin: 4px 0 0 0; font-size: 14px;'>
                Comparativo entre <strong>{$inicioAnterior->format('d/m')} a {$fimAnterior->format('d/m')}</strong>
                e <strong>{$inicioAtual->format('d/m')} a {$fimAtual->format('d/m')}</strong>
            </p>
        </div>
    </div><br>";

        $html .= "<center><h3 style='margin-top: 40px;'>Resumo</h3></center>";


        foreach ($nomesLojas as $nome) {
            $fatAtual = $mapFaturamentoAtual[$nome] ?? 0;
            $fatAnterior = $mapFaturamentoAnterior[$nome] ?? 0;
            $compraAtual = $mapComprasAtual[$nome] ?? 0;
            $compraAnterior = $mapComprasAnterior[$nome] ?? 0;

            $fatAtualFmt = 'R$ ' . number_format($fatAtual, 2, ',', '.');
            $fatAnteriorFmt = 'R$ ' . number_format($fatAnterior, 2, ',', '.');
            $compraAtualFmt = 'R$ ' . number_format($compraAtual, 2, ',', '.');
            $compraAnteriorFmt = 'R$ ' . number_format($compraAnterior, 2, ',', '.');

            $varFat = self::calcularVariacao($fatAtual, $fatAnterior);
            $varCompra = self::calcularVariacao($compraAtual, $compraAnterior);

            $varFatFmt = number_format($varFat, 2, ',', '.') . '%';
            $varCompraFmt = number_format($varCompra, 2, ',', '.') . '%';

            $classFat = $varFat >= 0 ? 'color: green;' : 'color: red;';
            $classCompra = $varCompra >= 0 ? 'color: green;' : 'color: red;';

            $html .= "
        <table style='width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px;'>
            <thead>
                <tr style='border-bottom: 1px solid #000; background-color: #f0f0f0;'>
                    <th style='text-align: left; padding: 6px;'>{$nome}</th>
                    <th style='text-align: right; padding: 6px;'>Semana Atual</th>
                    <th style='text-align: right; padding: 6px;'>Semana Anterior</th>
                    <th style='text-align: right; padding: 6px;'>Var (%)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style='padding: 6px;'>Faturamento Bruto</td>
                    <td style='text-align: right;'>{$fatAtualFmt}</td>
                    <td style='text-align: right;'>{$fatAnteriorFmt}</td>
                    <td style='text-align: right; {$classFat}'>{$varFatFmt}</td>
                </tr>
                <tr>
                    <td style='padding: 6px;'>Compras</td>
                    <td style='text-align: right;'>{$compraAtualFmt}</td>
                    <td style='text-align: right;'>{$compraAnteriorFmt}</td>
                    <td style='text-align: right; {$classCompra}'>{$varCompraFmt}</td>
                </tr>
            </tbody>
        </table>";

            $labels[] = $nome;
            $fatValues[] = round($mapFaturamentoAtual[$nome] ?? 0, 2);
            $compraValues[] = round($mapComprasAtual[$nome] ?? 0, 2);

        }

        $totalFatAtual = array_sum($fatValues);
        $totalFatAnterior = array_sum(array_map(fn($nome) => $mapFaturamentoAnterior[$nome] ?? 0, $labels));
        $totalCompraAtual = array_sum($compraValues);
        $totalCompraAnterior = array_sum(array_map(fn($nome) => $mapComprasAnterior[$nome] ?? 0, $labels));

        $totalFatAtualFmt = 'R$ ' . number_format($totalFatAtual, 2, ',', '.');
        $totalFatAnteriorFmt = 'R$ ' . number_format($totalFatAnterior, 2, ',', '.');
        $totalCompraAtualFmt = 'R$ ' . number_format($totalCompraAtual, 2, ',', '.');
        $totalCompraAnteriorFmt = 'R$ ' . number_format($totalCompraAnterior, 2, ',', '.');

        $varTotalFat = self::calcularVariacao($totalFatAtual, $totalFatAnterior);
        $varTotalCompra = self::calcularVariacao($totalCompraAtual, $totalCompraAnterior);

        $varTotalFatFmt = number_format($varTotalFat, 2, ',', '.') . '%';
        $varTotalCompraFmt = number_format($varTotalCompra, 2, ',', '.') . '%';

        $classTotalFat = $varTotalFat >= 0 ? 'color: green;' : 'color: red;';
        $classTotalCompra = $varTotalCompra >= 0 ? 'color: green;' : 'color: red;';

        $html .= "
            <table style='width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px;'>
                <thead>
                    <tr style='border-bottom: 1px solid #000; background-color: #e0e0e0;'>
                        <th style='text-align: left; padding: 6px;'>Consolidado Grupo</th>
                        <th style='text-align: right; padding: 6px;'>Semana Atual</th>
                        <th style='text-align: right; padding: 6px;'>Semana Anterior</th>
                        <th style='text-align: right; padding: 6px;'>Var (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style='padding: 6px;'>Faturamento Bruto</td>
                        <td style='text-align: right;'>{$totalFatAtualFmt}</td>
                        <td style='text-align: right;'>{$totalFatAnteriorFmt}</td>
                        <td style='text-align: right; {$classTotalFat}'>{$varTotalFatFmt}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px;'>Compras</td>
                        <td style='text-align: right;'>{$totalCompraAtualFmt}</td>
                        <td style='text-align: right;'>{$totalCompraAnteriorFmt}</td>
                        <td style='text-align: right; {$classTotalCompra}'>{$varTotalCompraFmt}</td>
                    </tr>
                </tbody>
            </table>";


        $graficoSrc = self::gerarGraficoBase64($labels, $fatValues, $compraValues);

        // Adiciona o gráfico ao HTML
        $html .= "<center><div style='text-align: center;'>
            <h3 style='margin-bottom: 10px;'>Comparativo Gráfico: Faturamento vs Compras</h3>
            <img src='{$graficoSrc}' style='max-width: 100%; height: auto;' />
        </div></center>";

        $html .= "<center><h3 style='margin-top: 40px;'>Notas Detalhadas por Loja</h3></center>";

        foreach ($comprasAtual as $loja) {
            $nomeLoja = strtoupper($loja['nomeLoja']);
            $notas = $loja['notas'] ?? [];

            if (empty($notas)) continue;

            // Agrupa por fornecedor
            $agrupado = [];
            $totalLoja = 0;

            foreach ($notas as $nota) {
                $fornecedor = $nota['fornecedor'];
                $valor = (float)($nota['valor_total'] ?? 0);
                $totalLoja += $valor;

                if (!isset($agrupado[$fornecedor])) {
                    $agrupado[$fornecedor] = ['total' => 0, 'qtd' => 0];
                }

                $agrupado[$fornecedor]['total'] += $valor;
                $agrupado[$fornecedor]['qtd'] += 1;
            }

            $html .= "<h4 style='margin-bottom: 8px; margin-top: 24px;'>$nomeLoja</h4>";
            $html .= "
    <table style='width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px;'>
        <thead>
            <tr style='border-bottom: 1px solid #000; background-color: #f9f9f9;'>
                <th style='text-align: left; padding: 6px;'>Fornecedor</th>
                <th style='text-align: center; padding: 6px;'>Notas</th>
                <th style='text-align: right; padding: 6px;'>Valor</th>
                <th style='text-align: right; padding: 6px;'>%</th>
            </tr>
        </thead>
        <tbody>";

            foreach ($agrupado as $fornecedor => $info) {
                $valorFmt = 'R$ ' . number_format($info['total'], 2, ',', '.');
                $percentual = $totalLoja > 0 ? ($info['total'] / $totalLoja * 100) : 0;
                $percentualFmt = number_format($percentual, 2, ',', '.') . '%';
                $qtdFmt = $info['qtd'];

                $html .= "
        <tr>
            <td style='padding: 6px;'>$fornecedor</td>
            <td style='text-align: center;'>$qtdFmt</td>
            <td style='text-align: right;'>$valorFmt</td>
            <td style='text-align: right;'>$percentualFmt</td>
        </tr>";
            }

            $html .= "</tbody></table>";
        }


        $html .= "<p style='text-align: right; font-size: 12px; color: #777;'>Gerado em " . date('d/m/Y H:i') . "</p>";
        $html .= "</div>";

        return $html;
    }



    private static function gerarHtmlConsolidadoGrupo(
        DateTimeInterface $inicioAtual,
        DateTimeInterface $fimAtual,
        DateTimeInterface $inicioAnterior,
        DateTimeInterface $fimAnterior,
        array $dadosAtuais,
        array $dadosAnteriores,
        array $rankingsPorLoja,
        array $mvAnterior,
        array $mvAtual
    ): string {
        $somaCampos = function(array $dados, array $campos) {
            $soma = array_fill_keys($campos, 0.0);
            foreach ($dados as $loja) {
                foreach ($campos as $campo) {
                    $soma[$campo] += (float)($loja[$campo] ?? 0);
                }
            }
            return $soma;
        };

        $campos = ['faturamento_bruto', 'descontos', 'taxa_servico', 'faturamento_liquido', 'numero_clientes'];

        $dadosAtual = $somaCampos($dadosAtuais, $campos);
        $dadosAnterior = $somaCampos($dadosAnteriores, $campos);

        $dadosAtual['ticket_medio'] = $dadosAtual['numero_clientes'] > 0
            ? $dadosAtual['faturamento_liquido'] / $dadosAtual['numero_clientes']
            : 0;

        $dadosAnterior['ticket_medio'] = $dadosAnterior['numero_clientes'] > 0
            ? $dadosAnterior['faturamento_liquido'] / $dadosAnterior['numero_clientes']
            : 0;

        // Modos de venda
        $consolidarModos = function($mapas) {
            $consolidados = ['SALAO' => 0.0, 'DELIVERY' => 0.0, 'BALCAO' => 0.0];
            foreach ($mapas as $loja) {
                foreach ($loja as $modo) {
                    if (isset($consolidados[$modo['modoVenda']])) {
                        $consolidados[$modo['modoVenda']] += (float)$modo['valor'];
                    }
                }
            }
            return array_map(fn($modo, $valor) => ['modoVenda' => $modo, 'valor' => $valor], array_keys($consolidados), $consolidados);
        };

        $mvAnt = $consolidarModos($mvAnterior);
        $mvAt = $consolidarModos($mvAtual);

        // Agrupar rankings
        $agruparRankings = function($tipo, $campo) use ($rankingsPorLoja) {
            $agregados = [];
            foreach ($rankingsPorLoja as $r) {
                foreach (($r[$tipo][$campo] ?? []) as $item) {
                    $chave = $item['cod_material'] ?? $item['nome_produto'];
                    if (!isset($agregados[$chave])) {
                        $agregados[$chave] = $item;
                    } else {
                        $agregados[$chave]['total_quantidade'] += (float)$item['total_quantidade'];
                        $agregados[$chave]['total_valor'] += (float)$item['total_valor'];
                    }
                }
            }
            return $agregados;
        };

        $top3 = function($arr, $campo, $ordem = SORT_DESC) {
            usort($arr, fn($a, $b) => $ordem === SORT_DESC ? $b[$campo] <=> $a[$campo] : $a[$campo] <=> $b[$campo]);
            return array_slice(array_values($arr), 0, 3);
        };

        $ranking = [
            'atual' => [
                'mais_vendidos_valor' => $top3($agruparRankings('atual', 'mais_vendidos_valor'), 'total_valor'),
                'menos_vendidos_valor' => $top3($agruparRankings('atual', 'menos_vendidos_valor'), 'total_valor', SORT_ASC),
                'mais_vendidos_quantidade' => $top3($agruparRankings('atual', 'mais_vendidos_quantidade'), 'total_quantidade'),
                'menos_vendidos_quantidade' => $top3($agruparRankings('atual', 'menos_vendidos_quantidade'), 'total_quantidade', SORT_ASC),
            ],
            'anterior' => [
                'mais_vendidos_valor' => $top3($agruparRankings('anterior', 'mais_vendidos_valor'), 'total_valor'),
                'menos_vendidos_valor' => $top3($agruparRankings('anterior', 'menos_vendidos_valor'), 'total_valor', SORT_ASC),
                'mais_vendidos_quantidade' => $top3($agruparRankings('anterior', 'mais_vendidos_quantidade'), 'total_quantidade'),
                'menos_vendidos_quantidade' => $top3($agruparRankings('anterior', 'menos_vendidos_quantidade'), 'total_quantidade', SORT_ASC),
            ]
        ];

        return self::gerarHtmlComparativoLoja(
            'Consolidado Geral',
            $inicioAtual, $fimAtual,
            $inicioAnterior, $fimAnterior,
            $dadosAtual,
            $dadosAnterior,
            $ranking,
            $mvAnt,
            $mvAt
        );
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
                    Semana Atual<br>
                    <strong>{$inicioAtual->format('d/m')} - {$fimAtual->format('d/m')}</strong>
                </th>
                <th style='padding: 6px; text-align: right;'>
                    Semana Anterior<br>
                    <strong>{$inicioAnterior->format('d/m')} - {$fimAnterior->format('d/m')}</strong>
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
            <td style='padding: 6px; text-align: right;'><strong>{$atualFormatado}</strong></td>
            <td style='padding: 6px; text-align: right;'>{$anteriorFormatado}</td>
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
            <td style='padding: 6px; text-align: right;'><strong>{$atualFmt}</strong></td>
            <td style='padding: 6px; text-align: right;'>{$antFmt}</td>
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
                <td style='padding: 4px; text-align: right;'><strong>{$left}</strong></td>
                <td style='padding: 4px; text-align: right;'>{$right}</td>

            </tr>";
            }

            return $html . "</tbody></table>";
        };

        $html .= $renderRankingBlock('Top 3 MAIOR faturamento:', $ranking['atual']['mais_vendidos_valor'] ?? [], $ranking['anterior']['mais_vendidos_valor'] ?? [], fn($i) => $i ? "{$i['nome_produto']} (R$ " . number_format($i['total_valor'], 0, ',', '.') . ")" : '');
        $html .= $renderRankingBlock('Top 3 MENOR faturamento:', $ranking['atual']['menos_vendidos_valor'] ?? [], $ranking['anterior']['menos_vendidos_valor'] ?? [], fn($i) => $i ? "{$i['nome_produto']} (R$ " . number_format($i['total_valor'], 0, ',', '.') . ")" : '');
        $html .= $renderRankingBlock('Top 3 MAIS vendidos:', $ranking['atual']['mais_vendidos_quantidade'] ?? [], $ranking['anterior']['mais_vendidos_quantidade'] ?? [], fn($i) => $i ? "{$i['nome_produto']} ({$i['total_quantidade']})" : '');
        $html .= $renderRankingBlock('Top 3 MENOS vendidos:', $ranking['atual']['menos_vendidos_quantidade'] ?? [], $ranking['anterior']['menos_vendidos_quantidade'] ?? [], fn($i) => $i ? "{$i['nome_produto']} ({$i['total_quantidade']})" : '');

        $html .= "<p style='text-align: right; font-size: 12px; margin-top: 30px; color: #777;'>Gerado em " . date('d/m/Y H:i') . "</p>";
        $html .= "</div>";


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

        // Buscar compras
        $comprasAtual = self::generateNotasPorGrupo($grupoId, $inicioAtual->format('Y-m-d 00:00:00'), $fimAtual->format('Y-m-d 23:59:59'));
        $comprasAnterior = self::generateNotasPorGrupo($grupoId, $inicioAnterior->format('Y-m-d 00:00:00'), $fimAnterior->format('Y-m-d 23:59:59'));

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



//            $html .= self::gerarHtmlComparativoLoja(
//                $dadosLojaAtual['nomeLoja'],
//                $inicioAtual, $fimAtual,
//                $inicioAnterior, $fimAnterior,
//                $dadosLojaAtual,
//                $dadosLojaAnterior,
//                $ranking,
//                $mvAnterior[$lojaId] ?? [],
//                $mvAtual[$lojaId] ?? []
//            );

        }

//        // Página Consolidado Geral
//        $html .= self::gerarHtmlConsolidadoGrupo(
//            $inicioAtual,
//            $fimAtual,
//            $inicioAnterior,
//            $fimAnterior,
//            $resumoAtual['data'],
//            $resumoAnterior['data'],
//            $rankingsPorLoja,
//            $mvAnterior,
//            $mvAtual
//        );

        // Página Consolidado Compras
        $html .= self::gerarHtmlComparativoComprasPorLoja(
            $inicioAtual, $fimAtual,
            $inicioAnterior, $fimAnterior,
            $resumoAtual['data'],
            $resumoAnterior['data'],
            $comprasAtual['data'],
            $comprasAnterior['data']
        );

        // Gerar PDF
        $dompdf = new Dompdf((new Options())->set('isRemoteEnabled', true));

        $dompdf->loadHtml('<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>');
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

            $liquido = $bruto - $taxaServico;
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
                $lojaId = $loja['custom_code'];
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
                $lojaId = $loja['custom_code'];

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

                $liquido = $bruto - $taxaServico;
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
                $lojaId = $loja['custom_code'];
                error_log("Processando loja: $lojaId - {$loja['name']}");
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
                $lojaId = $loja['custom_code'];

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
                $lojaId = $loja['custom_code'];

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
                $lojaId = $loja['custom_code'];

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
                $lojaId = $loja['custom_code'];

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
                          AND s.cod_material NOT IN (9000, 9600)
                          AND p.nome NOT LIKE '%taxa%'
                          AND p.nome NOT LIKE '%mal passado%'
                          AND p.nome NOT LIKE '%ao ponto%'
                          AND p.nome NOT LIKE '%bem passado%'
                          AND p.nome NOT LIKE '%serviço%'
                        GROUP BY s.cod_material, p.nome
                    ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':lojaId' => $lojaId,
                    ':dt_inicio' => $dt_inicio,
                    ':dt_fim' => $dt_fim
                ]);

                $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $ordenar = fn($arr, $campo, $ordem) => array_values(array_filter(
                    ($ordem === 'asc'
                        ? array_slice(array_filter($arr, fn($p) => $p[$campo] > 0), 0, 3) // top 3 crescentes
                        : array_slice($arr, 0, 3) // top 3 decrescentes
                    ),

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

    public static function generateNotasPorGrupo($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $saida = [];

            foreach ($lojas as $loja) {
                $unitId = $loja['system_unit_id'];

                $stmt = $pdo->prepare("
                SELECT 
                    *
                FROM nota_fiscal_entrada
                WHERE system_unit_id = :unitId
                  AND data_emissao BETWEEN :inicio AND :fim
                ORDER BY fornecedor DESC
            ");
                $stmt->execute([
                    ':unitId' => $unitId,
                    ':inicio' => $dt_inicio,
                    ':fim' => $dt_fim
                ]);

                $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $saida[] = [
                    'lojaId' => $unitId,
                    'nomeLoja' => $loja['name'],
                    'notas' => $notas
                ];
            }

            return ['success' => true, 'data' => $saida];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function generateComprasPorGrupo($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $saida = [];

            foreach ($lojas as $loja) {
                $unitId = $loja['system_unit_id'];

                // Agrupa itens por produto
                $stmt = $pdo->prepare("
                SELECT 
                    m.produto AS codigo,
                    SUM(m.quantidade) AS quantidade_total,
                    SUM(m.valor) AS valor_total
                FROM movimentacao m
                WHERE m.system_unit_id = :unitId
                  AND m.tipo = 'c'
                  AND m.tipo_mov = 'entrada'
                  AND m.data_emissao BETWEEN :inicio AND :fim
                GROUP BY m.produto
            ");
                $stmt->execute([
                    ':unitId' => $unitId,
                    ':inicio' => $dt_inicio,
                    ':fim' => $dt_fim
                ]);

                $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($itens)) continue;

                // Mapeia produtos da loja
                $codigos = array_column($itens, 'codigo');
                $placeholders = implode(',', array_fill(0, count($codigos), '?'));

                $stmtProd = $pdo->prepare("
                SELECT codigo, nome, und 
                FROM products 
                WHERE system_unit_id = ? AND codigo IN ($placeholders)
            ");
                $stmtProd->execute(array_merge([$unitId], $codigos));

                $produtosInfo = [];
                foreach ($stmtProd->fetchAll(PDO::FETCH_ASSOC) as $p) {
                    $produtosInfo[$p['codigo']] = $p;
                }

                // Enriquecer cada item com nome e unidade
                $dados = [];
                foreach ($itens as $item) {
                    $codigo = $item['codigo'];
                    $quantidade = (float)$item['quantidade_total'];
                    $valorTotal = (float)$item['valor_total'];
                    $custoMedio = $quantidade > 0 ? $valorTotal / $quantidade : 0;

                    $dados[] = [
                        'codigo'      => $codigo,
                        'descricao'   => $produtosInfo[$codigo]['nome'] ?? 'N/D',
                        'und'         => $produtosInfo[$codigo]['und'] ?? '',
                        'quantidade'  => round($quantidade, 2),
                        'valor_total' => round($valorTotal, 2),
                        'custo_medio' => round($custoMedio, 4)
                    ];
                }

                $saida[] = [
                    'lojaId'   => $unitId,
                    'nomeLoja' => $loja['name'],
                    'itens'    => $dados
                ];
            }

            return ['success' => true, 'data' => $saida];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }


    public static function generateSomaNotasPorGrupo($grupoId, $dt_inicio, $dt_fim): array
    {
        global $pdo;

        try {
            $lojas = BiController::getUnitsByGroup($grupoId);
            $saida = [];

            foreach ($lojas as $loja) {
                $unitId = $loja['system_unit_id'];

                $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) AS total_notas,
                    SUM(valor_total) AS soma_valor_total
                FROM nota_fiscal_entrada
                WHERE system_unit_id = :unitId
                  AND data_emissao BETWEEN :inicio AND :fim
            ");
                $stmt->execute([
                    ':unitId' => $unitId,
                    ':inicio' => $dt_inicio,
                    ':fim' => $dt_fim
                ]);

                $resumo = $stmt->fetch(PDO::FETCH_ASSOC);

                $saida[] = [
                    'lojaId' => $unitId,
                    'nomeLoja' => $loja['name'],
                    'quantidadeNotas' => (int) ($resumo['total_notas'] ?? 0),
                    'valorTotal' => (float) ($resumo['soma_valor_total'] ?? 0),
                ];
            }

            return ['success' => true, 'data' => $saida];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }






}
