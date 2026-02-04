<?php

class CategoriasController
{

    public static function listar(int $systemUnitId): array
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT id, codigo, nome, created_at 
                FROM categorias 
                WHERE system_unit_id = :unit_id 
                ORDER BY codigo ASC
            ");
            $stmt->execute([':unit_id' => $systemUnitId]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'data' => $dados];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar: ' . $e->getMessage()];
        }
    }

    public static function getById(int $id): array
    {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $dado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($dado) {
                return ['success' => true, 'data' => $dado];
            } else {
                return ['success' => false, 'message' => 'Categoria não encontrada.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar: ' . $e->getMessage()];
        }
    }

    public static function salvar(array $data): array
    {
        global $pdo;

        $id           = $data['id'] ?? null;
        $systemUnitId = $data['system_unit_id'] ?? null;
        $codigo       = $data['codigo'] ?? null;
        $nome         = trim($data['nome'] ?? '');

        if (!$systemUnitId || !$codigo || empty($nome)) {
            return ['success' => false, 'message' => 'Preencha Código, Nome e Unidade.'];
        }

        try {

            $sqlCheck = "SELECT id FROM categorias WHERE system_unit_id = :unit_id AND codigo = :codigo";
            $paramsCheck = [':unit_id' => $systemUnitId, ':codigo' => $codigo];

            if ($id) {
                $sqlCheck .= " AND id != :id";
                $paramsCheck[':id'] = $id;
            }

            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute($paramsCheck);
            if ($stmtCheck->fetch()) {
                return ['success' => false, 'message' => "Já existe uma categoria com o código {$codigo} nesta unidade."];
            }

            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE categorias 
                    SET codigo = :codigo, nome = :nome, updated_at = NOW() 
                    WHERE id = :id AND system_unit_id = :unit_id
                ");
                $stmt->execute([
                    ':codigo'  => $codigo,
                    ':nome'    => $nome,
                    ':id'      => $id,
                    ':unit_id' => $systemUnitId
                ]);
                $msg = 'Categoria atualizada com sucesso!';

            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO categorias (system_unit_id, codigo, nome) 
                    VALUES (:unit_id, :codigo, :nome)
                ");
                $stmt->execute([
                    ':unit_id' => $systemUnitId,
                    ':codigo'  => $codigo,
                    ':nome'    => $nome
                ]);
                $msg = 'Categoria cadastrada com sucesso!';
            }

            return ['success' => true, 'message' => $msg];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()];
        }
    }

    public static function excluir(int $id): array
    {
        global $pdo;

        try {

            $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Categoria excluída com sucesso.'];
            } else {
                return ['success' => false, 'message' => 'Categoria não encontrada ou já excluída.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()];
        }
    }

    /**
     * Retorna o próximo código disponível (MAX + 1)
     */
    public static function proximoCodigo(int $systemUnitId): array
    {
        global $pdo;

        try {
            // Busca o maior código numérico existente na unidade
            $stmt = $pdo->prepare("
                SELECT MAX(codigo) as max_code 
                FROM categorias 
                WHERE system_unit_id = :unit_id
            ");
            $stmt->execute([':unit_id' => $systemUnitId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Se tiver registro, soma 1. Se não tiver nada, começa do 1.
            $proximo = ($row && $row['max_code']) ? ((int)$row['max_code'] + 1) : 1;

            return ['success' => true, 'next_code' => $proximo];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao gerar código: ' . $e->getMessage()];
        }
    }
}