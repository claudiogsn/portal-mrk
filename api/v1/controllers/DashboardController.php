<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class DashboardController {

    public static function getDashboardData($unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT * FROM dashboard_data WHERE unit_id = :unit_id
        ");
        $stmt->bindParam(':unit_id', $unit_id);

        $stmt->execute();
        $dashboardData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dashboardData) {
            return $dashboardData;
        } else {
            throw new Exception('Dados de dashboard não encontrados.');
        }
    }
}
