<?php
/**
 * AccessLogger
 *
 * Service para registrar acessos dos usuários nas telas do sistema.
 * Usa o padrão Adianti: TRecord + TTransaction.
 *
 * Fail-safe: erros nunca quebram a tela do usuário.
 *
 * Uso no controller:
 *   $logId = AccessLogger::log(__CLASS__);
 *
 * @version    1.0
 * @package    service
 */
class AccessLogger
{
    /** @var string Nome da conexão Adianti (config em app/config/{conn}.ini) */
    const CONNECTION = 'permission';   // ← AJUSTE para o nome da sua conexão

    /** @var int|null ID do último log inserido neste request */
    private static $lastLogId = null;

    /**
     * Descobre o IP real do usuário (considera proxies reversos).
     */
    private static function getClientIp()
    {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return null;
    }

    /**
     * Monta a URL completa acessada.
     */
    private static function getFullUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        return mb_substr($protocol . '://' . $host . $uri, 0, 1000);
    }

    /**
     * Registra um acesso à tela.
     *
     * @param string      $className  Nome da classe do controller (__CLASS__)
     * @param string|null $action     Ação opcional (ex.: 'onEdit', 'onDelete')
     * @return int|null  ID do log inserido ou null em caso de falha
     */
    public static function log($className, $action = null)
    {
        try {
            TTransaction::open(self::CONNECTION);

            $log = new AccessLog();
            $log->user_id      = TSession::getValue('userid')     ?: null;
            $log->unit_id      = TSession::getValue('userunitid') ?: null;
            $log->class_name   = mb_substr($className, 0, 150);
            $log->action       = $action ? mb_substr($action, 0, 50) : null;
            $log->url          = self::getFullUrl();
            $log->ip           = self::getClientIp();
            $log->user_agent   = isset($_SERVER['HTTP_USER_AGENT'])
                ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
                : null;
            $log->data_entrada = date('Y-m-d H:i:s');
            $log->session_id   = TSession::getValue('sessionid')
                ? mb_substr(TSession::getValue('sessionid'), 0, 100)
                : null;
            $log->store();

            self::$lastLogId = (int) $log->id;

            TTransaction::close();
            return self::$lastLogId;

        } catch (Exception $e) {
            // Fail-safe: não propaga erro. Só anota no log do sistema.
            try { TTransaction::rollback(); } catch (Exception $ex) {}
            error_log('[AccessLogger::log] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Registra a saída da tela e calcula duração.
     * Chamado via endpoint quando o usuário fecha/troca de tela.
     *
     * @param int $logId ID retornado por log()
     * @return bool
     */
    public static function logout($logId)
    {
        if (!$logId) return false;

        try {
            TTransaction::open(self::CONNECTION);

            $log = new AccessLog((int) $logId);
            if ($log->id && empty($log->data_saida)) {
                $entrada = new DateTime($log->data_entrada);
                $saida   = new DateTime('now');
                $duracao = $saida->getTimestamp() - $entrada->getTimestamp();

                $log->data_saida  = $saida->format('Y-m-d H:i:s');
                $log->duracao_seg = max(0, $duracao);
                $log->store();
            }

            TTransaction::close();
            return true;

        } catch (Exception $e) {
            try { TTransaction::rollback(); } catch (Exception $ex) {}
            error_log('[AccessLogger::logout] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna o último ID de log gerado neste request.
     */
    public static function getLastLogId()
    {
        return self::$lastLogId;
    }
}