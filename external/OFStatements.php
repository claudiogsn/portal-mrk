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
    <title>Solicitações de Extrato 123</title>
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
        .bank-selector-search input { width: 100%; border: 1px solid #e0e0e0; border-radius: 6px; padding: 8px 12px; font-size: 13px; outline: none; background: #fafafa; }
        .bank-selector-search input:focus { border-color: var(--mrk-blue); background: #fff; }

        .bank-selector-list { overflow-y: auto; max-height: 250px; padding: 6px; }
        .bank-option { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 8px; cursor: pointer; transition: all 0.15s; margin-bottom: 2px; }
        .bank-option:hover { background: #f0f4ff; }
        .bank-option.selected { background: #e8eeff; border: 1px solid rgba(11, 70, 172, 0.2); }
        .bank-option img { width: 36px; height: 36px; border-radius: 8px; object-fit: contain; border: 1px solid #eee; background: #fff; padding: 3px; flex-shrink: 0; }
        .bank-option .bank-option-fallback { width: 36px; height: 36px; border-radius: 8px; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #aaa; flex-shrink: 0; }
        .bank-option-info { flex: 1; min-width: 0; }
        .bank-option-name { font-weight: 600; font-size: 13px; color: #333; font-family: 'Kanit', sans-serif; }
        .bank-option-detail { font-size: 11px; color: #888; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bank-option-check { font-size: 18px; color: var(--mrk-blue); display: none; }
        .bank-option.selected .bank-option-check { display: block; }
        .bank-selector-empty { padding: 25px; text-align: center; color: #aaa; font-size: 13px; }
        .bank-selector-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 999; }
        .bank-selector-overlay.open { display: block; }

        /* Filtros */
        .filter-section { background-color: #fcfcfc; padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; flex: 1; min-width: 150px; }
        .filter-group.btn-group-filter { flex: 0; min-width: 120px; }
        .filter-group label { font-size: 11px; font-weight: 600; color: #555; text-transform: uppercase; margin-bottom: 5px; }

        .body-content { padding: 20px; }

        /* Tabela */
        .table thead th { font-family: 'Kanit', sans-serif; color: var(--mrk-blue); font-weight: 600; font-size: 12px; background-color: #f8f9fa; border-bottom: 2px solid #e9ecef; white-space: nowrap; padding: 12px 10px; }
        .table tbody td { font-size: 13px; vertical-align: middle !important; border-top: 1px solid #f1f1f1; padding: 12px 10px; }
        .table tbody tr:hover { background-color: #fdfdfd; }

        .badge-status { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
        .badge-status.processing { background: #FFF8E1; color: #F57F17; border: 1px solid #FFECB3; }
        .badge-status.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
        .badge-status.error { background: #FFEBEE; color: #C62828; border: 1px solid #FFCDD2; }

        .btn-log { background: #f0f4ff; color: var(--mrk-blue); border: 1px solid #d0deff; font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 6px; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-log:hover { background: var(--mrk-blue); color: #fff; }

        .skeleton-wrapper { display: none; margin-top: 20px; }
        .skeleton { background: #e0e0e0; border-radius: 4px; margin-bottom: 10px; animation: pulse 1.5s infinite; }
        .sk-row { height: 45px; width: 100%; margin-bottom: 8px; }
        @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }

        /* Modal customizado (80%) */
        .modal-80 { width: 80% !important; margin: 30px auto; }
        .modal-header { background: #f8f9fa; border-bottom: 1px solid #eee; border-radius: 6px 6px 0 0; }
        .modal-title { font-family: 'Kanit', sans-serif; color: var(--mrk-blue); font-weight: 600; display: flex; align-items: center; gap: 8px; }

        .log-item { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .log-header { background: #fafafa; padding: 12px 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
        .log-header:hover { background: #f5f5f5; }
        .log-method { font-weight: 700; font-family: monospace; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .log-method.POST { background: #E8F5E9; color: #2E7D32; }
        .log-method.GET { background: #E3F2FD; color: #1565C0; }
        .log-method.PUT { background: #FFF3E0; color: #EF6C00; }
        .log-method.DELETE { background: #FFEBEE; color: #C62828; }
        .log-endpoint { font-size: 13px; color: #555; margin-left: 10px; font-family: monospace; }
        .log-status-badge { font-weight: 700; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .log-body { padding: 15px; display: none; background: #fff; }
        .log-box { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; overflow-x: auto; font-family: monospace; font-size: 12px; line-height: 1.5; margin-bottom: 15px; max-height: 400px; }
        .log-box-title { font-size: 11px; text-transform: uppercase; font-weight: 600; color: #888; margin-bottom: 5px; }

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
                <iconify-icon icon="icon-park-outline:history" width="24" style="margin: 0 8px;"></iconify-icon>
                SOLICITAÇÕES DE EXTRATO
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

            <div class="filter-group btn-group-filter">
                <button class="btn btn-primary waves-effect" id="btnBuscar" style="background-color: var(--mrk-blue); border: none; height: 42px; width: 100%; font-weight: 600; border-radius: 6px;">
                    <iconify-icon icon="icon-park-outline:search" style="vertical-align: sub; font-size: 14px;"></iconify-icon> BUSCAR
                </button>
            </div>
        </div>

        <div class="body-content">
            <div id="sk-table" class="skeleton-wrapper">
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
            </div>

            <div class="table-responsive">
                <table class="table" id="tabela-importacoes">
                    <thead>
                    <tr>
                        <th width="150">Data da Solicitação</th>
                        <th width="180">Protocolo (Unique ID)</th>
                        <th>Período do Extrato</th>
                        <th width="120">Status</th>
                        <th width="100" class="text-center">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td colspan="5" class="text-center muted" style="padding: 40px;">Selecione uma conta para visualizar o histórico de integrações.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalLogs" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-80" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <iconify-icon icon="icon-park-outline:code-computer"></iconify-icon>
                    Logs da Integração <small id="lblProtocolo" style="margin-left: 10px; color: #888; font-family: monospace;"></small>
                </h4>
            </div>
            <div class="modal-body" style="background-color: #f4f6f9; padding: 20px; max-height: 70vh; overflow-y: auto;" id="logs-container">
            </div>
            <div class="modal-footer" style="border-top: 1px solid #eee; background: #fff;">
                <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">FECHAR</button>
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
    function bankLogoUrl(domain) { return domain ? `https://img.logo.dev/${domain}?token=${LOGO_TOKEN}&size=64&format=png` : ''; }

    const bankBrands = {
        "001": { name: "Banco do Brasil",  domain: "bb.com.br" }, "033": { name: "Santander", domain: "santander.com.br" },
        "077": { name: "Banco Inter", domain: "inter.co" }, "104": { name: "Caixa", domain: "caixa.gov.br" },
        "237": { name: "Bradesco", domain: "bradesco.com.br" }, "341": { name: "Itaú", domain: "itau.com.br" },
        "748": { name: "Sicredi", domain: "sicredi.com.br" }, "756": { name: "Sicoob", domain: "sicoob.com.br" }
    };

    let contasCarregadas = [];
    let selectedAccountId = '';
    let dropdownOpen = false;

    // Utilitários
    function formatarDataHora(d) {
        if (!d) return '';
        const dt = new Date(d);
        return dt.toLocaleDateString('pt-BR') + ' às ' + dt.toLocaleTimeString('pt-BR');
    }
    function formatarDataSimples(d) {
        if (!d) return '';
        const p = d.split('T')[0].split('-');
        return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : d;
    }
    function escapeHtml(s) { return s == null ? '' : String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function normalizeText(s) { return String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }

    function toggleLoading(on) {
        if (on) { $('#tabela-importacoes tbody').empty(); $('#sk-table').show(); }
        else { $('#sk-table').hide(); }
    }

    // ====== CUSTOM BANK SELECTOR (Mesma lógica do Extrato) ======
    function toggleDropdown(forceClose) {
        const $trigger = $('#bankTrigger'), $dropdown = $('#bankDropdown'), $overlay = $('#bankOverlay');
        if (forceClose || dropdownOpen) {
            $trigger.removeClass('active'); $dropdown.removeClass('open'); $overlay.removeClass('open'); dropdownOpen = false;
        } else {
            $trigger.addClass('active'); $dropdown.addClass('open'); $overlay.addClass('open'); dropdownOpen = true;
            $('#bankSearchInput').val('').trigger('input').focus();
        }
    }

    function renderBankOptions(contas, filter) {
        const $list = $('#bankList'); $list.empty();
        const filterNorm = filter ? normalizeText(filter) : '';
        const filtered = contas.filter(c => {
            if (!filterNorm) return true;
            const brand = bankBrands[c.bank_code] || { name: 'Banco', domain: '' };
            return normalizeText(`${brand.name} ${c.agency} ${c.account_number} ${c.bank_code}`).includes(filterNorm);
        });

        if (filtered.length === 0) {
            $list.html('<div class="bank-selector-empty">Nenhuma conta encontrada</div>'); return;
        }

        filtered.forEach(c => {
            const brand = bankBrands[c.bank_code] || { name: 'Banco', domain: '' };
            const logoUrl = bankLogoUrl(brand.domain);
            const isSelected = String(c.id) === String(selectedAccountId);
            const agencia = c.agency_digit ? `${c.agency}-${c.agency_digit}` : c.agency;
            const conta = c.account_number_digit ? `${c.account_number}-${c.account_number_digit}` : c.account_number;

            const logoHtml = logoUrl ? `<img src="${logoUrl}" onerror="this.outerHTML='<div class=\\'bank-option-fallback\\'><iconify-icon icon=\\'icon-park-outline:bank\\'></iconify-icon></div>'">` : `<div class="bank-option-fallback"><iconify-icon icon="icon-park-outline:bank"></iconify-icon></div>`;

            $list.append(`
                <div class="bank-option ${isSelected ? 'selected' : ''}" data-id="${c.id}" data-domain="${brand.domain}" data-bank-name="${brand.name}">
                    ${logoHtml}
                    <div class="bank-option-info">
                        <div class="bank-option-name">${brand.name}</div>
                        <div class="bank-option-detail">Ag: ${escapeHtml(agencia)} · Cc: ${escapeHtml(conta)}</div>
                    </div>
                    <iconify-icon icon="icon-park-outline:check-one" class="bank-option-check"></iconify-icon>
                </div>
            `);
        });

        $list.find('.bank-option').on('click', function() {
            selecionarConta($(this).data('id'), $(this).data('domain'), $(this).data('bank-name'), $(this).find('.bank-option-detail').text());
            toggleDropdown(true);
        });
    }

    function selecionarConta(id, domain, bankName, detail) {
        selectedAccountId = String(id); $('#account_id').val(id);
        const logoUrl = bankLogoUrl(domain);

        $('#bankTrigger').html(`${logoUrl ? `<img src="${logoUrl}" style="width:26px;height:26px;border-radius:5px;object-fit:contain;">` : ''}<span class="trigger-text">${bankName} &middot; ${detail}</span><iconify-icon icon="icon-park-outline:down" class="trigger-arrow"></iconify-icon>`);

        if (logoUrl) {
            $('#bankBadge').html(`<img src="${logoUrl}" class="bank-logo"> ${bankName}`).show();
        } else {
            $('#bankBadge').html(bankName).show();
        }
        renderBankOptions(contasCarregadas, $('#bankSearchInput').val());
    }

    $(document).ready(() => {
        carregarContasDisponiveis();

        $('#btnBuscar').on('click', buscarImportacoes);
        $('#bankTrigger').on('click', () => toggleDropdown());
        $('#bankOverlay').on('click', () => toggleDropdown(true));
        $('#bankSearchInput').on('input', function() { renderBankOptions(contasCarregadas, $(this).val()); });
        $(document).on('keydown', e => { if (e.key === 'Escape' && dropdownOpen) toggleDropdown(true); });

        // Delegação de evento para o accordion dos logs
        $(document).on('click', '.log-header', function() {
            $(this).next('.log-body').slideToggle(200);
            const icon = $(this).find('.toggle-icon');
            icon.css('transform', icon.css('transform') === 'none' ? 'rotate(180deg)' : 'none');
        });
    });

    async function carregarContasDisponiveis() {
        try {
            const res = await axios.post(baseUrl, { method: 'openFinanceListLocalAccounts', token, data: { system_unit_id } });
            contasCarregadas = Array.isArray(res.data?.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);

            if (contasCarregadas.length === 0) {
                $('#bankTrigger').html('<span class="trigger-text trigger-placeholder">Nenhuma conta configurada</span><iconify-icon icon="icon-park-outline:down" class="trigger-arrow"></iconify-icon>');
                return;
            }

            $('#bankTrigger').html('<span class="trigger-text trigger-placeholder">Selecione a conta...</span><iconify-icon icon="icon-park-outline:down" class="trigger-arrow"></iconify-icon>');
            renderBankOptions(contasCarregadas, '');

            if (urlAccountId) {
                const f = contasCarregadas.find(c => String(c.id) === String(urlAccountId));
                if (f) {
                    const b = bankBrands[f.bank_code] || { name: 'Banco', domain: '' };
                    selecionarConta(f.id, b.domain, b.name, `Ag: ${f.agency} · Cc: ${f.account_number}`);
                    buscarImportacoes();
                }
            } else if (contasCarregadas.length === 1) {
                const c = contasCarregadas[0]; const b = bankBrands[c.bank_code] || { name: 'Banco', domain: '' };
                selecionarConta(c.id, b.domain, b.name, `Ag: ${c.agency} · Cc: ${c.account_number}`);
                buscarImportacoes();
            }
        } catch (e) { console.error(e); }
    }

    // ====== BUSCAR LISTA DE IMPORTAÇÕES ======
    async function buscarImportacoes() {
        const account_id = $('#account_id').val();
        if (!account_id) { Swal.fire('Atenção', 'Selecione uma conta bancária.', 'warning'); return; }

        toggleLoading(true);

        try {
            const res = await axios.post(baseUrl, {
                method: 'openFinanceListImportHistory',
                token: token,
                data: { system_unit_id, account_id }
            });

            // Se o controller retorna direto o array ou encapsulado
            const importacoes = Array.isArray(res.data?.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);
            renderTabela(importacoes);
        } catch (e) {
            toggleLoading(false);
            Swal.fire('Erro', 'Falha ao buscar histórico de importações.', 'error');
        }
    }

    function renderTabela(lista) {
        toggleLoading(false);
        const $tbody = $('#tabela-importacoes tbody');
        $tbody.empty();

        if (lista.length === 0) {
            $tbody.append('<tr><td colspan="5" class="text-center muted" style="padding: 40px;">Nenhuma solicitação de extrato encontrada para esta conta.</td></tr>');
            return;
        }

        lista.forEach(item => {
            let badgeStatus = '';
            const statusStr = (item.status || '').toLowerCase();

            if (statusStr === 'done' || statusStr === 'concluido' || statusStr === 'concluído') {
                badgeStatus = '<span class="badge-status success"><iconify-icon icon="icon-park-outline:check-one" style="vertical-align:text-bottom;"></iconify-icon> Concluído</span>';
            } else if (statusStr === 'error' || statusStr === 'falha') {
                badgeStatus = '<span class="badge-status error"><iconify-icon icon="icon-park-outline:close-one" style="vertical-align:text-bottom;"></iconify-icon> Falha</span>';
            } else {
                badgeStatus = `<span class="badge-status processing"><iconify-icon icon="icon-park-outline:loading" class="fa-spin" style="vertical-align:text-bottom;"></iconify-icon> Processando</span>`;
            }

            $tbody.append(`
                <tr>
                    <td style="color:#555; font-weight:500;">${formatarDataHora(item.created_at)}</td>
                    <td><span style="font-family:monospace; background:#f0f0f0; padding:3px 6px; border-radius:4px; font-size:12px;">${escapeHtml(item.unique_id)}</span></td>
                    <td style="color:#666;">${formatarDataSimples(item.date_start)} até ${formatarDataSimples(item.date_end)}</td>
                    <td>${badgeStatus}</td>
                    <td class="text-center">
                        <button class="btn-log" onclick="abrirModalLogs('${item.unique_id}')" title="Ver Detalhes">
                            <iconify-icon icon="icon-park-outline:code-computer"></iconify-icon> Logs
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    // ====== ABRIR MODAL E BUSCAR LOGS ======
    async function abrirModalLogs(uniqueId) {
        $('#lblProtocolo').text(`[${uniqueId}]`);
        const $container = $('#logs-container');

        $container.html('<div class="text-center" style="padding: 40px; color: var(--mrk-blue);"><iconify-icon icon="line-md:loading-twotone-loop" style="font-size:40px;"></iconify-icon><br><b style="font-family: Kanit, sans-serif;">Buscando logs de integração...</b></div>');
        $('#modalLogs').modal('show');

        try {
            const res = await axios.post(baseUrl, {
                method: 'getStatementLogs',
                token: token,
                data: { system_unit_id, unique_id: uniqueId }
            });

            if (res.data.status === 'success' && res.data.data.length > 0) {
                renderLogs(res.data.data);
            } else {
                $container.html('<div class="text-center" style="padding: 40px; color: #888;">Nenhum log técnico encontrado para este protocolo.</div>');
            }
        } catch (e) {
            console.error(e);
            $container.html('<div class="text-center text-danger" style="padding: 40px;">Falha na comunicação com o servidor ao buscar os logs.</div>');
        }
    }

    function renderLogs(logs) {
        const $container = $('#logs-container');
        $container.empty();

        logs.forEach((log, index) => {
            // Define as cores baseadas no Status HTTP
            const code = parseInt(log.http_code);
            let statusColor = '#555'; let statusBg = '#eee';
            if (code >= 200 && code < 300) { statusColor = '#2E7D32'; statusBg = '#E8F5E9'; }
            else if (code >= 400) { statusColor = '#C62828'; statusBg = '#FFEBEE'; }

            const methodClass = `log-method ${log.method}`;

            // Formatando JSON para exibição
            let reqBody = log.request_body_decoded || log.request_body || 'Nenhum payload enviado.';
            let resBody = log.response_body_decoded || log.response_body || 'Nenhuma resposta recebida.';

            if (typeof reqBody === 'object') reqBody = JSON.stringify(reqBody, null, 2);
            if (typeof resBody === 'object') resBody = JSON.stringify(resBody, null, 2);

            const isFirst = index === 0;

            const html = `
                <div class="log-item">
                    <div class="log-header">
                        <div>
                            <span class="${methodClass}">${escapeHtml(log.method)}</span>
                            <span class="log-endpoint">${escapeHtml(log.endpoint)}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-size: 11px; color: #888;">${formatarDataHora(log.created_at)} | ${log.execution_time_ms}ms</span>
                            <span class="log-status-badge" style="background:${statusBg}; color:${statusColor};">HTTP ${log.http_code}</span>
                            <iconify-icon icon="icon-park-outline:down" class="toggle-icon" style="color:#aaa; font-size:18px; transition: 0.2s; ${isFirst ? 'transform: rotate(180deg);' : ''}"></iconify-icon>
                        </div>
                    </div>
                    <div class="log-body" style="${isFirst ? 'display: block;' : ''}">

                        ${log.error_message ? `
                            <div style="background: #FFF3E0; border-left: 4px solid #EF6C00; padding: 10px; margin-bottom: 15px; font-size: 12px; border-radius: 4px;">
                                <b style="color: #E65100;">Erro Registrado:</b> ${escapeHtml(log.error_message)}
                            </div>
                        ` : ''}

                        <div class="row">
                            <div class="col-md-6">
                                <div class="log-box-title">Request Payload</div>
                                <div class="log-box">${escapeHtml(reqBody)}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="log-box-title">Response Payload</div>
                                <div class="log-box">${escapeHtml(resBody)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $container.append(html);
        });
    }
</script>

</body>
</html>