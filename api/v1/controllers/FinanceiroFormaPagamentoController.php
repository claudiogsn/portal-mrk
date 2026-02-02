<?php

require_once __DIR__ . '/../database/db.php';

class FinanceiroFormaPagamentoController
{
    /**
     * Cria uma nova forma de pagamento.
     * Nome do método alterado para: createFormaPagamento
     */
    public static function createFormaPagamento($data)
    {
        global $pdo;

        $system_unit_id = $data['system_unit_id'] ?? null;
        $codigo         = $data['codigo'] ?? null;
        $nome           = $data['nome'] ?? '';

        // Se não vier banco, tenta usar o 1 por padrão (Caixa/Principal)
        $banco_padrao_id = !empty($data['banco_padrao_id']) ? (int)$data['banco_padrao_id'] : 1;

        if (!$system_unit_id || !$codigo || $codigo === '') {
            return [
                'success' => false,
                'message' => 'Unidade e Código são obrigatórios.'
            ];
        }

        try {
            // Verifica duplicidade (Unidade + Código)
            $stmtCheck = $pdo->prepare("
                SELECT id 
                FROM financeiro_forma_pagamento 
                WHERE system_unit_id = :unit 
                  AND codigo = :cod
                LIMIT 1
            ");
            $stmtCheck->execute([':unit' => $system_unit_id, ':cod' => $codigo]);

            if ($stmtCheck->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Já existe uma forma de pagamento com este código nesta unidade.'
                ];
            }

            // Verifica se o banco 1 existe para esta unidade antes de vincular (segurança)
            if ($banco_padrao_id === 1) {
                $stmtB = $pdo->prepare("SELECT id FROM financeiro_banco WHERE id = 1 AND system_unit_id = ?");
                $stmtB->execute([$system_unit_id]);
                if (!$stmtB->fetch()) {
                    $banco_padrao_id = null; // Se não existe banco 1, deixa sem vínculo
                }
            }

            // Insere
            $stmt = $pdo->prepare("
                INSERT INTO financeiro_forma_pagamento 
                (system_unit_id, codigo, nome, banco_padrao_id, ativos)
                VALUES 
                (:unit, :cod, :nome, :banco, :ativos)
            ");

            $ativos = isset($data['ativos']) ? (int)!empty($data['ativos']) : 1;

            $stmt->execute([
                ':unit'   => $system_unit_id,
                ':cod'    => $codigo,
                ':nome'   => $nome,
                ':banco'  => $banco_padrao_id,
                ':ativos' => $ativos
            ]);

            return [
                'success' => true,
                'message' => 'Forma de pagamento criada com sucesso.',
                'id'      => $pdo->lastInsertId()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar forma de pagamento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Atualiza dados de uma forma de pagamento.
     * Nome do método alterado para: updateFormaPagamento
     */
    public static function updateFormaPagamento($data)
    {
        global $pdo;

        $id = $data['id'] ?? null;
        if (!$id) {
            return ['success' => false, 'message' => 'ID da forma de pagamento é obrigatório.'];
        }

        try {
            // Monta query dinâmica para atualizar apenas o que foi enviado
            $campos = [];
            $params = [':id' => $id];

            if (isset($data['nome'])) {
                $campos[] = "nome = :nome";
                $params[':nome'] = $data['nome'];
            }
            if (isset($data['codigo'])) {
                $campos[] = "codigo = :codigo";
                $params[':codigo'] = $data['codigo'];
            }
            if (array_key_exists('banco_padrao_id', $data)) {
                $campos[] = "banco_padrao_id = :banco";
                $params[':banco'] = !empty($data['banco_padrao_id']) ? (int)$data['banco_padrao_id'] : null;
            }
            if (isset($data['ativos'])) {
                $campos[] = "ativos = :ativos";
                $params[':ativos'] = (int)$data['ativos'];
            }

            if (empty($campos)) {
                return ['success' => true, 'message' => 'Nada para atualizar.'];
            }

            $sql = "UPDATE financeiro_forma_pagamento SET " . implode(', ', $campos) . " WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return ['success' => true, 'message' => 'Forma de pagamento atualizada com sucesso.'];

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return ['success' => false, 'message' => 'Este código já está em uso por outra forma de pagamento.'];
            }
            return ['success' => false, 'message' => 'Erro ao atualizar: ' . $e->getMessage()];
        }
    }

    /**
     * Exclui uma forma de pagamento.
     * Nome do método alterado para: deleteFormaPagamento
     */
    public static function deleteFormaPagamento($id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("DELETE FROM financeiro_forma_pagamento WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Forma de pagamento excluída com sucesso.'];
            }

            return ['success' => false, 'message' => 'Registro não encontrado ou já excluído.'];

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                return ['success' => false, 'message' => 'Não é possível excluir: existem contas vinculadas a esta forma de pagamento. Inative-a.'];
            }
            return ['success' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()];
        }
    }

    /**
     * Lista todas as formas de pagamento da unidade.
     * Nome do método alterado para: listFormasPagamento
     */
    public static function listFormasPagamento($system_unit_id, $apenasAtivos = false)
    {
        global $pdo;

        $sql = "
            SELECT 
                fp.*,
                b.nome as nome_banco_padrao
            FROM financeiro_forma_pagamento fp
            LEFT JOIN financeiro_banco b ON fp.banco_padrao_id = b.codigo AND b.system_unit_id = fp.system_unit_id
            WHERE fp.system_unit_id = :unit
        ";

        if ($apenasAtivos) {
            $sql .= " AND fp.ativos = 1";
        }

        $sql .= " ORDER BY fp.codigo ASC, fp.nome ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':unit' => $system_unit_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém uma única forma de pagamento pelo ID.
     * Nome do método alterado para: getFormaPagamentoById
     */
    public static function getFormaPagamentoById($id)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM financeiro_forma_pagamento WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Importa as formas de pagamento padrão para a unidade.
     * Nome do método alterado para: importarFormasPadrao
     */
    public static function importarFormasPadrao($system_unit_id)
    {
        global $pdo;

        if (!$system_unit_id) {
            return [
                'success' => false,
                'message' => 'system_unit_id é obrigatório'
            ];
        }

        // Garante que a unidade existe
        $stmtUnit = $pdo->prepare("SELECT id FROM system_unit WHERE id = :id LIMIT 1");
        $stmtUnit->bindValue(':id', (int)$system_unit_id, PDO::PARAM_INT);
        $stmtUnit->execute();
        $unit = $stmtUnit->fetch(PDO::FETCH_ASSOC);

        if (!$unit) {
            return [
                'success' => false,
                'message' => 'System Unit não encontrada'
            ];
        }

        // Lista de formas padrão
        $formasPagamento = [
            ['codigo' => 1, 'nome' => 'Dinheiro'],
            ['codigo' => 2, 'nome' => 'DDA'],
            ['codigo' => 3, 'nome' => 'PIX'],
            ['codigo' => 4, 'nome' => 'Cartão de Débito'],
            ['codigo' => 5, 'nome' => 'Cartão de Crédito'],
            ['codigo' => 6, 'nome' => 'Boleto'],
            ['codigo' => 7, 'nome' => 'Transferência'],
            ['codigo' => 8, 'nome' => 'Cheque'],
            ['codigo' => 9, 'nome' => 'Depósito'],
        ];

        try {
            // Insere ou Atualiza. Fixando banco_padrao_id = 1
            $stmt = $pdo->prepare("
                INSERT INTO financeiro_forma_pagamento (
                    system_unit_id,
                    codigo,
                    nome,
                    banco_padrao_id,
                    ativos
                ) VALUES (
                    :system_unit_id,
                    :codigo,
                    :nome,
                    1, 
                    :ativos
                )
                ON DUPLICATE KEY UPDATE
                    nome            = VALUES(nome),
                    banco_padrao_id = VALUES(banco_padrao_id),
                    ativos          = VALUES(ativos)
            ");

            foreach ($formasPagamento as $fp) {
                $stmt->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':codigo'         => $fp['codigo'],
                    ':nome'           => $fp['nome'],
                    ':ativos'         => 1,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Formas de pagamento padrão importadas com sucesso (Vínculo Banco ID 1).'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao importar formas de pagamento: ' . $e->getMessage()
            ];
        }
    }
}