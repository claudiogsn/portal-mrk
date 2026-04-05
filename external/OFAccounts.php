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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Minhas Contas - Open Finance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="bsb/favicon.ico" type="image/x-icon">

    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

    <link href="bsb/plugins/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="bsb/plugins/sweetalert/sweetalert.css" rel="stylesheet">
    <link href="bsb/css/style.css" rel="stylesheet">
    <link href="style/mrk.css" rel="stylesheet">

    <style>
        body { background-color: #f9f9f9; padding: 20px; font-family: 'Poppins', sans-serif; overflow-x: hidden; }

        /* Estilos do Loading Inicial */
        #loading-screen { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 60vh; color: var(--mrk-blue); }
        #loading-screen iconify-icon { font-size: 50px; animation: spin 1s linear infinite; margin-bottom: 15px; }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* Estilos da Tela de Venda (Upsell) / Bloqueio */
        .upsell-panel {
            background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            max-width: 650px; margin: 40px auto; padding: 50px 40px; text-align: center;
            border-top: 5px solid #000;
        }
        .upsell-logo { max-width: 250px; margin-bottom: 30px; }
        .upsell-title { font-family: 'Kanit', sans-serif; font-size: 24px; color: #222; font-weight: 700; margin-bottom: 15px; }
        .upsell-desc { color: #666; font-size: 15px; margin-bottom: 30px; line-height: 1.6; }
        .upsell-features { text-align: left; background: #f8f9fa; padding: 25px; border-radius: 8px; margin-bottom: 35px; }
        .feature-item { display: flex; align-items: flex-start; margin-bottom: 15px; }
        .feature-item:last-child { margin-bottom: 0; }
        .feature-item iconify-icon { color: #00A13A; font-size: 24px; margin-right: 15px; flex-shrink: 0; }
        .feature-text h4 { margin: 0 0 5px 0; font-size: 15px; font-weight: 600; color: #333; font-family: 'Poppins', sans-serif; }
        .feature-text p { margin: 0; font-size: 13px; color: #777; }

        .btn-whatsapp {
            background-color: #25D366; color: white; font-weight: 600; font-size: 16px; padding: 12px 30px;
            border-radius: 50px; border: none; transition: 0.3s; display: inline-flex; align-items: center; gap: 10px;
            text-decoration: none; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
        }
        .btn-whatsapp:hover { background-color: #1EBE5D; color: white; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4); }

        /* Estilos da Tela Principal (Cards) */
        .card-main { background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eee; border-top: 3px solid var(--mrk-blue) !important; margin-bottom: 20px; border-radius: 6px; padding: 20px; }
        .header-flex { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .header-flex h2 { margin: 0; font-family: 'Kanit', sans-serif; font-size: 20px; color: var(--mrk-blue); display: flex; align-items: center; }

        .kpi-card { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #eee; position: relative; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.3s; height: 100%; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .kpi-card.blue { border-left: 4px solid var(--mrk-blue); }
        .kpi-card.orange { border-left: 4px solid var(--mrk-amber); }
        .kpi-title { font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px; }
        .kpi-value { font-size: 22px; font-weight: 700; font-family: 'Kanit', sans-serif; color: var(--mrk-black); }
        .kpi-bg-icon { position: absolute; right: 15px; top: 15px; font-size: 28px; opacity: 0.15; color: var(--mrk-blue); }

        .account-card { background: #fff; border-radius: 12px; border: 1px solid #e0e0e0; overflow: hidden; transition: all 0.3s ease; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); display: flex; flex-direction: column; height: calc(100% - 20px); }
        .account-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .account-header { padding: 15px 20px; display: flex; align-items: center; gap: 12px; font-family: 'Kanit', sans-serif; font-size: 16px; font-weight: 600; }
        .account-header .bank-logo { width: 32px; height: 32px; border-radius: 6px; object-fit: contain; background: rgba(255,255,255,0.9); padding: 3px; flex-shrink: 0; }
        .account-body { padding: 20px; flex-grow: 1; }
        .account-info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
        .account-info-label { color: #888; font-weight: 500; }
        .account-info-val { color: #333; font-weight: 600; font-family: 'Kanit', sans-serif; font-size: 14px; }
        .account-footer { padding: 15px 20px; background: #fcfcfc; border-top: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }

        .badge-status { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; width: 100%; text-align: center; margin-top: 10px; }
        .badge-status.ativo { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
        .badge-status.pendente { background: #FFF3E0; color: #E65100; border: 1px solid #FFE0B2; }
        .badge-status.inativo { background: #FFEBEE; color: #C62828; border: 1px solid #FFCDD2; }

        .btn-connect { background-color: #1a73e8; color: white; font-weight: 600; font-size: 12px; padding: 8px 15px; border-radius: 6px; border: none; transition: 0.2s; width: 100%; cursor: pointer; }
        .btn-connect:hover { background-color: #155cb0; color: white; box-shadow: 0 3px 8px rgba(26,115,232,0.3); }

        .skeleton-wrapper { display: none; }
        .skeleton-card { background: #fff; border-radius: 12px; border: 1px solid #eee; height: 220px; margin-bottom: 20px; position: relative; overflow: hidden; }
        .skeleton-card::after { content: ""; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent); animation: sweep 1.5s infinite; }
        .sk-head { height: 60px; background: #e0e0e0; }
        .sk-body { padding: 20px; }
        .sk-line { height: 15px; background: #e0e0e0; margin-bottom: 10px; border-radius: 4px; }
        @keyframes sweep { 0% { left: -100%; } 100% { left: 200%; } }

        .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #eee; }
        .modal-title { font-family: 'Kanit', sans-serif; color: var(--mrk-blue); font-weight: 600; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; margin-top: 10px; display: block;}
        .form-control { border-radius: 4px; font-size: 13px; }

        /* Bank logo preview no modal */
        .bank-preview { display: flex; align-items: center; gap: 10px; margin-top: 10px; padding: 10px 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #eee; }
        .bank-preview img { width: 36px; height: 36px; border-radius: 6px; object-fit: contain; }
        .bank-preview .bank-preview-name { font-weight: 600; font-size: 14px; color: #333; font-family: 'Kanit', sans-serif; }
        .bank-preview .bank-preview-code { font-size: 11px; color: #999; }

        /* Btn extrato no footer */
        .btn-extrato { color: var(--mrk-blue); border-color: var(--mrk-blue); border-radius: 6px; font-size: 11px; font-weight: 600; background: transparent; padding: 4px 10px; transition: 0.2s; }
        .btn-extrato:hover { background: var(--mrk-blue); color: #fff; }
    </style>
</head>

<body>

<div class="container-fluid">

    <div id="loading-screen">
        <iconify-icon icon="icon-park-outline:loading"></iconify-icon>
        <h4>Verificando permissões...</h4>
    </div>

    <div id="unauthorized-content" style="display: none;">
        <div class="upsell-panel">
            <img src="https://openfinancebrasil.org.br/wp-content/themes/openbank/assets/img/logo.png" alt="Open Finance Brasil" class="upsell-logo">

            <h2 class="upsell-title">Módulo Open Finance Não Ativado</h2>
            <p class="upsell-desc">Sua unidade ainda não possui a integração bancária ativa. Eleve a gestão financeira do seu negócio conectando suas contas bancárias diretamente ao Portal MRK.</p>

            <div class="upsell-features">
                <div class="feature-item">
                    <iconify-icon icon="icon-park-solid:check-one"></iconify-icon>
                    <div class="feature-text">
                        <h4>Conciliação Bancária Automática</h4>
                        <p>Importação direta dos extratos, eliminando a digitação manual e os erros humanos.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <iconify-icon icon="icon-park-solid:check-one"></iconify-icon>
                    <div class="feature-text">
                        <h4>Pagamento de Boletos (DDA)</h4>
                        <p>Visualize e autorize pagamentos de contas a pagar diretamente por dentro do sistema.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <iconify-icon icon="icon-park-solid:check-one"></iconify-icon>
                    <div class="feature-text">
                        <h4>DRE e Visão Unificada</h4>
                        <p>Tenha todas as contas bancárias em um único lugar, permitindo um DRE preciso e em tempo real.</p>
                    </div>
                </div>
            </div>

            <a href="https://wa.me/5571991248941?text=Ol%C3%A1%21%20Gostaria%20de%20saber%20mais%20sobre%20a%20contrata%C3%A7%C3%A3o%20do%20M%C3%B3dulo%20Open%20Finance%20para%20minha%20unidade." target="_blank" class="btn-whatsapp">
                <iconify-icon icon="ic:baseline-whatsapp"></iconify-icon> Falar com o Suporte
            </a>
        </div>
    </div>

    <div id="dashboard-content" style="display: none;">
        <div class="card-main">
            <div class="header-flex">
                <h2>
                    <iconify-icon icon="icon-park-outline:wallet" style="margin-right: 8px;"></iconify-icon>
                    CONTAS BANCÁRIAS
                </h2>
                <div>
                    <button class="btn btn-default waves-effect" id="btnSync" style="margin-right: 10px; border-radius: 6px;">
                        <iconify-icon icon="icon-park-outline:refresh"></iconify-icon> SINCRONIZAR
                    </button>
                    <button class="btn btn-primary waves-effect" style="background-color: var(--mrk-blue); border: none; border-radius: 6px;" onclick="abrirModalNovaConta()">
                        <iconify-icon icon="icon-park-outline:plus"></iconify-icon> NOVA CONTA
                    </button>
                </div>
            </div>

            <div class="row clearfix" style="margin-bottom: 20px;">
                <div class="col-md-6">
                    <div class="kpi-card blue">
                        <span class="kpi-title">Contas Integradas</span>
                        <span class="kpi-value" id="totContas">0</span>
                        <iconify-icon icon="icon-park-outline:bank" class="kpi-bg-icon"></iconify-icon>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="kpi-card orange" id="cardPendentes">
                        <span class="kpi-title">Aguardando Autorização</span>
                        <span class="kpi-value" id="totPendentes">0</span>
                        <iconify-icon icon="icon-park-outline:time" class="kpi-bg-icon" style="color: var(--mrk-amber);"></iconify-icon>
                    </div>
                </div>
            </div>

            <div id="sk-cards" class="row skeleton-wrapper">
                <div class="col-md-4"><div class="skeleton-card"><div class="sk-head"></div><div class="sk-body"><div class="sk-line" style="width: 80%"></div><div class="sk-line" style="width: 60%"></div></div></div></div>
                <div class="col-md-4"><div class="skeleton-card"><div class="sk-head"></div><div class="sk-body"><div class="sk-line" style="width: 80%"></div><div class="sk-line" style="width: 60%"></div></div></div></div>
            </div>

            <div class="row" id="grid-contas">
            </div>

            <div id="msg-vazio" class="text-center muted" style="padding: 40px; display: none;">
                <iconify-icon icon="icon-park-outline:inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></iconify-icon><br>
                Nenhuma conta bancária configurada para sua unidade.
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAccount" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Cadastrar Conta Bancária</h4>
            </div>
            <div class="modal-body" style="padding: 20px 30px;">
                <form id="formAccount">
                    <div class="row clearfix">
                        <div class="col-md-12">
                            <label class="form-label">Instituição Financeira *</label>
                            <select class="form-control" id="bank_code" name="bank_code" required>
                                <option value="">Selecione o Banco...</option>
                                <option value="001">001 - Banco do Brasil</option>
                                <option value="004">004 - Banco do Nordeste do Brasil</option>
                                <option value="033">033 - Banco Santander</option>
                                <option value="041">041 - Banco Banrisul</option>
                                <option value="070">070 - Banco BRB</option>
                                <option value="077">077 - Banco Inter</option>
                                <option value="104">104 - Caixa Econômica Federal</option>
                                <option value="136">136 - Banco Unicred</option>
                                <option value="208">208 - BTG Pactual</option>
                                <option value="237">237 - Banco Bradesco</option>
                                <option value="318">318 - Banco BMG</option>
                                <option value="329">329 - Banco QI Tech</option>
                                <option value="341">341 - Banco Itaú</option>
                                <option value="365">365 - K8 Fintech</option>
                                <option value="422">422 - Banco Safra</option>
                                <option value="745">745 - Banco Citibank</option>
                                <option value="748">748 - Banco Sicredi</option>
                                <option value="755">755 - Bank Of America</option>
                                <option value="756">756 - Banco Sicoob</option>
                            </select>
                            <div id="bankPreview" class="bank-preview" style="display: none;">
                                <img id="bankPreviewLogo" src="" alt="">
                                <div>
                                    <div class="bank-preview-name" id="bankPreviewName"></div>
                                    <div class="bank-preview-code" id="bankPreviewCode"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row clearfix">
                        <div class="col-md-8">
                            <label class="form-label">Agência *</label>
                            <input type="text" class="form-control" id="agency" name="agency" maxlength="10" required placeholder="Ex: 1234">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dígito</label>
                            <input type="text" class="form-control" id="agency_digit" name="agency_digit" maxlength="2" placeholder="Ex: X">
                        </div>
                    </div>

                    <div class="row clearfix">
                        <div class="col-md-8">
                            <label class="form-label">Conta Corrente *</label>
                            <input type="text" class="form-control" id="account_number" name="account_number" maxlength="12" required placeholder="Ex: 123456">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dígito *</label>
                            <input type="text" class="form-control" id="account_number_digit" name="account_number_digit" maxlength="2" required placeholder="Ex: 7">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #eee;">
                <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-primary waves-effect" id="btnSaveAccount" style="background-color: var(--mrk-blue); border: none;">
                    SALVAR CONTA
                </button>
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

    const token = "<?php echo $token; ?>";
    const system_unit_id = "<?php echo $unit_id; ?>";

    const LOGO_TOKEN = 'pk_P0veRzAeSuCornQ7nz584Q';
    function bankLogoUrl(domain) {
        return domain ? `https://img.logo.dev/${domain}?token=${LOGO_TOKEN}&size=64&format=png` : '';
    }

    const bankBrands = {
        "001": { name: "Banco do Brasil", bg: "#FCE80A", text: "#0038A8", domain: "bb.com.br" },
        "003": { name: "Banco da Amazônia", bg: "#005BAA", text: "#FFFFFF", domain: "bancoamazonia.com.br" },
        "004": { name: "Banco do Nordeste", bg: "#004B87", text: "#FFFFFF", domain: "bnb.gov.br" },
        "021": { name: "Banestes", bg: "#003B7B", text: "#FFFFFF", domain: "banestes.com.br" },
        "033": { name: "Santander", bg: "#EC0000", text: "#FFFFFF", domain: "santander.com.br" },
        "041": { name: "Banrisul", bg: "#004B87", text: "#FFFFFF", domain: "banrisul.com.br" },
        "070": { name: "BRB", bg: "#003B73", text: "#FFFFFF", domain: "brb.com.br" },
        "077": { name: "Banco Inter", bg: "#FF7A00", text: "#FFFFFF", domain: "inter.co" },
        "104": { name: "Caixa Econômica", bg: "#005CA9", text: "#F39200", domain: "caixa.gov.br" },
        "136": { name: "Unicred", bg: "#00A651", text: "#FFFFFF", domain: "unicred.com.br" },
        "208": { name: "BTG Pactual", bg: "#003B73", text: "#FFFFFF", domain: "btgpactual.com" },
        "212": { name: "Banco Original", bg: "#00A650", text: "#FFFFFF", domain: "original.com.br" },
        "237": { name: "Bradesco", bg: "#CC092F", text: "#FFFFFF", domain: "bradesco.com.br" },
        "260": { name: "Nubank", bg: "#820AD1", text: "#FFFFFF", domain: "nubank.com.br" },
        "318": { name: "Banco BMG", bg: "#FF6600", text: "#FFFFFF", domain: "bancobmg.com.br" },
        "329": { name: "QI Tech", bg: "#1A1A2E", text: "#FFFFFF", domain: "qitech.com.br" },
        "336": { name: "C6 Bank", bg: "#1A1A1A", text: "#FFFFFF", domain: "c6bank.com.br" },
        "341": { name: "Itaú", bg: "#EC7000", text: "#FFFFFF", domain: "itau.com.br" },
        "365": { name: "K8 Fintech", bg: "#111111", text: "#FFFFFF", domain: "" },
        "422": { name: "Safra", bg: "#003B73", text: "#FFFFFF", domain: "safra.com.br" },
        "745": { name: "Citibank", bg: "#003B70", text: "#FFFFFF", domain: "citibank.com" },
        "748": { name: "Sicredi", bg: "#00A13A", text: "#FFFFFF", domain: "sicredi.com.br" },
        "755": { name: "Bank of America", bg: "#012169", text: "#FFFFFF", domain: "bankofamerica.com" },
        "756": { name: "Sicoob", bg: "#003641", text: "#00AE9D", domain: "sicoob.com.br" },
        "default": { name: "Banco", bg: "#607D8B", text: "#FFFFFF", domain: "" }
    };

    let contasBrutas = [];

    function escapeHtml(s) { return s == null ? '' : String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    function toggleLoadingGrid(isLoading) {
        if (isLoading) {
            $('#grid-contas').empty();
            $('#msg-vazio').hide();
            $('#sk-cards').css('display', 'flex');
        } else {
            $('#sk-cards').hide();
        }
    }

    $(document).ready(() => {
        verificarOpenFinanceAtivo();

        $('#btnSync').on('click', () => { carregarContasLocais(); });
        $('#btnSaveAccount').on('click', salvarConta);

        // Preview do logo ao selecionar banco no modal
        $('#bank_code').on('change', function() {
            const code = $(this).val();
            const $preview = $('#bankPreview');

            if (!code) {
                $preview.hide();
                return;
            }

            const brand = bankBrands[code] || bankBrands["default"];
            const logoUrl = bankLogoUrl(brand.domain);

            $('#bankPreviewName').text(brand.name);
            $('#bankPreviewCode').text('Código: ' + code);

            if (logoUrl) {
                $('#bankPreviewLogo').attr('src', logoUrl).show();
            } else {
                $('#bankPreviewLogo').hide();
            }

            $preview.show();
        });
    });

    async function verificarOpenFinanceAtivo() {
        try {
            const res = await axios.post(baseUrl, {
                method: 'openFinanceCheckPayer',
                token: token,
                data: { system_unit_id: system_unit_id }
            });

            $('#loading-screen').hide();

            if (res.data && res.data.exists === true) {
                $('#dashboard-content').fadeIn();
                carregarContasLocais();
            } else {
                $('#unauthorized-content').fadeIn();
            }
        } catch (e) {
            console.error("Erro ao verificar pagador", e);
            $('#loading-screen').hide();
            $('#unauthorized-content').fadeIn();
        }
    }

    async function carregarContasLocais() {
        toggleLoadingGrid(true);
        try {
            const res = await axios.post(baseUrl, {
                method: 'openFinanceListLocalAccounts',
                token: token,
                data: { system_unit_id: system_unit_id }
            });

            contasBrutas = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);
            renderCards(contasBrutas);
            toggleLoadingGrid(false);
        } catch (e) {
            console.error(e);
            toggleLoadingGrid(false);
            Swal.fire('Erro', 'Falha ao buscar contas no servidor.', 'error');
        }
    }

    async function salvarConta() {
        const form = document.getElementById('formAccount');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        payload.system_unit_id = system_unit_id;

        const $btn = $('#btnSaveAccount');
        const txtOriginal = $btn.text();
        $btn.text('SALVANDO...').prop('disabled', true);

        try {
            const res = await axios.post(baseUrl, {
                method: 'openFinanceCreateAccount',
                token: token,
                data: payload
            });

            if (!res.data.error) {
                Swal.fire('Sucesso', res.data.message || 'Conta cadastrada!', 'success');
                $('#modalAccount').modal('hide');
                carregarContasLocais();
            } else {
                Swal.fire('Atenção', res.data.error || res.data.message, 'warning');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Erro', 'Falha na comunicação ao tentar salvar a conta.', 'error');
        } finally {
            $btn.text(txtOriginal).prop('disabled', false);
        }
    }

    // ==========================================
    // FLUXO DE VERIFICAÇÃO E SOLICITAÇÃO 365 DIAS
    // ==========================================

    async function verificarStatusConta(accountId, accountHash, btnElement) {
        const originalHtml = btnElement.innerHTML;
        btnElement.innerHTML = '<iconify-icon icon="icon-park-outline:loading" class="fa-spin"></iconify-icon> Verificando...';
        btnElement.disabled = true;

        try {
            const res = await axios.post(baseUrl, {
                method: 'openFinanceGetAccountByHash',
                token: token,
                data: { system_unit_id: system_unit_id, account_hash: accountHash }
            });

            if (!res.data.error && res.data.status === 'success') {
                const statusApi = res.data.data.statusOpenfinance || 'PENDENTE_ATIVACAO';

                if (statusApi === 'ATIVO' || statusApi === 'CONECTADO') {
                    Swal.fire({
                        title: 'Banco Conectado!',
                        text: 'Sua conta foi autorizada! Estamos iniciando a importação do extrato...',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 2500
                    });

                    solicitarExtratoInicial(accountId);

                } else {
                    Swal.fire('Atenção', `A conta ainda consta como: ${statusApi}.`, 'info');
                }

                carregarContasLocais();
            } else {
                Swal.fire('Erro', res.data.message || res.data.error, 'error');
                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Erro', 'Falha ao verificar status.', 'error');
            btnElement.innerHTML = originalHtml;
            btnElement.disabled = false;
        }
    }

    async function solicitarExtratoInicial(accountId) {
        try {
            const res = await axios.post(baseUrl, {
                method: 'requestStatementFromLastYear',
                token: token,
                data: {
                    system_unit_id: system_unit_id,
                    account_id: accountId
                }
            });

            if (!res.data.error && res.data.status === 'success') {
                setTimeout(() => {
                    Swal.fire('Importação Iniciada', 'Os extratos dos últimos 365 dias estão sendo importados em segundo plano. Isso pode levar alguns minutos.', 'success');
                }, 2600);
            } else {
                setTimeout(() => {
                    Swal.fire('Aviso', res.data.message || 'A conta foi conectada, mas houve um aviso ao solicitar o extrato histórico.', 'warning');
                }, 2600);
            }
        } catch (e) {
            console.error('Erro ao solicitar extrato de 365 dias:', e);
            setTimeout(() => {
                Swal.fire('Aviso', 'A conta foi conectada, mas ocorreu uma falha de rede ao tentar puxar o histórico.', 'warning');
            }, 2600);
        }
    }

    // ==========================================

    function revogarAcesso(accountHash) {
        Swal.fire({
            title: 'Desconectar Banco?',
            text: "Você não poderá mais importar extratos automaticamente desta conta.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonText: 'Cancelar',
            confirmButtonText: 'Sim, desconectar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const res = await axios.post(baseUrl, {
                        method: 'openFinanceRevoke',
                        token: token,
                        data: { system_unit_id: system_unit_id, account_hash: accountHash, revokeAndDisable: true }
                    });

                    if (!res.data.error) {
                        Swal.fire('Desconectado', 'Acesso revogado com sucesso.', 'success');
                        carregarContasLocais();
                    } else {
                        Swal.fire('Erro', res.data.error || res.data.message, 'error');
                    }
                } catch(e) {
                    Swal.fire('Erro', 'Falha ao revogar.', 'error');
                }
            }
        });
    }

    function abrirPopupBanco(url) {
        const width = 450;
        const height = 750;
        const left = (window.innerWidth / 2) - (width / 2) + window.screenX;
        const top = (window.innerHeight / 2) - (height / 2) + window.screenY;
        window.open(url, 'OpenFinanceAuth', `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes,status=no,toolbar=no,menubar=no,location=no`);
    }

    function abrirModalNovaConta() {
        $('#formAccount')[0].reset();
        $('#bankPreview').hide();
        $('#modalAccount').modal('show');
    }

    function renderCards(lista){
        const $grid = $('#grid-contas');
        $grid.empty();

        let pendentes = 0;

        if (lista.length === 0) {
            $('#msg-vazio').show();
            $('#totContas').text('0');
            $('#totPendentes').text('0');
            return;
        }

        $('#msg-vazio').hide();
        $('#totContas').text(lista.length);

        lista.forEach(item => {
            const statusAtual = item.status_openfinance ? item.status_openfinance.toUpperCase() : 'PENDENTE_ATIVACAO';
            const isPendente = statusAtual === 'PENDENTE_ATIVACAO';
            const isAtivo = !isPendente && statusAtual !== 'REVOGADO';

            if(isPendente) pendentes++;

            let badgeStatus = '';
            if (isPendente) {
                badgeStatus = '<span class="badge-status pendente"><iconify-icon icon="icon-park-outline:time"></iconify-icon> Aguardando Conexão</span>';
            } else if (statusAtual === 'REVOGADO') {
                badgeStatus = '<span class="badge-status inativo"><iconify-icon icon="icon-park-outline:close-one"></iconify-icon> Desconectado</span>';
            } else {
                badgeStatus = `<span class="badge-status ativo"><iconify-icon icon="icon-park-outline:check-one"></iconify-icon> Sincronizado</span>`;
            }

            const agencia = item.agency_digit ? `${item.agency}-${item.agency_digit}` : item.agency;
            const conta = item.account_number_digit ? `${item.account_number}-${item.account_number_digit}` : item.account_number;

            const brand = bankBrands[item.bank_code] || bankBrands["default"];
            const bankName = item.bank_code === "365" ? "K8 Fintech" : brand.name;
            const logoUrl = bankLogoUrl(brand.domain);

            const logoHtml = logoUrl
                ? `<img src="${logoUrl}" class="bank-logo" alt="${bankName}" onerror="this.style.display='none'">`
                : `<iconify-icon icon="icon-park-outline:bank"></iconify-icon>`;

            // Botão de extrato para contas ativas
            const btnExtrato = isAtivo
                ? `<a href="OFTransactions.php?account_id=${item.id}" class="btn btn-xs btn-extrato" title="Ver Extrato"><iconify-icon icon="icon-park-outline:list-view" style="vertical-align: sub;"></iconify-icon> Extrato</a>`
                : '';

            let cardHtml = `
                <div class="col-md-4">
                    <div class="account-card">
                        <div class="account-header" style="background-color: ${brand.bg}; color: ${brand.text};">
                            ${logoHtml}
                            <span>${bankName} <small style="opacity: 0.8; font-weight: 400; font-size: 12px;">(${item.bank_code})</small></span>
                        </div>

                        <div class="account-body">
                            <div class="account-info-row">
                                <span class="account-info-label">Agência</span>
                                <span class="account-info-val">${escapeHtml(agencia)}</span>
                            </div>
                            <div class="account-info-row">
                                <span class="account-info-label">Conta</span>
                                <span class="account-info-val">${escapeHtml(conta)}</span>
                            </div>

                            ${badgeStatus}

                            ${(isPendente && item.openfinance_link) ? `
                                <div style="display: flex; gap: 5px; margin-top: 15px;">
                                    <button class="btn-connect" style="flex: 1;" onclick="abrirPopupBanco('${item.openfinance_link}')">
                                        <iconify-icon icon="icon-park-outline:link-cloud" style="vertical-align: sub; font-size: 14px;"></iconify-icon> CONECTAR
                                    </button>
                                    <button class="btn btn-default" style="flex: 1; border-color: #ddd; color: #555; border-radius: 6px; font-size: 11px; font-weight: 600;" onclick="verificarStatusConta('${item.id}', '${item.account_hash}', this)">
                                        <iconify-icon icon="icon-park-outline:refresh" style="vertical-align: sub;"></iconify-icon> VERIFICAR
                                    </button>
                                </div>
                            ` : ''}
                        </div>

                        <div class="account-footer">
                            <small class="text-muted" style="font-size: 10px;">
                                Hash: ${escapeHtml(item.account_hash).substring(0,6)}...
                            </small>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                ${btnExtrato}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $grid.append(cardHtml);
        });

        $('#totPendentes').text(pendentes);
        if(pendentes > 0) {
            $('#cardPendentes').removeClass('green').addClass('orange');
        } else {
            $('#cardPendentes').removeClass('orange').addClass('green');
        }
    }
</script>

</body>
</html>