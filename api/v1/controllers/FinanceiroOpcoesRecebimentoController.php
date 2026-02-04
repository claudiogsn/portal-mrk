<?php


require_once __DIR__ . '/../database/db.php';

class FinanceiroOpcoesRecebimentoController
{
    /**
     * Cria uma nova opção de recebimento.
     */
    public static function createOpcaoRecebimento($data)
    {
        global $pdo;

        $system_unit_id = $data['system_unit_id'] ?? null;
        $codigo = $data['codigo'] ?? null;
        $nome = $data['nome'] ?? '';
        $banco_id = !empty($data['banco_id']) ? (int)$data['banco_id'] : null;
        $plano_contas_id = !empty($data['plano_contas_id']) ? (int)$data['plano_contas_id'] : null;
        $taxa = isset($data['taxa']) ? (float)$data['taxa'] : 0.00;
        $prazo = isset($data['prazo']) ? (int)$data['prazo'] : 0;

        if (!$system_unit_id || !$codigo) {
            return [
                'success' => false,
                'message' => 'Unidade, Código são obrigatórios.'
            ];
        }

        try {
            // Verifica duplicidade (Unidade + Código) devido à nossa UNIQUE KEY
            $stmtCheck = $pdo->prepare("
                SELECT id FROM financeiro_opcoes_recebimento 
                WHERE system_unit_id = :unit AND codigo = :cod LIMIT 1
            ");
            $stmtCheck->execute([':unit' => $system_unit_id, ':cod' => $codigo]);

            if ($stmtCheck->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Já existe uma opção de recebimento com este código nesta unidade.'
                ];
            }

            $sql = "INSERT INTO financeiro_opcoes_recebimento 
                    (system_unit_id, codigo, nome, banco_id, plano_contas_id, taxa, prazo, created_at, updated_at)
                    VALUES 
                    (:unit, :cod, :nome, :banco, :plano, :taxa, :prazo, NOW(), NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':unit' => $system_unit_id,
                ':cod' => $codigo,
                ':nome' => $nome,
                ':banco' => $banco_id,
                ':plano' => $plano_contas_id,
                ':taxa' => $taxa,
                ':prazo' => $prazo
            ]);

            return [
                'success' => true,
                'message' => 'Opção de recebimento criada com sucesso.',
                'id' => $pdo->lastInsertId()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Atualiza dados de uma opção de recebimento.
     */
    public static function updateOpcaoRecebimento($data)
    {
        global $pdo;

        $id = $data['id'] ?? null;
        if (!$id) {
            return ['success' => false, 'message' => 'ID é obrigatório.'];
        }

        try {
            $campos = [];
            $params = [':id' => $id];

            // Mapeamento de campos permitidos para atualização
            $fieldsMap = [
                'nome' => ':nome',
                'codigo' => ':codigo',
                'banco_id' => ':banco',
                'plano_contas_id' => ':plano',
                'taxa' => ':taxa',
                'prazo' => ':prazo'
            ];

            foreach ($fieldsMap as $key => $param) {
                if (isset($data[$key])) {
                    $campos[] = "$key = $param";
                    $params[$param] = $data[$key];
                }
            }

            if (empty($campos)) {
                return ['success' => true, 'message' => 'Nada para atualizar.'];
            }

            $campos[] = "updated_at = NOW()";
            $sql = "UPDATE financeiro_opcoes_recebimento SET " . implode(', ', $campos) . " WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return ['success' => true, 'message' => 'Opção de recebimento atualizada.'];

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return ['success' => false, 'message' => 'Este código já existe nesta unidade.'];
            }
            return ['success' => false, 'message' => 'Erro ao atualizar: ' . $e->getMessage()];
        }
    }

    /**
     * Lista opções de recebimento de uma unidade.
     */
    public static function listOpcoesRecebimento($system_unit_id)
    {
        global $pdo;

        // Join ajustado para a tabela 'financeiro_plano'
        $sql = "SELECT 
                op.*, 
                b.nome as nome_banco,
                fp.descricao as nome_plano_contas
            FROM financeiro_opcoes_recebimento op
            LEFT JOIN financeiro_banco b ON op.banco_id = b.id
            LEFT JOIN financeiro_plano fp ON op.plano_contas_id = fp.codigo 
                 AND fp.system_unit_id = op.system_unit_id
            WHERE op.system_unit_id = :unit
            ORDER BY op.nome ASC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':unit' => $system_unit_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Log de erro opcional
            return [];
        }
    }
    /**
     * Exclui uma opção de recebimento.
     */
    public static function deleteOpcaoRecebimento($id)
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("DELETE FROM financeiro_opcoes_recebimento WHERE id = :id");
            $stmt->execute([':id' => $id]);

            return $stmt->rowCount() > 0
                ? ['success' => true, 'message' => 'Opção excluída.']
                : ['success' => false, 'message' => 'Não encontrado.'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    /**
     * Importa opções padrão (Ex: Cartões com taxas genéricas)
     */
    public static function importarOpcoesPadrao($system_unit_id, $plano_contas_id)
    {
        global $pdo;

        $opcoes = [
            ['codigo' => 'DEBITO', 'nome' => 'Cartão de Débito', 'taxa' => 1.50, 'prazo' => 1],
            ['codigo' => 'CREDITO', 'nome' => 'Cartão de Crédito', 'taxa' => 3.20, 'prazo' => 30],
            ['codigo' => 'PIX_REC', 'nome' => 'Recebimento via PIX', 'taxa' => 0.00, 'prazo' => 0],
        ];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO financeiro_opcoes_recebimento 
                (system_unit_id, codigo, nome, plano_contas_id, taxa, prazo, created_at, updated_at)
                VALUES (:unit, :cod, :nome, :plano, :taxa, :prazo, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                nome = VALUES(nome), taxa = VALUES(taxa), prazo = VALUES(prazo), updated_at = NOW()
            ");

            foreach ($opcoes as $opt) {
                $stmt->execute([
                    ':unit' => $system_unit_id,
                    ':cod' => $opt['codigo'],
                    ':nome' => $opt['nome'],
                    ':plano' => $plano_contas_id,
                    ':taxa' => $opt['taxa'],
                    ':prazo' => $opt['prazo']
                ]);
            }

            return ['success' => true, 'message' => 'Opções padrão importadas.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function vincularMeioPagamento($data)
    {
        global $pdo;

        $system_unit_id = $data['system_unit_id'] ?? null;
        $loja_id        = $data['loja_id'] ?? null; // Novo campo
        $codigo_opcao   = $data['codigo_opcao'] ?? null;
        $nome_opcao     = $data['nome_opcao'] ?? '';
        $codigo_meio    = $data['codigo_meio'] ?? null;
        $nome_meio      = $data['nome_meio'] ?? '';

        if (!$system_unit_id || !$codigo_opcao || !$codigo_meio) {
            return ['success' => false, 'message' => 'Dados incompletos para vinculação.'];
        }

        try {
            $sql = "INSERT INTO financeiro_opcoes_vinculo_meios 
                (system_unit_id, loja_id, codigo_opcao, nome_opcao, codigo_meio, nome_meio, created_at, updated_at)
                VALUES 
                (:unit, :loja, :cod_op, :nom_op, :cod_meio, :nom_meio, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    loja_id      = VALUES(loja_id), -- Garante que atualiza se mudar
                    codigo_opcao = VALUES(codigo_opcao),
                    nome_opcao   = VALUES(nome_opcao),
                    nome_meio    = VALUES(nome_meio),
                    updated_at   = NOW()";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':unit'     => $system_unit_id,
                ':loja'     => $loja_id,
                ':cod_op'   => $codigo_opcao,
                ':nom_op'   => $nome_opcao,
                ':cod_meio' => $codigo_meio,
                ':nom_meio' => $nome_meio
            ]);

            return ['success' => true, 'message' => 'Meio de pagamento vinculado com sucesso.'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao vincular: ' . $e->getMessage()];
        }
    }

    /**
     * Lista os meios de pagamento vinculados a uma Opção (Pai).
     */
    public static function listMeiosPorOpcao($system_unit_id, $codigo_opcao)
    {
        global $pdo;

        if (!$system_unit_id || !$codigo_opcao) {
            return ['success' => false, 'message' => 'Parâmetros inválidos.'];
        }

        try {
            $sql = "SELECT 
                    id, 
                    codigo_meio, 
                    nome_meio, 
                    loja_id,
                    created_at 
                FROM financeiro_opcoes_vinculo_meios
                WHERE system_unit_id = :unit 
                  AND codigo_opcao = :cod
                ORDER BY nome_meio ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':unit' => $system_unit_id,
                ':cod'  => $codigo_opcao
            ]);

            return [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar meios: ' . $e->getMessage()];
        }
    }
}