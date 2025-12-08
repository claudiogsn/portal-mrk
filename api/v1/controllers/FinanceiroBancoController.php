<?php

require_once __DIR__ . '/../database/db.php';

class FinanceiroBancoController
{
    /**
     * Cria um banco/forma de pagamento para uma unidade.
     * Se já existir (system_unit_id + codigo), retorna o ID existente.
     */
    public static function createBanco($data)
    {
        global $pdo;

        $system_unit_id = $data['system_unit_id'] ?? null;
        $codigo         = $data['codigo'] ?? null;

        if (!$system_unit_id) {
            return [
                'success' => false,
                'message' => 'system_unit_id é obrigatório'
            ];
        }

        if ($codigo === null || $codigo === '') {
            return [
                'success' => false,
                'message' => 'Código do banco/forma de pagamento é obrigatório'
            ];
        }

        // Verifica se já existe (chave lógica: unidade + codigo)
        $stmt = $pdo->prepare("
            SELECT id 
            FROM financeiro_banco 
            WHERE system_unit_id = :system_unit_id
              AND codigo = :codigo
            LIMIT 1
        ");
        $stmt->execute([
            ':system_unit_id' => $system_unit_id,
            ':codigo' => $codigo,
        ]);
        $banco = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($banco) {
            return [
                'success'    => true,
                'message'    => 'Banco já existe para esta unidade',
                'banco_id'   => $banco['id']
            ];
        }

        // Cria novo banco
        $stmt = $pdo->prepare("
            INSERT INTO financeiro_banco 
                (system_unit_id, codigo, nome, descricao, ativos)
            VALUES 
                (:system_unit_id, :codigo, :nome, :descricao, :ativos)
        ");

        $ativos = isset($data['ativos']) ? (int)!empty($data['ativos']) : 1;

        $stmt->execute([
            ':system_unit_id' => $system_unit_id,
            ':codigo'         => $codigo,
            ':nome'           => $data['nome'] ?? '',
            ':descricao'      => $data['descricao'] ?? null,
            ':ativos'         => $ativos,
        ]);

        if ($stmt->rowCount() > 0) {
            return [
                'success'  => true,
                'message'  => 'Banco criado com sucesso',
                'banco_id' => $pdo->lastInsertId()
            ];
        }

        return [
            'success' => false,
            'message' => 'Falha ao criar banco'
        ];
    }

    /**
     * Atualiza dados de um banco.
     * Não permite trocar system_unit_id.
     */
    public static function updateBanco($data)
    {
        global $pdo;

        $id = $data['id'] ?? null;
        if (!$id) {
            return [
                'success' => false,
                'message' => 'ID do banco é obrigatório'
            ];
        }

        // Confirma existência
        $stmtCheck = $pdo->prepare("
            SELECT id, system_unit_id, codigo 
            FROM financeiro_banco 
            WHERE id = :id
            LIMIT 1
        ");
        $stmtCheck->execute([':id' => $id]);
        $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$existente) {
            return [
                'success' => false,
                'message' => 'Banco não encontrado'
            ];
        }

        // Campos que NÃO podem ser alterados diretamente
        unset($data['system_unit_id'], $data['id']);

        // Opcional: se quiser travar por unidade no WHERE
        $system_unit_id_where = $data['system_unit_id_where'] ?? null;

        // Lista de campos permitidos para update
        $permitidos = [
            'codigo',
            'nome',
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
            return [
                'success' => false,
                'message' => 'Nenhum campo válido para atualizar'
            ];
        }

        $sql = "UPDATE financeiro_banco SET " . implode(', ', $sets) . " WHERE id = :id";

        // Se quiser garantir que só atualiza dentro da unidade:
        // if ($system_unit_id_where) {
        //     $sql .= " AND system_unit_id = :system_unit_id";
        //     $values[':system_unit_id'] = $system_unit_id_where;
        // }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Banco atualizado com sucesso'
            ];
        }

        return [
            'success' => true,
            'message' => 'Nenhuma alteração aplicada (dados iguais)'
        ];
    }

    /**
     * Busca um banco por ID e unidade.
     */
    public static function getBancoById($id, $system_unit_id)
    {
        global $pdo;

        $sql = "
            SELECT *
            FROM financeiro_banco
            WHERE system_unit_id = :system_unit_id
              AND id = :id
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->bindValue(':system_unit_id', (int)$system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Remove um banco pelo ID.
     * (Se tiver relação com lançamentos, talvez depois você troque para "inativar" em vez de deletar.)
     */
    public static function deleteBanco($id)
    {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM financeiro_banco WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Banco excluído com sucesso'
            ];
        }

        return [
            'success' => false,
            'message' => 'Falha ao excluir banco'
        ];
    }

    /**
     * Lista bancos de uma unidade.
     * Se $apenasAtivos = true, retorna só onde ativos = 1.
     */
    public static function listBancos($system_unit_id, $apenasAtivos = false)
    {
        global $pdo;

        $sql = "SELECT * 
                FROM financeiro_banco 
                WHERE system_unit_id = :system_unit_id";

        if ($apenasAtivos) {
            $sql .= " AND ativos = 1";
        }

        $sql .= " ORDER BY codigo ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Importa/cadastra os bancos padrão (formas de pagamento) para a unidade.
     * Usa INSERT ... ON DUPLICATE KEY baseado em (system_unit_id, codigo).
     */
    public static function importarBancosPadrao($system_unit_id)
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

        // Formas de pagamento padrão (bancos) - baseado no array que você passou
        $formasPagamento = [
            ['codigo' => 1, 'descricao' => 'dinheiro',      'nome' => 'Dinheiro'],
            ['codigo' => 2, 'descricao' => 'dda',           'nome' => 'DDA'],
            ['codigo' => 3, 'descricao' => 'pix',           'nome' => 'PIX'],
            ['codigo' => 4, 'descricao' => 'debito',        'nome' => 'Cartão de Débito'],
            ['codigo' => 5, 'descricao' => 'credito',       'nome' => 'Cartão de Crédito'],
            ['codigo' => 6, 'descricao' => 'boleto',        'nome' => 'Boleto'],
            ['codigo' => 7, 'descricao' => 'transferencia', 'nome' => 'Transferência'],
            ['codigo' => 8, 'descricao' => 'cheque',        'nome' => 'Cheque'],
            ['codigo' => 9, 'descricao' => 'deposito',      'nome' => 'Depósito'],
        ];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO financeiro_banco (
                    system_unit_id,
                    codigo,
                    nome,
                    descricao,
                    ativos
                ) VALUES (
                    :system_unit_id,
                    :codigo,
                    :nome,
                    :descricao,
                    :ativos
                )
                ON DUPLICATE KEY UPDATE
                    nome      = VALUES(nome),
                    descricao = VALUES(descricao),
                    ativos    = VALUES(ativos)
            ");

            foreach ($formasPagamento as $fp) {
                $stmt->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':codigo'         => $fp['codigo'],
                    ':nome'           => $fp['nome'],
                    ':descricao'      => $fp['descricao'],
                    ':ativos'         => 1,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Bancos padrão importados/cadastrados com sucesso'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao importar bancos padrão: ' . $e->getMessage()
            ];
        }
    }
}
