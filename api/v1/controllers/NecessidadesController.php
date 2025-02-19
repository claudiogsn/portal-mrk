<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class NecessidadesController {

    public static function getInsumoConsumptionMatriz($matriz_id, $dates, $insumoIds, $type = 'media') {
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
                'saldo_total' => 0, // Inicializando o campo
                'nome' => $nome,
                'categoria' => $categoria,
            ];
        }

        // Passo 3: Calcular consumo para cada filial e agregar
        foreach ($filiais as $filial_id) {
            $dadosFilial = self::getInsumoConsumption($filial_id, $dates, $insumoIds, $type);

            foreach ($dadosFilial as $insumo) {
                $insumo_id = $insumo['codigo'];
                if (!isset($insumoConsumption[$insumo_id])) continue;

                // Converter valores para float antes de somar
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

            if ($type === 'media') {
                $margem = 0;
                $recomendado = max(0, ceil($sales - $saldo_total));
            } else {
                // Lógica para outros tipos (se necessário)
                $margem = 0;
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


    public static function getInsumoConsumption($system_unit_id, $dates, $insumoIds, $type = 'media') {
        global $pdo;

        // Remove datas duplicadas
        $dates = array_unique($dates);
    
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
            $insumoConsumption[$insumo_id]['saldo'] = number_format($stockData['saldo'] ?? 0, 2, '.', '');


            if ($type === 'media') {
                // Calcula margem e recomendado
                $insumoConsumption[$insumo_id]['sales'] = number_format($insumoConsumption[$insumo_id]['sales'] / 4, 2, '.', '');
                $insumoConsumption[$insumo_id]['margem'] = number_format($insumoConsumption[$insumo_id]['sales'] * 0.30, 2, '.', '');
                $insumoConsumption[$insumo_id]['recomendado'] = ceil($insumoConsumption[$insumo_id]['sales'] + $insumoConsumption[$insumo_id]['margem'] - $insumoConsumption[$insumo_id]['saldo']);
            }

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

    public static function getFiliaisProduction($user_id) {
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
    
    





    public static function getFiliaisByMatriz($user_id) {
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
