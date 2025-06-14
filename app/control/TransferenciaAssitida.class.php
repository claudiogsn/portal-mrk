<?php
class TransferenciaAssitida extends TPage
{
    private $form;
    public function __construct($param)
    {
        parent::__construct();

        $username = TSession::getValue('userid');
        $token = TSession::getValue('sessionid');
        $unit_id = TSession::getValue('userunitid');


        if($_SERVER['SERVER_NAME'] == "localhost"){
            $link = "http://localhost/portal-mrk/external/previsaoDemandaLoja.html?username={$username}&token={$token}";
        }else{
            $link = "https://portal.mrksolucoes.com.br/external/previsaoDemandaLoja.html?username={$username}&token={$token}";
        }

        $iframe = new TElement('iframe');
        $iframe->id = "iframe_external";
        $iframe->src = $link;
        $iframe->frameborder = "0";
        $iframe->scrolling = "yes";
        $iframe->width = "100%";
        $iframe->height = "800px";
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