<?php
class ReportManipulacao extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/relatorioManipulacao.php";
        }
        return "https://portal.mrksolucoes.com.br/external/relatorioManipulacao.php";
    }
}
