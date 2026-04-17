<?php
/**
 * WelcomeView
 *
 * @version    7.6
 * @package    control
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class DashboardEstoque extends MRKIframePage
{
    protected function getFrontendUrl(): string
    {
        $user_id = TSession::getValue('userid');
        $token   = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');

        if ($_SERVER['SERVER_NAME'] == 'localhost') {
            return "http://localhost/portal-mrk/external/dashboardEstoque.html?system_unit_id={$unit_id}&user_id={$user_id}&token={$token}";
        }
        return "https://portal.mrksolucoes.com.br/external/dashboardEstoque.html?system_unit_id={$unit_id}&user_id={$user_id}&token={$token}";
    }
}
