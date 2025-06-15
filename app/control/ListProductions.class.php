<?php
class ListProductions extends TPage
{
    private $form;
    protected $pageNavigation;

    public function __construct($param)
    {
        parent::__construct();

        $token = TSession::getValue('sessionid');
        $system_unit_id = TSession::getValue('userunitid');


        if ($_SERVER['SERVER_NAME'] == "localhost") {
            $link = "http://localhost/portal-mrk/external/listProductions.html?token={$token}&system_unit_id={$system_unit_id}";
        } else {
            $link = "https://portal.mrksolucoes.com.br/external/listProductions.html?token={$token}&system_unit_id={$system_unit_id}";
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

    function onFeed($param)
    {
        // $id = $param['key'];
    }

    function onEdit($param)
    {
        // $id = $param['key'];
    }
}
