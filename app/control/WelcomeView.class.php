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
class WelcomeView extends TPage
    {
        private $form;
        public function __construct($param)
        {
            parent::__construct();
    
            $username = TSession::getValue('userid');
            $token = TSession::getValue('sessionid');
            $unit_id = TSession::getValue('userunitid');
    
    
            if($_SERVER['SERVER_NAME'] == "localhost"){
                $link = "http://localhost/portal-mrk/external/Dashboard.html?username={$username}&token={$token}&unit_id={$unit_id}";
            }else{
                $link = "https://portal.mrksolucoes.com.br/external/Dashboard.html?username={$username}&token={$token}&unit_id={$unit_id}";
            }
    
            $iframe = new TElement('iframe');
            $iframe->id = "iframe_external";
            $iframe->src = $link;
            $iframe->frameborder = "0";
            $iframe->scrolling = "yes";
            $iframe->width = "100%";
            $iframe->height = "1000px";
    
            parent::add($iframe);
        }
        function onFeed($param){
            // $id = $param['key'];
        }
        function onEdit($param){
            // $id = $param['key'];
        }
    }
