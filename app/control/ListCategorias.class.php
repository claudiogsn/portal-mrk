<?php
class ListCategorias extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/listCategorias.html?user={$username}&token={$token}&system_unit_id={$unit_id}";
        }
        return "https://portal.mrksolucoes.com.br/external/listCategorias.html?user={$username}&token={$token}&system_unit_id={$unit_id}";
    }
}
