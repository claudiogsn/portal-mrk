<?php
class Dre extends TPage
{
    private $form;
    public function __construct($param)
    {
        parent::__construct();

        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');


        if($_SERVER['SERVER_NAME'] == "localhost"){
            $link = "http://localhost/portal-mrk/external/dreGerencial.html?username={$username}&token={$token}&unit_id={$unit_id}";
        }else{
            $link = "https://portal.mrksolucoes.com.br/external/dreGerencial.html?username={$username}&token={$token}&unit_id={$unit_id}";
        }

        $iframe = new TElement('iframe');
        $iframe->id = "iframe_external";
        $iframe->src = $link;
        $iframe->frameborder = "0";
        $iframe->scrolling = "no";
        $iframe->width = "100%";
        $iframe->height = "100%";

        parent::add($iframe);
    }
    function onFeed($param){
        // $id = $param['key'];
    }
    function onEdit($param){
        // $id = $param['key'];
    }
}