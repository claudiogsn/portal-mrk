<?php
session_name('PHPSESSID_MRKSolutions');
session_start();

if (!isset($_SESSION['MRKSolutions'])) {
    die("Sessão expirada. Faça login novamente.");
}

$appData = $_SESSION['MRKSolutions'];
$token   = $appData['sessionid']  ?? '';

// Aqui não usamos mais o $unit_id para travar a tela, pois é um painel global

if (empty($token)) {
    die("Acesso negado.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Admin - Open Finance (Global)</title>
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

        .card {
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            border-top: 3px solid var(--mrk-blue) !important;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .kpi-card {
            background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #eee;
            position: relative; overflow: hidden; display: flex; flex-direction: column;
            transition: transform 0.3s; height: 100%;
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .kpi-card.blue { border-left: 4px solid var(--mrk-blue); }
        .kpi-card.green { border-left: 4px solid var(--mrk-green); }
        .kpi-title { font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px; }
        .kpi-value { font-size: 22px; font-weight: 700; font-family: 'Kanit', sans-serif; color: var(--mrk-black); }
        .kpi-bg-icon { position: absolute; right: 15px; top: 15px; font-size: 28px; opacity: 0.15; }
        .kpi-card.blue .kpi-bg-icon { color: var(--mrk-blue); }
        .kpi-card.green .kpi-bg-icon { color: var(--mrk-green); }

        .header-flex { display: flex; align-items: center; justify-content: space-between; }
        .input-group-addon { background-color: #f0f4f8; border: 1px solid #ddd; border-right: none; color: var(--mrk-blue); }

        .table thead th {
            font-family: 'Kanit', sans-serif; color: var(--mrk-blue); font-weight: 600;
            font-size: 12px; background-color: #f8f9fa; border-bottom: 2px solid #e9ecef; white-space: nowrap;
        }
        .table tbody td { font-size: 12px; vertical-align: middle !important; border-top: 1px solid #f1f1f1; }

        .badge-status { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; color: white; }
        .badge-status.ativo { background: var(--mrk-green); }
        .badge-status.inativo { background: var(--mrk-red); }

        .skeleton-wrapper { display: none; margin-top: 20px; }
        .skeleton { background: #e0e0e0; border-radius: 4px; margin-bottom: 10px; animation: pulse 1.5s infinite; }
        .sk-row { height: 40px; width: 100%; margin-bottom: 8px; }
        @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }

        .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #eee; }
        .modal-title { font-family: 'Kanit', sans-serif; color: var(--mrk-blue); font-weight: 600; }
        .form-label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; margin-top: 10px; display: block;}
        .form-control { border-radius: 4px; font-size: 13px; }
        .form-control[readonly] { background-color: #f0f4f8; border-color: #ddd; color: #666; cursor: not-allowed; }
        .section-title { font-size: 13px; font-family: 'Kanit'; color: var(--mrk-blue); border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 20px; margin-bottom: 10px; }
    </style>
</head>

<body>

<div class="container-fluid">

    <div class="card">
        <div class="header header-flex">
            <h2>
                <iconify-icon icon="icon-park-outline:earth" width="24" style="color: var(--mrk-blue); vertical-align: bottom; margin-right: 5px;"></iconify-icon>
                PAINEL GLOBAL - OPEN FINANCE
            </h2>
            <button class="btn btn-primary waves-effect" style="background-color: var(--mrk-blue); border: none;" onclick="abrirModalNovo()">
                <iconify-icon icon="icon-park-outline:plus" style="vertical-align: middle;"></iconify-icon> ATIVAR UNIDADE
            </button>
        </div>

        <div class="body">

            <div class="row clearfix" style="margin-bottom: 20px;">
                <div class="col-md-6">
                    <div class="kpi-card blue">
                        <span class="kpi-title">Unidades Integradas</span>
                        <span class="kpi-value" id="totRegistros">0</span>
                        <iconify-icon icon="icon-park-outline:peoples" class="kpi-bg-icon"></iconify-icon>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="kpi-card green">
                        <span class="kpi-title">Integrações Ativas</span>
                        <span class="kpi-value" id="totAtivos">0</span>
                        <iconify-icon icon="icon-park-outline:check-one" class="kpi-bg-icon"></iconify-icon>
                    </div>
                </div>
            </div>

            <div class="row clearfix" style="margin-bottom: 15px;">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-addon"><iconify-icon icon="icon-park-outline:search"></iconify-icon></span>
                        <input type="text" id="filtroTexto" class="form-control" placeholder="Buscar por Unidade, Nome, Razão Social ou CNPJ...">
                    </div>
                </div>
                <div class="col-md-2">
                    <button id="btnAtualizar" class="btn btn-default btn-block waves-effect" style="border-color: #ddd;">
                        <iconify-icon icon="icon-park-outline:refresh"></iconify-icon> REFRESH
                    </button>
                </div>
            </div>

            <div id="sk-table" class="skeleton-wrapper">
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="tabela-pagadores">
                    <thead>
                    <tr>
                        <th width="200">Unidade (MRK)</th>
                        <th>Nome / Razão Social (Pluggy)</th>
                        <th width="150">Documento (CNPJ)</th>
                        <th class="text-center" width="120">Open Finance</th>
                        <th class="text-center" width="100">Status API</th>
                        <th class="text-right" width="80">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td colspan="6" class="text-center muted" style="padding: 40px;">Carregando dados...</td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 10px; text-align: right;">
                <small class="muted" id="infoStatus"></small>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="modalPayer" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Ativar Unidade no Open Finance</h4>
            </div>
            <div class="modal-body" style="padding: 20px 30px;">
                <form id="formPayer">
                    <input type="hidden" id="payerId" name="payerId">

                    <div class="row clearfix">
                        <div class="col-md-8">
                            <label class="form-label">Selecione a Unidade MRK *</label>
                            <select class="form-control" id="system_unit_id" name="system_unit_id" required>
                                <option value="">Carregando unidades...</option>
                            </select>
                            <small class="text-muted" style="font-size: 10px;">Apenas unidades com Open Finance = 0 aparecem aqui.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CNPJ da Unidade *</label>
                            <input type="text" class="form-control" id="cpfCnpj" name="cpfCnpj" readonly required>
                        </div>
                    </div>

                    <div class="row clearfix">
                        <div class="col-md-6">
                            <label class="form-label">Nome / Razão Social *</label>
                            <input type="text" class="form-control" id="name" name="name" maxlength="250" required placeholder="Ex: MRK Soluções LTDA">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail de Contato</label>
                            <input type="email" class="form-control" id="email" name="email" maxlength="250" placeholder="financeiro@empresa.com.br">
                        </div>
                    </div>

                    <div class="section-title">Dados de Endereço</div>

                    <div class="row clearfix">
                        <div class="col-md-3">
                            <label class="form-label">CEP *</label>
                            <input type="text" class="form-control" id="zipcode" name="zipcode" required placeholder="00000-000">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Logradouro *</label>
                            <input type="text" class="form-control" id="street" name="street" maxlength="250" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Número *</label>
                            <input type="text" class="form-control" id="addressNumber" name="addressNumber" maxlength="10" required>
                        </div>
                    </div>

                    <div class="row clearfix">
                        <div class="col-md-5">
                            <label class="form-label">Bairro *</label>
                            <input type="text" class="form-control" id="neighborhood" name="neighborhood" maxlength="250" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Complemento</label>
                            <input type="text" class="form-control" id="addressComplement" name="addressComplement" maxlength="250">
                        </div>
                    </div>

                    <div class="row clearfix">
                        <div class="col-md-9">
                            <label class="form-label">Cidade *</label>
                            <input type="text" class="form-control" id="city" name="city" maxlength="250" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">UF *</label>
                            <input type="text" class="form-control" id="state" name="state" maxlength="2" required placeholder="Ex: PR" style="text-transform: uppercase;">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #eee;">
                <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-primary waves-effect" id="btnSavePayer" style="background-color: var(--mrk-blue); border: none;">
                    SALVAR E ATIVAR
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

    let dadosBrutos = [];
    let dadosFiltrados = [];

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function toggleLoading(isLoading) {
        if (isLoading) {
            $('#tabela-pagadores tbody').empty();
            $('#sk-table').show();
        } else {
            $('#sk-table').hide();
        }
    }

    function debounce(fn, ms){
        let t; return function(){ clearTimeout(t); const args = arguments; t = setTimeout(() => fn.apply(null, args), ms); }
    }

    $(document).ready(() => {
        fetchBackend();
        carregarSelectUnidades();

        $('#btnAtualizar').on('click', fetchBackend);
        $('#filtroTexto').on('input', debounce(applyFiltrosTempoReal, 200));
        $('#btnSavePayer').on('click', salvarPagador);

        // Preencher o CNPJ automaticamente ao selecionar a unidade no modal
        $('#system_unit_id').on('change', function() {
            const cnpj = $(this).find(':selected').data('cnpj');
            $('#cpfCnpj').val(cnpj || '');

            // Sugere o nome da unidade no campo Razão Social se estiver vazio
            const nomeUnidade = $(this).find(':selected').text().split(' - ')[1];
            if (!$('#name').val() && nomeUnidade) {
                $('#name').val(nomeUnidade);
            }
        });

        // ================= INTEGRAÇÃO VIACEP =================
        $('#zipcode').on('blur', async function() {
            let cep = $(this).val().replace(/\D/g, '');
            if (cep.length === 8) {
                // Coloca um loading visual básico enquanto busca
                const btnSave = $('#btnSavePayer');
                btnSave.prop('disabled', true);

                try {
                    const response = await axios.get(`https://viacep.com.br/ws/${cep}/json/`);
                    if (!response.data.erro) {
                        $('#street').val(response.data.logradouro);
                        $('#neighborhood').val(response.data.bairro);
                        $('#city').val(response.data.localidade);
                        $('#state').val(response.data.uf);
                        // Move o foco para o número
                        $('#addressNumber').focus();
                    } else {
                        Swal.fire('Atenção', 'CEP não encontrado.', 'warning');
                    }
                } catch (error) {
                    console.error('Erro ao buscar CEP', error);
                } finally {
                    btnSave.prop('disabled', false);
                }
            }
        });
    });

    // ================= BACKEND CALLS =================

    async function carregarSelectUnidades() {
        try {
            const res = await axios.post(baseUrl, {
                method: 'openFinanceListAvailableUnits', // Ajustado para o nome do método no seu PHP
                token: token,
                data: {}
            });

            const $select = $('#system_unit_id');
            $select.empty().append('<option value="">Selecione uma Unidade...</option>');

            // Verifica se os dados vieram encapsulados no 'data' ou direto no 'res.data'
            const unidades = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);

            unidades.forEach(unit => {
                $select.append(`<option value="${unit.id}" data-cnpj="${unit.cnpj}">${unit.id} - ${escapeHtml(unit.name)}</option>`);
            });

        } catch (e) {
            console.error("Falha ao carregar unidades.", e);
        }
    }

    async function fetchBackend(){
        toggleLoading(true);

        try {
            const res = await axios.post(baseUrl, {
                method: 'openFinanceListAllPayers', // Ajustado para o nome do método no seu PHP
                token: token,
                data: {}
            });

            // Extrai o array diretamente (corrige o bug de não carregar o grid)
            dadosBrutos = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);

            $('#filtroTexto').val('');
            applyFiltrosTempoReal();
            toggleLoading(false);

        } catch (e) {
            console.error(e);
            toggleLoading(false);
            Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');
        }
    }

    async function salvarPagador() {
        const form = document.getElementById('formPayer');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        const $btn = $('#btnSavePayer');
        const txtOriginal = $btn.text();
        $btn.text('ENVIANDO...').prop('disabled', true);

        try {
            const res = await axios.post(baseUrl, {
                method: 'openFinanceCreatePayer', // Ajustado para bater com o case do index.php
                token: token,
                data: payload
            });

            // O backend da Tecnospeed as vezes retorna sucesso sem a palavra status:'success' no root dependendo de como você montou.
            // Se der algum erro aqui, verifique o que exatamente o createPayer() devolve.
            if (!res.data.error) {
                Swal.fire('Sucesso', 'Unidade ativada com sucesso no Pluggy!', 'success');
                $('#modalPayer').modal('hide');
                fetchBackend();
                carregarSelectUnidades();
            } else {
                Swal.fire('Atenção', res.data.error || res.data.message || 'Falha ao integrar unidade.', 'warning');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Erro', 'Falha na comunicação ao tentar salvar.', 'error');
        } finally {
            $btn.text(txtOriginal).prop('disabled', false);
        }
    }

    function desativarPagador(id, unit_id) {
        Swal.fire({
            title: 'Desativar Unidade?',
            text: "A unidade perderá a conexão com o banco de dados do Open Finance.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Sim, desativar',
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const res = await axios.post(baseUrl, {
                        method: 'openFinanceDeactivatePayer', // Ajustado
                        token: token,
                        data: { system_unit_id: unit_id, id: id }
                    });

                    if (!res.data.error) {
                        Swal.fire('Desativado!', 'Integração encerrada.', 'success');
                        fetchBackend();
                        carregarSelectUnidades();
                    } else {
                        Swal.fire('Erro', res.data.error || res.data.message, 'error');
                    }
                } catch(e) {
                    Swal.fire('Erro', 'Falha ao desativar integração.', 'error');
                }
            }
        });
    }

    // ================= UI / RENDER =================

    function abrirModalNovo() {
        $('#formPayer')[0].reset();
        $('#payerId').val('');
        $('#cpfCnpj').val('');
        $('#modalPayer').modal('show');
    }

    function applyFiltrosTempoReal(){
        const termo = ($('#filtroTexto').val() || '').trim().toLowerCase();
        let arr = Array.isArray(dadosBrutos) ? dadosBrutos.slice() : [];

        if (termo) {
            arr = arr.filter(p => {
                const doc  = String(p.cpf_cnpj || '').toLowerCase();
                const nome = String(p.name || '').toLowerCase();
                const unitName = String(p.unit_name || '').toLowerCase();
                return doc.includes(termo) || nome.includes(termo) || unitName.includes(termo);
            });
        }

        dadosFiltrados = arr;
        renderTotalizadores(arr);
        renderTabela(arr);
        renderInfoStatus();
    }

    function renderTotalizadores(lista) {
        $('#totRegistros').text(lista.length);
        const ativos = lista.filter(i => parseInt(i.active) === 1).length;
        $('#totAtivos').text(ativos);
    }

    function renderInfoStatus(){
        const total = dadosBrutos.length;
        const filtrados = dadosFiltrados.length;
        if (total === 0) { $('#infoStatus').text(''); }
        else if (filtrados !== total) { $('#infoStatus').html(`Exibindo <b>${filtrados}</b> de <b>${total}</b> unidades.`); }
        else { $('#infoStatus').html(`Total de <b>${total}</b> unidades integradas.`); }
    }

    function renderTabela(lista){
        const $tbody = $('#tabela-pagadores tbody');
        $tbody.empty();

        if (lista.length === 0) {
            $tbody.append('<tr><td colspan="6" class="text-center muted" style="padding: 30px;">Nenhuma unidade configurada para Open Finance.</td></tr>');
            return;
        }

        lista.forEach(item => {
            const isAtivo = parseInt(item.active) === 1;
            const badgeAtivo = isAtivo ? '<span class="badge-status ativo">Ativo</span>' : '<span class="badge-status inativo">Inativo</span>';
            const badgeOpen = parseInt(item.statement_actived) === 1
                ? '<span class="badge-status ativo"><iconify-icon icon="icon-park-outline:check-small"></iconify-icon> Habilitado</span>'
                : '<span class="badge-status inativo">Desabilitado</span>';

            let tr = `
                <tr style="${!isAtivo ? 'opacity: 0.6;' : ''}">
                    <td><span class="text-muted">#${item.system_unit_id}</span> - <strong>${escapeHtml(item.unit_name || 'Desconhecida')}</strong></td>
                    <td>${escapeHtml(item.name)}</td>
                    <td class="nowrap">${escapeHtml(item.cpf_cnpj)}</td>
                    <td class="text-center">${badgeOpen}</td>
                    <td class="text-center">${badgeAtivo}</td>
                    <td class="text-right nowrap">
                        ${isAtivo ? `<button class="btn btn-xs btn-danger" title="Desativar" onclick="desativarPagador(${item.id}, ${item.system_unit_id})"><iconify-icon icon="icon-park-outline:close-one"></iconify-icon></button>` : ''}
                    </td>
                </tr>
            `;
            $tbody.append(tr);
        });
    }
</script>

</body>
</html>