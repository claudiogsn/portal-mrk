<?php

require_once __DIR__ . '/../database/db.php';

/**
 * AlertController
 *
 * Gerencia os alertas do Portal MRK (tabela mrk_alerts).
 * Segue o mesmo padrão do UserController: métodos estáticos, global $pdo.
 */
class AlertController
{
    /* =========================================================
     *  LEITURA (listagens e buscas)
     * ========================================================= */

    /**
     * Lista todos os alertas ativos de uma unidade.
     * Quando passado $user_id, já retorna o flag is_read por alerta.
     *
     * @param int      $unit_id
     * @param int|null $user_id
     * @param array    $filters  ['type' => ..., 'category' => ..., 'only_unread' => bool]
     */
    public static function getActiveAlertsByUnit(int $unit_id, ?int $user_id = null, array $filters = []): array
    {
        global $pdo;

        $where  = ["a.system_unit_id = :unit_id", "a.active = 'Y'"];
        $params = [':unit_id' => $unit_id];

        if (!empty($filters['type'])) {
            $where[] = "a.type = :type";
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['category'])) {
            $where[] = "a.category = :category";
            $params[':category'] = $filters['category'];
        }

        // Campo is_read via LEFT JOIN com mrk_alert_reads
        if ($user_id) {
            $sql = "
                SELECT a.*,
                       CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read,
                       r.read_at
                FROM mrk_alerts a
                LEFT JOIN mrk_alert_reads r
                       ON r.alert_id = a.id
                      AND r.system_user_id = :user_id
                WHERE " . implode(' AND ', $where) . "
            ";
            $params[':user_id'] = $user_id;

            if (!empty($filters['only_unread'])) {
                $sql .= " AND r.id IS NULL ";
            }
        } else {
            $sql = "
                SELECT a.*, 0 AS is_read, NULL AS read_at
                FROM mrk_alerts a
                WHERE " . implode(' AND ', $where) . "
            ";
        }

        $sql .= " ORDER BY FIELD(a.type, 'danger','warning','info','success'), a.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'total'   => count($alerts),
            'alerts'  => $alerts,
        ];
    }

    /**
     * Retorna 1 alerta pelo ID.
     */
    public static function getById(int $alert_id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM mrk_alerts WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $alert_id, PDO::PARAM_INT);
        $stmt->execute();
        $alert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alert) {
            return ['success' => false, 'message' => 'Alerta não encontrado'];
        }

        return ['success' => true, 'alert' => $alert];
    }

    /**
     * Retorna só o total de alertas não lidos por usuário+unidade.
     * Útil pra badge de notificação.
     */
    public static function countUnread(int $unit_id, int $user_id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM mrk_alerts a
            LEFT JOIN mrk_alert_reads r
                   ON r.alert_id = a.id
                  AND r.system_user_id = :user_id
            WHERE a.system_unit_id = :unit_id
              AND a.active = 'Y'
              AND r.id IS NULL
        ");
        $stmt->execute([
            ':unit_id' => $unit_id,
            ':user_id' => $user_id,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'total'   => (int) ($row['total'] ?? 0),
        ];
    }

    /**
     * Resumo agrupado por tipo (quantos de cada).
     * Útil pra dashboard: "2 urgentes, 5 avisos, 3 infos".
     */
    public static function getSummaryByUnit(int $unit_id, ?int $user_id = null): array
    {
        global $pdo;

        $sql = "
            SELECT a.type, COUNT(*) AS total
            FROM mrk_alerts a
        ";

        $params = [':unit_id' => $unit_id];

        if ($user_id) {
            $sql .= "
                LEFT JOIN mrk_alert_reads r
                       ON r.alert_id = a.id
                      AND r.system_user_id = :user_id
                WHERE a.system_unit_id = :unit_id
                  AND a.active = 'Y'
                  AND r.id IS NULL
            ";
            $params[':user_id'] = $user_id;
        } else {
            $sql .= "
                WHERE a.system_unit_id = :unit_id
                  AND a.active = 'Y'
            ";
        }

        $sql .= " GROUP BY a.type";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normaliza a saída com todos os tipos (mesmo que zero)
        $summary = ['info' => 0, 'success' => 0, 'warning' => 0, 'danger' => 0];
        foreach ($rows as $r) {
            $summary[$r['type']] = (int) $r['total'];
        }
        $summary['total'] = array_sum($summary);

        return ['success' => true, 'summary' => $summary];
    }

    /**
     * Lista as categorias usadas pela unidade (pra popular filtros).
     */
    public static function getCategoriesByUnit(int $unit_id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT category, COUNT(*) AS total
            FROM mrk_alerts
            WHERE system_unit_id = :unit_id
              AND active = 'Y'
            GROUP BY category
            ORDER BY category
        ");
        $stmt->execute([':unit_id' => $unit_id]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'categories' => $categories];
    }

    /* =========================================================
     *  ESCRITA (CRUD)
     * ========================================================= */

    /**
     * Cria um novo alerta.
     *
     * @param array $data  [system_unit_id, title, message, type, category, created_by]
     */
    public static function create(array $data): array
    {
        global $pdo;

        if (empty($data['system_unit_id']) || empty($data['title']) || empty($data['message'])) {
            return ['success' => false, 'message' => 'system_unit_id, title e message são obrigatórios'];
        }

        $allowedTypes      = ['info', 'success', 'warning', 'danger'];
        $type              = in_array($data['type'] ?? 'info', $allowedTypes) ? $data['type'] : 'info';
        $category          = $data['category']   ?? 'geral';
        $created_by        = $data['created_by'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO mrk_alerts
                (system_unit_id, title, message, type, category, active, created_by)
            VALUES
                (:unit_id, :title, :message, :type, :category, 'Y', :created_by)
        ");
        $stmt->execute([
            ':unit_id'    => (int) $data['system_unit_id'],
            ':title'      => $data['title'],
            ':message'    => $data['message'],
            ':type'       => $type,
            ':category'   => $category,
            ':created_by' => $created_by ? (int) $created_by : null,
        ]);

        return [
            'success'  => true,
            'alert_id' => (int) $pdo->lastInsertId(),
        ];
    }

    /**
     * Atualiza um alerta existente (parcialmente).
     * Passe só os campos que quer alterar.
     */
    public static function update(int $alert_id, array $data): array
    {
        global $pdo;

        $updatable = ['title', 'message', 'type', 'category', 'active'];
        $sets      = [];
        $params    = [':id' => $alert_id];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($sets)) {
            return ['success' => false, 'message' => 'Nenhum campo para atualizar'];
        }

        $sql = "UPDATE mrk_alerts SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return ['success' => true, 'affected' => $stmt->rowCount()];
    }

    /**
     * Desativa um alerta (soft delete global).
     */
    public static function deactivate(int $alert_id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("UPDATE mrk_alerts SET active = 'N' WHERE id = :id");
        $stmt->execute([':id' => $alert_id]);

        return ['success' => true, 'affected' => $stmt->rowCount()];
    }

    /**
     * Reativa um alerta.
     */
    public static function activate(int $alert_id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("UPDATE mrk_alerts SET active = 'Y' WHERE id = :id");
        $stmt->execute([':id' => $alert_id]);

        return ['success' => true, 'affected' => $stmt->rowCount()];
    }

    /**
     * Exclui de verdade (hard delete). Use com cuidado.
     * Por FK com ON DELETE CASCADE, as leituras também somem.
     */
    public static function delete(int $alert_id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM mrk_alerts WHERE id = :id");
        $stmt->execute([':id' => $alert_id]);

        return ['success' => true, 'affected' => $stmt->rowCount()];
    }

    /* =========================================================
     *  LEITURAS POR USUÁRIO
     * ========================================================= */

    /**
     * Marca um alerta como lido para um usuário.
     */
    public static function markAsRead(int $alert_id, int $user_id): array
    {
        global $pdo;

        // INSERT IGNORE evita duplicata quando o usuário clica 2x
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO mrk_alert_reads (alert_id, system_user_id)
            VALUES (:alert_id, :user_id)
        ");
        $stmt->execute([
            ':alert_id' => $alert_id,
            ':user_id'  => $user_id,
        ]);

        return ['success' => true];
    }

    /**
     * Marca todos os alertas ativos da unidade como lidos pelo usuário.
     * Útil pro botão "marcar todas como lidas".
     */
    public static function markAllAsRead(int $unit_id, int $user_id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO mrk_alert_reads (alert_id, system_user_id)
            SELECT a.id, :user_id
            FROM mrk_alerts a
            WHERE a.system_unit_id = :unit_id
              AND a.active = 'Y'
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':unit_id' => $unit_id,
        ]);

        return ['success' => true, 'affected' => $stmt->rowCount()];
    }

    /**
     * Desmarca (volta a ser "não lido").
     */
    public static function markAsUnread(int $alert_id, int $user_id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            DELETE FROM mrk_alert_reads
            WHERE alert_id = :alert_id
              AND system_user_id = :user_id
        ");
        $stmt->execute([
            ':alert_id' => $alert_id,
            ':user_id'  => $user_id,
        ]);

        return ['success' => true, 'affected' => $stmt->rowCount()];
    }

    /* =========================================================
     *  HELPERS / ESTATÍSTICAS
     * ========================================================= */

    /**
     * Lista os usuários que já leram um alerta específico.
     * Útil pra admin ver "quem já viu esse aviso".
     */
    public static function getReadersByAlert(int $alert_id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.login, r.read_at
            FROM mrk_alert_reads r
            INNER JOIN system_users u ON u.id = r.system_user_id
            WHERE r.alert_id = :alert_id
            ORDER BY r.read_at DESC
        ");
        $stmt->execute([':alert_id' => $alert_id]);
        $readers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success'  => true,
            'total'    => count($readers),
            'readers'  => $readers,
        ];
    }
    /**
     * Carrega os alertas do dashboard para um usuário.
     * Sem filtros aplicados, retornando todos os alertas da unidade paginados
     * em blocos de 4 (in-memory, para evitar novos requests do front-end).
     *
     * @param int   $user_id
     * @param array $filters  Contém a 'unit_id' atual do usuário.
     */
    public static function getAlertsForDashboard(int $user_id, array $filters = []): array
    {
        global $pdo;

        if ($user_id <= 0) {
            return ['success' => false, 'message' => 'user_id inválido'];
        }

        $unit_id_req = $filters['unit_id'] ?? 0;

        /* ------------------------------------------------------------
         * 1) Valida se o usuário tem acesso à unidade solicitada
         * ------------------------------------------------------------ */
        $stmtUnits = $pdo->prepare("
            SELECT system_unit_id
            FROM system_user_unit
            WHERE system_user_id = :user_id AND system_unit_id = :unit_id
        ");
        $stmtUnits->execute([
            ':user_id' => $user_id,
            ':unit_id' => $unit_id_req
        ]);
        $unitIds = $stmtUnits->fetchAll(PDO::FETCH_COLUMN);

        if (empty($unitIds)) {
            return [
                'success'      => true,
                'total_alerts' => 0,
                'unread'       => 0,
                'total_pages'  => 0,
                'units'        => [],
                'summary'      => ['info' => 0, 'success' => 0, 'warning' => 0, 'danger' => 0, 'total' => 0],
                'alerts'       => [], // Retorna vazio
                'message'      => 'Usuário não possui acesso a esta unidade',
            ];
        }

        $unitIds      = array_map('intval', $unitIds);
        $placeholders = implode(',', array_fill(0, count($unitIds), '?'));

        /* ------------------------------------------------------------
         * 2) Query principal (Sem filtros extras, pegando tudo que está ativo)
         * ------------------------------------------------------------ */
        $where  = ["a.system_unit_id IN ($placeholders)", "a.active = 'Y'"];
        $params = $unitIds;

        $sql = "
            SELECT
                a.id,
                a.system_unit_id,
                u.name AS unit_name,
                a.title,
                a.message,
                a.type,
                a.category,
                a.created_at,
                CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read,
                r.read_at
            FROM mrk_alerts a
            INNER JOIN system_unit u        ON u.id = a.system_unit_id
            LEFT  JOIN mrk_alert_reads r    ON r.alert_id = a.id
                                           AND r.system_user_id = ?
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                is_read ASC,
                FIELD(a.type, 'danger','warning','info','success'),
                a.created_at DESC
        ";

        // Adiciona o user_id no começo dos parâmetros para o LEFT JOIN
        array_unshift($params, $user_id);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $allAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /* ------------------------------------------------------------
         * 3) Monta summary em um único loop
         * ------------------------------------------------------------ */
        $summary = ['info' => 0, 'success' => 0, 'warning' => 0, 'danger' => 0];
        $unread  = 0;

        foreach ($allAlerts as $a) {
            if (isset($summary[$a['type']])) {
                $summary[$a['type']]++;
            }
            if ((int) $a['is_read'] === 0) {
                $unread++;
            }
        }
        $summary['total'] = array_sum($summary);

        /* ------------------------------------------------------------
         * 4) Paginação em Memória (Fatiamento)
         * ------------------------------------------------------------ */
        // Divide todos os alertas em arrays menores, contendo 4 itens cada
        $paginatedAlerts = array_chunk($allAlerts, 4);

        return [
            'success'      => true,
            'total_alerts' => count($allAlerts),           // Total bruto de alertas
            'unread'       => $unread,
            'total_pages'  => count($paginatedAlerts),     // Quantidade de páginas geradas
            'per_page'     => 4,
            'units'        => $unitIds,
            'summary'      => $summary,
            'alerts'       => $paginatedAlerts,            // Retorna a matriz já paginada
        ];
    }

    // Método para registrar os alertas vindos do worker da Zig
    public static function registerZigAlerts($data) {
        $system_unit_id = $data['system_unit_id'] ?? null;
        $alertas = $data['alertas'] ?? [];

        if (!$system_unit_id || empty($alertas)) {
            return ['status' => 'success', 'message' => 'Sem alertas para processar.'];
        }

        foreach ($alertas as $item) {
            $sku = $item['sku'];
            $nome = $item['nome'];

            self::create([
                'system_unit_id' => $system_unit_id,
                'title'          => "Produto não mapeado na Zig: {$sku}",
                'message'        => "O produto '{$nome}' (SKU Zig: {$sku}) teve movimentação de saída, mas não possui o 'sku_zig' vinculado no cadastro do Portal MRK.",
                'type'           => 'warning',
                'category'       => 'integracao_pdv'
            ]);
        }

        return [
            'status' => 'success',
            'message' => count($alertas) . ' alertas registrados com sucesso.'
        ];
    }
}