<?php
class DashboardFaturamento extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $user_id = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/dashboardFaturamento.html?system_unit_id={$unit_id}&user_id={$user_id}&token={$token}";
        }
        return "https://portal.mrksolucoes.com.br/external/dashboardFaturamento.html?system_unit_id={$unit_id}&user_id={$user_id}&token={$token}";
    }
}
