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
                if (!isset($nota['documento'], $nota['data_entrada'], $nota['data_emissao'], $nota['fornecedor'], $nota['valor_total'])) {
                    throw new Exception("Nota malformada: " . json_encode($nota));
                }

                $documento = trim($nota['documento']);
                $dataEntrada = $nota['data_entrada']; // formato 'YYYY-MM-DD'
                $dataEmissao = $nota['data_emissao'];
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

}
?>