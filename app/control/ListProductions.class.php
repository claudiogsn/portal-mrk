<?php
class ListProductions extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/listProd.php";
        }
        return "https://portal.mrksolucoes.com.br/external/listProd.php";
    }
}
