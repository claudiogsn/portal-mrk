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

        // Busca os detalhes do usuário
        $stmt = $pdo->prepare("SELECT id, name, login, function_name, system_unit_id FROM system_users WHERE login = :user AND active = 'Y' LIMIT 1");
        $stmt->bindParam(':user', $user, PDO::PARAM_STR);
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

        // Busca o último acesso sem logout
        $stmtLog = $pdo->prepare("
            SELECT sessionid
            FROM system_access_log
            WHERE login = :user
            AND logout_time = '0000-00-00 00:00:00'
            ORDER BY login_time DESC
            LIMIT 1
        ");
        $stmtLog->bindParam(':user', $user, PDO::PARAM_STR);
        $stmtLog->execute();
        $lastAccess = $stmtLog->fetch(PDO::FETCH_ASSOC);

        $userDetails['token'] = $lastAccess['sessionid'] ?? null;
        $userDetails['is_logged'] = isset($lastAccess['sessionid']);

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

   public static function getMenuMobile($user_id, $system_unit_id)
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT m.id, m.name, m.label, m.description, m.icon, m.route, m.ordem
            FROM menu_mobile m
            INNER JOIN menu_mobile_access a ON a.menu_id = m.id
            WHERE a.system_user_id = :user_id
              AND a.system_unit_id = :system_unit_id
            ORDER BY m.ordem, m.label
        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$menus) {
            return ['success' => false, 'message' => 'Nenhum menu disponível para esse usuário e unidade.'];
        }

        return ['success' => true, 'menus' => $menus];
    }


}
?>
