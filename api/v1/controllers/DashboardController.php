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


    public static function generateHourlySalesByStore($start_datetime, $end_datetime)
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
                    SUM(vlTotalRecebido) / COUNT(DISTINCT DATE(dataFechamento)) AS media_hora
                FROM 
                    movimento_caixa
                WHERE 
                    dataFechamento BETWEEN :start AND :end
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

    public static function generateResumoFinanceiroPorLoja($lojaId, $dt_inicio, $dt_fim)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
            SELECT
                SUM(vlTotalRecebido) AS faturamento_bruto,
                SUM(vlDesconto) AS total_descontos,
                SUM(vlServicoRecebido) AS total_taxa_servico,
                SUM(numPessoas) AS total_clientes
            FROM
                movimento_caixa
            WHERE
                lojaId = :lojaId
                AND dataFechamento BETWEEN :dt_inicio AND :dt_fim
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
                'ticket_medio' => round($ticketMedio, 2)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao gerar resumo: ' . $e->getMessage()
            ];
        }
    }


}
