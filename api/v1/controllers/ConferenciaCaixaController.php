<?php

require_once __DIR__ . '/../database/db.php';

class ConferenciaCaixaController
{
    private static function getLojaIdBySystemUnit($systemUnitId)
    {
        global $pdo;
        // ... (mesmo código anterior) ...
        $stmt = $pdo->prepare("SELECT custom_code FROM system_unit WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $systemUnitId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) throw new Exception("Unidade não encontrada.");
        return $result['custom_code'];
    }

    /**
     * Salva ou Atualiza a conferência realizada pelo usuário.
     * Espera um array de itens.
     */
    /**
     * Salva ou Atualiza a conferência com Auditoria.
     */
    public static function saveConferencia($data)
    {
        global $pdo;

        $systemUnitId = $data['system_unit_id'] ?? null;
        $dataContabil = $data['data'] ?? null;
        $userId       = $data['user_id'] ?? null;
        $items        = $data['items'] ?? [];

        if (!$systemUnitId || !$dataContabil || !$userId || empty($items)) {
            return ['success' => false, 'message' => 'Dados incompletos.'];
        }

        try {
            $pdo->beginTransaction();

            // Preparando as queries fora do loop para performance

            // 1. Busca registro existente
            $stmtCheck = $pdo->prepare("
                SELECT id, valor_adquirente 
                FROM conferencia_caixa 
                WHERE system_unit_id = :system_unit_id 
                  AND data_contabil = :data_contabil 
                  AND forma_pagamento = :forma_pagamento
                LIMIT 1
            ");

            // 2. Insert (Caso novo)
            $stmtInsert = $pdo->prepare("
                INSERT INTO conferencia_caixa (
                    system_unit_id, data_contabil, forma_pagamento, 
                    valor_venda, valor_processado, valor_adquirente, diferenca, user_id
                ) VALUES (
                    :system_unit_id, :data_contabil, :forma_pagamento,
                    :valor_venda, :valor_processado, :valor_adquirente, :diferenca, :user_id
                )
            ");

            // 3. Update (Caso existente)
            $stmtUpdate = $pdo->prepare("
                UPDATE conferencia_caixa SET
                    valor_venda = :valor_venda,
                    valor_processado = :valor_processado,
                    valor_adquirente = :valor_adquirente,
                    diferenca = :diferenca,
                    user_id = :user_id,
                    updated_at = NOW()
                WHERE id = :id
            ");

            // 4. Auditoria (Histórico)
            $stmtAudit = $pdo->prepare("
                INSERT INTO conferencia_caixa_auditoria (
                    conferencia_id, user_id, valor_anterior, valor_novo, motivo
                ) VALUES (
                    :conferencia_id, :user_id, :valor_anterior, :valor_novo, :motivo
                )
            ");

            foreach ($items as $item) {
                $formaPagamento = strtoupper(trim($item['forma_pagamento']));

                // Valores
                $venda       = (float) ($item['venda_pdv'] ?? 0);
                $processado  = (float) ($item['processado_pagos'] ?? 0);
                $adquirente  = (float) ($item['valor_adquirente'] ?? 0);
                $diferenca   = $venda - $adquirente;

                // O motivo vem de cada item editado na grid, ou um geral do payload
                // Assumindo que vem no item: $item['motivo']
                $motivo = $item['motivo'] ?? null;

                // --- Lógica de Auditoria ---

                // 1. Verifica se já existe
                $stmtCheck->execute([
                    ':system_unit_id'  => $systemUnitId,
                    ':data_contabil'   => $dataContabil,
                    ':forma_pagamento' => $formaPagamento
                ]);
                $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existente) {
                    // --- CENÁRIO DE UPDATE ---

                    $idConferencia = $existente['id'];
                    $valorAnterior = (float) $existente['valor_adquirente'];

                    // Só audita se o valor mudou significativamente
                    // Usa abs() para evitar problemas com ponto flutuante
                    if (abs($valorAnterior - $adquirente) > 0.001) {

                        // Se quiser forçar erro caso não tenha motivo no backend também:
                        /*
                        if (empty($motivo)) {
                           throw new Exception("É necessário informar o motivo para alterar '{$formaPagamento}' de R$ {$valorAnterior} para R$ {$adquirente}.");
                        }
                        */

                        // Grava Auditoria
                        $stmtAudit->execute([
                            ':conferencia_id' => $idConferencia,
                            ':user_id'        => $userId,
                            ':valor_anterior' => $valorAnterior,
                            ':valor_novo'     => $adquirente,
                            ':motivo'         => $motivo ?? 'Alteração de valor'
                        ]);
                    }

                    // Atualiza o registro principal
                    $stmtUpdate->execute([
                        ':valor_venda'      => $venda,
                        ':valor_processado' => $processado,
                        ':valor_adquirente' => $adquirente,
                        ':diferenca'        => $diferenca,
                        ':user_id'          => $userId,
                        ':id'               => $idConferencia
                    ]);

                } else {
                    // --- CENÁRIO DE INSERT (NOVO) ---
                    // Não precisa de auditoria (ou pode criar um log de criação se quiser)
                    $stmtInsert->execute([
                        ':system_unit_id'   => $systemUnitId,
                        ':data_contabil'    => $dataContabil,
                        ':forma_pagamento'  => $formaPagamento,
                        ':valor_venda'      => $venda,
                        ':valor_processado' => $processado,
                        ':valor_adquirente' => $adquirente,
                        ':diferenca'        => $diferenca,
                        ':user_id'          => $userId
                    ]);
                }
            }

            $pdo->commit();
            return ['success' => true, 'message' => 'Conferência salva com sucesso!'];

        } catch (Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()];
        }
    }
    /**
     * Retorna o detalhamento UNIFICADO com o que já foi salvo no banco.
     */
    public static function getDetalhamentoPorFormaPagamento($systemUnitId, $dataAnalise)
    {
        global $pdo;

        try {
            // 1. Converte ID interno para ID da Loja (API)
            $lojaId = self::getLojaIdBySystemUnit($systemUnitId);

            // 2. Query detalhada com Join na system_users
            $sql = "
            SELECT 
                T.forma_pagamento,
                
                -- Valores calculados na hora (API)
                SUM(T.valor_movimento) AS venda_pdv,
                SUM(T.valor_pagamento) AS processado_pagos,
                
                -- Valor salvo pelo usuário (se existir)
                COALESCE(C.valor_adquirente, 0) AS valor_adquirente,
                
                -- Diferença: Se usuário digitou, usa o digitado. Senão, usa o processado API.
                CASE 
                    WHEN C.valor_adquirente IS NOT NULL AND C.valor_adquirente > 0 
                    THEN (SUM(T.valor_movimento) - C.valor_adquirente)
                    ELSE (SUM(T.valor_movimento) - SUM(T.valor_pagamento))
                END AS diferenca,

                -- Auditoria (ID e Nome do Usuário)
                C.user_id AS ultimo_usuario_id,
                COALESCE(U.name, 'Não conferido') AS nome_usuario_alteracao,
                DATE_FORMAT(C.updated_at, '%d/%m/%Y %H:%i') AS data_ultima_alteracao

            FROM (
                -- 1. Detalhe do Movimento (Vendas)
                SELECT 
                    UPPER(TRIM(p.nome_meio)) as forma_pagamento, 
                    p.valor as valor_movimento,
                    0 as valor_pagamento
                FROM api_movimento_caixa m
                JOIN api_movimento_caixa_pagamentos p ON p.movimento_caixa_uuid = m.uuid
                WHERE m.loja_id = :loja_id 
                  AND DATE(m.data_contabil) = :data_analise 
                  AND m.cancelado = 0

                UNION ALL

                -- 2. Detalhe dos Pagamentos (Comprovantes)
                SELECT 
                    UPPER(TRIM(descricao)), 
                    0,
                    valor
                FROM api_pagamentos
                WHERE id_loja = :loja_id 
                  AND data_contabil = :data_analise
                  AND (status_pagamento IS NULL OR status_pagamento != 'cancelado')

            ) AS T

            -- Join com a tabela de conferência para pegar os valores digitados
            LEFT JOIN conferencia_caixa C 
                ON C.system_unit_id = :system_unit_id 
                AND C.data_contabil = :data_analise
                AND C.forma_pagamento = T.forma_pagamento
            
            -- Join com a tabela de usuários para pegar o nome de quem salvou
            LEFT JOIN system_users U ON U.id = C.user_id

            GROUP BY T.forma_pagamento
            ORDER BY T.forma_pagamento ASC
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':loja_id' => $lojaId,
                ':system_unit_id' => $systemUnitId,
                ':data_analise' => $dataAnalise
            ]);

            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    /**
     * Retorna o detalhe financeiro (API Pagamentos) para uma forma de pagamento.
     * Exibe NSU, Adquirente, Taxas e Valor Líquido.
     */
    public static function getDetalhePagamentosPorFormaPagamento($systemUnitId, $dataAnalise, $formaPagamento)
    {
        global $pdo;

        if (!$systemUnitId || !$dataAnalise || !$formaPagamento) {
            return ['success' => false, 'message' => 'Parâmetros inválidos para o detalhamento financeiro.'];
        }

        try {
            $lojaId = self::getLojaIdBySystemUnit($systemUnitId);

            // Formata a hora que vem '1403' para '14:03' visualmente
            $sql = "
                SELECT 
                    -- Formatação da Hora (HHMM -> HH:mm)
                    CONCAT(SUBSTRING(hora_lancamento, 1, 2), ':', SUBSTRING(hora_lancamento, 3, 2)) AS hora,
                    
                    -- Identificadores da Transação
                    COALESCE(nsu, '-') AS nsu,
                    COALESCE(autorizacao, '-') AS autorizacao,
                    
                    -- Informações da Maquininha/Operadora
                    COALESCE(adquirente, 'Não Identificado') AS adquirente, -- Ex: STONE, CIELO
                    COALESCE(bandeira, '') AS bandeira,       -- Ex: MASTER, VISA
                    
                    -- Valores
                    valor AS valor_bruto,
                    taxa_comissao AS taxa_percentual,
                    valor_comissao AS valor_taxa,
                    valor_liquido AS valor_liquido,
                    
                    -- Status
                    status_pagamento
                
                FROM api_pagamentos
                
                WHERE id_loja = :loja_id 
                  AND data_contabil = :data_analise
                  AND (status_pagamento IS NULL OR status_pagamento != 'cancelado')
                  
                  -- Bate a descrição com a forma de pagamento selecionada na grid
                  AND UPPER(TRIM(descricao)) = UPPER(TRIM(:forma_pagamento))
                
                ORDER BY hora_lancamento ASC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':loja_id' => $lojaId,
                ':data_analise' => $dataAnalise,
                ':forma_pagamento' => $formaPagamento
            ]);

            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar detalhes financeiros: ' . $e->getMessage()];
        }
    }

    public static function getHistoricoAuditoria($conferenciaId)
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT 
                a.valor_anterior, 
                a.valor_novo, 
                a.motivo, 
                DATE_FORMAT(a.created_at, '%d/%m/%Y %H:%i') as data_alteracao,
                u.name as usuario_nome
            FROM conferencia_caixa_auditoria a
            LEFT JOIN system_users u ON u.id = a.user_id -- Ajuste para sua tabela de usuários
            WHERE a.conferencia_id = :id
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([':id' => $conferenciaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}