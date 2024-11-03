<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class NecessidadesController {

    public static function getInsumoConsumption($system_unit_id, $dates, $insumoIds) {
        global $pdo;
    
        // Busca os nomes de todos os insumos independentemente do consumo
        $stmt = $pdo->prepare("
            SELECT codigo AS insumo_id, nome 
            FROM products 
            WHERE codigo IN (" . implode(',', array_fill(0, count($insumoIds), '?')) . ") 
            AND system_unit_id = ?
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
                'nome' => $row['nome'], // Adiciona o nome do insumo
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
            $insumoConsumption[$insumo_id]['saldo'] = $stockData['saldo'] ?? 0;
    
            // Calcula margem e recomendado
            $insumoConsumption[$insumo_id]['sales'] = ceil( $insumoConsumption[$insumo_id]['sales'] / 4);
            $insumoConsumption[$insumo_id]['margem'] = ceil($insumoConsumption[$insumo_id]['sales'] * 0.15); // 15% da venda, arredondado para cima
            $insumoConsumption[$insumo_id]['recomendado'] = ceil($insumoConsumption[$insumo_id]['sales'] + $insumoConsumption[$insumo_id]['margem'] - $insumoConsumption[$insumo_id]['saldo']);
        }
    
        return array_values($insumoConsumption); // Retorna um array indexado
    }
    
    private static function fetchTotalConsumption($system_unit_id, $insumoIds, $date) {
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
        $stmt->bindParam(':date', $date, PDO::PARAM_STR);
    
        // Bind dos parâmetros dos insumos
        foreach ($insumoIds as $insumo_id) {
            $stmt->bindValue(":insumo_$insumo_id", $insumo_id, PDO::PARAM_INT);
        }
    
        $stmt->execute(); // Executa a consulta
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Retorna todos os resultados
    }
    
    





    public static function getFiliaisByMatriz($unit_matriz_id) {
        global $pdo;

        $sql = "
            SELECT
                su.id AS filial_id,
                su.name AS filial_nome
            FROM
                system_unit_rel sur
            JOIN
                system_unit su ON sur.unit_filial = su.id
            WHERE
                sur.unit_matriz = ?;
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$unit_matriz_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getProductStock($system_unit_id, $codigo) {
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






}
