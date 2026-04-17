<?php
class ConferenciaCaixa extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/conferenciaCaixa.html?user_id={$username}&token={$token}&unit_id={$unit_id}";
        }
        return "https://portal.mrksolucoes.com.br/external/conferenciaCaixa.html?user_id={$username}&token={$token}&unit_id={$unit_id}";
    }
}
