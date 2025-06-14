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
class DashboardEstoque extends TPage
    {
        private $form;
        public function __construct($param)
        {
            parent::__construct();
    
            $user_id = TSession::getValue('userid');
            $token = TSession::getValue('sessionid');
            $unit_id = TSession::getValue('userunitid');
    
    
            if($_SERVER['SERVER_NAME'] == "localhost"){
                $link = "http://localhost/portal-mrk/external/dashboardEstoque.html?system_unit_id={$unit_id}&user_id={$user_id}&token={$token}";
            }else{
                $link = "https://portal.mrksolucoes.com.br/external/dashboardEstoque.html?system_unit_id={$unit_id}&user_id={$user_id}&token={$token}";
            }
    
            $iframe = new TElement('iframe');
            $iframe->id = "iframe_external";
            $iframe->src = $link;
            $iframe->frameborder = "0";
            $iframe->scrolling = "yes";
            $iframe->width = "100%";
            $iframe->height = "1000px";
            $container = new TVBox;
            $container->style = 'width: 100%';
            $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
            $container->add($iframe);

            parent::add($container);
        }
        function onFeed($param){
            // $id = $param['key'];
        }
        function onEdit($param){
            // $id = $param['key'];
        }
    }
