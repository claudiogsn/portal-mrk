<?php
session_name('PHPSESSID_MRKSolutions');
session_start();

if (!isset($_SESSION['MRKSolutions'])) {
    die("Sessão expirada. Faça login novamente.");
}

$appData = $_SESSION['MRKSolutions'];
$token   = $appData['sessionid']  ?? '';
$unit_id = $appData['userunitid'] ?? '';

if (empty($token) || empty($unit_id)) {
    die("Acesso negado. Unidade não identificada.");
}

$url_account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Extrato Bancário - Open Finance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="bsb/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

    <link href="bsb/plugins/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="bsb/plugins/sweetalert/sweetalert.css" rel="stylesheet">
    <link href="bsb/css/style.css" rel="stylesheet">
    <link href="style/mrk.css" rel="stylesheet">

    <style>
        body { background-color: #f9f9f9; font-family: 'Poppins', sans-serif; overflow-x: hidden; }

        .card { box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eee; border-top: 3px solid var(--mrk-blue) !important; margin-bottom: 20px; border-radius: 6px; }
        .header-flex { display: flex; align-items: center; justify-content: space-between; padding: 20px; border-bottom: 1px solid #eee; }
        .header-flex h2 { margin: 0; font-family: 'Kanit', sans-serif; font-size: 20px; color: var(--mrk-blue); display: flex; align-items: center; }

        /* Botão Voltar */
        .btn-voltar { display: inline-flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #ddd; color: #555; font-weight: 600; font-size: 13px; padding: 8px 16px; border-radius: 8px; cursor: pointer; transition: all 0.2s; text-decoration: none; }
        .btn-voltar:hover { background: #f5f5f5; color: var(--mrk-blue); border-color: var(--mrk-blue); text-decoration: none; }
        .btn-voltar iconify-icon { font-size: 18px; }

        /* ====== CUSTOM BANK SELECTOR ====== */
        .bank-selector-wrapper { position: relative; }
        .bank-selector-trigger {
            display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #ccc;
            border-radius: 8px; padding: 8px 14px; cursor: pointer; transition: all 0.2s; height: 42px; min-width: 280px;
        }
        .bank-selector-trigger:hover { border-color: var(--mrk-blue); }
        .bank-selector-trigger.active { border-color: var(--mrk-blue); box-shadow: 0 0 0 3px rgba(11, 70, 172, 0.1); border-radius: 8px 8px 0 0; }
        .bank-selector-trigger img { width: 26px; height: 26px; border-radius: 5px; object-fit: contain; }
        .bank-selector-trigger .trigger-text { flex: 1; font-size: 13px; color: #333; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bank-selector-trigger .trigger-placeholder { color: #999; }
        .bank-selector-trigger .trigger-arrow { font-size: 18px; color: #999; transition: transform 0.2s; }
        .bank-selector-trigger.active .trigger-arrow { transform: rotate(180deg); }

        .bank-selector-dropdown {
            display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000;
            background: #fff; border: 1px solid var(--mrk-blue); border-top: none;
            border-radius: 0 0 10px 10px; box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            max-height: 320px; overflow: hidden;
        }
        .bank-selector-dropdown.open { display: block; }
        .bank-selector-search { padding: 10px 14px; border-bottom: 1px solid #eee; }
        .bank-selector-search input {
            width: 100%; border: 1px solid #e0e0e0; border-radius: 6px; padding: 8px 12px; font-size: 13px;
            outline: none; background: #fafafa;
        }
        .bank-selector-search input:focus { border-color: var(--mrk-blue); background: #fff; }

        .bank-selector-list { overflow-y: auto; max-height: 250px; padding: 6px; }
        .bank-option {
            display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 8px;
            cursor: pointer; transition: all 0.15s; margin-bottom: 2px;
        }
        .bank-option:hover { background: #f0f4ff; }
        .bank-option.selected { background: #e8eeff; border: 1px solid rgba(11, 70, 172, 0.2); }
        .bank-option img { width: 36px; height: 36px; border-radius: 8px; object-fit: contain; border: 1px solid #eee; background: #fff; padding: 3px; flex-shrink: 0; }
        .bank-option .bank-option-fallback {
            width: 36px; height: 36px; border-radius: 8px; background: #e9ecef; display: flex;
            align-items: center; justify-content: center; font-size: 18px; color: #aaa; flex-shrink: 0;
        }
        .bank-option-info { flex: 1; min-width: 0; }
        .bank-option-name { font-weight: 600; font-size: 13px; color: #333; font-family: 'Kanit', sans-serif; }
        .bank-option-detail { font-size: 11px; color: #888; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bank-option-check { font-size: 18px; color: var(--mrk-blue); display: none; }
        .bank-option.selected .bank-option-check { display: block; }

        .bank-selector-empty { padding: 25px; text-align: center; color: #aaa; font-size: 13px; }

        /* Overlay para fechar o dropdown */
        .bank-selector-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 999; }
        .bank-selector-overlay.open { display: block; }

        /* Filtros */
        .filter-section { background-color: #fcfcfc; padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap; }
        .filter-row-2 { background-color: #fcfcfc; padding: 10px 20px 15px; border-bottom: 1px solid #eee; display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; flex: 1; min-width: 150px; }
        .filter-group.btn-group-filter { flex: 0; min-width: 120px; }
        .filter-group label { font-size: 11px; font-weight: 600; color: #555; text-transform: uppercase; margin-bottom: 5px; }
        .filter-group input, .filter-group select { border-radius: 6px; border: 1px solid #ccc; padding: 8px 12px; font-size: 13px; outline: none; transition: 0.2s; background: #fff; height: 38px;}
        .filter-group input:focus, .filter-group select:focus { border-color: var(--mrk-blue); box-shadow: 0 0 5px rgba(11, 70, 172, 0.2); }

        .search-highlight { background-color: #fff3cd; padding: 1px 3px; border-radius: 3px; }

        /* KPIs */
        .body-content { padding: 20px; }
        .kpi-card { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #eee; position: relative; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.3s; height: 100%; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .kpi-card.blue { border-left: 4px solid var(--mrk-blue); }
        .kpi-card.green { border-left: 4px solid var(--mrk-green); }
        .kpi-card.red { border-left: 4px solid #F44336; }
        .kpi-title { font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px; }
        .kpi-value { font-size: 22px; font-weight: 700; font-family: 'Kanit', sans-serif; color: var(--mrk-black); }
        .kpi-bg-icon { position: absolute; right: 15px; top: 15px; font-size: 28px; opacity: 0.15; }
        .kpi-card.blue .kpi-bg-icon { color: var(--mrk-blue); }
        .kpi-card.green .kpi-bg-icon { color: var(--mrk-green); }
        .kpi-card.red .kpi-bg-icon { color: #F44336; }

        /* Tabela */
        .table thead th { font-family: 'Kanit', sans-serif; color: var(--mrk-blue); font-weight: 600; font-size: 12px; background-color: #f8f9fa; border-bottom: 2px solid #e9ecef; white-space: nowrap; padding: 12px 10px; }
        .table tbody td { font-size: 13px; vertical-align: middle !important; border-top: 1px solid #f1f1f1; padding: 12px 10px; }
        .table tbody tr:hover { background-color: #fdfdfd; }

        .val-credit { color: var(--mrk-green); font-weight: 600; }
        .val-debit { color: #F44336; font-weight: 600; }
        .tx-icon { margin-right: 5px; font-size: 16px; vertical-align: text-bottom; }

        .skeleton-wrapper { display: none; margin-top: 20px; }
        .skeleton { background: #e0e0e0; border-radius: 4px; margin-bottom: 10px; animation: pulse 1.5s infinite; }
        .sk-row { height: 45px; width: 100%; margin-bottom: 8px; }
        @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }

        .result-counter { font-size: 12px; color: #888; padding: 5px 0; }

        .bank-icon-display { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: #555; margin-left: 15px; padding: 4px 12px; background: #f0f4ff; border-radius: 20px; font-family: 'Poppins', sans-serif; font-weight: 500; }
        .bank-logo { width: 22px; height: 22px; border-radius: 4px; object-fit: contain; vertical-align: middle; }
    </style>
</head>

<body>

<div class="bank-selector-overlay" id="bankOverlay"></div>

<div class="container-fluid">
    <div class="card">
        <div class="header-flex">
            <h2>
                <a href="OFAccounts.php" class="btn-voltar" title="Voltar para Contas">
                    <iconify-icon icon="icon-park-outline:left"></iconify-icon>
                </a>
                <iconify-icon icon="icon-park-outline:list-view" width="24" style="margin: 0 8px;"></iconify-icon>
                EXTRATO BANCÁRIO
                <span id="bankBadge" class="bank-icon-display" style="display: none;"></span>
            </h2>
        </div>

        <div class="filter-section">
            <div class="filter-group" style="flex: 2; min-width: 280px;">
                <label>Conta Bancária</label>
                <div class="bank-selector-wrapper" id="bankSelectorWrapper">
                    <div class="bank-selector-trigger" id="bankTrigger">
                        <span class="trigger-text trigger-placeholder">Carregando contas...</span>
                        <iconify-icon icon="icon-park-outline:down" class="trigger-arrow"></iconify-icon>
                    </div>
                    <div class="bank-selector-dropdown" id="bankDropdown">
                        <div class="bank-selector-search">
                            <input type="text" id="bankSearchInput" placeholder="Buscar banco ou conta..." autocomplete="off">
                        </div>
                        <div class="bank-selector-list" id="bankList"></div>
                    </div>
                </div>
                <input type="hidden" id="account_id" value="">
            </div>
            <div class="filter-group">
                <label>Data Inicial</label>
                <input type="date" id="date_start">
            </div>
            <div class="filter-group">
                <label>Data Final</label>
                <input type="date" id="date_end">
            </div>
            <div class="filter-group btn-group-filter">
                <button class="btn btn-primary waves-effect" id="btnBuscar" style="background-color: var(--mrk-blue); border: none; height: 42px; width: 100%; font-weight: 600; border-radius: 6px;">
                    <iconify-icon icon="icon-park-outline:search" style="vertical-align: sub; font-size: 14px;"></iconify-icon> BUSCAR
                </button>
            </div>
        </div>

        <div class="filter-row-2">
            <div class="filter-group" style="flex: 0 0 180px; min-width: 180px;">
                <label>Tipo</label>
                <select id="type_filter">
                    <option value="">Todos</option>
                    <option value="credit">Entradas</option>
                    <option value="debit">Saídas</option>
                </select>
            </div>
            <div class="filter-group" style="flex: 1;">
                <label>Pesquisar</label>
                <input type="text" id="search_filter" placeholder="Buscar por descrição ou valor..." autocomplete="off">
            </div>
        </div>

        <div class="body-content">
            <div class="row clearfix" style="margin-bottom: 20px;">
                <div class="col-md-4">
                    <div class="kpi-card green">
                        <span class="kpi-title">Total Entradas</span>
                        <span class="kpi-value" id="kpiEntradas">R$ 0,00</span>
                        <iconify-icon icon="icon-park-outline:trend-two" class="kpi-bg-icon"></iconify-icon>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kpi-card red">
                        <span class="kpi-title">Total Saídas</span>
                        <span class="kpi-value" id="kpiSaidas">R$ 0,00</span>
                        <iconify-icon icon="icon-park-outline:trend-one" class="kpi-bg-icon"></iconify-icon>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kpi-card blue">
                        <span class="kpi-title">Saldo do Período</span>
                        <span class="kpi-value" id="kpiSaldo">R$ 0,00</span>
                        <iconify-icon icon="icon-park-outline:wallet" class="kpi-bg-icon"></iconify-icon>
                    </div>
                </div>
            </div>

            <div id="result-counter" class="result-counter" style="display: none;"></div>

            <div id="sk-table" class="skeleton-wrapper">
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
            </div>

            <div class="table-responsive">
                <table class="table" id="tabela-extrato">
                    <thead>
                    <tr>
                        <th width="100">Data</th>
                        <th width="120">Tipo</th>
                        <th>Descrição da Transação</th>
                        <th width="150" class="text-right">Valor</th>
                        <th width="120" class="text-center">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td colspan="5" class="text-center muted" style="padding: 40px;">Selecione uma conta e clique em buscar.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalNovaConta" tabindex="-1">
    <div class="modal-dialog modal-flutuante">
        <div class="modal-content" style="border: none; border-radius: 12px; background: transparent; box-shadow: none;">
            <div class="modal-body" style="padding: 0 !important; overflow: hidden; border-radius: 12px;">
                <iframe id="iframeNovaConta" class="modal-iframe" style="width: 100%; height: 85vh; border: none; display: block; border-radius: 12px; background: #fff;"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script src="bsb/plugins/bootstrap/js/bootstrap.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
    const baseUrl = window.location.hostname !== 'localhost'
        ? 'https://portal.mrksolucoes.com.br/api/v1/open_finance.php'
        : 'http://localhost/portal-mrk/api/v1/open_finance.php';

    const token   = "<?php echo $token; ?>";
    const system_unit_id = "<?php echo $unit_id; ?>";
    const urlAccountId   = "<?php echo $url_account_id; ?>";

    const LOGO_TOKEN = 'pk_P0veRzAeSuCornQ7nz584Q';
    function bankLogoUrl(domain) {
        return domain ? `https://img.logo.dev/${domain}?token=${LOGO_TOKEN}&size=64&format=png` : '';
    }

    const bankBrands = {
        "001": { name: "Banco do Brasil",  domain: "bb.com.br" },
        "003": { name: "Banco da Amazônia", domain: "bancoamazonia.com.br" },
        "004": { name: "Banco do Nordeste", domain: "bnb.gov.br" },
        "021": { name: "Banestes",          domain: "banestes.com.br" },
        "033": { name: "Santander",         domain: "santander.com.br" },
        "041": { name: "Banrisul",          domain: "banrisul.com.br" },
        "070": { name: "BRB",              domain: "brb.com.br" },
        "077": { name: "Banco Inter",      domain: "inter.co" },
        "104": { name: "Caixa Econômica",  domain: "caixa.gov.br" },
        "136": { name: "Unicred",          domain: "unicred.com.br" },
        "208": { name: "BTG Pactual",      domain: "btgpactual.com" },
        "212": { name: "Banco Original",   domain: "original.com.br" },
        "237": { name: "Bradesco",         domain: "bradesco.com.br" },
        "260": { name: "Nubank",           domain: "nubank.com.br" },
        "290": { name: "PagSeguro",        domain: "pagseguro.uol.com.br" },
        "318": { name: "Banco BMG",        domain: "bancobmg.com.br" },
        "323": { name: "Mercado Pago",     domain: "mercadopago.com.br" },
        "329": { name: "QI Tech",          domain: "qitech.com.br" },
        "336": { name: "C6 Bank",          domain: "c6bank.com.br" },
        "341": { name: "Itaú",             domain: "itau.com.br" },
        "365": { name: "K8 Fintech",       domain: "" },
        "380": { name: "PicPay",           domain: "picpay.com" },
        "399": { name: "HSBC",             domain: "hsbc.com.br" },
        "403": { name: "Cora",             domain: "cora.com.br" },
        "422": { name: "Safra",            domain: "safra.com.br" },
        "745": { name: "Citibank",         domain: "citibank.com" },
        "748": { name: "Sicredi",          domain: "sicredi.com.br" },
        "755": { name: "Bank of America",  domain: "bankofamerica.com" },
        "756": { name: "Sicoob",           domain: "sicoob.com.br" }
    };

    // ====== STATE ======
    let allTransactions = [];
    let contasCarregadas = [];
    let selectedAccountId = '';
    let dropdownOpen = false;

    // ====== FUNÇÕES EXPOSTAS PARA O IFRAME ======
    // O formulário de finanças dentro do Iframe vai tentar chamar `window.parent.buscar()` após salvar
    window.buscar = function() {
        $('#modalNovaConta').modal('hide');
        // Recarrega o extrato para quem sabe mostrar algum indicador visual de que a transação já foi vinculada
        buscarExtrato();
    };

    // Função que o botão da tabela vai chamar para abrir o modal
    function abrirModalVinculoTransacao(idTransacao, descEncoded, valorOriginal, dataFormatada, tipoTransacao) {
        // Transforma o desc para URL seguro e o valor para absoluto
        let valorAbsoluto = Math.abs(parseFloat(valorOriginal));

        // Monta a URL passando as infos da transação como parâmetro
        let url = `financeiroConta.html?token=${token}&system_unit_id=${system_unit_id}&modo=transacao` +
            `&transacao_id=${encodeURIComponent(idTransacao)}` +
            `&transacao_desc=${descEncoded}` +
            `&transacao_valor=${valorAbsoluto}` +
            `&transacao_data=${dataFormatada}` +
            `&transacao_tipo=${tipoTransacao}`;

        // Altera a origem do iframe e abre o modal
        $('#iframeNovaConta').attr('src', url);
        $('#modalNovaConta').modal('show');
    }

    // Limpa o src do iframe ao fechar para evitar acúmulo em memória
    $('#modalNovaConta').on('hidden.bs.modal', function () {
        $('#iframeNovaConta').attr('src', '');
    });

    // ====== UTILS ======
    function formatarMoeda(v) { return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }
    function formatarDataBR(d) {
        if (!d) return '';
        const p = d.split('T')[0].split('-');
        return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : d;
    }
    function escapeHtml(s) { return s == null ? '' : String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function normalizeText(s) { return String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }

    function toggleLoading(on) {
        if (on) { $('#tabela-extrato tbody').empty(); $('#sk-table').show(); $('#result-counter').hide(); }
        else { $('#sk-table').hide(); }
    }

    // ====== CUSTOM BANK SELECTOR ======
    function toggleDropdown(forceClose) {
        const $trigger  = $('#bankTrigger');
        const $dropdown = $('#bankDropdown');
        const $overlay  = $('#bankOverlay');

        if (forceClose || dropdownOpen) {
            $trigger.removeClass('active');
            $dropdown.removeClass('open');
            $overlay.removeClass('open');
            dropdownOpen = false;
        } else {
            $trigger.addClass('active');
            $dropdown.addClass('open');
            $overlay.addClass('open');
            dropdownOpen = true;
            $('#bankSearchInput').val('').trigger('input').focus();
        }
    }

    function renderBankOptions(contas, filter) {
        const $list = $('#bankList');
        $list.empty();

        const filterNorm = filter ? normalizeText(filter) : '';

        const filtered = contas.filter(c => {
            if (!filterNorm) return true;
            const brand = bankBrands[c.bank_code] || { name: 'Banco', domain: '' };
            const text = normalizeText(`${brand.name} ${c.agency} ${c.account_number} ${c.bank_code}`);
            return text.indexOf(filterNorm) !== -1;
        });

        if (filtered.length === 0) {
            $list.html('<div class="bank-selector-empty"><iconify-icon icon="icon-park-outline:search" style="font-size: 24px; display: block; margin-bottom: 8px;"></iconify-icon>Nenhuma conta encontrada</div>');
            return;
        }

        filtered.forEach(c => {
            const brand   = bankBrands[c.bank_code] || { name: 'Banco', domain: '' };
            const logoUrl = bankLogoUrl(brand.domain);
            const agencia = c.agency_digit ? `${c.agency}-${c.agency_digit}` : c.agency;
            const conta   = c.account_number_digit ? `${c.account_number}-${c.account_number_digit}` : c.account_number;
            const isSelected = String(c.id) === String(selectedAccountId);

            const logoHtml = logoUrl
                ? `<img src="${logoUrl}" alt="${brand.name}" onerror="this.outerHTML='<div class=\\'bank-option-fallback\\'><iconify-icon icon=\\'icon-park-outline:bank\\'></iconify-icon></div>'">`
                : `<div class="bank-option-fallback"><iconify-icon icon="icon-park-outline:bank"></iconify-icon></div>`;

            const optHtml = `
                <div class="bank-option ${isSelected ? 'selected' : ''}" data-id="${c.id}" data-domain="${brand.domain}" data-bank-name="${brand.name}">
                    ${logoHtml}
                    <div class="bank-option-info">
                        <div class="bank-option-name">${brand.name}</div>
                        <div class="bank-option-detail">Ag: ${escapeHtml(agencia)} &middot; Cc: ${escapeHtml(conta)}</div>
                    </div>
                    <iconify-icon icon="icon-park-outline:check-one" class="bank-option-check"></iconify-icon>
                </div>
            `;
            $list.append(optHtml);
        });

        // Click handler
        $list.find('.bank-option').on('click', function() {
            const id     = $(this).data('id');
            const domain = $(this).data('domain');
            const name   = $(this).data('bank-name');
            const detail = $(this).find('.bank-option-detail').text();

            selecionarConta(id, domain, name, detail);
            toggleDropdown(true);
        });
    }

    function selecionarConta(id, domain, bankName, detail) {
        selectedAccountId = String(id);
        $('#account_id').val(id);

        // Atualiza trigger
        const $trigger = $('#bankTrigger');
        const logoUrl  = bankLogoUrl(domain);

        let triggerHtml = '';
        if (logoUrl) {
            triggerHtml += `<img src="${logoUrl}" alt="${bankName}" style="width:26px;height:26px;border-radius:5px;object-fit:contain;" onerror="this.style.display='none'">`;
        }
        triggerHtml += `<span class="trigger-text">${bankName} &middot; ${detail}</span>`;
        triggerHtml += `<iconify-icon icon="icon-park-outline:down" class="trigger-arrow"></iconify-icon>`;
        $trigger.html(triggerHtml);

        // Badge no header
        const $badge = $('#bankBadge');
        if (logoUrl) {
            $badge.html(`<img src="${logoUrl}" class="bank-logo" alt="${bankName}" onerror="this.style.display='none'"> ${bankName}`).show();
        } else {
            $badge.html(bankName).show();
        }

        // Re-render options para atualizar o check
        renderBankOptions(contasCarregadas, $('#bankSearchInput').val());
    }

    // ====== INIT ======
    $(document).ready(() => {
        const hoje = new Date();
        $('#date_start').val(new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().split('T')[0]);
        $('#date_end').val(new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0).toISOString().split('T')[0]);

        carregarContasDisponiveis();

        $('#btnBuscar').on('click', buscarExtrato);
        $('#type_filter').on('change', aplicarFiltrosLocais);
        $('#search_filter').on('input', aplicarFiltrosLocais);

        // Dropdown toggle
        $('#bankTrigger').on('click', () => toggleDropdown());
        $('#bankOverlay').on('click', () => toggleDropdown(true));

        // Pesquisa dentro do dropdown
        $('#bankSearchInput').on('input', function() {
            renderBankOptions(contasCarregadas, $(this).val());
        });

        // Fechar com ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && dropdownOpen) toggleDropdown(true);
        });
    });

    // ====== FILTRO LOCAL ======
    function aplicarFiltrosLocais() {
        const tipoFiltro    = $('#type_filter').val();
        const termoPesquisa = normalizeText($('#search_filter').val().trim());

        let filtered = allTransactions;

        if (tipoFiltro) {
            filtered = filtered.filter(tx => (tx.type || '').toLowerCase() === tipoFiltro);
        }

        if (termoPesquisa) {
            filtered = filtered.filter(tx => {
                const desc     = normalizeText(tx.description || '');
                const valorFmt = normalizeText(formatarMoeda(parseFloat(tx.amount || 0)));
                const valorRaw = String(tx.amount || '');
                return desc.indexOf(termoPesquisa) !== -1 || valorFmt.indexOf(termoPesquisa) !== -1 || valorRaw.indexOf(termoPesquisa) !== -1;
            });
        }

        renderTabela(filtered, termoPesquisa);
    }

    // ====== BACKEND ======
    async function carregarContasDisponiveis() {
        try {
            const res = await axios.post(baseUrl, {
                method: 'openFinanceListLocalAccounts',
                token: token,
                data: { system_unit_id: system_unit_id }
            });

            contasCarregadas = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);

            if (contasCarregadas.length === 0) {
                $('#bankTrigger').html('<span class="trigger-text trigger-placeholder">Nenhuma conta configurada</span><iconify-icon icon="icon-park-outline:down" class="trigger-arrow"></iconify-icon>');
                return;
            }

            // Seta placeholder
            $('#bankTrigger').html('<span class="trigger-text trigger-placeholder">Selecione a conta...</span><iconify-icon icon="icon-park-outline:down" class="trigger-arrow"></iconify-icon>');

            renderBankOptions(contasCarregadas, '');

            // Auto-select
            if (urlAccountId) {
                const found = contasCarregadas.find(c => String(c.id) === String(urlAccountId));
                if (found) {
                    const brand   = bankBrands[found.bank_code] || { name: 'Banco', domain: '' };
                    const agencia = found.agency_digit ? `${found.agency}-${found.agency_digit}` : found.agency;
                    const conta   = found.account_number_digit ? `${found.account_number}-${found.account_number_digit}` : found.account_number;
                    selecionarConta(found.id, brand.domain, brand.name, `Ag: ${agencia} · Cc: ${conta}`);
                    buscarExtrato();
                }
            } else if (contasCarregadas.length === 1) {
                const c     = contasCarregadas[0];
                const brand = bankBrands[c.bank_code] || { name: 'Banco', domain: '' };
                const ag    = c.agency_digit ? `${c.agency}-${c.agency_digit}` : c.agency;
                const cc    = c.account_number_digit ? `${c.account_number}-${c.account_number_digit}` : c.account_number;
                selecionarConta(c.id, brand.domain, brand.name, `Ag: ${ag} · Cc: ${cc}`);
                buscarExtrato();
            }

        } catch (e) {
            console.error("Falha ao carregar contas", e);
            $('#bankTrigger').html('<span class="trigger-text" style="color:#d33;">Erro ao carregar contas</span>');
        }
    }

    async function buscarExtrato() {
        const account_id = $('#account_id').val();
        const date_start = $('#date_start').val();
        const date_end   = $('#date_end').val();

        if (!account_id) { Swal.fire('Atenção', 'Selecione uma conta bancária.', 'warning'); return; }
        if (!date_start || !date_end) { Swal.fire('Atenção', 'Selecione a data inicial e final.', 'warning'); return; }

        $('#type_filter').val('');
        $('#search_filter').val('');
        toggleLoading(true);

        try {
            const res = await axios.post(baseUrl, {
                method: 'listInternalTransactions',
                token: token,
                data: { system_unit_id, account_id, date_start, date_end }
            });

            const transacoes = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);
            allTransactions = transacoes;
            renderTabela(transacoes);

        } catch (e) {
            console.error(e);
            toggleLoading(false);
            allTransactions = [];
            Swal.fire('Erro', 'Falha ao buscar o extrato no servidor.', 'error');
            $('#tabela-extrato tbody').html('<tr><td colspan="5" class="text-center text-danger" style="padding: 30px;">Erro ao carregar dados.</td></tr>');
        }
    }

    // ====== RENDER ======
    function highlightText(text, term) {
        if (!term) return escapeHtml(text);
        const escaped = escapeHtml(text);
        let result = '', i = 0;
        while (i < escaped.length) {
            if (normalizeText(escaped.substring(i, i + term.length)) === term) {
                result += '<span class="search-highlight">' + escaped.substring(i, i + term.length) + '</span>';
                i += term.length;
            } else { result += escaped[i]; i++; }
        }
        return result;
    }

    function renderTabela(lista, searchTerm) {
        toggleLoading(false);
        let totalEntradas = 0, totalSaidas = 0;
        const $tbody = $('#tabela-extrato tbody');
        $tbody.empty();

        if (lista.length === 0) {
            $tbody.append('<tr><td colspan="5" class="text-center muted" style="padding: 40px;"><iconify-icon icon="icon-park-outline:inbox" style="font-size: 30px; color: #ddd; display: block; margin-bottom: 10px;"></iconify-icon>Nenhuma transação encontrada.</td></tr>');
            atualizarKPIs(0, 0);
            $('#result-counter').hide();
            return;
        }

        lista.forEach(tx => {
            const isCredit = tx.type && tx.type.toLowerCase() === 'credit';
            const valor = parseFloat(tx.amount || 0);
            if (isCredit) totalEntradas += valor; else totalSaidas += valor;

            const valClass  = isCredit ? 'val-credit' : 'val-debit';
            const badgeTipo = isCredit
                ? '<span style="background:#E8F5E9;color:#2E7D32;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600;">ENTRADA</span>'
                : '<span style="background:#FFEBEE;color:#C62828;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600;">SAÍDA</span>';
            const icon = isCredit
                ? '<iconify-icon icon="icon-park-outline:arrow-circle-up" class="tx-icon"></iconify-icon>'
                : '<iconify-icon icon="icon-park-outline:arrow-circle-down" class="tx-icon"></iconify-icon>';

            const descHtml = searchTerm ? highlightText(tx.description, searchTerm) : escapeHtml(tx.description);

            // Tratamento especial da descrição para enviar na URL
            const escDesc = encodeURIComponent(tx.description || '');

            // Define o ID correto provido pela API
            const txId = tx.pluggy_transaction_id || tx.id;

            // Botão Ações
            const btnLancar = `<button class="btn btn-sm btn-success" title="Vincular Conta Financeira" style="padding: 4px 10px; font-size: 11px; border-radius: 6px; border: none; box-shadow: 0 2px 4px rgba(46,125,50,0.3);" onclick="abrirModalVinculoTransacao('${txId}', '${escDesc}', '${valor}', '${tx.date}', '${tx.type}')"><iconify-icon icon="icon-park-outline:plus" style="vertical-align: sub; font-size: 13px; margin-right: 3px;"></iconify-icon> Lançar</button>`;

            $tbody.append(`
                <tr>
                    <td class="text-muted" style="font-weight:500;">${formatarDataBR(tx.date)}</td>
                    <td>${badgeTipo}</td>
                    <td style="color:#444;">${descHtml}</td>
                    <td class="text-right ${valClass}">${icon} ${formatarMoeda(valor)}</td>
                    <td class="text-center">${btnLancar}</td>
                </tr>
            `);
        });

        atualizarKPIs(totalEntradas, totalSaidas);

        const total = allTransactions.length, showing = lista.length;
        if (showing < total) {
            $('#result-counter').text(`Exibindo ${showing} de ${total} transações`).show();
        } else {
            $('#result-counter').text(`${total} transações`).show();
        }
    }

    function atualizarKPIs(entradas, saidas) {
        const saldo = entradas - saidas;
        $('#kpiEntradas').text(formatarMoeda(entradas));
        $('#kpiSaidas').text(formatarMoeda(saidas));
        const $s = $('#kpiSaldo').text(formatarMoeda(saldo));
        $s.css('color', saldo > 0 ? 'var(--mrk-green)' : saldo < 0 ? '#F44336' : 'var(--mrk-black)');
    }
</script>

</body>
</html>