<?php
/**
 * AccessLogger
 *
 * Service para registrar acessos dos usuários nas telas do sistema.
 * Padrão Adianti: TRecord + TTransaction.
 *
 * Fail-safe: erros nunca quebram a tela do usuário.
 *
 * Uso básico:
 *   AccessLogger::log(__CLASS__);
 *
 * Uso com URL do iframe:
 *   AccessLogger::log(__CLASS__, null, $link);
 *
 * @version    1.1
 * @package    service
 */
class AccessLogger
{
    /** @var string Nome da conexão Adianti */
    const CONNECTION = 'permission';   // ← AJUSTE para o nome da sua conexão

    /** @var int|null */
    private static $lastLogId = null;

    /**
     * Descobre o IP real do cliente.
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
     * URL completa do Adianti (com ?class=...).
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
     * @param string      $className    Nome da classe (__CLASS__)
     * @param string|null $action       Ação opcional
     * @param string|null $frontendUrl  URL do frontend (iframe) — passe a variável $link do controller
     * @return int|null
     */
    public static function log($className, $action = null, $frontendUrl = null)
    {
        try {
            TTransaction::open(self::CONNECTION);

            $log = new AccessLog();
            $log->user_id      = TSession::getValue('userid')     ?: null;
            $log->unit_id      = TSession::getValue('userunitid') ?: null;
            $log->class_name   = mb_substr($className, 0, 150);
            $log->action       = $action ? mb_substr($action, 0, 50) : null;
            $log->url          = self::getFullUrl();
            $log->frontend_url = $frontendUrl ? mb_substr($frontendUrl, 0, 1000) : null;
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
            try { TTransaction::rollback(); } catch (Exception $ex) {}
            error_log('[AccessLogger::log] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Registra a saída da tela e calcula duração.
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

    public static function getLastLogId()
    {
        return self::$lastLogId;
    }
}