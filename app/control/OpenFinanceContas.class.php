<?php
class OpenFinanceContas extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/OFAccounts.php";
        }
        return "https://portal.mrksolucoes.com.br/external/OFAccounts.php";
    }
}
