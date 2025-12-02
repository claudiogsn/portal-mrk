<?php

require_once __DIR__ . '/../database/db.php';

class SystemUnitController
{
    public static function salvarSystemUnit($data)
    {
        global $pdo;

        // Regra de negócio: name é obrigatório
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'O campo "name" é obrigatório'];
        }

        // Descobre os campos reais da tabela system_unit
        // (cache simples em memória para não ficar dando SHOW COLUMNS toda hora)
        static $systemUnitColumns = null;
        if ($systemUnitColumns === null) {
            $stmt = $pdo->query("SHOW COLUMNS FROM system_unit");
            $systemUnitColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Garante que status tenha um default em INSERT, se não vier nada
        if (!isset($data['status'])) {
            $data['status'] = 1;
        }

        // Filtra apenas campos que existem na tabela
        $payloadFields = array_intersect(array_keys($data), $systemUnitColumns);

        // Nenhum campo além de name/status veio? Ainda assim deixamos seguir,
        // mas em prática sempre terá pelo menos name.
        if (empty($payloadFields)) {
            return ['success' => false, 'message' => 'Nenhum campo válido para salvar.'];
        }

        // UPDATE (se veio id)
        if (!empty($data['id'])) {
            $id = (int)$data['id'];

            $setParts = [];
            $params   = [':id' => $id];

            foreach ($payloadFields as $field) {
                if ($field === 'id') {
                    continue; // não atualiza o PK
                }
                $setParts[]          = "$field = :$field";
                $params[":$field"]   = $data[$field];
            }

            if (empty($setParts)) {
                return ['success' => false, 'message' => 'Nenhum campo para atualizar.'];
            }

            $sql  = "UPDATE system_unit SET " . implode(', ', $setParts) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return [
                'success' => true,
                'message' => 'Unidade atualizada com sucesso',
                'id'      => $id,
            ];
        }

        // INSERT (não veio id)
        // Continua usando MAX(id)+1 como você já faz
        $stmt  = $pdo->query("SELECT MAX(id) AS max_id FROM system_unit");
        $maxId = (int)$stmt->fetchColumn();
        $newId = $maxId + 1;

        $data['id'] = $newId;

        // Recalcula campos, agora incluindo id
        $payloadFields = array_intersect(array_keys($data), $systemUnitColumns);

        $columns      = [];
        $placeholders = [];
        $params       = [];

        foreach ($payloadFields as $field) {
            $columns[]            = $field;
            $placeholders[]       = ":$field";
            $params[":$field"]    = $data[$field];
        }

        $sql  = "INSERT INTO system_unit (" . implode(', ', $columns) . ")
             VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'success' => true,
            'message' => 'Unidade criada com sucesso',
            'id'      => $newId,
        ];
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

    public static function getConfigGroupByUnitId(int $system_unit_id): ?array
    {
        global $pdo;

        try {
            // Consulta o grupo mais antigo associado à unidade
            $stmt = $pdo->prepare("
            SELECT ge.*
            FROM grupo_estabelecimento_rel ger
            INNER JOIN grupo_estabelecimento ge ON ge.id = ger.grupo_id
            WHERE ger.system_unit_id = :system_unit_id
            ORDER BY ge.id ASC
            LIMIT 1
        ");
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();

            $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

            return $grupo ?: null;
        } catch (Exception $e) {
            return null;
        }
    }


    public static function listSystemUnitsSameGroup($system_unit_id, $incluirAtual = true)
    {
        global $pdo;

        try {
            // Garantir inteiro
            $system_unit_id = (int)$system_unit_id;

            // Busca todas as unidades que compartilham qualquer grupo com a unidade informada
            // Estratégia: auto-join na tabela de relação por grupo_id
            $sql = "
            SELECT DISTINCT su.*
            FROM grupo_estabelecimento_rel ger_cur
            INNER JOIN grupo_estabelecimento_rel ger
                ON ger.grupo_id = ger_cur.grupo_id
            INNER JOIN system_unit su
                ON su.id = ger.system_unit_id
            WHERE ger_cur.system_unit_id = :unit_id
        ";

            if (!$incluirAtual) {
                $sql .= " AND su.id <> :unit_id";
            }

            $sql .= " ORDER BY su.id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();

            $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'unidades' => $unidades];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar unidades do mesmo grupo: ' . $e->getMessage()];
        }
    }

    public static function listSystemUnitsByGrupo($grupo_id, $incluirAtivasSomente = false)
    {
        global $pdo;

        try {
            $grupo_id = (int)$grupo_id;

            $sql = "
            SELECT su.*
            FROM grupo_estabelecimento_rel ger
            INNER JOIN system_unit su ON su.id = ger.system_unit_id
            WHERE ger.grupo_id = :grupo_id
            ORDER BY su.id ASC
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':grupo_id', $grupo_id, PDO::PARAM_INT);
            $stmt->execute();

            $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'unidades' => $unidades];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar unidades por grupo: ' . $e->getMessage()];
        }
    }



}
