<?php

ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../database/db.php';

class UserController {

    public static function getUserDetails($user)
    {
        global $pdo;

        // Verifica se é ID numérico
        $isId = is_numeric($user);

        if ($isId) {
            $stmt = $pdo->prepare("
            SELECT id, name, login, function_name, system_unit_id
            FROM system_users
            WHERE id = :user AND active = 'Y'
            LIMIT 1
        ");
            $stmt->bindParam(':user', $user, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare("
            SELECT id, name, login, function_name, system_unit_id
            FROM system_users
            WHERE login = :user AND active = 'Y'
            LIMIT 1
        ");
            $stmt->bindParam(':user', $user, PDO::PARAM_STR);
        }

        $stmt->execute();
        $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userDetails) {
            return ['success' => false, 'message' => 'Usuário não encontrado'];
        }

        // Busca o nome da unidade
        if (!empty($userDetails['system_unit_id'])) {
            $stmtUnit = $pdo->prepare("SELECT name FROM system_unit WHERE id = :unit_id LIMIT 1");
            $stmtUnit->bindParam(':unit_id', $userDetails['system_unit_id'], PDO::PARAM_INT);
            $stmtUnit->execute();
            $unit = $stmtUnit->fetch(PDO::FETCH_ASSOC);

            $userDetails['unit_name'] = $unit ? $unit['name'] : null;
        } else {
            $userDetails['unit_name'] = null;
        }

        // Busca o último acesso sem logout (token da sessão)
        $stmtLog = $pdo->prepare("
        SELECT sessionid
        FROM system_access_log
        WHERE login = :login
          AND logout_time IS NULL
        ORDER BY login_time DESC
        LIMIT 1
    ");
        $stmtLog->bindParam(':login', $userDetails['login'], PDO::PARAM_STR);
        $stmtLog->execute();
        $lastAccess = $stmtLog->fetch(PDO::FETCH_ASSOC);

        $userDetails['token'] = $lastAccess['sessionid'] ?? null;
        $userDetails['is_logged'] = isset($lastAccess['sessionid']);

        // -------- NOVO: permissões (grupos) ----------
        // Lista todos os grupos do usuário
        $stmtGroups = $pdo->prepare("
        SELECT g.id, g.name
        FROM system_user_group ug
        INNER JOIN system_group g ON g.id = ug.system_group_id
        WHERE ug.system_user_id = :uid
        ORDER BY g.name
    ");
        $stmtGroups->bindParam(':uid', $userDetails['id'], PDO::PARAM_INT);
        $stmtGroups->execute();
        $groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

        // Anexa ao payload
        $userDetails['groups'] = $groups ?: []; // [{ id, name }, ...]
        $userDetails['group_names'] = array_map(static function ($g) {
            return $g['name'];
        }, $groups ?: []);

        return ['success' => true, 'userDetails' => $userDetails];
    }



    public static function getUnitsUser($user_id)
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT su.id, su.name
            FROM system_unit su
            INNER JOIN system_user_unit suu ON su.id = suu.system_unit_id
            WHERE suu.system_user_id = :user_id
        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$units) {
            return ['success' => false, 'message' => 'Nenhuma unidade encontrada para o usuário.'];
        }

        return ['success' => true, 'units' => $units];
    }

   public static function getMenuMobile($user_id)
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT DISTINCT
                m.id, 
                m.name, 
                m.label, 
                m.description, 
                m.icon, 
                m.route, 
                m.ordem
            FROM menu_mobile m
            INNER JOIN menu_mobile_access a ON a.menu_id = m.id
            WHERE a.system_user_id = :user_id
            ORDER BY m.ordem;

        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$menus) {
            return ['success' => false, 'message' => 'Nenhum menu disponível para esse usuário e unidade.'];
        }

        return ['success' => true, 'menus' => $menus];
    }

    public static function getUsers()
    {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM system_users WHERE active = 'Y' ORDER BY name ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$users) {
            return ['success' => false, 'message' => 'Nenhum usuário encontrado.'];
        }

        return ['success' => true, 'users' => $users];

    }


}
?>
