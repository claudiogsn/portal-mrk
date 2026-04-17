<?php
class ListCompositions extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $token = TSession::getValue('sessionid');
        $system_unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/listCompositions.html?token={$token}&system_unit_id={$system_unit_id}";
        }
        return "https://portal.mrksolucoes.com.br/external/listCompositions.html?token={$token}&system_unit_id={$system_unit_id}";
    }
}
