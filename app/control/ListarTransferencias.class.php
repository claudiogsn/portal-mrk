<?php
class ListarTransferencias extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/listTransferencias.php";
        }
        return "https://portal.mrksolucoes.com.br/external/listTransferencias.php";
    }
}
