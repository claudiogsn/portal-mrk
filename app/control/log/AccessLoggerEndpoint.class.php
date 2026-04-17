<?php
/**
 * AccessLoggerEndpoint
 *
 * Endpoint chamado pelo navigator.sendBeacon() quando o usuário sai de uma tela.
 * Atualiza data_saida e duracao_seg no log.
 *
 * Chamada:
 *   engine.php?class=AccessLoggerEndpoint&method=logout&log_id=XXX
 *
 * @version    1.0
 * @package    control
 */
class AccessLoggerEndpoint extends TPage
{
    public function __construct($param)
    {
        parent::__construct();
    }

    /**
     * Método público chamado pelo sendBeacon.
     */
    public function logout($param)
    {
        $log_id = isset($param['log_id']) ? (int) $param['log_id'] : 0;

        if ($log_id > 0) {
            AccessLogger::logout($log_id);
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
}