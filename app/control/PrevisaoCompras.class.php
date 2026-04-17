<?php
class PrevisaoCompras extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/previsaoCompras.html?username={$username}&token={$token}";
        }
        return "https://portal.mrksolucoes.com.br/external/previsaoCompras.html?username={$username}&token={$token}";
    }
}
