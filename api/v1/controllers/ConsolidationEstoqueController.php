<?php


ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../database/db.php';

class ConsolidationEstoqueController {

    public static function getStatusConsolidationMonth($system_unit_id,$month = null, $year = null)
    {
        global $pdo;

        // Se não forem passados o mês e o ano, usa o mês e ano atuais
        $month = $month ?? date('m');
        $year = $year ?? date('Y');

        // Primeiro e último dia do mês
        $firstDay = sprintf('%04d-%02d-01', $year, $month);
        $lastDay = date('Y-m-t', strtotime($firstDay));

        // Query para buscar dados consolidados no intervalo
        $sql = "
        SELECT data 
        FROM diferencas_estoque 
        WHERE system_unit_id = :system_unit_id
          AND data BETWEEN :data_inicial AND :data_final 
        GROUP BY data
    ";

        // Prepara e executa a consulta
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicial', $firstDay);
        $stmt->bindParam(':data_final', $lastDay);
        $stmt->bindParam(':system_unit_id', $system_unit_id);
        $stmt->execute();

        // Obtém os resultados
        $consolidatedDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Número total de dias no mês
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        // Monta a lista com os dias e seus status
        $result = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

            $result[] = [
                'date' => $date,
                'status' => in_array($date, $consolidatedDates) ? 'consolidated' : 'pending'
            ];
        }

        return $result;
    }




}