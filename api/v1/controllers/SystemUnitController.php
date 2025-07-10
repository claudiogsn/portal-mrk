<?php

require_once __DIR__ . '/../database/db.php';

class SystemUnitController
{
    public static function salvarSystemUnit($data)
    {
        global $pdo;

        $campos = [
            'id', 'name', 'custom_code', 'intg_financeiro',
            'token_zig', 'rede_zig',
            'zig_integration_faturamento', 'zig_integration_estoque',
            'menew_integration_estoque', 'menew_integration_faturamento',
            'status'
        ];

        $params = [];
        foreach ($campos as $campo) {
            $params[$campo] = $data[$campo] ?? null;
        }

        if (empty($params['name'])) {
            return ['success' => false, 'message' => 'O campo "name" é obrigatório'];
        }

        // Se ID estiver presente, faz UPDATE
        if (!empty($params['id'])) {
            $sql = "
                UPDATE system_unit SET
                    name = :name,
                    custom_code = :custom_code,
                    intg_financeiro = :intg_financeiro,
                    token_zig = :token_zig,
                    rede_zig = :rede_zig,
                    zig_integration_faturamento = :zig_integration_faturamento,
                    zig_integration_estoque = :zig_integration_estoque,
                    menew_integration_estoque = :menew_integration_estoque,
                    menew_integration_faturamento = :menew_integration_faturamento,
                    status = :status
                WHERE id = :id
            ";
        } else {
            // INSERT
            $sql = "
                INSERT INTO system_unit (
                    id, name, custom_code, intg_financeiro,
                    token_zig, rede_zig,
                    zig_integration_faturamento, zig_integration_estoque,
                    menew_integration_estoque, menew_integration_faturamento,
                    status
                ) VALUES (
                    :id, :name, :custom_code, :intg_financeiro,
                    :token_zig, :rede_zig,
                    :zig_integration_faturamento, :zig_integration_estoque,
                    :menew_integration_estoque, :menew_integration_faturamento,
                    :status
                )
            ";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $params['id'],
            ':name' => $params['name'],
            ':custom_code' => $params['custom_code'],
            ':intg_financeiro' => $params['intg_financeiro'],
            ':token_zig' => $params['token_zig'],
            ':rede_zig' => $params['rede_zig'],
            ':zig_integration_faturamento' => $params['zig_integration_faturamento'],
            ':zig_integration_estoque' => $params['zig_integration_estoque'],
            ':menew_integration_estoque' => $params['menew_integration_estoque'],
            ':menew_integration_faturamento' => $params['menew_integration_faturamento'],
            ':status' => $params['status'] ?? 1,
        ]);

        return ['success' => true, 'message' => 'Unidade salva com sucesso'];
    }

    public static function toggleStatus($id)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT status FROM system_unit WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $unit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$unit) {
            return ['success' => false, 'message' => 'Unidade não encontrada'];
        }

        $novoStatus = $unit['status'] ? 0 : 1;

        $stmt = $pdo->prepare("UPDATE system_unit SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $novoStatus, ':id' => $id]);

        return ['success' => true, 'message' => 'Status atualizado com sucesso', 'status' => $novoStatus];
    }

    public static function getSystemUnitById($id)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM system_unit WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function listSystemUnits()
    {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM system_unit ORDER BY id ASC");
        return ['success' => true, 'unidades' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }
}
