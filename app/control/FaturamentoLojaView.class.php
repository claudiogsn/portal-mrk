<?php
class FaturamentoLojaView extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/dashboardFaturamentoLoja.html?token={$token}&system_unit_id={$unit_id}";
        }
        return "https://portal.mrksolucoes.com.br/external/dashboardFaturamentoLoja.html?token={$token}&system_unit_id={$unit_id}";
    }
}
