<?php
class OpenFinancePagadores extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/OFListPayers.php";
        }
        return "https://portal.mrksolucoes.com.br/external/OFListPayers.php";
    }
}
