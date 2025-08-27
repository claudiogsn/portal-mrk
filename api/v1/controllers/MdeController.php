<?php

ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../database/db.php';

class MdeController
{
    // Retorna o ID do fornecedor existente ou cria um novo
    public static function getCreateFornecedor($system_unit_id, $fornecedorData)
    {
        global $pdo;

        $cnpjCpf = $fornecedorData['cnpj_cpf'] ?? null;
        if (!$cnpjCpf) {
            throw new Exception("CNPJ/CPF do fornecedor é obrigatório");
        }

        // Verifica se já existe
        $stmt = $pdo->prepare("SELECT id FROM financeiro_fornecedor WHERE system_unit_id = ? AND cnpj_cpf = ?");
        $stmt->execute([$system_unit_id, $cnpjCpf]);
        $fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fornecedor) {
            return $fornecedor['id'];
        }

        // Cria novo fornecedor
        $stmt = $pdo->prepare("
            INSERT INTO financeiro_fornecedor 
            (system_unit_id, codigo, razao, nome, cnpj_cpf, plano_contas, endereco, cep, insc_estadual, fone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $system_unit_id,
            $fornecedorData['codigo'] ?? '',
            $fornecedorData['razao'] ?? '',
            $fornecedorData['nome'] ?? '',
            $cnpjCpf,
            $fornecedorData['plano_contas'] ?? null,
            $fornecedorData['endereco'] ?? null,
            $fornecedorData['cep'] ?? null,
            $fornecedorData['insc_estadual'] ?? null,
            $fornecedorData['fone'] ?? null
        ]);

        return $pdo->lastInsertId();
    }

    // Importa uma nota fiscal (JSON convertido do XML)
    public static function importNotaFiscal($system_unit_id, $notaJson): array
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            $fornecedorData = $notaJson['fornecedor'] ?? null;
            if (!$fornecedorData) {
                throw new Exception("Dados do fornecedor não enviados");
            }

            $fornecedor_id = self::getCreateFornecedor($system_unit_id, $fornecedorData);

            $chaveAcesso = $notaJson['chave_acesso'] ?? null;
            $numeroNF = $notaJson['numero_nf'] ?? null;
            $serie = $notaJson['serie'] ?? null;

            if (!$chaveAcesso || !$numeroNF) {
                throw new Exception("Chave de acesso e número da NF são obrigatórios");
            }

            // Verifica se nota já existe para este fornecedor, serie e unidade
            $stmt = $pdo->prepare("
                SELECT id FROM estoque_nota 
                WHERE system_unit_id = ? AND fornecedor_id = ? AND numero_nf = ? AND serie = ?
            ");
            $stmt->execute([$system_unit_id, $fornecedor_id, $numeroNF, $serie]);
            $notaExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($notaExistente) {
                throw new Exception("Nota já existe para este fornecedor, série e unidade");
            }

            // Insere a nota
            $stmt = $pdo->prepare("
                INSERT INTO estoque_nota 
                (system_unit_id, fornecedor_id, chave_acesso, numero_nf, serie, data_emissao, data_saida, natureza_operacao, valor_total, valor_produtos, valor_frete) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $system_unit_id,
                $fornecedor_id,
                $chaveAcesso,
                $numeroNF,
                $serie,
                $notaJson['data_emissao'] ?? null,
                $notaJson['data_saida'] ?? null,
                $notaJson['natureza_operacao'] ?? null,
                $notaJson['valor_total'] ?? 0,
                $notaJson['valor_produtos'] ?? 0,
                $notaJson['valor_frete'] ?? 0
            ]);

            $nota_id = $pdo->lastInsertId();

            // Insere os itens
            if (!empty($notaJson['itens']) && is_array($notaJson['itens'])) {
                $stmtItem = $pdo->prepare("
                    INSERT INTO estoque_nota_item
                    (system_unit_id, nota_id, numero_item, codigo_produto, descricao, ncm, cfop, unidade, quantidade, valor_unitario, valor_total, valor_frete)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($notaJson['itens'] as $item) {
                    $stmtItem->execute([
                        $system_unit_id,
                        $nota_id,
                        $item['numero_item'] ?? 0,
                        $item['codigo_produto'] ?? null,
                        $item['descricao'] ?? null,
                        $item['ncm'] ?? null,
                        $item['cfop'] ?? null,
                        $item['unidade'] ?? null,
                        $item['quantidade'] ?? 0,
                        $item['valor_unitario'] ?? 0,
                        $item['valor_total'] ?? 0,
                        $item['valor_frete'] ?? 0
                    ]);
                }
            }

            $pdo->commit();
            return ['success' => true, 'nota_id' => $nota_id];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function listarNotas($system_unit_id): array
    {
        global $pdo;

        try {
            $sql = "SELECT 
                    en.id,
                    en.chave_acesso,
                    en.numero_nf,
                    en.serie,
                    en.data_emissao,
                    en.data_entrada,
                    en.valor_total,
                    en.incluida_estoque,
                    en.incluida_financeiro,
                    ff.razao AS fornecedor_razao,
                    ff.cnpj_cpf AS fornecedor_cnpj
                FROM estoque_nota en
                JOIN financeiro_fornecedor ff ON en.fornecedor_id = ff.id 
                    AND ff.system_unit_id = en.system_unit_id
                WHERE en.system_unit_id = :unit_id
                ORDER BY en.data_emissao DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();

            $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $notas,
                'total' => count($notas)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao listar notas fiscais: ' . $e->getMessage()
            ];
        }
    }
}
