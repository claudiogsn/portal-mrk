<?php
class WelcomeView extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/welcomeView.php";
        }
        return "https://portal.mrksolucoes.com.br/external/welcomeView.php";
    }
}
