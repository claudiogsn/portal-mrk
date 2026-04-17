<?php
class GrupoEstabelecimento extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $token = TSession::getValue('sessionid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/listGrupos.html?token={$token}";
        }
        return "https://portal.mrksolucoes.com.br/external/listGrupos.html?token={$token}";
    }
}
