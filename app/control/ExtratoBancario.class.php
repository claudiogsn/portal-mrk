<?php
class ExtratoBancario extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/extratoBancario.html?unit_id={$unit_id}&token={$token}";
        }
        return "https://portal.mrksolucoes.com.br/external/extratoBancario.html?unit_id={$unit_id}&token={$token}";
    }
}
