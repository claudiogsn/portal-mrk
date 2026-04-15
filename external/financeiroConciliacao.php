<?php
session_name('PHPSESSID_MRKSolutions');
session_start();

if (!isset($_SESSION['MRKSolutions'])) {
    die("Sessão expirada. Faça login novamente.");
}

$appData = $_SESSION['MRKSolutions'];
$token   = $appData['sessionid']  ?? '';
$unit_id = $appData['userunitid'] ?? '';
$username = $appData['userid'] ?? '';

if (empty($token) || empty($unit_id)) {
    die("Acesso negado. Unidade não identificada.");
}

$banco_id = $_GET['banco_id'] ?? '';
$data_ref = $_GET['date'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Conciliação de Movimentos | Portal MRK</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

    <link href="bsb/plugins/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="bsb/plugins/sweetalert/sweetalert.css" rel="stylesheet" />
    <link href="bsb/css/style.css" rel="stylesheet">
    <link href="style/mrk.css" rel="stylesheet">

    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #F4F7F6; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-voltar { background: #fff; border: 1px solid #ccc; border-radius: 6px; padding: 6px 15px; font-weight: 600; color: #555; transition: 0.2s; text-decoration: none !important;}
        .btn-voltar:hover { background: #eee; }

        /* Card Pai - Garante que ele é a âncora principal */
        .pane-card { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; display: flex; flex-direction: column; height: 82vh; }

        /* Headers Dinâmicos */
        .bank-header { padding: 12px 15px; display: flex; align-items: center; gap: 12px; font-family: 'Kanit'; font-size: 15px; font-weight: 600; border-bottom: 1px solid #eee; }
        .bank-logo { width: 30px; height: 30px; border-radius: 6px; object-fit: contain; background: rgba(255,255,255,0.9); padding: 2px; }

        /* Evita que o cabeçalho seja esmagado */
        .pane-header { padding: 15px; border-bottom: 1px solid #eee; background: #fdfdfd; flex-shrink: 0; }
        .pane-header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .pane-header-top h4 { margin: 0; font-family: 'Kanit'; font-size: 15px; color: #333; }

        .action-link { font-size: 12px; color: var(--mrk-blue); cursor: pointer; font-weight: 600; text-decoration: underline; margin-right: 15px; }
        .action-btn-sm { background: var(--mrk-blue); color: #fff; border: none; padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; font-family: 'Kanit'; cursor: pointer; transition: 0.2s; }
        .action-btn-sm:hover { background: #1565C0; }

        /* === TABELA COM ROLAGEM CORRIGIDA === */
        .table-wrap { flex-grow: 1; height: 0; min-height: 0; overflow-y: auto; background: #f9f9f9; padding: 10px; }

        .table-conciliacao { width: 100%; border-collapse: separate; border-spacing: 0 5px; margin-bottom: 0; }

        .table-conciliacao th {
            background: #3b82f6;
            color: white;
            padding: 8px;
            font-family: 'Kanit';
            font-weight: 400;
            font-size: 11px;
            position: sticky;
            top: -10px;
            z-index: 99;
            text-transform: uppercase;
        }

        /* Disfarça os cantos redondos no scroll */
        .table-conciliacao th::before { content: ""; position: absolute; top: -10px; left: 0; right: 0; height: 10px; background: #f9f9f9; z-index: -1; }

        .table-conciliacao th:first-child { border-radius: 6px 0 0 6px; }
        .table-conciliacao th:last-child { border-radius: 0 6px 6px 0; text-align: center; }

        .row-item { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: 0.1s; border-radius: 6px; border-left: 3px solid transparent; }
        .row-item:hover { transform: scale(1.01); border-left: 3px solid var(--mrk-blue); }
        .row-item.selected { background: #e3f2fd; border-left: 3px solid #3b82f6; }

        /* SMART MATCH HIGHLIGHT */
        .row-item.highlight-match { background: #fffde7; border-left: 3px solid #facc15; }

        .row-item td { padding: 10px 8px; font-size: 11px; vertical-align: middle; }
        .row-item td:first-child { border-radius: 6px 0 0 6px; }
        .row-item td:last-child { border-radius: 0 6px 6px 0; text-align: center; }

        .valor-entrada { color: var(--mrk-green); font-weight: 600; }
        .valor-saida { color: var(--mrk-red); font-weight: 600; }
        .badge-dc { font-size: 9px; padding: 2px 4px; border-radius: 3px; margin-left: 5px; font-weight: bold; }
        .badge-c { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .badge-d { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        .btn-add-trans { color: var(--mrk-blue); font-size: 16px; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; padding: 2px; }
        .btn-add-trans:hover { transform: scale(1.2); color: var(--mrk-green); }

        /* Rodapé - flex-shrink evita o esmagamento */
        .pane-footer { background: #10b981; color: white; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; font-family: 'Kanit'; font-size: 16px; font-weight: 600; flex-shrink: 0; }

        .btn-acao-principal { background: var(--mrk-blue); color: white; border: none; padding: 10px 25px; border-radius: 6px; font-family: 'Kanit'; font-size: 16px; transition: 0.3s; opacity: 0.5; pointer-events: none; }
        .btn-acao-principal.active { opacity: 1; pointer-events: auto; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); cursor: pointer; }
        .btn-acao-principal.active:hover { background: #2563eb; transform: translateY(-2px); }

        .btn-confirmar-dia { background: var(--mrk-green); color: white; border: none; padding: 10px 25px; border-radius: 6px; font-family: 'Kanit'; font-size: 16px; transition: 0.3s; display: none; margin-left: 10px;}
        .btn-confirmar-dia:hover { box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); transform: translateY(-2px); }

        .search-input { border: 1px solid #ddd; border-radius: 4px; padding: 4px 8px; font-size: 11px; width: 100%; margin-top: 5px; }
        .custom-checkbox { width: 16px; height: 16px; pointer-events: none; }
        .bank-header { padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; font-family: 'Kanit'; font-size: 15px; font-weight: 600; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>

<div class="container-fluid" style="padding: 10px 20px;">

    <div class="top-bar">
        <div>
            <a href="listConciliacao.html?username=<?php echo $username; ?>&token=<?php echo $token; ?>&unit_id=<?php echo $unit_id; ?>&financeiro_banco_id=<?php echo $banco_id; ?>" class="btn-voltar">
                <iconify-icon icon="icon-park-outline:left"></iconify-icon> Voltar ao Calendário
            </a>
            <h3 style="display:inline-block; margin-left: 20px; font-family: 'Kanit'; color: var(--mrk-blue); font-size: 20px; margin-bottom: 0;">
                Conciliação do Dia: <b><?php echo date('d/m/Y', strtotime($data_ref)); ?></b>
            </h3>
        </div>
        <div style="display: flex;">
            <button id="btnConciliar" class="btn-acao-principal">
                <iconify-icon icon="icon-park-outline:link-cloud"></iconify-icon> CONCILIAR SELECIONADOS
            </button>
            <button id="btnConfirmarDia" class="btn-confirmar-dia">
                <iconify-icon icon="icon-park-outline:check-one"></iconify-icon> CONFIRMAR DIA
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="pane-card">
                <div class="bank-header" id="headerSys" style="background: #e3f2fd; color: #1565c0;">
                    <iconify-icon icon="icon-park-outline:bank" style="font-size: 24px;"></iconify-icon>
                    <span>Carregando Sistema...</span>
                </div>
                <div class="pane-header">
                    <div class="pane-header-top">
                        <h4>Movimentos do Sistema <span style="font-size: 11px; color: #888;" id="resumoSistema">(0)</span></h4>
                        <div>
                            <span class="action-link" id="btnSelectAllSys">Selecionar Todos</span>
                            <span class="action-link" id="btnAtualizarSys"><iconify-icon icon="icon-park-outline:refresh"></iconify-icon> Recarregar</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><label style="font-size:10px;margin:0;">De:</label><input type="date" id="sysDataIni" class="form-control input-sm" value="<?php echo $data_ref; ?>"></div>
                        <div class="col-md-4"><label style="font-size:10px;margin:0;">Até:</label><input type="date" id="sysDataFim" class="form-control input-sm" value="<?php echo $data_ref; ?>"></div>
                        <div class="col-md-4"><label style="font-size:10px;margin:0;">Filtrar:</label><input type="text" id="searchSys" class="search-input" placeholder="Buscar histórico ou valor..."></div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table-conciliacao">
                        <thead><tr><th>Data</th><th>Descrição / Doc</th><th>Valor</th><th><iconify-icon icon="icon-park-outline:check-small"></iconify-icon></th></tr></thead>
                        <tbody id="listaSistema"></tbody>
                    </table>
                </div>
                <div class="pane-footer"><span>Total Selecionado:</span><span id="somaSistemaStr">R$ 0,00</span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="pane-card">
                <div class="bank-header" id="headerBan" style="background: #f5f5f5; color: #333;">
                    <iconify-icon icon="icon-park-outline:api" style="font-size: 24px;"></iconify-icon>
                    <span>Carregando Open Finance...</span>
                </div>
                <div class="pane-header">
                    <div class="pane-header-top">
                        <h4>Extrato Bancário <span style="font-size: 11px; color: #888;" id="resumoBanco">(0)</span></h4>
                        <div>
                            <span class="action-link" id="btnSelectAllBan">Selecionar Todos</span>
                            <span class="action-link" id="btnAtualizarListas"><iconify-icon icon="icon-park-outline:refresh"></iconify-icon> Recarregar</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><label style="font-size:10px;margin:0;">Data Ref:</label><input type="date" class="form-control input-sm" value="<?php echo $data_ref; ?>" readonly></div>
                        <div class="col-md-8"><label style="font-size:10px;margin:0;">Filtrar:</label><input type="text" id="searchBan" class="search-input" placeholder="Buscar histórico ou valor..."></div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table-conciliacao">
                        <thead><tr><th style="width: 30px;">Ação</th><th>Data</th><th>Histórico Bancário</th><th>Valor</th><th><iconify-icon icon="icon-park-outline:check-small"></iconify-icon></th></tr></thead>
                        <tbody id="listaBanco"></tbody>
                    </table>
                </div>
                <div class="pane-footer"><span>Total Selecionado:</span><span id="somaBancoStr">R$ 0,00</span></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNovaConta" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document" style="width: 70%; height: 90vh; margin: 5vh auto;">
        <div class="modal-content" style="background: transparent; box-shadow: none; border: none; height: 100%;">
            <button type="button" class="close" data-dismiss="modal" style="position: absolute; right: -35px; top: 0; color: white; opacity: 1; font-size: 35px; text-shadow: 0 2px 4px rgba(0,0,0,0.5); z-index: 9999;">&times;</button>
            <div class="modal-body" style="padding: 0; height: 100%; overflow: hidden; border-radius: 8px; background: #fff;">
                <iframe id="iframeNovaConta" src="" style="width: 100%; height: 100%; border: none; display: block;" scrolling="yes"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script src="bsb/plugins/bootstrap/js/bootstrap.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    'use strict';

    const baseUrl = window.location.hostname !== 'localhost' ? 'https://portal.mrksolucoes.com.br/api/v1/financeiro.php' : 'http://localhost/portal-mrk/api/v1/financeiro.php';
    const token = '<?php echo $token; ?>';
    const system_unit_id = '<?php echo $unit_id; ?>';
    const financeiro_banco_id = '<?php echo $banco_id; ?>';
    const data_fixa = '<?php echo $data_ref; ?>';
    const usuario_id = '<?php echo $username; ?>';

    let movsSistema = [];
    let extratoBanco = [];
    let selSys = new Set();
    let selBan = new Set();

    let isDragging = false;
    let dragTargetState = false;
    let dragTipo = null;

    const formatCurrency = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);
    const parseDateBr = (d) => d.split('-').reverse().join('/');

    // Base de marcas do Open Finance
    const LOGO_TOKEN = 'pk_P0veRzAeSuCornQ7nz584Q';
    const bankBrands = {
        "001": { name: "Banco do Brasil", bg: "#FCE80A", text: "#0038A8", domain: "bb.com.br" },
        "033": { name: "Santander", bg: "#EC0000", text: "#FFFFFF", domain: "santander.com.br" },
        "104": { name: "Caixa Econômica", bg: "#005CA9", text: "#F39200", domain: "caixa.gov.br" },
        "237": { name: "Bradesco", bg: "#CC092F", text: "#FFFFFF", domain: "bradesco.com.br" },
        "260": { name: "Nubank", bg: "#820AD1", text: "#FFFFFF", domain: "nubank.com.br" },
        "341": { name: "Itaú", bg: "#EC7000", text: "#FFFFFF", domain: "itau.com.br" },
        "748": { name: "Sicredi", bg: "#00A13A", text: "#FFFFFF", domain: "sicredi.com.br" },
        "756": { name: "Sicoob", bg: "#003641", text: "#00AE9D", domain: "sicoob.com.br" },
        "default": { name: "Banco Open Finance", bg: "#E3F2FD", text: "#1565C0", domain: "" }
    };

    // FUNÇÃO GLOBAL (Para o Iframe chamar e recarregar os dados do sistema)
    window.buscar = function() {
        carregarSistema();
    };

    window.abrirNovaConta = function(extraParams = '') {
        // Adicionado o &banco_id=${financeiro_banco_id} que contém o CÓDIGO do banco
        let url = `financeiroConta.html?token=${token}&unit_id=${system_unit_id}&username=${usuario_id}&banco_id=${financeiro_banco_id}`;

        if(extraParams) {
            url += '&' + extraParams;
        } else {
            url += '&modo=create';
        }

        $('#iframeNovaConta').attr('src', url);
        $('#modalNovaConta').modal('show');
    };

    $(document).ready(() => {
        carregarInfoBancos();
        carregarListas();

        $('#sysDataIni, #sysDataFim').change(() => carregarSistema());
        $('#btnAtualizarListas').click(() => carregarBanco()); // Recarrega só o banco ( OF )
        $('#btnAtualizarSys').click(() => carregarSistema()); // Recarrega só o sistema

        // Pesquisa em tempo real
        $('#searchSys').on('input', function() { filterTable('#listaSistema', $(this).val()); });
        $('#searchBan').on('input', function() { filterTable('#listaBanco', $(this).val()); });

        $('#btnSelectAllSys').click(function() { toggleSelectAll('sys'); });
        $('#btnSelectAllBan').click(function() { toggleSelectAll('ban'); });

        // --- LÓGICA DE DRAG TO SELECT ---
        $(document).on('mousedown', '.row-item', function(e) {
            // Ignora se clicou no botão de '+'
            if ($(e.target).closest('a, button, .btn-add-trans').length) return;

            isDragging = true;
            dragTipo = $(this).data('tipo'); // 'sys' ou 'ban'
            const id = $(this).data('id');
            const set = dragTipo === 'sys' ? selSys : selBan;

            // Inverte o estado da primeira linha clicada
            dragTargetState = !set.has(id);
            marcarLinha($(this), id, dragTipo, dragTargetState);
        });

        $(document).on('mouseenter', '.row-item', function() {
            if (isDragging && dragTipo === $(this).data('tipo')) {
                const id = $(this).data('id');
                marcarLinha($(this), id, dragTipo, dragTargetState);
            }
        });

        $(window).on('mouseup', function() {
            if (isDragging) {
                isDragging = false;
                dragTipo = null;
                calcularSomas();
                aplicarSmartMatch();
            }
        });

        // Evento Botão de Nova Conta a partir da transação (Delegação de evento)
        $(document).on('click', '.btn-add-trans', function(e) {
            e.stopPropagation(); // Evita que a linha seja selecionada
            const id = $(this).data('id');
            const desc = $(this).data('desc');
            const val = $(this).data('val');
            const date = $(this).data('date');
            const tipo = $(this).data('tipo');

            const params = `modo=transacao&transacao_id=${id}&transacao_desc=${desc}&transacao_valor=${val}&transacao_data=${date}&transacao_tipo=${tipo}`;
            abrirNovaConta(params);
        });

        // Conciliar
        $('#btnConciliar').click(async function() {
            if(selSys.size === 0 || selBan.size === 0) return;
            Swal.fire({ title: 'Conciliando...', didOpen: () => Swal.showLoading() });

            try {
                const res = await axios.post(baseUrl, {
                    method: 'executarConciliacaoMatch', token,
                    data: {
                        system_unit_id,
                        financeiro_banco_id,
                        data_ref: data_fixa,
                        usuario_id,
                        sys_ids: Array.from(selSys),
                        ban_ids: Array.from(selBan)
                    }
                });
                if(res.data.success) {
                    Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Lançamentos conciliados.', timer: 1500, showConfirmButton: false});
                    selSys.clear(); selBan.clear();
                    carregarListas();
                } else { Swal.fire('Erro', res.data.message, 'error'); }
            } catch (e) { Swal.fire('Erro', 'Falha ao conciliar.', 'error'); }
        });

        // Fechar Dia
        $('#btnConfirmarDia').click(async function() {
            Swal.fire({
                title: 'Confirmar Dia?', text: "O dia será marcado como concluído no calendário.",
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Sim, confirmar!'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const res = await axios.post(baseUrl, {
                            method: 'fecharDiaConciliacao', token,
                            data: { system_unit_id, financeiro_banco_id, data_conciliacao: data_fixa, usuario_id }
                        });
                        if(res.data.success) {
                            Swal.fire('Fechado!', 'Dia conciliado com sucesso.', 'success').then(() => {
                                window.location.href = `listConciliacao.html?username=${usuario_id}&token=${token}&unit_id=${system_unit_id}&financeiro_banco_id=${financeiro_banco_id}`;
                            });
                        }
                    } catch (e) { Swal.fire('Erro', 'Falha ao fechar o dia.', 'error'); }
                }
            });
        });
    });

    // Funções de Tabela & Seleção
    function marcarLinha($tr, id, tipo, checkState) {
        const set = tipo === 'sys' ? selSys : selBan;
        const chk = $tr.find('input[type="checkbox"]');

        if(checkState) {
            set.add(id);
            $tr.addClass('selected');
            chk.prop('checked', true);
        } else {
            set.delete(id);
            $tr.removeClass('selected');
            chk.prop('checked', false);
        }
    }

    async function carregarInfoBancos() {
        try {
            const res = await axios.post(baseUrl, { method: 'getInfoBancosConciliacao', token, data: { system_unit_id, financeiro_banco_id } });
            if (res.data.success) {
                const b = res.data.data;

                // LADO ESQUERDO (SISTEMA): Ícone, Nome e o botão NOVA CONTA alinhado à direita
                $('#headerSys').html(`
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <iconify-icon icon="icon-park-outline:bank" style="font-size: 20px;"></iconify-icon>
                        <span><b>${b.sys_nome}</b> <small style="font-weight:400;">(Ag: ${b.sys_agencia || '-'} / Cc: ${b.sys_conta || '-'})</small></span>
                    </div>
                    <button class="action-btn-sm" onclick="abrirNovaConta()" style="padding: 5px 15px; font-size: 12px; background: var(--mrk-blue); color: #fff; border: none; border-radius: 4px;">
                        <iconify-icon icon="icon-park-outline:plus" style="vertical-align: sub;"></iconify-icon> Nova Conta
                    </button>
                `);

                if (b.of_codigo) {
                    const brandCode = String(b.of_codigo).padStart(3, '0');
                    const brand = bankBrands[brandCode] || bankBrands["default"];
                    const logoUrl = brand.domain ? `https://img.logo.dev/${brand.domain}?token=${LOGO_TOKEN}&size=64&format=png` : '';
                    const logoHtml = logoUrl ? `<img src="${logoUrl}" class="bank-logo" onerror="this.style.display='none'">` : `<iconify-icon icon="icon-park-outline:api" style="font-size: 20px;"></iconify-icon>`;

                    const contaOF = b.of_conta_digito ? `${b.of_conta}-${b.of_conta_digito}` : b.of_conta;

                    // LADO DIREITO (OPEN FINANCE): Apenas Ícone e Nome
                    $('#headerBan').css({ 'background-color': brand.bg, 'color': brand.text }).html(`
                        <div style="display: flex; align-items: center; gap: 12px;">
                            ${logoHtml}
                            <span><b>${b.of_nome || brand.name}</b> <small style="font-weight:400;">(Ag: ${b.of_agencia || '-'} / Cc: ${contaOF || '-'})</small></span>
                        </div>
                    `);
                }
            }
        } catch(e) { console.error('Info bancos falhou'); }
    }

    async function carregarListas() {
        $('.row-item').removeClass('highlight-match');
        await Promise.all([carregarSistema(), carregarBanco()]);
        verificarBotaoFechamento();
    }

    async function carregarSistema() {
        try {
            const res = await axios.post(baseUrl, {
                method: 'getMovimentosSistemaAbertos', token,
                data: { system_unit_id, financeiro_banco_id, data_ini: $('#sysDataIni').val(), data_fim: $('#sysDataFim').val() }
            });
            movsSistema = res.data.data || [];

            const validIds = new Set(movsSistema.map(m => m.id));
            selSys.forEach(id => { if(!validIds.has(id)) selSys.delete(id); });

            renderTabelaSistema(movsSistema);
            atualizarResumoCabecalho(movsSistema, '#resumoSistema');
            calcularSomas();
            aplicarSmartMatch();
        } catch(e) { console.error('Erro sys'); }
    }

    async function carregarBanco() {
        try {
            const res = await axios.post(baseUrl, {
                method: 'getExtratoBancoAbertos', token,
                data: { system_unit_id, financeiro_banco_id, data_ref: data_fixa }
            });
            extratoBanco = res.data.data || [];

            const validIds = new Set(extratoBanco.map(m => m.id));
            selBan.forEach(id => { if(!validIds.has(id)) selBan.delete(id); });

            renderTabelaBanco(extratoBanco);
            atualizarResumoCabecalho(extratoBanco, '#resumoBanco');
            calcularSomas();
            aplicarSmartMatch();
        } catch(e) { console.error('Erro ban'); }
    }

    function renderTabelaSistema(dados) {
        const tb = $('#listaSistema').empty();
        if(!dados.length) { tb.append('<tr><td colspan="4" style="text-align:center; padding:20px; color:#999;">Nenhum registro no sistema.</td></tr>'); return; }

        dados.forEach(d => {
            const valNum = parseFloat(d.valor);
            const valClass = valNum >= 0 ? 'valor-entrada' : 'valor-saida';
            const extraDoc = d.doc ? `<br><small style="color:#888;">Doc: ${d.doc}</small>` : '';
            const isChecked = selSys.has(d.id);

            tb.append(`
                <tr class="row-item row-sys ${isChecked ? 'selected' : ''}" data-id="${d.id}" data-tipo="sys" data-valor="${valNum}" data-desc="${(d.descricao || '').toLowerCase()}">
                    <td>${parseDateBr(d.data)}</td>
                    <td><b>${d.descricao}</b>${extraDoc}</td>
                    <td class="${valClass}">${formatCurrency(valNum)}</td>
                    <td><input type="checkbox" class="custom-checkbox chk-sys" data-id="${d.id}" data-valor="${valNum}" ${isChecked ? 'checked' : ''}></td>
                </tr>
            `);
        });
    }

    function renderTabelaBanco(dados) {
        const tb = $('#listaBanco').empty();
        if(!dados.length) { tb.append('<tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">Extrato limpo / conciliado.</td></tr>'); return; }

        dados.forEach(d => {
            const valNum = parseFloat(d.valor);
            const isCredit = valNum >= 0;
            const valClass = isCredit ? 'valor-entrada' : 'valor-saida';
            const badgeDc = isCredit ? '<span class="badge-dc badge-c">C</span>' : '<span class="badge-dc badge-d">D</span>';
            const tipoPayload = isCredit ? 'credit' : 'debit';
            const descEncoded = encodeURIComponent(d.descricao || '');
            const isChecked = selBan.has(d.id);

            tb.append(`
                <tr class="row-item row-ban ${isChecked ? 'selected' : ''}" data-id="${d.id}" data-tipo="ban" data-valor="${valNum}" data-desc="${(d.descricao || '').toLowerCase()}">
                    <td><a href="javascript:void(0)" class="btn-add-trans" data-id="${d.id}" data-desc="${descEncoded}" data-val="${Math.abs(valNum)}" data-date="${d.data}" data-tipo="${tipoPayload}" title="Nova Conta com esta transação"><iconify-icon icon="icon-park-outline:plus"></iconify-icon></a></td>
                    <td>${parseDateBr(d.data)}</td>
                    <td><b>${d.descricao}</b></td>
                    <td class="${valClass}">${formatCurrency(valNum)} ${badgeDc}</td>
                    <td><input type="checkbox" class="custom-checkbox chk-ban" data-id="${d.id}" data-valor="${valNum}" ${isChecked ? 'checked' : ''}></td>
                </tr>
            `);
        });
    }

    // --- FUNÇÕES UTILITÁRIAS ---

    function filterTable(selector, term) {
        term = term.toLowerCase();
        $(selector).find('tr.row-item').each(function() {
            const rowText = $(this).text().toLowerCase();
            const rowVal = $(this).data('valor').toString();
            if (rowText.includes(term) || rowVal.includes(term)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    function toggleSelectAll(tipo) {
        const set = tipo === 'sys' ? selSys : selBan;
        const boxes = $(`.chk-${tipo}:visible`);

        let checkedCount = 0;
        boxes.each(function() { if($(this).prop('checked')) checkedCount++; });

        const checkAll = checkedCount !== boxes.length;

        boxes.each(function() {
            const id = $(this).data('id');
            const $tr = $(this).closest('tr');

            if (checkAll) { set.add(id); $tr.addClass('selected'); $(this).prop('checked', true); }
            else { set.delete(id); $tr.removeClass('selected'); $(this).prop('checked', false); }
        });

        calcularSomas();
        aplicarSmartMatch();
    }

    function calcularSomas() {
        let sumSys = 0; let sumBan = 0;

        selSys.forEach(id => { const el = $(`.row-sys[data-id="${id}"]`); if(el.length) sumSys += parseFloat(el.data('valor')); });
        selBan.forEach(id => { const el = $(`.row-ban[data-id="${id}"]`); if(el.length) sumBan += parseFloat(el.data('valor')); });

        $('#somaSistemaStr').text(formatCurrency(sumSys));
        $('#somaBancoStr').text(formatCurrency(sumBan));

        const roundSys = Math.round(Math.abs(sumSys) * 100);
        const roundBan = Math.round(Math.abs(sumBan) * 100);

        if(selSys.size > 0 && selBan.size > 0 && roundSys === roundBan) {
            $('#btnConciliar').addClass('active').text('CONCILIAR SELECIONADOS');
        } else {
            $('#btnConciliar').removeClass('active');
            if(selSys.size > 0 || selBan.size > 0) {
                $('#btnConciliar').text(`Diferença: ${formatCurrency(Math.abs(sumSys - sumBan))}`);
            } else {
                $('#btnConciliar').text('CONCILIAR SELECIONADOS');
            }
        }
    }

    // --- SMART MATCH ALGORITHM ---
    function aplicarSmartMatch() {
        $('.row-item').removeClass('highlight-match');

        let targetContainer = null; // Variável para saber qual lado deve rolar

        if (selSys.size > 0 && selBan.size === 0) {
            selSys.forEach(id => {
                const el = $(`.row-sys[data-id="${id}"]`);
                highlightMatches('.row-ban', Math.abs(el.data('valor')), el.data('desc'));
            });
            targetContainer = '#listaBanco'; // O match aconteceu no lado do banco
        }
        else if (selBan.size > 0 && selSys.size === 0) {
            selBan.forEach(id => {
                const el = $(`.row-ban[data-id="${id}"]`);
                highlightMatches('.row-sys', Math.abs(el.data('valor')), el.data('desc'));
            });
            targetContainer = '#listaSistema'; // O match aconteceu no lado do sistema
        }

        // --- NOVO: AUTO-SCROLL SUAVE ---
        if (targetContainer) {
            // Busca o primeiro item que foi marcado de amarelo naquele container
            const firstMatch = $(targetContainer).find('.highlight-match').first();

            if (firstMatch.length) {
                // Rola a tabela até o item e centraliza ele na visão do usuário
                firstMatch[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }
    }

    function highlightMatches(targetSelector, targetValAbs, targetDesc) {
        $(targetSelector).each(function() {
            const thisVal = Math.abs(parseFloat($(this).data('valor')));
            const thisDesc = $(this).data('desc');

            if (Math.round(thisVal * 100) === Math.round(targetValAbs * 100)) {
                $(this).addClass('highlight-match');
            }
            else if (checkTextSimilarity(thisDesc, targetDesc)) {
                $(this).addClass('highlight-match');
            }
        });
    }

    function checkTextSimilarity(str1, str2) {
        if(!str1 || !str2) return false;
        const words1 = str1.split(/\W+/).filter(w => w.length > 3);
        const words2 = str2.split(/\W+/).filter(w => w.length > 3);
        if (words1.length === 0 || words2.length === 0) return false;
        return words1.some(w => words2.includes(w));
    }

    function atualizarResumoCabecalho(dados, selector) {
        let soma = 0; dados.forEach(d => soma += parseFloat(d.valor));
        $(selector).text(`(${dados.length} regs | ${formatCurrency(soma)})`);
    }

    function verificarBotaoFechamento() {
        if (extratoBanco.length === 0 && movsSistema.length >= 0) {
            $('#btnConfirmarDia').show();
        } else {
            $('#btnConfirmarDia').hide();
        }
    }
</script>
</body>
</html>