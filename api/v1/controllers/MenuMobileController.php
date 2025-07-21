<?php

class MenuMobileController
{
    public static function listMenus(): array
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM menu_mobile ORDER BY ordem ASC, name ASC");
        return [
            "success" => true,
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    public static function getMenuById($id): array
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM menu_mobile WHERE id = ?");
        $stmt->execute([$id]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$menu) {
            return ["success" => false, "message" => "Menu não encontrado."];
        }

        return ["success" => true, "data" => $menu];
    }

    public static function createMenu($data): array
    {
        global $pdo;

        $required = ['name', 'label'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ["success" => false, "message" => "Campo obrigatório: $field"];
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO menu_mobile (name, label, description, icon, route, ordem, status)
            VALUES (:name, :label, :description, :icon, :route, :ordem, :status)
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':label' => $data['label'],
            ':description' => $data['description'] ?? null,
            ':icon' => $data['icon'] ?? null,
            ':route' => $data['route'] ?? null,
            ':ordem' => $data['ordem'] ?? 0,
            ':status' => $data['status'] ?? 1,
        ]);

        return ["success" => true, "message" => "Menu criado com sucesso."];
    }

    public static function updateMenu($data): array
    {
        global $pdo;

        if (empty($data['id'])) {
            return ["success" => false, "message" => "ID é obrigatório para atualização."];
        }

        $stmt = $pdo->prepare("
            UPDATE menu_mobile 
            SET name = :name,
                label = :label,
                description = :description,
                icon = :icon,
                route = :route,
                ordem = :ordem,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $data['id'],
            ':name' => $data['name'],
            ':label' => $data['label'],
            ':description' => $data['description'] ?? null,
            ':icon' => $data['icon'] ?? null,
            ':route' => $data['route'] ?? null,
            ':ordem' => $data['ordem'] ?? 0,
            ':status' => $data['status'] ?? 1,
        ]);

        return ["success" => true, "message" => "Menu atualizado com sucesso."];
    }

    public static function deleteMenu($id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM menu_mobile WHERE id = ?");
        $stmt->execute([$id]);

        return ["success" => true, "message" => "Menu excluído com sucesso."];
    }

    public static function toggleStatus($id): array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT status FROM menu_mobile WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            return ["success" => false, "message" => "Registro não encontrado."];
        }

        $newStatus = $current == 1 ? 0 : 1;

        $stmt = $pdo->prepare("UPDATE menu_mobile SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        return ["success" => true, "message" => "Status atualizado com sucesso.", "newStatus" => $newStatus];
    }

    public static function createOrUpdateMenuPermission($data): array
    {
        global $pdo;

        if (!is_array($data)) {
            return ["success" => false, "message" => "O campo 'data' deve ser um array."];
        }

        try {
            $pdo->beginTransaction();

            foreach ($data as $item) {
                if (
                    !isset($item['menu_id']) ||
                    !isset($item['system_user_id'])
                ) {
                    $pdo->rollBack();
                    return ["success" => false, "message" => "Campos obrigatórios ausentes em um dos registros."];
                }

                if (!empty($item['id'])) {
                    // UPDATE se id estiver presente
                    $stmt = $pdo->prepare("
                    UPDATE menu_mobile_access 
                    SET menu_id = :menu_id, system_user_id = :system_user_id
                    WHERE id = :id
                ");
                    $stmt->execute([
                        ':id' => $item['id'],
                        ':menu_id' => $item['menu_id'],
                        ':system_user_id' => $item['system_user_id']
                    ]);
                } else {
                    // INSERT se id não estiver presente
                    $stmt = $pdo->prepare("
                    INSERT IGNORE INTO menu_mobile_access (menu_id, system_user_id)
                    VALUES (:menu_id, :system_user_id)
                ");
                    $stmt->execute([
                        ':menu_id' => $item['menu_id'],
                        ':system_user_id' => $item['system_user_id']
                    ]);
                }
            }

            $pdo->commit();
            return ["success" => true, "message" => "Permissões salvas com sucesso."];

        } catch (Exception $e) {
            $pdo->rollBack();
            return ["success" => false, "message" => "Erro ao salvar permissões: " . $e->getMessage()];
        }
    }


    public static function getPermissionsByMenu($menuId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.system_user_id,
            u.name AS usuario_nome
        FROM menu_mobile_access a
        INNER JOIN system_users u ON a.system_user_id = u.id
        WHERE a.menu_id = ?
        ORDER BY u.name
    ");
        $stmt->execute([$menuId]);
        $permissoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "success" => true,
            "data" => $permissoes
        ];
    }

    public static function deleteMenuPermission($data): array
    {
        global $pdo;

        if (empty($data)) {
            return ["success" => false, "message" => "ID da permissão é obrigatório."];
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM menu_mobile_access WHERE id = ?");
            $stmt->execute([$data]);

            return ["success" => true, "message" => "Permissão removida com sucesso."];
        } catch (Exception $e) {
            return ["success" => false, "message" => "Erro ao remover permissão: " . $e->getMessage()];
        }
    }



}
