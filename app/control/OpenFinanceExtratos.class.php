<?php
class OpenFinanceExtratos extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/OFStatements.php";
        }
        return "https://portal.mrksolucoes.com.br/external/OFStatements.php";
    }
}
