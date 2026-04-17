<?php
class ImportComprasCsv extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/importComprasCsv.html?user_id={$username}&token={$token}";
        }
        return "https://portal.mrksolucoes.com.br/external/importComprasCsv.html?user_id={$username}&token={$token}";
    }
}
