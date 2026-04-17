<?php
class ExecutarManipulacao extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/executarManipulacao.php";
        }
        return "https://portal.mrksolucoes.com.br/external/executarManipulacao.php";
    }
}
