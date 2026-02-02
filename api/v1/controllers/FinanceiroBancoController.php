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

        $sql = "SELECT * FROM financeiro_banco 
                WHERE system_unit_id = :unit";

        if ($apenasAtivos) {
            $sql .= " AND ativos = 1";
        }

        $sql .= " ORDER BY nome ASC"; // Ordenar por nome costuma ser melhor para bancos

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':unit', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}