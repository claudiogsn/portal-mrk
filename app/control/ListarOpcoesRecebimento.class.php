<?php
class ListarOpcoesRecebimento extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/listOpcoesRecebimento.html?system_unit_id={$unit_id}&token={$token}";
        }
        return "https://portal.mrksolucoes.com.br/external/listOpcoesRecebimento.html?system_unit_id={$unit_id}&token={$token}";
    }
}
