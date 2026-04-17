<?php
class OpenFinanceTransacoes extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/OFTransactions.php";
        }
        return "https://portal.mrksolucoes.com.br/external/OFTransactions.php";
    }
}
