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
class DashboardFinanceiro extends TPage
    {
        private $form;
        public function __construct($param)
        {
            parent::__construct();
    
            $user_id = TSession::getValue('userid');
            $token = TSession::getValue('sessionid');
            $unit_id = TSession::getValue('userunitid');
    
    
            if($_SERVER['SERVER_NAME'] == "localhost"){
                $link = "http://localhost/portal-mrk/external/dashboardFinanceiro.html?system_unit_id={$unit_id}&user_id={$user_id}&token={$token}";
            }else{
                $link = "https://portal.mrksolucoes.com.br/external/dashboardFinanceiro.html?system_unit_id={$unit_id}&user_id={$user_id}&token={$token}";
            }

            $this->container = new TVBox;
            $this->container->style = "width:100%; height:100vh; overflow:hidden; margin:0; padding:0;";

            $breadcrumb = new TXMLBreadCrumb('menu.xml', __CLASS__);

            // ---- IFRAME RESPONSIVO ----
            $iframe = new TElement('iframe');
            $iframe->id          = "iframe_external";
            $iframe->src         = $link;
            $iframe->frameborder = "0";
            $iframe->scrolling   = "yes"; // remove scroll interno
            $iframe->style       = "width:100%; height: calc(100vh - 70px); overflow:hidden; border:none; margin:0; padding:0;";

            // ---- ADICIONA AO CONTAINER ----
            $this->container->add($breadcrumb);
            $this->container->add($iframe);

            parent::add($this->container);

            // ---- SCRIPT PARA REAJUSTAR ALTURA DINAMICAMENTE ----
            TScript::create("
function resizeIframe() {
        try {
            var iframe = document.getElementById('iframe_external');
            var bc     = document.querySelector('.breadcrumb');
            var bcH    = bc ? bc.offsetHeight : 70;
            iframe.style.height = (window.innerHeight - bcH) + 'px';
        } catch(e) {}
    }
    window.onresize = resizeIframe;
    window.onload   = resizeIframe;
");

$pageName = __CLASS__;

TScript::create("
    if (typeof gtag === 'function') {
        gtag('event', 'page_view', {
            page_title: " . json_encode($pageName) . ",
            page_path: " . json_encode($pageName) . ",
            empresa_id: " . json_encode($unit_id) . "
        });
    }
");

        }
        function onFeed($param){
            // $id = $param['key'];
        }
        function onEdit($param){
            // $id = $param['key'];
        }
    }
