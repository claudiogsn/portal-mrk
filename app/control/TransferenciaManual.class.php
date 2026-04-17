<?php
class TransferenciaManual extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/transferenciaManual.html?username={$username}&token={$token}";
        }
        return "https://portal.mrksolucoes.com.br/external/transferenciaManual.html?username={$username}&token={$token}";
    }
}
