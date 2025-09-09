<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class NotaFiscalEntradaController {
    public static function importarNotasFiscaisEntrada($system_unit_id, $notas, $usuario_id): array
    {
        global $pdo;

        try {
            error_log("### Início da função importarNotasFiscaisEntrada ###");

            if (empty($system_unit_id) || empty($usuario_id) || !is_array($notas)) {
                throw new Exception('Parâmetros inválidos.');
            }

            $pdo->beginTransaction();
            error_log("Transação iniciada.");

            $stmtInsertNota = $pdo->prepare("
            INSERT INTO nota_fiscal_entrada (
                system_unit_id, documento, data_entrada, data_emissao, fornecedor, valor_total
            ) VALUES (
                :system_unit_id, :documento, :data_entrada, :data_emissao, :fornecedor, :valor_total
            )
            ON DUPLICATE KEY UPDATE 
                data_entrada = VALUES(data_entrada),
                data_emissao = VALUES(data_emissao),
                valor_total = VALUES(valor_total)
        ");

            $notasImportadas = 0;

            foreach ($notas as $nota) {
                if (!isset($nota['documento'], $nota['data_entrada'], $nota['fornecedor'], $nota['valor_total'])) {
                    throw new Exception("Nota malformada: " . json_encode($nota));
                }

                $documento = trim($nota['documento']);
                $dataEntrada = $nota['data_entrada']; // formato 'YYYY-MM-DD'
                $dataEmissao = $nota['data_emissao'] ?? $dataEntrada; // se não houver data de emissão, usa a data de entrada
                $fornecedor = mb_substr(trim($nota['fornecedor']), 0, 100);
                $valorTotal = str_replace(',', '.', $nota['valor_total']);

                $stmtInsertNota->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':documento' => $documento,
                    ':data_entrada' => $dataEntrada,
                    ':data_emissao' => $dataEmissao,
                    ':fornecedor' => $fornecedor,
                    ':valor_total' => $valorTotal
                ]);

                $notasImportadas++;
            }

            $pdo->commit();
            error_log("Transação concluída com sucesso.");
            error_log("### Fim da função importarNotasFiscaisEntrada ###");

            return [
                'status' => 'success',
                'message' => 'Importação concluída com sucesso.',
                'notas_importadas' => $notasImportadas
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
                error_log("Rollback executado.");
            }

            error_log("Erro capturado: " . $e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Erro na importação: ' . $e->getMessage()
            ];
        }
    }


    public static function getNotaFinanceiroPayload(array $data): array
    {
        global $pdo;

        try {
            // === Validação de entrada ===
            if (empty($data['system_unit_id'])) {
                throw new Exception('system_unit_id é obrigatório.');
            }
            $system_unit_id = (int)$data['system_unit_id'];

            // pelo menos um identificador da nota
            $hasId      = !empty($data['nota_id']);
            $hasChave   = !empty($data['chave_acesso']);
            $hasNumero  = !empty($data['numero_nf']);

            if (!$hasId && !$hasChave && !$hasNumero) {
                throw new Exception('Informe nota_id, chave_acesso ou numero_nf.');
            }

            // === Monta WHERE dinamicamente ===
            $where = ['system_unit_id = :unit'];
            $params = [':unit' => $system_unit_id];

            if ($hasId) {
                $where[] = 'id = :id';
                $params[':id'] = (int)$data['nota_id'];
            } elseif ($hasChave) {
                $where[] = 'chave_acesso = :chave';
                $params[':chave'] = trim((string)$data['chave_acesso']);
            } else { // numero_nf (+ opcional série)
                $where[] = 'numero_nf = :numero';
                $params[':numero'] = trim((string)$data['numero_nf']);
                if (!empty($data['serie'])) {
                    $where[] = 'serie = :serie';
                    $params[':serie'] = trim((string)$data['serie']);
                }
            }

            $sqlNota = "
            SELECT 
                fornecedor_id,
                numero_nf,
                data_emissao,
                valor_total
            FROM estoque_nota
            WHERE " . implode(' AND ', $where) . "
            LIMIT 1
        ";
            $st = $pdo->prepare($sqlNota);
            $st->execute($params);
            $nota = $st->fetch(PDO::FETCH_ASSOC);

            if (!$nota) {
                return ['success' => false, 'error' => 'Nota não encontrada para os parâmetros informados.'];
            }

            // Emissão em YYYY-MM-DD (se vier null mantém null)
            $emissao = null;
            if (!empty($nota['data_emissao'])) {
                $ts = strtotime($nota['data_emissao']);
                if ($ts !== false) {
                    $emissao = date('Y-m-d', $ts);
                }
            }

            // Valor total normalizado com ponto decimal
            $valorTotal = isset($nota['valor_total']) ? number_format((float)$nota['valor_total'], 2, '.', '') : '0.00';

            // === Planos de contas (da unidade + globais) ===
            $stPlano = $pdo->prepare("
            SELECT id, codigo, descricao
            FROM financeiro_plano
            WHERE system_unit_id = :unit 
            ORDER BY 
                CASE WHEN codigo = '' THEN 1 ELSE 0 END,  -- códigos vazios por último
                codigo, descricao
        ");
            $stPlano->execute([':unit' => $system_unit_id]);
            $planos = $stPlano->fetchAll(PDO::FETCH_ASSOC);

            // Mapeia para formato enxuto
            $planosDeConta = array_map(function ($p) {
                return [
                    'id'        => (int)$p['id'],
                    'codigo'    => (string)$p['codigo'],
                    'descricao' => (string)$p['descricao'],
                    'label'     => trim(($p['codigo'] ? "{$p['codigo']} - " : "") . $p['descricao']),
                ];
            }, $planos);

            // === Formas de pagamento padrão (IDs estáveis) ===
            // Ajuste os IDs conforme sua convenção interna, se necessitar.
            $formasPagamento = [
                ['id' => 1, 'codigo' => 'dinheiro',      'descricao' => 'Dinheiro'],
                ['id' => 2, 'codigo' => 'pix',           'descricao' => 'PIX'],
                ['id' => 3, 'codigo' => 'debito',        'descricao' => 'Cartão de Débito'],
                ['id' => 4, 'codigo' => 'credito',       'descricao' => 'Cartão de Crédito'],
                ['id' => 5, 'codigo' => 'boleto',        'descricao' => 'Boleto'],
                ['id' => 6, 'codigo' => 'transferencia', 'descricao' => 'Transferência'],
                ['id' => 7, 'codigo' => 'cheque',        'descricao' => 'Cheque'],
                ['id' => 8, 'codigo' => 'deposito',      'descricao' => 'Depósito']
            ];

            // === Monta payload ===
            $payload = [
                'fornecedor_id'     => (int)$nota['fornecedor_id'],
                'documento'         => (string)$nota['numero_nf'],
                'emissao'           => $emissao,                  // "YYYY-MM-DD" ou null
                'valor_total'       => $valorTotal,               // "1290.50"
                'planos_de_conta'   => $planosDeConta,            // lista de {id, codigo, descricao, label}
                'formas_pagamento'  => $formasPagamento           // lista de {id, codigo, descricao}
            ];

            return ['success' => true, 'data' => $payload];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


}
?>