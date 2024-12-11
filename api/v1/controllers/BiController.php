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

            // Insere na tabela jobs_diference para a unidade atual
            $stmt = $pdo->prepare("
            INSERT INTO jobs_diference (system_unit_id, data, status)
            VALUES (:system_unit_id, :data, :status)
            ON DUPLICATE KEY UPDATE
                status = :status
        ");
            $status = 0;
            $stmt->bindParam(':system_unit_id', $unit, PDO::PARAM_STR);
            $stmt->bindParam(':data', $dt_fim, PDO::PARAM_STR); // Usa a data final como referência
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);
            $stmt->execute();

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
    public static function GetInfoConsolidationEstoque($system_unit_id,$data) {
        global $pdo;

        try {
            // Consulta para verificar se existe balanço
            $stmt = $pdo->prepare(
                "SELECT doc, produto, quantidade AS contagem_realizada
            FROM movimentacao
            WHERE id IN (
                SELECT MAX(id)
                FROM movimentacao
                WHERE data = :data AND system_unit_id = :system_unit_id AND status = 1 AND tipo = 'b'
                GROUP BY produto
            )
            ORDER BY id DESC"
            );
            $stmt->bindParam(':data', $data, PDO::PARAM_STR);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($balances)) {
                return [
                    'status' => 'error',
                    'message' => 'Nenhum balanço para o dia encontrado.',
                    'balances' => $balances,
                    'data' => $data,
                    'system_unit_id' => $system_unit_id
                ];
            }

            $result = [];

            foreach ($balances as $balance) {
                $produto = $balance['produto'];
                $doc = $balance['doc'];
                $contagem_realizada = $balance['contagem_realizada'];

                // Consulta saldo inicial
                $stmt = $pdo->prepare(
                    "SELECT codigo AS produto, saldo, nome 
                FROM products 
                WHERE system_unit_id = :system_unit_id AND codigo = :produto"
                );
                $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
                $stmt->bindParam(':produto', $produto, PDO::PARAM_INT);
                $stmt->execute();
                $saldo_data = $stmt->fetch(PDO::FETCH_ASSOC);

                $saldo_inicial = $saldo_data['saldo'] ?? 0;
                $nome_produto = $saldo_data['nome'] ?? 'Produto Desconhecido';

                // Consulta entradas
                $stmt = $pdo->prepare(
                    "SELECT produto, SUM(quantidade) AS quantidade
                FROM movimentacao
                WHERE system_unit_id = :system_unit_id AND status = 1 AND data = :data AND produto = :produto AND tipo_mov = 'entrada'"
                );
                $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
                $stmt->bindParam(':data', $data, PDO::PARAM_STR);
                $stmt->bindParam(':produto', $produto, PDO::PARAM_INT);
                $stmt->execute();
                $entradas_data = $stmt->fetch(PDO::FETCH_ASSOC);

                $entradas = $entradas_data['quantidade'] ?? 0;

                // Consulta saídas
                $stmt = $pdo->prepare(
                    "SELECT produto, SUM(quantidade) AS quantidade
                FROM movimentacao
                WHERE system_unit_id = :system_unit_id AND status = 1 AND data = :data AND produto = :produto AND tipo_mov = 'saida'"
                );
                $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
                $stmt->bindParam(':data', $data, PDO::PARAM_STR);
                $stmt->bindParam(':produto', $produto, PDO::PARAM_INT);
                $stmt->execute();
                $saidas_data = $stmt->fetch(PDO::FETCH_ASSOC);

                $saidas = $saidas_data['quantidade'] ?? 0;

                // Cálculo de movimentação e diferença
                $saldo_final = $saldo_inicial + $entradas - $saidas;
                $saldo_final_formatado = number_format($saldo_final, 2, '.', '');
                $diferenca =  $contagem_realizada - $saldo_final_formatado;

                // Determina o status da diferença
                $status_dif = ($diferenca === 0) ? 0 : 1;

                // Adiciona os dados ao resultado
                $result[] = [
                    'doc' => $doc,
                    'produto' => $produto,
                    'nome_produto' => $nome_produto,
                    'saldo_anterior' => $saldo_inicial,
                    'entradas' => $entradas,
                    'saidas' => $saidas,
                    'contagem_ideal' => $saldo_final_formatado,
                    'contagem_realizada' => $contagem_realizada,
                    'diferenca' => $diferenca,
                    'status_dif' => $status_dif
                ];
            }

            return [
                'status' => 'success',
                'data' => $result
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao consolidar informações de estoque: ' . $e->getMessage()
            ];
        }
    }
    public static function persistStockDifferences($system_unit_id,$date, $data) {
        global $pdo;

        // Lista de campos obrigatórios
        $requiredFields = [
            'produto', 'nome_produto', 'saldo_anterior', 'entradas', 'saidas', 'contagem_ideal', 'contagem_realizada', 'diferenca'
        ];

        // Verifica se todos os campos obrigatórios estão presentes em cada item do array
        foreach ($data as $index => $item) {
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!isset($item[$field])) {
                    $missingFields[] = $field;
                }
            }
            if (!empty($missingFields)) {
                http_response_code(400); // Bad Request
                return [
                    'status' => 'error',
                    'message' => 'Campos obrigatórios ausentes no item ' . ($index + 1) . ': ' . implode(", ", $missingFields)
                ];
            }
        }

        try {
            // Inicia a transação
            $pdo->beginTransaction();

            // Prepara a consulta SQL para inserir ou atualizar os dados na tabela diferencas_estoque
            $stmt = $pdo->prepare("
                INSERT INTO diferencas_estoque (
                    data, system_unit_id, doc, produto, nome_produto, saldo_anterior, entradas, saidas, contagem_ideal, contagem_realizada, diferenca
                ) VALUES (
                    :data, :system_unit_id, :doc, :produto, :nome_produto, :saldo_anterior, :entradas, :saidas, :contagem_ideal, :contagem_realizada, :diferenca
                )
                ON DUPLICATE KEY UPDATE
                    saldo_anterior = VALUES(saldo_anterior),
                    entradas = VALUES(entradas),
                    saidas = VALUES(saidas),
                    contagem_ideal = VALUES(contagem_ideal),
                    contagem_realizada = VALUES(contagem_realizada),
                    diferenca = VALUES(diferenca),
                    updated_at = CURRENT_TIMESTAMP
            ");

            foreach ($data as $item) {
                // Associa os parâmetros
                $stmt->bindParam(':data', $date, PDO::PARAM_STR);
                $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
                $stmt->bindParam(':doc', $item['doc'], PDO::PARAM_STR);
                $stmt->bindParam(':produto', $item['produto'], PDO::PARAM_STR);
                $stmt->bindParam(':nome_produto', $item['nome_produto'], PDO::PARAM_STR);
                $stmt->bindParam(':saldo_anterior', $item['saldo_anterior'], PDO::PARAM_STR);
                $stmt->bindParam(':entradas', $item['entradas'], PDO::PARAM_STR);
                $stmt->bindParam(':saidas', $item['saidas'], PDO::PARAM_STR);
                $stmt->bindParam(':contagem_ideal', $item['contagem_ideal'], PDO::PARAM_STR);
                $stmt->bindParam(':contagem_realizada', $item['contagem_realizada'], PDO::PARAM_STR);
                $stmt->bindParam(':diferenca', $item['diferenca'], PDO::PARAM_STR);

                // Executa a consulta
                $stmt->execute();
                // Atualiza o saldo do produto
                ProductController::updateStockBalance($system_unit_id, $item['produto'], $item['contagem_realizada'],$item['doc']);
            }

            // Confirma a transação
            $pdo->commit();

            return [
                'status' => 'success',
                'message' => 'Diferenças de estoque registradas com sucesso.'
            ];
        } catch (Exception $e) {
            // Reverte a transação em caso de erro
            $pdo->rollBack();

            return [
                'status' => 'error',
                'message' => 'Erro ao registrar diferenças de estoque: ' . $e->getMessage()
            ];
        }
    }


}

?>
