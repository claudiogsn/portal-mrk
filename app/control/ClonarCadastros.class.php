<?php
class ClonarCadastros extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/migrations/index.html?user_id={$username}&token={$token}&unit_id={$unit_id}";
        }
        return "https://portal.mrksolucoes.com.br/migrations/index.html?user_id={$username}&token={$token}&unit_id={$unit_id}";
    }
}
