<?php
session_name('PHPSESSID_MRKSolutions');
session_start();

if (!isset($_SESSION['MRKSolutions'])) {
    die("Sessão expirada. Faça login novamente.");
}

$appData = $_SESSION['MRKSolutions'];
$token   = $appData['sessionid']  ?? '';
$unit_id = $appData['userunitid'] ?? '';

if (empty($token)) {
    die("Acesso negado.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório - Manipulações Realizadas</title>
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

        /* Card Principal */
        .card {
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            border-top: 3px solid var(--mrk-blue) !important;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        /* --- KPIs Internos --- */
        .kpi-card {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #eee;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s;
            height: 100%;
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

        .kpi-card.blue   { border-left: 4px solid var(--mrk-blue); }
        .kpi-card.green  { border-left: 4px solid var(--mrk-green); }
        .kpi-card.orange { border-left: 4px solid var(--mrk-amber); }
        .kpi-card.red    { border-left: 4px solid var(--mrk-red); }

        .kpi-title { font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px; }
        .kpi-value { font-size: 22px; font-weight: 700; font-family: 'Kanit', sans-serif; color: var(--mrk-black); }

        .kpi-bg-icon {
            position: absolute; right: 15px; top: 15px; font-size: 28px; opacity: 0.15;
        }
        .kpi-card.blue .kpi-bg-icon { color: var(--mrk-blue); }
        .kpi-card.green .kpi-bg-icon { color: var(--mrk-green); }
        .kpi-card.orange .kpi-bg-icon { color: var(--mrk-amber); }
        .kpi-card.red .kpi-bg-icon { color: var(--mrk-red); }

        /* Filtros */
        .header-flex { display: flex; align-items: center; justify-content: space-between; }
        .filter-box { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #eee; }
        .input-group-addon { background-color: #f0f4f8; border: 1px solid #ddd; border-right: none; color: var(--mrk-blue); }

        /* Tabela */
        .table thead th {
            font-family: 'Kanit', sans-serif;
            color: var(--mrk-blue);
            font-weight: 600;
            font-size: 12px;
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }
        .table tbody td { font-size: 12px; vertical-align: middle !important; border-top: 1px solid #f1f1f1; }

        /* Estilos Mestre-Detalhe (Relatório de Manipulação) */
        .row-master { background: #fff; cursor: pointer; transition: background 0.2s; }
        .row-master:hover { background: #f9fbfd; }
        .row-details { background: #fafbfc; display: none; }
        .details-container { padding: 15px 40px; border-left: 3px solid var(--mrk-amber); }

        .table-sub { width: auto; min-width: 400px; background: white; margin: 10px 0; border: 1px solid #e0e0e0; }
        .table-sub th { background: #f0f0f0; font-size: 11px; color: #555; text-transform: uppercase; padding: 6px 10px; }
        .table-sub td { padding: 6px 10px; font-size: 12px; border-bottom: 1px solid #f0f0f0; }

        .toggle-icon { transition: transform 0.2s; color: var(--mrk-blue); font-size: 18px; display: inline-block; }
        .row-master.expanded .toggle-icon { transform: rotate(180deg); }

        .badge-perda { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; color: white; }
        .badge-perda.ok { background: var(--mrk-green); }
        .badge-perda.alta { background: var(--mrk-red); }

        /* Skeleton */
        .skeleton-wrapper { display: none; margin-top: 20px; }
        .skeleton { background: #e0e0e0; border-radius: 4px; margin-bottom: 10px; animation: pulse 1.5s infinite; }
        .sk-row { height: 40px; width: 100%; margin-bottom: 8px; }
        @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }
    </style>
</head>

<body>

<div class="container-fluid">

    <div class="card">
        <div class="header header-flex">
            <h2>
                <iconify-icon icon="icon-park-outline:experiment" width="24" style="color: var(--mrk-blue); vertical-align: bottom; margin-right: 5px;"></iconify-icon>
                MANIPULAÇÕES REALIZADAS
            </h2>
        </div>

        <div class="body">

            <div class="filter-box">
                <div class="row clearfix">
                    <div class="col-md-4">
                        <label class="muted">Data Inicial</label>
                        <div class="input-group">
                            <span class="input-group-addon"><iconify-icon icon="icon-park-outline:calendar"></iconify-icon></span>
                            <input type="date" id="dtInicio" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="muted">Data Final</label>
                        <div class="input-group">
                            <span class="input-group-addon"><iconify-icon icon="icon-park-outline:calendar"></iconify-icon></span>
                            <input type="date" id="dtFim" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4" style="margin-top: 22px;">
                        <button id="btnAtualizar" class="btn btn-primary btn-block waves-effect" style="background-color: var(--mrk-blue); border-color: var(--mrk-blue);">
                            <iconify-icon icon="icon-park-outline:search" style="vertical-align: middle; margin-right: 5px;"></iconify-icon>
                            ATUALIZAR DADOS
                        </button>
                    </div>
                </div>
            </div>

            <div class="row clearfix" style="margin-bottom: 20px;">
                <div class="col-md-4">
                    <div class="kpi-card blue">
                        <span class="kpi-title">Manipulações Realizadas</span>
                        <span class="kpi-value" id="totRegistros">0</span>
                        <iconify-icon icon="icon-park-outline:list-numbers" class="kpi-bg-icon"></iconify-icon>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="kpi-card" id="cardMmediaPerda">
                        <span class="kpi-title">Média de Perda no Período</span>
                        <span class="kpi-value" id="mediaPerda">0,00%</span>
                        <iconify-icon icon="icon-park-outline:chart-line-down" class="kpi-bg-icon"></iconify-icon>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="kpi-card green" id="cardPerdaZero">
                        <span class="kpi-title">Manipulações com Perda Zero</span>
                        <span class="kpi-value" id="totPerdaZero">0</span>
                        <iconify-icon icon="icon-park-outline:check-one" class="kpi-bg-icon"></iconify-icon>
                    </div>
                </div>
            </div>

            <div class="alert alert-info" style="font-size: 11px; padding: 10px; margin-bottom: 20px; background-color: #E3F2FD; border-color: #BBDEFB; color: #0D47A1;">
                <iconify-icon icon="icon-park-outline:info" style="vertical-align: middle; margin-right: 4px;"></iconify-icon>
                <strong>Dica:</strong> Clique sobre a linha da manipulação para expandir e visualizar os produtos resultantes gerados por ela.
            </div>

            <div class="row clearfix" style="margin-bottom: 15px;">
                <div class="col-md-12">
                    <div class="input-group">
                        <span class="input-group-addon"><iconify-icon icon="icon-park-outline:search"></iconify-icon></span>
                        <input type="text" id="filtroTexto" class="form-control" placeholder="Filtrar por Documento, Código ou Nome da Matéria-Prima...">
                    </div>
                </div>
            </div>

            <div id="sk-table" class="skeleton-wrapper">
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
                <div class="skeleton sk-row"></div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="tabela-relatorio">
                    <thead>
                    <tr>
                        <th width="40"></th>
                        <th class="nowrap" width="100">Data</th>
                        <th class="nowrap" width="120">Documento</th>
                        <th width="80">Código</th>
                        <th>Matéria-Prima Base</th>
                        <th class="text-right nowrap" width="140">Qtd. Total</th>
                        <th class="text-right nowrap" width="120">Perda</th>
                        <th class="text-center nowrap" width="80">% Perda</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td colspan="8" class="text-center muted" style="padding: 40px;">
                            Utilize os filtros acima e clique em <b>ATUALIZAR DADOS</b>.
                        </td>
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

<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script src="bsb/plugins/bootstrap/js/bootstrap.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
    // ================= CONFIG =================
    const baseUrl = window.location.hostname !== 'localhost'
        ? 'https://portal.mrksolucoes.com.br/api/v1/index.php'
        : 'http://localhost/portal-mrk/api/v1/index.php';

    const token = "<?php echo $token; ?>";
    const system_unit_id = "<?php echo $unit_id; ?>";

    // ================= STATE =================
    let dadosBrutos = [];
    let dadosFiltrados = [];

    // ================= UTILS =================
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function formatNumber(v) {
        const n = Number(v || 0);
        return n.toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
    }

    function toggleLoading(isLoading) {
        if (isLoading) {
            $('#tabela-relatorio tbody').empty();
            $('#sk-table').show();
        } else {
            $('#sk-table').hide();
        }
    }

    function debounce(fn, ms){
        let t; return function(){ clearTimeout(t); const args = arguments; t = setTimeout(() => fn.apply(null, args), ms); }
    }

    // ================= INIT =================
    $(document).ready(() => {
        // Datas Padrão (Últimos 7 dias)
        const hoje = new Date();
        const y = hoje.getFullYear();
        const m = String(hoje.getMonth() + 1).padStart(2, '0');
        const d = String(hoje.getDate()).padStart(2, '0');
        $('#dtFim').val(`${y}-${m}-${d}`);

        const ini = new Date(hoje.getTime() - 7*24*60*60*1000);
        const y2 = ini.getFullYear();
        const m2 = String(ini.getMonth() + 1).padStart(2, '0');
        const d2 = String(ini.getDate()).padStart(2, '0');
        $('#dtInicio').val(`${y2}-${m2}-${d2}`);

        // Eventos
        $('#btnAtualizar').on('click', fetchBackend);
        $('#filtroTexto').on('input', debounce(applyFiltrosTempoReal, 150));

        // Expansão da Linha (Master-Detail)
        $('#tabela-relatorio').on('click', '.row-master', function() {
            $(this).toggleClass('expanded');
            $(this).next('.row-details').fadeToggle(150);
        });
    });

    // ================= BACKEND =================
    async function fetchBackend(){
        const dt_inicio = $('#dtInicio').val();
        const dt_fim    = $('#dtFim').val();

        if (!dt_inicio || !dt_fim) {
            return Swal.fire('Atenção', 'Informe o período.', 'warning');
        }

        toggleLoading(true);

        try {
            const payload = {
                unit_id: system_unit_id,
                data_inicio: dt_inicio,
                data_fim: dt_fim
            };

            // O endpoint que criamos anteriormente
            const res = await axios.post(baseUrl, {
                method: 'getRelatorioManipulacao',
                token: token,
                data: payload
            });

            if (!res.data || res.data.success === false) {
                toggleLoading(false);
                dadosBrutos = [];
                applyFiltrosTempoReal();
                return Swal.fire('Atenção', res.data?.message || 'Nenhum registro encontrado.', 'warning');
            }

            dadosBrutos = res.data.data || [];

            $('#filtroTexto').val('');
            applyFiltrosTempoReal();

            toggleLoading(false);

            if (dadosBrutos.length === 0) {
                Swal.fire('Info', 'Nenhuma manipulação registrada no período.', 'info');
            }

        } catch (e) {
            console.error(e);
            toggleLoading(false);
            Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');
        }
    }

    // ================= FILTROS & RENDER =================
    function applyFiltrosTempoReal(){
        const termo = ($('#filtroTexto').val() || '').trim().toLowerCase();
        let arr = Array.isArray(dadosBrutos) ? dadosBrutos.slice() : [];

        if (termo) {
            arr = arr.filter(p => {
                const doc   = String(p.doc_mp || '').toLowerCase();
                const data  = String(p.data || '').toLowerCase();
                const cod   = String(p.insumo?.codigo || '').toLowerCase();
                const nome  = String(p.insumo?.nome || '').toLowerCase();

                return doc.includes(termo) || data.includes(termo) || cod.includes(termo) || nome.includes(termo);
            });
        }

        dadosFiltrados = arr;
        renderTotalizadores(arr);
        renderTabela(arr);
        renderInfoStatus();
    }

    function renderTotalizadores(lista) {
        const $qtdRegistros = $('#totRegistros');
        const $mediaPerda   = $('#mediaPerda');
        const $qtdPerdaZero = $('#totPerdaZero');
        const $cardMedia    = $('#cardMmediaPerda');

        $qtdRegistros.text(lista.length);

        if (lista.length === 0) {
            $mediaPerda.text('0,00%');
            $qtdPerdaZero.text('0');
            $cardMedia.removeClass('red green orange').addClass('green');
            return;
        }

        let somaPercPerda = 0;
        let countPerdaZero = 0;

        lista.forEach(item => {
            const perc = Number(item.metricas?.perc_perda || 0);
            somaPercPerda += perc;
            if (perc <= 0) countPerdaZero++;
        });

        const media = somaPercPerda / lista.length;

        $mediaPerda.text(media.toFixed(2).replace('.', ',') + '%');
        $qtdPerdaZero.text(countPerdaZero);

        // Ajusta a cor do card da média baseada no valor (ex: > 5% fica vermelho)
        $cardMedia.removeClass('red green orange');
        if (media > 5) {
            $cardMedia.addClass('red');
        } else if (media > 0) {
            $cardMedia.addClass('orange');
        } else {
            $cardMedia.addClass('green');
        }
    }

    function renderInfoStatus(){
        const total = dadosBrutos.length;
        const filtrados = dadosFiltrados.length;

        if (total === 0) {
            $('#infoStatus').text('');
        } else if (filtrados !== total) {
            $('#infoStatus').html(`Exibindo <b>${filtrados}</b> de <b>${total}</b> manipulações.`);
        } else {
            $('#infoStatus').html(`Total de <b>${total}</b> manipulações.`);
        }
    }

    function renderTabela(lista){
        const $tbody = $('#tabela-relatorio tbody');
        $tbody.empty();

        if (lista.length === 0) {
            $tbody.append('<tr><td colspan="8" class="text-center muted" style="padding: 30px;">Nenhum registro encontrado.</td></tr>');
            return;
        }

        const limiteRender = 500;
        const renderList = lista.slice(0, limiteRender);

        renderList.forEach(item => {
            const insumo = item.insumo || {};
            const metrics = item.metricas || {};

            const badgeClass = metrics.perc_perda > 5 ? 'alta' : 'ok';

            // HTML Linha Principal
            let trMaster = `
                <tr class="row-master">
                    <td class="text-center"><iconify-icon icon="icon-park-outline:down" class="toggle-icon"></iconify-icon></td>
                    <td class="nowrap">${escapeHtml(item.data)}</td>
                    <td class="nowrap"><strong>#${escapeHtml(item.doc_mp)}</strong></td>
                    <td class="nowrap text-muted">${escapeHtml(insumo.codigo)}</td>
                    <td>${escapeHtml(insumo.nome)} <br> <small class="text-muted">Aproveitado: ${formatNumber(metrics.qtd_aproveitada)} ${insumo.und}</small></td>
                    <td class="text-right nowrap"><strong>${formatNumber(metrics.qtd_total_manipulada)} ${insumo.und}</strong></td>
                    <td class="text-right nowrap text-danger">${formatNumber(metrics.qtd_perda)} ${insumo.und}</td>
                    <td class="text-center nowrap"><span class="badge-perda ${badgeClass}">${metrics.perc_perda.toFixed(2).replace('.', ',')}%</span></td>
                </tr>
            `;

            // HTML Subtabela (Produtos Resultantes)
            let trDetails = `<tr class="row-details"><td colspan="8"><div class="details-container">`;
            trDetails += `<h6 style="margin-top:0; color:#555; font-family:'Kanit';">Produtos Resultantes (Gerados)</h6>`;
            trDetails += `<table class="table-sub"><thead><tr><th>Cód</th><th>Produto Resultante</th><th class="text-right">Qtd. Gerada</th></tr></thead><tbody>`;

            if (item.itens_resultantes && item.itens_resultantes.length > 0) {
                item.itens_resultantes.forEach(sub => {
                    trDetails += `<tr>
                                    <td>${escapeHtml(sub.codigo)}</td>
                                    <td>${escapeHtml(sub.nome)}</td>
                                    <td class="text-right"><strong>${formatNumber(sub.quantidade)} ${escapeHtml(sub.und)}</strong></td>
                                  </tr>`;
                });
            } else {
                trDetails += `<tr><td colspan="3" class="text-muted">Nenhum produto resultante registrado.</td></tr>`;
            }

            trDetails += `</tbody></table></div></td></tr>`;

            $tbody.append(trMaster);
            $tbody.append(trDetails);
        });

        if (lista.length > limiteRender) {
            $tbody.append(`<tr><td colspan="8" class="text-center text-warning" style="padding: 10px;">Exibindo apenas os primeiros ${limiteRender} registros. Refine sua busca.</td></tr>`);
        }
    }
</script>

</body>
</html>