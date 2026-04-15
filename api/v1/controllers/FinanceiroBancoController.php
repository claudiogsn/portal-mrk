<?php

require_once __DIR__ . '/../database/db.php';

class FinanceiroBancoController
{
    /**
     * Cria uma nova Conta Bancária / Caixa.
     */
    public static function createBanco($data)
    {
        global $pdo;

        $system_unit_id = $data['system_unit_id'] ?? null;
        $codigo         = $data['codigo'] ?? null;
        $nome           = $data['nome'] ?? '';

        // Campos bancários opcionais
        $agencia  = $data['agencia'] ?? null;
        $conta    = $data['conta'] ?? null;
        $carteira = $data['carteira'] ?? null;
        $descricao = $data['descricao'] ?? null;

        if (!$system_unit_id) {
            return ['success' => false, 'message' => 'system_unit_id é obrigatório'];
        }

        if ($codigo === null || $codigo === '') {
            return ['success' => false, 'message' => 'Código do banco é obrigatório'];
        }

        try {
            // Verifica duplicidade (Unidade + Código)
            // Obs: Alguns sistemas permitem repetir código de banco se forem agências diferentes,
            // mas mantive a trava por código+unidade conforme seu padrão anterior.
            $stmt = $pdo->prepare("
                SELECT id 
                FROM financeiro_banco 
                WHERE system_unit_id = :unit
                  AND codigo = :codigo
                LIMIT 1
            ");
            $stmt->execute([
                ':unit'   => $system_unit_id,
                ':codigo' => $codigo,
            ]);

            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Já existe uma conta com este código para esta unidade.'
                ];
            }

            // Cria novo banco com os campos bancários
            $stmt = $pdo->prepare("
                INSERT INTO financeiro_banco 
                    (system_unit_id, codigo, nome, agencia, conta, carteira, descricao, ativos)
                VALUES 
                    (:unit, :codigo, :nome, :ag, :cc, :cart, :desc, :ativos)
            ");

            $ativos = isset($data['ativos']) ? (int)!empty($data['ativos']) : 1;

            $stmt->execute([
                ':unit'    => $system_unit_id,
                ':codigo'  => $codigo,
                ':nome'    => $nome,
                ':ag'      => $agencia,
                ':cc'      => $conta,
                ':cart'    => $carteira,
                ':desc'    => $descricao,
                ':ativos'  => $ativos,
            ]);

            return [
                'success'  => true,
                'message'  => 'Conta bancária criada com sucesso',
                'banco_id' => $pdo->lastInsertId()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar conta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Atualiza dados de uma conta bancária.
     */
    public static function updateBanco($data)
    {
        global $pdo;

        $id = $data['id'] ?? null;
        if (!$id) {
            return ['success' => false, 'message' => 'ID é obrigatório'];
        }

        // Lista de campos permitidos para update
        $permitidos = [
            'nome',
            'agencia',  // Novo
            'conta',    // Novo
            'carteira', // Novo
            'descricao',
            'ativos',
        ];

        $sets   = [];
        $values = [':id' => $id];

        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $data)) {
                $sets[]              = "{$campo} = :{$campo}";
                $values[":{$campo}"] = $data[$campo];
            }
        }

        if (empty($sets)) {
            return ['success' => true, 'message' => 'Nenhuma alteração enviada.'];
        }

        try {
            $sql = "UPDATE financeiro_banco SET " . implode(', ', $sets) . " WHERE id = :id";

            // Opcional: Adicionar validação de system_unit_id no WHERE para segurança

            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            return [
                'success' => true,
                'message' => 'Conta bancária atualizada com sucesso'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao atualizar: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca um banco por ID e unidade.
     */
    public static function getBancoById($id, $system_unit_id)
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT *
            FROM financeiro_banco
            WHERE system_unit_id = :unit
              AND id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':unit' => $system_unit_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Remove um banco pelo ID.
     */
    public static function deleteBanco($id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("DELETE FROM financeiro_banco WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Conta excluída com sucesso'];
            }

            return ['success' => false, 'message' => 'Conta não encontrada ou já excluída'];

        } catch (Exception $e) {
            // Verifica constraint de chave estrangeira (se já tem lançamentos nesta conta)
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                return [
                    'success' => false,
                    'message' => 'Não é possível excluir: existem lançamentos vinculados a esta conta. Inative-a em vez de excluir.'
                ];
            }

            return ['success' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()];
        }
    }

    /**
     * Lista bancos de uma unidade.
     */
    public static function listBancos($system_unit_id, $apenasAtivos = false)
    {
        global $pdo;

        // Faz o JOIN com a tabela do Open Finance para pegar o código real do banco conectado
        $sql = "
        SELECT 
            fb.*, 
            pa.bank_code AS pluggy_bank_code 
        FROM financeiro_banco fb
        LEFT JOIN pluggy_accounts pa ON pa.id = fb.pluggy_account_id
        WHERE fb.system_unit_id = :unit
    ";

        // Adiciona fb. antes das colunas para evitar ambiguidade no banco de dados
        if ($apenasAtivos) {
            $sql .= " AND fb.ativos = 1";
        }

        $sql .= " ORDER BY fb.nome ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':unit', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function linkOpenFinanceAccount(array $data): array
    {
        global $pdo;

        // 1. Coleta e validação dos dados enviados no payload
        $financeiro_banco_id = isset($data['financeiro_banco_id']) ? (int)$data['financeiro_banco_id'] : 0;
        $pluggy_account_id   = isset($data['pluggy_account_id']) ? (int)$data['pluggy_account_id'] : 0;
        $system_unit_id      = isset($data['system_unit_id']) ? (int)$data['system_unit_id'] : 0;

        if (!$financeiro_banco_id || !$pluggy_account_id || !$system_unit_id) {
            return [
                'success' => false,
                'message' => 'Parâmetros inválidos ou incompletos.'
            ];
        }

        try {
            // Inicia a transação para garantir que ambas as tabelas sejam atualizadas juntas
            $pdo->beginTransaction();

            // 2. Busca os dados reais da conta importada do Open Finance
            $stmtOF = $pdo->prepare("
                SELECT agency, account_number, account_number_digit 
                FROM pluggy_accounts 
                WHERE id = ? AND system_unit_id = ?
            ");
            $stmtOF->execute([$pluggy_account_id, $system_unit_id]);
            $contaOF = $stmtOF->fetch(PDO::FETCH_ASSOC);

            if (!$contaOF) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Conta do Open Finance não encontrada na base.'
                ];
            }

            // Formata a conta unindo o número e o dígito (caso exista)
            $agencia = $contaOF['agency'];
            $contaCorrente = $contaOF['account_number'];
            if (!empty($contaOF['account_number_digit'])) {
                $contaCorrente .= '-' . $contaOF['account_number_digit'];
            }

            // 3. Atualiza a tabela do Financeiro (Vínculo + Sobrescrita de Ag/Conta)
            $stmtBanco = $pdo->prepare("
                UPDATE financeiro_banco 
                SET pluggy_account_id = ?, 
                    agencia = ?, 
                    conta = ? 
                WHERE id = ? AND system_unit_id = ?
            ");
            $stmtBanco->execute([
                $pluggy_account_id,
                $agencia,
                $contaCorrente,
                $financeiro_banco_id,
                $system_unit_id
            ]);

            // 4. Atualiza a tabela do Pluggy (Para manter a relação bilateral que criamos)
            $stmtPluggy = $pdo->prepare("
                UPDATE pluggy_accounts 
                SET financeiro_banco_id = ? 
                WHERE id = ? AND system_unit_id = ?
            ");
            $stmtPluggy->execute([
                $financeiro_banco_id,
                $pluggy_account_id,
                $system_unit_id
            ]);

            // Salva as alterações definitivamente no banco de dados
            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Contas vinculadas com sucesso e dados atualizados!'
            ];

        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Verifica o erro de UNIQUE constraint
            if ($e->getCode() == 23000) {
                return [
                    'success' => false,
                    'message' => 'Esta conta já possui um vínculo com outro banco ou registro.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Erro interno ao tentar vincular: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Erro genérico ao tentar vincular: ' . $e->getMessage()
            ];
        }
    }
}