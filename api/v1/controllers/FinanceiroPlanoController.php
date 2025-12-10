<?php

require_once __DIR__ . '/../database/db.php';

class FinanceiroPlanoController
{
    /**
     * Cria um plano de contas para uma unidade específica.
     * Espera em $data: system_unit_id, codigo, descricao
     */
    public static function createPlano($data): array
    {
        global $pdo;

        // Validação básica
        if (empty($data['system_unit_id']) || empty($data['codigo']) || empty($data['descricao'])) {
            return [
                'success' => false,
                'message' => 'Parâmetros obrigatórios: system_unit_id, codigo, descricao'
            ];
        }

        $system_unit_id = (int)$data['system_unit_id'];
        $codigo         = trim($data['codigo']);
        $descricao      = trim($data['descricao']);

        try {
            // Verifica se já existe plano com esse código para a unidade
            $check = $pdo->prepare("
                SELECT id 
                FROM financeiro_plano
                WHERE system_unit_id = :unit
                  AND codigo = :codigo
                LIMIT 1
            ");
            $check->execute([
                ':unit'   => $system_unit_id,
                ':codigo' => $codigo
            ]);

            if ($check->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Já existe um plano com este código para esta unidade.'
                ];
            }

            // Insere novo plano (ativo = 1 por padrão no banco)
            $stmt = $pdo->prepare("
                INSERT INTO financeiro_plano (system_unit_id, codigo, descricao)
                VALUES (:unit, :codigo, :descricao)
            ");

            $stmt->execute([
                ':unit'      => $system_unit_id,
                ':codigo'    => $codigo,
                ':descricao' => $descricao
            ]);

            if ($stmt->rowCount() > 0) {
                return [
                    'success'  => true,
                    'message'  => 'Plano criado com sucesso',
                    'plano_id' => $pdo->lastInsertId()
                ];
            }

            return [
                'success' => false,
                'message' => 'Falha ao criar plano'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar plano: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Atualiza somente a descrição de um plano (não altera código nem unidade).
     */
    public static function updatePlano($id, $data): array
    {
        global $pdo;

        if (!isset($data['descricao']) || trim($data['descricao']) === '') {
            return [
                'success' => false,
                'message' => 'Campo descricao é obrigatório para atualização.'
            ];
        }

        $descricao = trim($data['descricao']);

        try {
            $stmt = $pdo->prepare("
                UPDATE financeiro_plano
                SET descricao = :descricao
                WHERE id = :id
            ");

            $stmt->execute([
                ':descricao' => $descricao,
                ':id'        => (int)$id
            ]);

            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Plano atualizado com sucesso'
                ];
            }

            // Nenhuma linha alterada — pode ser porque a descrição é igual à anterior
            return [
                'success' => true,
                'message' => 'Nenhuma alteração realizada (descrição igual à atual).'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao atualizar plano: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Inativa o plano (soft delete). Seta ativo = 0.
     */
    public static function inativarPlano($system_unit_id,$codigo): array
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                UPDATE financeiro_plano
                SET ativo = 0
                WHERE 
                    system_unit_id = :unit
                    AND codigo = :codigo
            ");

            $stmt->execute([
                ':unit'   => (int)$system_unit_id,
                ':codigo' => $codigo
            ]);

            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Plano inativado com sucesso.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Nenhuma alteração realizada (plano já pode estar inativo ou não existe).'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao inativar plano: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca um plano pelo system_unit_id + codigo.
     */
    public static function getPlanoByCodigo($system_unit_id, $codigo)
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT *
            FROM financeiro_plano
            WHERE system_unit_id = :unit
              AND codigo = :codigo
            LIMIT 1
        ");

        $stmt->execute([
            ':unit'   => (int)$system_unit_id,
            ':codigo' => $codigo
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mantido só por compatibilidade, se ainda existir código chamando por ID.
     */
    public static function getPlanoById($id)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM financeiro_plano WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Exclui plano definitivamente, mas só se nunca foi usado em nenhuma conta.
     */
    public static function deletePlano($system_unit_id, $codigo): array
    {
        global $pdo;

        try {
            $system_unit_id = (int)$system_unit_id;
            $codigo = trim($codigo);

            // 1) Busca dados do plano pelo system_unit_id + codigo
            $stmtPlano = $pdo->prepare("
            SELECT id, system_unit_id, codigo
            FROM financeiro_plano
            WHERE system_unit_id = :unit
              AND codigo = :codigo
            LIMIT 1
        ");
            $stmtPlano->execute([
                ':unit'   => $system_unit_id,
                ':codigo' => $codigo
            ]);
            $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);

            if (!$plano) {
                return [
                    'success' => false,
                    'message' => 'Plano não encontrado para esta unidade e código.'
                ];
            }

            // 2) Verifica se existe alguma conta usando esse plano
            // Ajuste o nome da tabela/campo se forem diferentes
            $stmtCheck = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM financeiro_conta
            WHERE system_unit_id = :unit
              AND plano_contas = :codigo
        ");
            $stmtCheck->execute([
                ':unit'   => $system_unit_id,
                ':codigo' => $codigo
            ]);
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            $totalUsos = (int)($row['total'] ?? 0);

            if ($totalUsos > 0) {
                return [
                    'success' => false,
                    'message' => 'Este plano já foi utilizado em lançamentos. Não é possível excluir. Inative o plano em vez disso.'
                ];
            }

            // 3) Se não tem uso, pode excluir
            $stmtDel = $pdo->prepare("
            DELETE FROM financeiro_plano
            WHERE system_unit_id = :unit
              AND codigo = :codigo
            LIMIT 1
        ");

            $stmtDel->execute([
                ':unit'   => $system_unit_id,
                ':codigo' => $codigo
            ]);

            if ($stmtDel->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Plano excluído com sucesso.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Falha ao excluir plano.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao excluir plano: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista os planos de uma unidade, apenas ativos, ordenados por código ASC.
     */
    public static function listPlanos($system_unit_id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT *
            FROM financeiro_plano
            WHERE system_unit_id = :unit
              AND ativo = 1
            ORDER BY codigo ASC
        ");

        $stmt->execute([':unit' => (int)$system_unit_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function importarPlanosApi($system_unit_id): array
    {
        global $pdo;

        try {
            // Obtém o custom_code a partir do system_unit_id
            $stmt = $pdo->prepare("SELECT custom_code AS estabelecimento FROM system_unit WHERE id = :id");
            $stmt->bindParam(':id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                throw new Exception("System Unit ID inválido ou não encontrado.");
            }

            $estabelecimento = $result['estabelecimento'];

            // Chama o método da API para buscar os planos
            $planos = FinanceiroApiMenewController::fetchFinanceiroPlano($estabelecimento);

            if (!$planos['success']) {
                throw new Exception("Erro ao buscar planos da API: " . $planos['message']);
            }

            foreach ($planos['planos'] as $plano) {
                $stmt = $pdo->prepare("
                    INSERT INTO financeiro_plano (system_unit_id, codigo, descricao) 
                    VALUES (:unit, :codigo, :descricao)
                    ON DUPLICATE KEY UPDATE 
                        descricao = VALUES(descricao),
                        ativo     = 1
                ");

                // Exemplo: prefixo 0 no código vindo da API (mantido do seu código original)
                $plano_contas = '0' . $plano['codigo'];

                $stmt->execute([
                    ':unit'      => $system_unit_id,
                    ':codigo'    => $plano_contas,
                    ':descricao' => $plano['descricao']
                ]);
            }

            return ["success" => true, "message" => "Planos importados com sucesso"];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

}
