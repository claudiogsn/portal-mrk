<?php
class ListManipulation extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/listManipulacao.php";
        }
        return "https://portal.mrksolucoes.com.br/external/listManipulacao.php";
    }
}
