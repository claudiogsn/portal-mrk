<?php

abstract class MRKIframePage extends TPage
{
    protected $container;

    /**
     * Cada filha implementa retornando a URL do iframe.
     */
    abstract protected function getFrontendUrl(): string;

    public function __construct($param)
    {
        parent::__construct();

        $unit_id = TSession::getValue('userunitid');
        $link    = $this->getFrontendUrl();

        // ---- LOG DE ACESSO ----
        $linkParaLog = preg_replace('/\?.*$/', '', $link);
        $logId = AccessLogger::log(static::class, null, $linkParaLog);

        // ---- CONTAINER ----
        $this->container = new TVBox;
        $this->container->style = "width:100%; height:100vh; overflow:hidden; margin:0; padding:0;";

        $breadcrumb = new TXMLBreadCrumb('menu.xml', static::class);

        // ---- IFRAME ----
        $iframe = new TElement('iframe');
        $iframe->id          = "iframe_external";
        $iframe->src         = $link;
        $iframe->frameborder = "0";
        $iframe->scrolling   = "yes";
        $iframe->style       = "width:100%; height: calc(100vh - 70px); overflow:hidden; border:none; margin:0; padding:0;";

        $this->container->add($breadcrumb);
        $this->container->add($iframe);

        parent::add($this->container);

        // ---- RESIZE ----
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

        // ---- BEACON DE SAÍDA ----
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

        // ---- GOOGLE ANALYTICS ----
        $pageName = static::class;
        TScript::create("
            if (typeof gtag === 'function') {
                gtag('event', 'page_view', {
                    page_title: " . json_encode($pageName) . ",
                    page_path: "  . json_encode($pageName) . ",
                    empresa_id: " . json_encode($unit_id)  . "
                });
            }
        ");
    }

    public function onFeed($param) {}
    public function onEdit($param) {}
}