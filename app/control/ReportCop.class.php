<?php
class ReportCop extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $token = TSession::getValue('sessionid');
        $system_unit_id = TSession::getValue('userunitid');
        $unit_name = TSession::getValue('userunitname');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/reportCop.html?token={$token}&system_unit_id={$system_unit_id}&unit_name={$unit_name}";
        }
        return "https://portal.mrksolucoes.com.br/external/reportCop.html?token={$token}&system_unit_id={$system_unit_id}&unit_name={$unit_name}";
    }
}
