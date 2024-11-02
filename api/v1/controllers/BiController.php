<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class BiController {

    public static function getUnitsByGroup($group_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT rel.system_unit_id, su.custom_code FROM grupo_estabelecimento_rel AS rel JOIN  system_unit AS su ON rel.system_unit_id = su.id WHERE rel.grupo_id = :group_id;");
        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
