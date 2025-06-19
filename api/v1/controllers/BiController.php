<?php


ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class BiController {

    public static function createGroup($nome, $slug = null, $ativo = 1, $bi = 0) {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT INTO grupo_estabelecimento (nome, slug, ativo, bi)
            VALUES (:nome, :slug, :ativo, :bi)
        ");

        $stmt->execute([
            ':nome' => $nome,
            ':slug' => $slug ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $nome)),
            ':ativo' => $ativo,
            ':bi' => $bi
        ]);

        return $pdo->lastInsertId();
    }
    public static function editGroup($id, $nome, $slug = null, $ativo = 1, $bi = 0) {
        global $pdo;

        $stmt = $pdo->prepare("
            UPDATE grupo_estabelecimento
            SET nome = :nome, slug = :slug, ativo = :ativo, bi = :bi
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':nome' => $nome,
            ':slug' => $slug ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $nome)),
            ':ativo' => $ativo,
            ':bi' => $bi
        ]);
    }
    public static function toggleGroupAtivo($id, $ativo) {
        global $pdo;

        $stmt = $pdo->prepare("
        UPDATE grupo_estabelecimento
        SET ativo = :ativo
        WHERE id = :id
    ");

        return $stmt->execute([
            ':id' => $id,
            ':ativo' => $ativo
        ]);
    }
    public static function updateUnitsGroup($grupo_id, array $unidades) {
        global $pdo;

        // Inicia transação para garantir integridade
        $pdo->beginTransaction();

        try {
            // Remove unidades existentes do grupo
            $stmtDelete = $pdo->prepare("
                DELETE FROM grupo_estabelecimento_rel
                WHERE grupo_id = :grupo_id
            ");
            $stmtDelete->execute([':grupo_id' => $grupo_id]);

            // Insere novas unidades
            $stmtInsert = $pdo->prepare("
                INSERT INTO grupo_estabelecimento_rel (grupo_id, system_unit_id)
                VALUES (:grupo_id, :system_unit_id)
            ");

            foreach ($unidades as $unitId) {
                $stmtInsert->execute([
                    ':grupo_id' => $grupo_id,
                    ':system_unit_id' => $unitId
                ]);
            }

            $pdo->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public static function getUnitsByGroup($group_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT 
        rel.system_unit_id, 
        su.custom_code,
        su.name
    FROM 
        grupo_estabelecimento_rel AS rel 
    JOIN 
        system_unit AS su ON rel.system_unit_id = su.id 
    WHERE 
        rel.grupo_id = :group_id
        AND su.custom_code IS NOT NULL
    ORDER BY FIELD(su.id, 9, 3, 4, 5, 7);  -- Ordem específica
    ");

        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getUnitsToProcess() {
        global $pdo;

        $stmt = $pdo->prepare("SELECT 
            su.id, 
            su.custom_code,
            su.name
            FROM 
                system_unit AS su
            WHERE 
                su.custom_code IS NOT NULL
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getGroupsToProcess() {
        global $pdo;

        $stmt = $pdo->prepare("SELECT 
            su.id, 
            su.nome
            FROM 
                grupo_estabelecimento AS su
            WHERE 
                su.bi = 1
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function ListUnitsByGroup($group_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT 
        rel.system_unit_id, 
        su.custom_code,
        su.name
    FROM 
        grupo_estabelecimento_rel AS rel 
    JOIN 
        system_unit AS su ON rel.system_unit_id = su.id 
    WHERE 
        rel.grupo_id = :group_id
    ");

        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getGroupByUnit($system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT 
        ge.id, 
        ge.nome,
        ge.slug
    FROM 
        grupo_estabelecimento_rel AS rel 
    JOIN 
        grupo_estabelecimento AS ge ON rel.grupo_id = ge.id 
    WHERE 
        rel.system_unit_id = :system_unit_id
    ");

        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getGroupByUser($user_id) {
        global $pdo;

        // Consulta com JOIN na tabela de lojas (system_unit)
        $stmt = $pdo->prepare("
        SELECT 
            ge.id AS grupo_id,
            ge.nome AS grupo_nome,
            ge.slug AS grupo_slug,
            ge.ativo AS grupo_ativo,
            ge.bi AS grupo_bi,
            su.id AS loja_id,
            su.name AS loja_nome,
            su.custom_code AS loja_codigo
        FROM system_user_unit suu
        INNER JOIN grupo_estabelecimento_rel ger ON suu.system_unit_id = ger.system_unit_id
        INNER JOIN grupo_estabelecimento ge ON ger.grupo_id = ge.id
        INNER JOIN system_unit su ON su.id = ger.system_unit_id
        WHERE suu.system_user_id = :user_id
    ");

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grupos = [];
        $lojasPorGrupo = [];

        foreach ($dados as $row) {
            $grupoId = $row['grupo_id'];

            // Monta lista única de grupos
            if (!isset($grupos[$grupoId])) {
                $grupos[$grupoId] = [
                    'id' => $row['grupo_id'],
                    'nome' => $row['grupo_nome'],
                    'slug' => $row['grupo_slug'],
                    'ativo' => $row['grupo_ativo'],
                    'bi' => $row['grupo_bi']
                ];
            }

            // Agrupa lojas por grupo
            if (!isset($lojasPorGrupo[$grupoId])) {
                $lojasPorGrupo[$grupoId] = [];
            }

            $lojasPorGrupo[$grupoId][] = [
                'id' => $row['loja_id'],
                'name' => $row['loja_nome'],
                'custom_code' => $row['loja_codigo']
            ];
        }

        return [
            "grupos" => array_values($grupos),
            "lojas_por_grupo" => $lojasPorGrupo
        ];
    }
    public static function getGroups() {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM grupo_estabelecimento");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    public static function getUnits() {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM system_unit");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getUnitsNotGrouped() {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM system_unit where status = 1");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getUnitsByGroupMov($group_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT 
            rel.system_unit_id, 
            su.custom_code,
            su.name
        FROM 
            grupo_estabelecimento_rel AS rel 
        JOIN 
            system_unit AS su ON rel.system_unit_id = su.id 
        WHERE 
            rel.grupo_id = :group_id;
        ");
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
        // pega as vendas dentro de sales
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
                       MAX(custom_code) AS custom_code
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
    public static function persistMovimentoCaixa($movimentos): array
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            $stmtMovimento = $pdo->prepare("
            INSERT INTO movimento_caixa (
                id, num_controle, redeId, rede, lojaId, loja, modoVenda, idModoVenda, hora,
                idAtendente, codAtendente, nomeAtendente, vlDesconto, vlAcrescimo, vlTotalReceber,
                vlTotalRecebido, vlServicoRecebido, vlTrocoFormasPagto, vlRepique, vlTaxaEntrega,
                numPessoas, operacaoId, maquinaId, nomeMaquina, maquinaCod, maquinaPortaFiscal,
                periodoId, periodoCod, periodoNome, cancelado, modoVenda2, dataAbertura,
                dataFechamento, dataContabil
            )
            VALUES (
                :id, :num_controle, :redeId, :rede, :lojaId, :loja, :modoVenda, :idModoVenda, :hora,
                :idAtendente, :codAtendente, :nomeAtendente, :vlDesconto, :vlAcrescimo, :vlTotalReceber,
                :vlTotalRecebido, :vlServicoRecebido, :vlTrocoFormasPagto, :vlRepique, :vlTaxaEntrega,
                :numPessoas, :operacaoId, :maquinaId, :nomeMaquina, :maquinaCod, :maquinaPortaFiscal,
                :periodoId, :periodoCod, :periodoNome, :cancelado, :modoVenda2, :dataAbertura,
                :dataFechamento, :dataContabil
            )
            ON DUPLICATE KEY UPDATE
                redeId = VALUES(redeId),
                rede = VALUES(rede),
                loja = VALUES(loja),
                modoVenda = VALUES(modoVenda),
                idModoVenda = VALUES(idModoVenda),
                hora = VALUES(hora),
                idAtendente = VALUES(idAtendente),
                codAtendente = VALUES(codAtendente),
                nomeAtendente = VALUES(nomeAtendente),
                vlDesconto = VALUES(vlDesconto),
                vlAcrescimo = VALUES(vlAcrescimo),
                vlTotalReceber = VALUES(vlTotalReceber),
                vlTotalRecebido = VALUES(vlTotalRecebido),
                vlServicoRecebido = VALUES(vlServicoRecebido),
                vlTrocoFormasPagto = VALUES(vlTrocoFormasPagto),
                vlRepique = VALUES(vlRepique),
                vlTaxaEntrega = VALUES(vlTaxaEntrega),
                numPessoas = VALUES(numPessoas),
                operacaoId = VALUES(operacaoId),
                maquinaId = VALUES(maquinaId),
                nomeMaquina = VALUES(nomeMaquina),
                maquinaCod = VALUES(maquinaCod),
                maquinaPortaFiscal = VALUES(maquinaPortaFiscal),
                periodoId = VALUES(periodoId),
                periodoCod = VALUES(periodoCod),
                periodoNome = VALUES(periodoNome),
                cancelado = VALUES(cancelado),
                modoVenda2 = VALUES(modoVenda2),
                dataAbertura = VALUES(dataAbertura),
                dataFechamento = VALUES(dataFechamento),
                dataContabil = VALUES(dataContabil)
        ");

            $stmtDeleteMeios = $pdo->prepare("DELETE FROM meios_pagamento WHERE num_controle = :num_controle AND lojaId = :lojaId");
            $stmtInsertMeios = $pdo->prepare("
            INSERT INTO meios_pagamento (id, num_controle, lojaId, codigo, nome, valor, troco, valorRecebido)
            VALUES (:id, :num_controle, :lojaId, :codigo, :nome, :valor, :troco, :valorRecebido)
        ");

            $stmtDeleteConsumidores = $pdo->prepare("DELETE FROM consumidores WHERE num_controle = :num_controle AND lojaId = :lojaId");
            $stmtInsertConsumidor = $pdo->prepare("
            INSERT INTO consumidores (num_controle, lojaId, documento, tipo, nome)
            VALUES (:num_controle, :lojaId, :documento, :tipo, :nome)
        ");

            foreach ($movimentos as $mov) {
                $stmtMovimento->execute([
                    ':id' => $mov['id'],
                    ':num_controle' => $mov['num_controle'],
                    ':redeId' => $mov['redeId'],
                    ':rede' => $mov['rede'],
                    ':lojaId' => $mov['lojaId'],
                    ':loja' => $mov['loja'],
                    ':modoVenda' => $mov['modoVenda'],
                    ':idModoVenda' => $mov['idModoVenda'],
                    ':hora' => $mov['hora'],
                    ':idAtendente' => $mov['idAtendente'],
                    ':codAtendente' => $mov['codAtendente'],
                    ':nomeAtendente' => $mov['nomeAtendente'],
                    ':vlDesconto' => $mov['vlDesconto'],
                    ':vlAcrescimo' => $mov['vlAcrescimo'],
                    ':vlTotalReceber' => $mov['vlTotalReceber'],
                    ':vlTotalRecebido' => $mov['vlTotalRecebido'],
                    ':vlServicoRecebido' => $mov['vlServicoRecebido'],
                    ':vlTrocoFormasPagto' => $mov['vlTrocoFormasPagto'],
                    ':vlRepique' => $mov['vlRepique'],
                    ':vlTaxaEntrega' => $mov['vlTaxaEntrega'],
                    ':numPessoas' => $mov['numPessoas'],
                    ':operacaoId' => $mov['operacaoId'],
                    ':maquinaId' => $mov['maquinaId'],
                    ':nomeMaquina' => $mov['nomeMaquina'],
                    ':maquinaCod' => $mov['maquinaCod'],
                    ':maquinaPortaFiscal' => $mov['maquinaPortaFiscal'],
                    ':periodoId' => $mov['periodoId'],
                    ':periodoCod' => $mov['periodoCod'],
                    ':periodoNome' => $mov['periodoNome'],
                    ':cancelado' => $mov['cancelado'],
                    ':modoVenda2' => $mov['modoVenda2'],
                    ':dataAbertura' => $mov['dataAbertura'],
                    ':dataFechamento' => $mov['dataFechamento'],
                    ':dataContabil' => $mov['dataContabil']
                ]);

                // Meios de pagamento
                $stmtDeleteMeios->execute([
                    ':num_controle' => $mov['num_controle'],
                    ':lojaId' => $mov['lojaId']
                ]);
                foreach ($mov['meiosPagamento'] as $mp) {
                    $stmtInsertMeios->execute([
                        ':id' => $mp['id'],
                        ':num_controle' => $mov['num_controle'],
                        ':lojaId' => $mov['lojaId'],
                        ':codigo' => $mp['codigo'],
                        ':nome' => $mp['nome'],
                        ':valor' => $mp['valor'],
                        ':troco' => $mp['troco'],
                        ':valorRecebido' => $mp['valorRecebido']
                    ]);
                }

                // Consumidores
                $stmtDeleteConsumidores->execute([
                    ':num_controle' => $mov['num_controle'],
                    ':lojaId' => $mov['lojaId']
                ]);
                foreach ($mov['consumidores'] as $cons) {
                    $stmtInsertConsumidor->execute([
                        ':num_controle' => $mov['num_controle'],
                        ':lojaId' => $mov['lojaId'],
                        ':documento' => $cons['documento'],
                        ':tipo' => $cons['tipo'],
                        ':nome' => $cons['nome']
                    ]);
                }
            }

            $pdo->commit();
            return ['status' => 'success', 'message' => 'Movimentos persistidos com sucesso.'];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 'error', 'message' => 'Erro ao persistir movimentos: ' . $e->getMessage()];
        }
    }
    public static function GetInfoConsolidationEstoque($system_unit_id, $data) {
        global $pdo;

        try {
            // 1. Verifica se ja foi feita a consolidacao para o dia
            $stmt = $pdo->prepare(
                "SELECT * FROM diferencas_estoque 
             WHERE data = :data AND system_unit_id = :system_unit_id"
            );
            $stmt->bindParam(':data', $data, PDO::PARAM_STR);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $consolidated_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Retorna os dados existentes se a consolidacao ja foi feita
            if (!empty($consolidated_data)) {
                return [
                    'status' => 'success',
                    'message' => 'Consolidação já realizada para este dia.',
                    'data' => $consolidated_data
                ];
            }

            // 3. Continua com a lógica original caso não exista consolidacao
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
                $saidas = number_format($saidas_data['quantidade'] ?? 0, 2, '.', '');

                // Cálculo de movimentação e diferença
                $saldo_final = $saldo_inicial + $entradas - $saidas;
                $saldo_final_formatado = number_format($saldo_final, 2, '.', '');
                $diferenca = number_format($contagem_realizada - $saldo_final_formatado, 2, '.', '');


                // Adiciona os dados ao resultado
                $result[] = [
                    'doc' => $doc,
                    'produto' => $produto,
                    'nome_produto' => $nome_produto,
                    'saldo_anterior' => $saldo_inicial,
                    'entradas' => $entradas,
                    'saidas' => $saidas,
                    'contagem_ideal' => $saldo_final_formatado,
                    'contagem_realizada' => number_format($contagem_realizada, 2, '.', ''),
                    'diferenca' => $diferenca
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Informações Disponiveis.',
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
    public static function getSalesByInsumos ($systemUnitId, $data)
    {
        global $pdo;

        try {
            // Consulta os produtos vendidos
            $stmt = $pdo->prepare("SELECT system_unit_id, cod_material AS produto, quantidade AS qtde, data_movimento AS data
                                FROM _bi_sales
                                WHERE system_unit_id = :systemUnitId AND data_movimento = :data");
            $stmt->execute([
                ':systemUnitId' => $systemUnitId,
                ':data' => $data
            ]);

            $produtosVendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($produtosVendas)) {
                return ["error" => "Nenhuma movimentação encontrada para a unidade e data informadas."];
            }

            // Obter IDs dos produtos vendidos
            $produtosVendidosIds = array_map(function ($produto) {
                return $produto['produto'];
            }, $produtosVendas);

            // Consulta insumos relacionados aos produtos vendidos
            $placeholders = implode(',', array_fill(0, count($produtosVendidosIds), '?'));

            $stmtInsumos = $pdo->prepare("SELECT DISTINCT insumo_id, p.nome AS nome_insumo
                                      FROM compositions c
                                      JOIN products p ON p.codigo = c.insumo_id AND p.system_unit_id = c.system_unit_id
                                      WHERE c.system_unit_id = ? AND c.product_id IN ($placeholders)");
            $stmtInsumos->execute(array_merge([$systemUnitId], $produtosVendidosIds));
            $insumos = $stmtInsumos->fetchAll(PDO::FETCH_ASSOC);

            if (empty($insumos)) {
                return ["error" => "Nenhum insumo relacionado encontrado para os produtos vendidos."];
            }

            $result = [];

            foreach ($insumos as $insumo) {
                // Obter detalhes dos produtos vendidos que utilizam o insumo
                $stmtProdutos = $pdo->prepare("SELECT c.product_id AS codigo_produto, p.nome AS nome_produto, c.quantity AS quantidade_insumo, 
                                                  s.quantidade AS quantidade_venda_produto, (c.quantity * s.quantidade) AS uso_insumo
                                           FROM compositions c
                                           JOIN _bi_sales s ON c.product_id = s.cod_material AND c.system_unit_id = s.system_unit_id
                                           JOIN products p ON p.codigo = s.cod_material AND p.system_unit_id = s.system_unit_id
                                           WHERE c.system_unit_id = :systemUnitId AND c.insumo_id = :insumoId AND s.data_movimento = :data");
                $stmtProdutos->execute([
                    ':systemUnitId' => $systemUnitId,
                    ':insumoId' => $insumo['insumo_id'],
                    ':data' => $data
                ]);

                $produtosVendidos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

                $totalUsoInsumo = array_reduce($produtosVendidos, function ($carry, $produto) {
                    return $carry + $produto['uso_insumo'];
                }, 0);

                $result[] = [
                    "codigo_insumo" => $insumo['insumo_id'],
                    "nome_insumo" => $insumo['nome_insumo'],
                    "sale_insumos" => number_format($totalUsoInsumo, 2, '.', ''),
                    "produtos_vendidos" => $produtosVendidos
                ];
            }

            return $result;
        } catch (Exception $e) {
            return ["error" => "Erro ao processar os dados: " . $e->getMessage()];
        }
    }
    public static function generateDashboardData($system_unit_id, $start_date, $end_date) {
        global $pdo;

        $data = [
            'Cards' => [],
            'Tables' => [],
            'Status' => []
        ];

        try {
            // Cards
            $cardsQueries = [
                'ComposicoesCadastradas' => "SELECT COUNT(*) FROM compositions WHERE system_unit_id = :unit_id",
                'InsumosCadastrados' => "SELECT COUNT(*) FROM products WHERE system_unit_id = :unit_id AND insumo = 1",
                'ProdutosCadastrados' => "SELECT COUNT(*) FROM products WHERE system_unit_id = :unit_id",
                'Vendas' => "SELECT CONCAT('R$ ', FORMAT(SUM(valor_liquido), 2, 'pt_BR')) AS valor_formatado FROM _bi_sales 
                WHERE system_unit_id = :unit_id AND data_movimento BETWEEN :start_date AND :end_date;"
            ];

            foreach ($cardsQueries as $key => $query) {
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':unit_id', $system_unit_id, PDO::PARAM_INT);
                if ($key === 'Vendas') {
                    $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
                    $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
                }
                $stmt->execute();
                $data['Cards'][$key] = $stmt->fetchColumn();
            }

            // Tables
            // Ultimas Movs
            $stmt = $pdo->prepare("SELECT doc, data, tipo_mov, tipo FROM movimentacao WHERE system_unit_id = :unit_id AND data BETWEEN :start_date AND :end_date GROUP BY doc DESC LIMIT 10;");
            $stmt->bindParam(':unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
            $stmt->execute();
            $data['Tables']['UltimasMovs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Ranking de Consumo de Produtos
            $stmt = $pdo->prepare("SELECT s.cod_material AS codigo, p.nome AS nome_produto, SUM(s.quantidade) AS total_quantidade FROM _bi_sales s JOIN products p ON s.cod_material = p.codigo AND s.system_unit_id = p.system_unit_id WHERE s.system_unit_id = :unit_id AND s.data_movimento BETWEEN :start_date AND :end_date GROUP BY s.cod_material ORDER BY total_quantidade DESC LIMIT 10;");
            $stmt->bindParam(':unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
            $stmt->execute();
            $data['Tables']['RankingProdutos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Ranking de Consumo de Insumos
            $stmt = $pdo->prepare("SELECT m.produto AS codigo, p.nome AS nome_produto, SUM(m.quantidade) AS total_quantidade FROM movimentacao m JOIN products p ON m.produto = p.codigo AND m.system_unit_id = p.system_unit_id WHERE m.system_unit_id = :unit_id AND m.tipo = 'v' AND m.data BETWEEN :start_date AND :end_date GROUP BY m.produto ORDER BY total_quantidade DESC LIMIT 10;");
            $stmt->bindParam(':unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
            $stmt->execute();
            $data['Tables']['RankingInsumos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Status (mocked data for now)
            $data['Status']['ImportarVendas'] = [
                ['data' => '2024-12-01', 'hora' => '12:00:00', 'status' => 'success'],
                ['data' => '2024-12-02', 'hora' => '13:00:00', 'status' => 'pending']
            ];
            $data['Status']['ProcessamentoBI'] = [
                ['data' => '2024-12-01', 'hora' => '14:00:00', 'status' => 'success'],
                ['data' => '2024-12-02', 'hora' => '15:00:00', 'status' => 'error']
            ];
            $data['Status']['GerarDocSaida'] = [
                ['data' => '2024-12-01', 'hora' => '16:00:00', 'status' => 'success'],
                ['data' => '2024-12-02', 'hora' => '17:00:00', 'status' => 'pending']
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to generate dashboard data: ' . $e->getMessage()
            ];
        }

        return $data;
    }

}

?>
