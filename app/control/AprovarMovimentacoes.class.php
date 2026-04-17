<?php
class AprovarMovimentacoes extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/AprovarMovimentacoes.html?username={$username}&token={$token}&unit_id={$unit_id}";
        }
        return "https://portal.mrksolucoes.com.br/external/AprovarMovimentacoes.html?username={$username}&token={$token}&unit_id={$unit_id}";
    }
}
