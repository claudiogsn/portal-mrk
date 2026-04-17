<?php
class ListarContatos extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $token = TSession::getValue('sessionid');
        $user_id = TSession::getValue('userid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/listContatos.html?token={$token}&user_id={$user_id}";
        }
        return "https://portal.mrksolucoes.com.br/external/listContatos.html?token={$token}&user_id={$user_id}";
    }
}
