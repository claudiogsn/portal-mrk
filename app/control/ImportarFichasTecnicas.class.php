<?php
class ImportarFichasTecnicas extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');
        $unit_name = TSession::getValue('userunitname');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/importarFichasTecnicas.html?username={$username}&token={$token}&unit_id={$unit_id}&unit_name={$unit_name}";
        }
        return "https://portal.mrksolucoes.com.br/external/importarFichasTecnicas.html?username={$username}&token={$token}&unit_id={$unit_id}&unit_name={$unit_name}";
    }
}
