<?php


ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class BiController {

    public static function getUnitsByGroup($group_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT rel.system_unit_id, su.custom_code FROM grupo_estabelecimento_rel AS rel JOIN  system_unit AS su ON rel.system_unit_id = su.id WHERE rel.grupo_id = :group_id;");
        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function registerJobExecution($data) {
        global $pdo;

        // Extrai os dados recebidos
        $nome_job = $data['nome_job'];
        $system_unit_id = $data['system_unit_id'];
        $custom_code = $data['custom_code'];
        $inicio = $data['inicio'];
        $final = isset($data['final']) ? $data['final'] : null;

        // Calcula a duração se a data final for fornecida
        $duracao = $final ? (strtotime($final) - strtotime($inicio)) / 60.0 : null;

        // Prepara a consulta SQL para inserir o registro
        $stmt = $pdo->prepare("
            INSERT INTO job_execution (nome_job, system_unit_id, custom_code, inicio, final, duracao)
            VALUES (:nome_job, :system_unit_id, :custom_code, :inicio, :final, :duracao)
        ");

        // Associa os parâmetros
        $stmt->bindParam(':nome_job', $nome_job, PDO::PARAM_STR);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->bindParam(':custom_code', $custom_code, PDO::PARAM_STR);
        $stmt->bindParam(':inicio', $inicio, PDO::PARAM_STR);
        $stmt->bindParam(':final', $final, PDO::PARAM_STR);
        $stmt->bindParam(':duracao', $duracao, PDO::PARAM_STR);

        // Executa a consulta e verifica o sucesso
        if ($stmt->execute()) {
            return [
                'status' => 'success',
                'message' => 'Job execution registered successfully.',
                'job_execution_id' => $pdo->lastInsertId()
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to register job execution.'
            ];
        }
    }

    public static function consolidateSalesByUnit($system_unit_id, $dt_inicio, $dt_fim) {
    global $pdo;

    // Consulta para consolidar os dados
    $stmt = $pdo->prepare("
        SELECT dtLancamento, codMaterial, SUM(quantidade) AS total_quantidade,
               SUM(valorBruto) AS total_valor_bruto, SUM(valorUnitario) AS total_valor_unitario,
               SUM(valorUnitarioLiquido) AS total_valor_unitario_liquido, SUM(valorLiquido) AS total_valor_liquido,
               custom_code
        FROM sales
        WHERE system_unit_id = :system_unit_id
          AND dtLancamento BETWEEN :dt_inicio AND :dt_fim
        GROUP BY dtLancamento, codMaterial
    ");

    // Associa os parâmetros
    $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
    $stmt->bindParam(':dt_inicio', $dt_inicio, PDO::PARAM_STR);
    $stmt->bindParam(':dt_fim', $dt_fim, PDO::PARAM_STR);

    $stmt->execute();
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Loop através dos dados e insira ou atualize na tabela _bi_sales
    foreach ($salesData as $data) {
        $stmt = $pdo->prepare("
            INSERT INTO _bi_sales (data_movimento, cod_material, quantidade, valor_bruto, valor_unitario, valor_unitario_liquido, valor_liquido, custom_code, system_unit_id)
            VALUES (:data_movimento, :cod_material, :quantidade, :valor_bruto, :valor_unitario, :valor_unitario_liquido, :valor_liquido, :custom_code, :system_unit_id)
            ON DUPLICATE KEY UPDATE
                quantidade = quantidade + :quantidade,
                valor_bruto = valor_bruto + :valor_bruto,
                valor_unitario = valor_unitario + :valor_unitario,
                valor_unitario_liquido = valor_unitario_liquido + :valor_unitario_liquido,
                valor_liquido = valor_liquido + :valor_liquido,
                updated_at = NOW()
        ");

        // Associa os parâmetros
        $stmt->bindParam(':data_movimento', $data['dtLancamento'], PDO::PARAM_STR);
        $stmt->bindParam(':cod_material', $data['codMaterial'], PDO::PARAM_INT);
        $stmt->bindParam(':quantidade', $data['total_quantidade'], PDO::PARAM_INT);
        $stmt->bindParam(':valor_bruto', $data['total_valor_bruto'], PDO::PARAM_STR);
        $stmt->bindParam(':valor_unitario', $data['total_valor_unitario'], PDO::PARAM_STR);
        $stmt->bindParam(':valor_unitario_liquido', $data['total_valor_unitario_liquido'], PDO::PARAM_STR);
        $stmt->bindParam(':valor_liquido', $data['total_valor_liquido'], PDO::PARAM_STR);
        $stmt->bindParam(':custom_code', $data['custom_code'], PDO::PARAM_STR);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);

        // Executa a consulta e verifica o sucesso
        $stmt->execute();
    }

    return [
        'status' => 'success',
        'message' => 'Sales consolidated successfully for unit.'
    ];
}

    public static function consolidateSalesByGroup($group_id, $dt_inicio, $dt_fim) {
        global $pdo;

        // Primeiro, obtém todos os system_unit_id do grupo
        $stmt = $pdo->prepare("
        SELECT system_unit_id FROM grupo_estabelecimento_rel WHERE grupo_id = :group_id
    ");
        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($units)) {
            return [
                'status' => 'error',
                'message' => 'No units found for the specified group.'
            ];
        }

        // Consolida os dados para todas as unidades do grupo
        foreach ($units as $unit) {
            $stmt = $pdo->prepare("
            SELECT dtLancamento, codMaterial, 
                   SUM(quantidade) AS total_quantidade,
                   SUM(valorBruto) AS total_valor_bruto, 
                   SUM(valorUnitario) AS total_valor_unitario,
                   SUM(valorUnitarioLiquido) AS total_valor_unitario_liquido, 
                   SUM(valorLiquido) AS total_valor_liquido,
                   custom_code
            FROM sales
            WHERE system_unit_id = :system_unit_id
              AND dtLancamento BETWEEN :dt_inicio AND :dt_fim
            GROUP BY dtLancamento, codMaterial
        ");

            // Associa os parâmetros
            $stmt->bindParam(':system_unit_id', $unit, PDO::PARAM_INT);
            $stmt->bindParam(':dt_inicio', $dt_inicio, PDO::PARAM_STR);
            $stmt->bindParam(':dt_fim', $dt_fim, PDO::PARAM_STR);
            $stmt->execute();
            $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Loop através dos dados e insira ou atualize na tabela _bi_sales
            foreach ($salesData as $data) {
                $stmt = $pdo->prepare("
                INSERT INTO _bi_sales (data_movimento, cod_material, quantidade, valor_bruto, valor_unitario, valor_unitario_liquido, valor_liquido, custom_code, system_unit_id)
                VALUES (:data_movimento, :cod_material, :quantidade, :valor_bruto, :valor_unitario, :valor_unitario_liquido, :valor_liquido, :custom_code, :system_unit_id)
                ON DUPLICATE KEY UPDATE
                    quantidade = :quantidade,
                    valor_bruto = :valor_bruto,
                    valor_unitario = :valor_unitario,
                    valor_unitario_liquido = :valor_unitario_liquido,
                    valor_liquido = :valor_liquido,
                    updated_at = NOW()
            ");

                // Associa os parâmetros
                $stmt->bindParam(':data_movimento', $data['dtLancamento'], PDO::PARAM_STR);
                $stmt->bindParam(':cod_material', $data['codMaterial'], PDO::PARAM_INT);
                $stmt->bindParam(':quantidade', $data['total_quantidade'], PDO::PARAM_INT);
                $stmt->bindParam(':valor_bruto', $data['total_valor_bruto'], PDO::PARAM_STR);
                $stmt->bindParam(':valor_unitario', $data['total_valor_unitario'], PDO::PARAM_STR);
                $stmt->bindParam(':valor_unitario_liquido', $data['total_valor_unitario_liquido'], PDO::PARAM_STR);
                $stmt->bindParam(':valor_liquido', $data['total_valor_liquido'], PDO::PARAM_STR);
                $stmt->bindParam(':custom_code', $data['custom_code'], PDO::PARAM_STR);
                $stmt->bindParam(':system_unit_id', $unit, PDO::PARAM_INT); // Aqui usamos o system_unit_id atual

                // Executa a consulta e verifica o sucesso
                $stmt->execute();
            }
        }

        return [
            'status' => 'success',
            'message' => 'Sales consolidated successfully for group.'
        ];
    }

    public static function persistSales($salesData) {
        global $pdo;

        // Inicia a transação
        try {
            $pdo->beginTransaction();

            // Prepara a consulta SQL para inserir os dados
            $stmt = $pdo->prepare("
            INSERT INTO sales (idItemVenda, valorBruto, valorUnitario, valorUnitarioLiquido, valorLiquido, modoVenda, idModoVenda, quantidade, dtLancamento, unidade, lojaId, idMaterial, codMaterial, descricao, grupo__idGrupo, grupo__codigo, grupo__descricao, __nfNumeroC, custom_code, system_unit_id)
            VALUES (:idItemVenda, :valorBruto, :valorUnitario, :valorUnitarioLiquido, :valorLiquido, :modoVenda, :idModoVenda, :quantidade, :dtLancamento, :unidade, :lojaId, :idMaterial, :codMaterial, :descricao, :grupo_idGrupo, :grupo_codigo, :grupo_descricao, :nfNumeroC, :custom_code, :system_unit_id)
            ON DUPLICATE KEY UPDATE 
                valorBruto = VALUES(valorBruto),
                valorUnitario = VALUES(valorUnitario),
                valorUnitarioLiquido = VALUES(valorUnitarioLiquido),
                valorLiquido = VALUES(valorLiquido),
                modoVenda = VALUES(modoVenda),
                idModoVenda = VALUES(idModoVenda),
                quantidade = VALUES(quantidade),
                dtLancamento = VALUES(dtLancamento),
                unidade = VALUES(unidade),
                lojaId = VALUES(lojaId),
                idMaterial = VALUES(idMaterial),
                codMaterial = VALUES(codMaterial),
                descricao = VALUES(descricao),
                grupo__idGrupo = VALUES(grupo__idGrupo),
                grupo__codigo = VALUES(grupo__codigo),
                grupo__descricao = VALUES(grupo__descricao),
                __nfNumeroC = VALUES(__nfNumeroC),
                custom_code = VALUES(custom_code),
                system_unit_id = VALUES(system_unit_id),
                updated_at = CURRENT_TIMESTAMP
        ");

            foreach ($salesData as $data) {
                // Associa os parâmetros para cada venda
                $stmt->bindParam(':idItemVenda', $data['idItemVenda'], PDO::PARAM_STR);
                $stmt->bindParam(':valorBruto', $data['valorBruto'], PDO::PARAM_STR);
                $stmt->bindParam(':valorUnitario', $data['valorUnitario'], PDO::PARAM_STR);
                $stmt->bindParam(':valorUnitarioLiquido', $data['valorUnitarioLiquido'], PDO::PARAM_STR);
                $stmt->bindParam(':valorLiquido', $data['valorLiquido'], PDO::PARAM_STR);
                $stmt->bindParam(':modoVenda', $data['modoVenda'], PDO::PARAM_STR);
                $stmt->bindParam(':idModoVenda', $data['idModoVenda'], PDO::PARAM_INT);
                $stmt->bindParam(':quantidade', $data['quantidade'], PDO::PARAM_INT);
                $stmt->bindParam(':dtLancamento', $data['dtLancamento'], PDO::PARAM_STR);
                $stmt->bindParam(':unidade', $data['unidade'], PDO::PARAM_STR);
                $stmt->bindParam(':lojaId', $data['lojaId'], PDO::PARAM_INT);
                $stmt->bindParam(':idMaterial', $data['idMaterial'], PDO::PARAM_INT);
                $stmt->bindParam(':codMaterial', $data['codMaterial'], PDO::PARAM_INT);
                $stmt->bindParam(':descricao', $data['descricao'], PDO::PARAM_STR);
                $stmt->bindParam(':grupo_idGrupo', $data['grupo__idGrupo'], PDO::PARAM_INT);
                $stmt->bindParam(':grupo_codigo', $data['grupo__codigo'], PDO::PARAM_INT);
                $stmt->bindParam(':grupo_descricao', $data['grupo__descricao'], PDO::PARAM_STR);
                $stmt->bindParam(':nfNumeroC', $data['__nfNumeroC'], PDO::PARAM_INT);
                $stmt->bindParam(':custom_code', $data['custom_code'], PDO::PARAM_STR);
                $stmt->bindParam(':system_unit_id', $data['system_unit_id'], PDO::PARAM_INT);

                // Executa a consulta para cada venda
                $stmt->execute();
            }

            // Confirma a transação
            $pdo->commit();
            return [
                'status' => 'success',
                'message' => 'Todas as vendas foram persistidas com sucesso.'
            ];
        } catch (Exception $e) {
            // Reverte a transação em caso de erro
            $pdo->rollBack();
            return [
                'status' => 'error',
                'message' => 'Falha ao persistir os dados: ' . $e->getMessage()
            ];
        }
    }

    public static function criarDiferencasEstoque($system_unit_id, $data) {
        global $pdo;

        // Inicializar o array de diferencas
        $diferencas = [];

        try {
            // Montando o array de diferencas
            $sql = "SELECT
            doc,
            produto,
            quantidade
        FROM movimentacao
        WHERE id IN (
            SELECT MAX(id)
            FROM movimentacao
            WHERE 
                data = :data
                AND system_unit_id = :system_unit_id
                AND status = 1
                AND tipo = 'b'
            GROUP BY produto
        )
        ORDER BY id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':data', $data);
            $stmt->bindParam(':system_unit_id', $system_unit_id);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Verifica se o resultado está vazio
            if (empty($result)) {
                // Se não houver resultados, retorna e encerra a função
                return ['mensage' => 'Nenhuma movimentação encontrada para a data e unidade especificadas.'] ;
            }

            // Preenche o array de diferencas com os dados dos balanços
            foreach ($result as $row) {
                $diferencas[] = [
                    'data' => $data,
                    'system_unit_id' => $system_unit_id,
                    'doc' => $row['doc'],
                    'produto' => $row['produto'],
                    'nome_produto' => $row['produto'], // Nome do produto, se disponível
                    'saldo' => 0, // Inicializa com 0, será atualizado depois
                    'saldo_ideal' => 0,
                    'vendas' => 0, // Inicializa com 0, será atualizado depois
                    'balanco' => $row['quantidade'], // O balanço do produto
                    'diferenca' => 0 // Inicializa com 0, será calculado depois
                ];
            }

            // Monta o array de datas duplicadas (se necessário, pode duplicar a data por produto)
            $datasDuplicadas = array_fill(0, count($diferencas), $data); // Duplicando a data para cada produto

            // Agora, chama a função NecessidadesController::getInsumoConsumption passando o array de datas duplicadas
            $produtos = array_column($diferencas, 'produto'); // ID dos produtos

            // Chama a função e passa o array de datas duplicadas
            $consumos = NecessidadesController::getInsumoConsumption($system_unit_id, $datasDuplicadas, $produtos);

            // Agora vamos preencher as informações de vendas, saldo e nome no array de diferencas
            foreach ($diferencas as $key => $diferenca) {
                foreach ($consumos as $consumo) {
                    if ($consumo['codigo'] == $diferenca['produto']) {
                        // Garantir que saldo, vendas e balanco sejam tratados como números
                        $diferencas[$key]['vendas'] = floatval($consumo['sales']);
                        $diferencas[$key]['nome_produto'] = $consumo['nome'];
                        $diferencas[$key]['saldo'] = floatval($consumo['saldo']);
                        $diferencas[$key]['saldo_ideal'] = floatval(($consumo['saldo'] - $consumo['sales']));
                        $diferencas[$key]['balanco'] = floatval($diferenca['balanco']);
                        $diferencas[$key]['diferenca'] = $diferenca['balanco'] - ($consumo['saldo'] - $consumo['sales']);
                    }
                }
            }

            // Persistir as diferenças no banco de dados
            foreach ($diferencas as $diferenca) {
                $sqlInsert = "INSERT INTO diferencas_estoque (
                    data,
                    system_unit_id,
                    doc,
                    produto,
                    nome_produto,
                    saldo,
                    saldo_ideal,
                    vendas,
                    balanco,
                    diferenca
                ) VALUES (
                    :data,
                    :system_unit_id,
                    :doc,
                    :produto,
                    :nome_produto,
                    :saldo,
                    :saldo_ideal,
                    :vendas,
                    :balanco,
                    :diferenca
                ) ON DUPLICATE KEY UPDATE
                    saldo = VALUES(saldo),
                    saldo_ideal = VALUES(saldo_ideal),
                    vendas = VALUES(vendas),
                    balanco = VALUES(balanco),
                    diferenca = VALUES(diferenca)";

                $stmt = $pdo->prepare($sqlInsert);
                $stmt->bindParam(':data', $diferenca['data']);
                $stmt->bindParam(':system_unit_id', $diferenca['system_unit_id']);
                $stmt->bindParam(':doc', $diferenca['doc']);
                $stmt->bindParam(':produto', $diferenca['produto']);
                $stmt->bindParam(':nome_produto', $diferenca['nome_produto']);
                $stmt->bindParam(':saldo', $diferenca['saldo']);
                $stmt->bindParam(':saldo_ideal', $diferenca['saldo_ideal']);
                $stmt->bindParam(':vendas', $diferenca['vendas']);
                $stmt->bindParam(':balanco', $diferenca['balanco']);
                $stmt->bindParam(':diferenca', $diferenca['diferenca']);
                $stmt->execute();
            }

            return $diferencas;

        } catch (PDOException $e) {
            // Em caso de erro na execução, captura a exceção e retorna a mensagem de erro
            return "Erro no banco de dados: " . $e->getMessage();
        } catch (Exception $e) {
            // Em caso de outro tipo de erro, captura a exceção e retorna a mensagem de erro
            return "Erro inesperado: " . $e->getMessage();
        }
    }










}

?>
