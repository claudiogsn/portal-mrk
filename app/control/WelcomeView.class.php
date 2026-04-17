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

            $logId = AccessLogger::log(__CLASS__);
    
            $username = TSession::getValue('userid');
            $token = TSession::getValue('sessionid');
            $unit_id = TSession::getValue('userunitid');
    
    
            if($_SERVER['SERVER_NAME'] == "localhost"){
                $link = "http://localhost/portal-mrk/external/welcomeView.php";
            }else{
                $link = "https://portal.mrksolucoes.com.br/external/welcomeView.php";
            }
    
            $iframe = new TElement('iframe');
            $iframe->id = "iframe_external";
            $iframe->src = $link;
            $iframe->frameborder = "0";
            $iframe->scrolling = "yes";
            $iframe->width = "100%";
            $iframe->height = "1000px";
    
            parent::add($iframe);

            if ($logId) {
                TScript::create("
                (function() {
                    var logId = " . (int) $logId . ";
                    var sent  = false;
                    function sendExit() {
                        if (sent) return;
                        sent = true;
                        var url = 'engine.php?class=AccessLoggerEndpoint&method=logout&log_id=' + logId;
                        if (navigator.sendBeacon) {
                            navigator.sendBeacon(url);
                        } else {
                            var xhr = new XMLHttpRequest();
                            xhr.open('GET', url, false);
                            try { xhr.send(); } catch(e) {}
                        }
                    }
                    window.addEventListener('beforeunload', sendExit);
                    window.addEventListener('pagehide', sendExit);
                })();
            ");
            }
        }
        function onFeed($param){
            // $id = $param['key'];
        }
        function onEdit($param){
            // $id = $param['key'];
        }
    }
