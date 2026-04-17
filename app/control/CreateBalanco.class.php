<?php
class CreateBalanco extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/listModelBalanco.html?username={$username}&token={$token}&unit_id={$unit_id}";
        }
        return "https://portal.mrksolucoes.com.br/external/listModelBalanco.html?username={$username}&token={$token}&unit_id={$unit_id}";
    }
}
