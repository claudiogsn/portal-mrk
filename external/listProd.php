<?php
session_name('PHPSESSID_MRKSolutions');
session_start();

if (!isset($_SESSION['MRKSolutions'])) {
    die("Sessão expirada. Faça login novamente.");
}

$appData = $_SESSION['MRKSolutions'];

$token   = $appData['sessionid']    ?? '';
$unit_id = $appData['userunitid']   ?? '';
$user_id = $appData['userid']       ?? '';

if (empty($token)) {
    die("Acesso negado.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Fichas de Produção | Portal MRK</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

    <link href="bsb/plugins/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="bsb/plugins/sweetalert/sweetalert.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="bsb/css/style.css" rel="stylesheet">
    <link href="style/mrk.css" rel="stylesheet">

    <style>
        /* ====== AJUSTES GERAIS MRK ====== */
        html, body { background: transparent !important; }
        body { font-family: 'Poppins', sans-serif; background-color: #F4F7F6; }
        .container-fluid { padding-top: 15px; }

        .card {
            background: rgba(255, 255, 255, 0.98) !important;
            border-top: 2px solid var(--mrk-blue) !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
        }
        .card .header h2 { font-family: 'Kanit', sans-serif; font-weight: 600; color: var(--mrk-blue); display: flex; align-items: center; gap: 8px; }

        /* ====== SKELETON ====== */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
            border-radius: 4px; height: 15px; width: 100%; display: inline-block;
        }
        @keyframes skeleton-loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        /* ====== TABELA E AÇÕES ====== */
        .table thead th {
            background-color: #f8f9fa;
            color: var(--mrk-blue);
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            text-transform: uppercase;
        }
        .col-action { width: 60px; text-align: center !important; }
        .action-icon { font-size: 22px; cursor: pointer; transition: transform 0.2s; display: inline-block; text-decoration: none !important; color: var(--mrk-blue); }
        .action-icon:hover { transform: scale(1.1); color: var(--mrk-amber); }

        /* ====== MODAL CORRIGIDO ====== */
        .modal-content { border-radius: 12px; border: none; overflow: hidden; }
        .modal-header { position: relative; border-bottom: 2px solid var(--mrk-blue); padding: 18px 25px; background: #fff; }
        .modal-title { font-family: 'Kanit', sans-serif; color: var(--mrk-blue); font-weight: 600; padding-right: 35px; }
        .btn-close-mrk { position: absolute; top: 15px; right: 15px; color: var(--mrk-red); font-size: 26px; background: none; border: none; outline: none !important; cursor: pointer; }

        .label-mrk { border-radius: 4px; padding: 4px 8px; font-size: 10px; font-weight: 700; text-transform: uppercase; }

        /* Select2 MRK */
        .select2-container--default .select2-selection--single { height: 38px !important; border: 1px solid #ddd !important; border-radius: 6px !important; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; padding-left: 12px; }

        label { font-family: 'Kanit', sans-serif; font-size: 12px; color: #666; margin-bottom: 5px; }
        .form-control { height: 38px; border-radius: 6px; border: 1px solid #ddd; }
    </style>
</head>
<body class="theme-blue">

<div class="container-fluid">
    <div class="card">
        <div class="header">
            <h2><iconify-icon icon="icon-park-outline:id-card-h" width="24"></iconify-icon> FICHAS DE PRODUÇÃO</h2>
        </div>
        <div class="body">
            <div class="row clearfix" style="margin-bottom: 20px;">
                <div class="col-md-4 mb-2">
                    <label>Pesquisar Produto</label>
                    <input type="text" id="filtroNome" class="form-control" placeholder="Nome ou código...">
                </div>
                <div class="col-md-3 mb-2">
                    <label>Comprável?</label>
                    <select id="filtroCompravel" class="form-control">
                        <option value="">Todos</option>
                        <option value="1">Sim</option>
                        <option value="0">Não</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label>Ficha de Produção</label>
                    <select id="filtroFicha" class="form-control">
                        <option value="">Todos</option>
                        <option value="1">Com Ficha</option>
                        <option value="0">Sem Ficha</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="tabela-produtos">
                    <thead>
                    <tr>
                        <th class="col-action">Editar</th>
                        <th style="width: 100px;">Código</th>
                        <th>Descrição do Produto</th>
                        <th class="text-center">Comprável</th>
                        <th class="text-center">Ficha Status</th>
                    </tr>
                    </thead>
                    <tbody id="listaProdutosBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFicha" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modalTituloFicha">Ficha de Produção</h4>
                <button class="btn-close-mrk" data-dismiss="modal"><iconify-icon icon="icon-park-outline:close-small"></iconify-icon></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="produtoCodigo">

                <div class="row clearfix mb-3">
                    <div class="col-md-4">
                        <label>Rendimento da Receita (Qtd Final)</label>
                        <input type="text" id="rendimento" class="form-control text-center" style="font-weight:bold; font-size:16px; color:var(--mrk-blue);">
                    </div>
                </div>

                <hr style="margin: 10px 0 20px 0;">

                <div class="row clearfix" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 0 0 20px 0; border: 1px solid #eee;">
                    <div class="col-md-6 mb-2">
                        <label>Insumo / Matéria Prima</label>
                        <select id="selectInsumo" class="form-control"></select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>Quantidade</label>
                        <input type="text" id="quantidadeInsumo" class="form-control text-center" placeholder="0,000" style="font-weight:bold;">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>&nbsp;</label>
                        <button id="adicionarInsumo" class="btn btn-success btn-block" style="height:38px; background-color: var(--mrk-green) !important;">
                            <iconify-icon icon="icon-park-outline:plus" style="vertical-align:middle; margin-right: 5px;"></iconify-icon> INCLUIR
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 350px;">
                    <table class="table table-hover" id="tabela-insumos">
                        <thead>
                        <tr style="background:#f1f1f1;">
                            <th class="col-action">Excluir</th>
                            <th>Descrição do Insumo</th>
                            <th style="width: 150px;" class="text-center">Quantidade</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #eee;">
                <button id="salvarFicha" class="btn btn-primary" style="background-color: var(--mrk-blue) !important; padding: 10px 30px;">
                    <iconify-icon icon="icon-park-outline:save" style="vertical-align:middle; margin-right:5px;"></iconify-icon> SALVAR FICHA
                </button>
                <button class="btn btn-link" data-dismiss="modal" style="color:#666;">CANCELAR</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script src="bsb/plugins/bootstrap/js/bootstrap.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    'use strict';

    const baseUrl = window.location.hostname !== 'localhost' ? 'https://portal.mrksolucoes.com.br/api/v1/index.php' : 'http://localhost/portal-mrk/api/v1/index.php';
    const token = "<?php echo $token; ?>";
    const system_unit_id = "<?php echo $unit_id; ?>";

    let todosProdutos = [];

    // ====== MÁSCARA INTELIGENTE (LIMITE 5 DÍGITOS) ======
    function applyQtyMask(val) {
        let v = val.replace(/\D/g, '');
        if (v === '') return '';
        if (v.length > 8) v = v.substring(0, 8); // 5 antes + 3 depois
        return (parseInt(v) / 1000).toFixed(3).replace('.', ',');
    }

    $(document).ready(() => {
        carregarProdutos();

        $('#filtroNome').on('input', aplicarFiltros);
        $('#filtroCompravel, #filtroFicha').on('change', aplicarFiltros);

        // Aplica máscara nos campos de entrada
        $('#rendimento, #quantidadeInsumo').on('input', function() { $(this).val(applyQtyMask($(this).val())); });

        $('#adicionarInsumo').click(() => {
            const id = $('#selectInsumo').val(), nome = $('#selectInsumo option:selected').text(), qtd = $('#quantidadeInsumo').val();
            if (!id || !qtd || qtd === '0,000') {
                Swal.fire('Atenção', 'Selecione um insumo e informe a quantidade.', 'warning');
                return;
            }

            $('#tabela-insumos tbody').prepend(`
                <tr data-id="${id}">
                    <td class="col-action"><a class="action-icon removerInsumo"><iconify-icon icon="icon-park-outline:delete-five" style="color:var(--mrk-red)"></iconify-icon></a></td>
                    <td>${nome}</td>
                    <td><input type="text" class="form-control text-center quantidade-grid" value="${qtd}" style="font-weight:bold; height:30px;"></td>
                </tr>
            `);

            $('#quantidadeInsumo').val('');
            $('#selectInsumo').val(null).trigger('change');
            $('.quantidade-grid').off('input').on('input', function() { $(this).val(applyQtyMask($(this).val())); });
        });

        $('#tabela-insumos').on('click', '.removerInsumo', function () { $(this).closest('tr').remove(); });

        $('#salvarFicha').click(async () => {
            const product_id = $('#produtoCodigo').val();
            const rendimento = parseFloat($('#rendimento').val().replace(',', '.')) || 1;
            const insumos = [];

            $('#tabela-insumos tbody tr').each(function () {
                const qty = $(this).find('.quantidade-grid').val().replace(',', '.');
                insumos.push({ insumo_id: $(this).data('id'), quantity: parseFloat(qty), rendimento: rendimento });
            });

            try {
                Swal.fire({ title: 'Salvando...', didOpen: () => Swal.showLoading() });
                await axios.post(baseUrl, { method: 'saveFichaTecnica', token, data: { system_unit_id, product_id, rendimento, insumos } });
                Swal.fire({ icon:'success', title:'Ficha Salva!', timer:1500, showConfirmButton:false });
                $('#modalFicha').modal('hide');
                carregarProdutos();
            } catch { Swal.fire('Erro', 'Erro ao salvar ficha.', 'error'); }
        });
    });

    function showMainSkeletons() {
        const tb = $('#listaProdutosBody').empty();
        for(let i=0; i<8; i++) {
            tb.append(`<tr><td class="col-action"><div class="skeleton" style="width:25px;height:25px;border-radius:50%"></div></td><td><div class="skeleton"></div></td><td><div class="skeleton"></div></td><td><div class="skeleton"></div></td><td><div class="skeleton"></div></td></tr>`);
        }
    }

    async function carregarProdutos() {
        showMainSkeletons();
        try {
            const res = await axios.post(baseUrl, { method: 'listProdutosComFichaStatus', token, data: { system_unit_id } });
            todosProdutos = res.data.produtos;
            aplicarFiltros();
        } catch { Swal.fire('Erro', 'Erro ao carregar dados.', 'error'); }
    }

    function aplicarFiltros() {
        const nome = $('#filtroNome').val().toLowerCase(), comp = $('#filtroCompravel').val(), ficha = $('#filtroFicha').val();
        let filtrados = todosProdutos.filter(p => {
            return p.nome.toLowerCase().includes(nome) && (comp === "" || String(p.compravel) === comp) && (ficha === "" || String(p.tem_ficha) === ficha);
        });

        const tbody = $('#listaProdutosBody').empty();
        filtrados.forEach(p => {
            const labelFicha = p.tem_ficha == 1 ? '<span class="label-mrk" style="background:#e8f5e9; color:#2e7d32;">Cadastrada</span>' : '<span class="label-mrk" style="background:#ffebee; color:#d32f2f;">Pendente</span>';
            const labelComp = p.compravel == 1 ? '<span class="label-mrk" style="background:#e3f2fd; color:#0b46ac;">Sim</span>' : '<span class="label-mrk" style="background:#f5f5f5; color:#999;">Não</span>';

            tbody.append(`
                <tr>
                    <td class="col-action"><a class="action-icon" onclick="abrirFicha(${p.codigo}, '${p.nome}')"><iconify-icon icon="icon-park-outline:edit-two"></iconify-icon></a></td>
                    <td><b>${p.codigo}</b></td>
                    <td>${p.nome}</td>
                    <td class="text-center">${labelComp}</td>
                    <td class="text-center">${labelFicha}</td>
                </tr>
            `);
        });
    }

    async function abrirFicha(codigo, nome) {
        $('#modalTituloFicha').text(`Ficha de Produção: ${codigo} - ${nome}`);
        $('#produtoCodigo').val(codigo);
        $('#rendimento').val('1,000');
        $('#tabela-insumos tbody').empty();

        if ($('#selectInsumo').hasClass('select2-hidden-accessible')) $('#selectInsumo').select2('destroy');
        $('#selectInsumo').empty();

        Swal.fire({ title: 'Carregando Ficha...', didOpen: () => Swal.showLoading() });

        try {
            const [resFicha, resInsumos] = await Promise.all([
                axios.post(baseUrl, { method: 'getFichaTecnica', token, data: { system_unit_id, product_id: codigo } }),
                axios.post(baseUrl, { method: 'listInsumosDisponiveis', token, data: { system_unit_id } })
            ]);

            const ficha = resFicha.data;
            if (Array.isArray(ficha) && ficha.length > 0) {
                $('#rendimento').val(parseFloat(ficha[0].rendimento || 1).toFixed(3).replace('.', ','));
                ficha.forEach(ins => {
                    $('#tabela-insumos tbody').append(`
                        <tr data-id="${ins.insumo_id}">
                            <td class="col-action"><a class="action-icon removerInsumo"><iconify-icon icon="icon-park-outline:delete-five" style="color:var(--mrk-red)"></iconify-icon></a></td>
                            <td>${ins.insumo_nome}</td>
                            <td><input type="text" class="form-control text-center quantidade-grid" value="${parseFloat(ins.quantity).toFixed(3).replace('.',',')}" style="font-weight:bold; height:30px;"></td>
                        </tr>
                    `);
                });
            }

            $('#selectInsumo').append(new Option('', '', true, true));
            resInsumos.data.forEach(i => $('#selectInsumo').append(new Option(`${i.codigo} - ${i.nome}`, i.codigo)));

            $('#selectInsumo').select2({ dropdownParent: $('#modalFicha'), width: '100%', placeholder: 'Pesquisar ingrediente...' });
            $('.quantidade-grid').on('input', function() { $(this).val(applyQtyMask($(this).val())); });

            Swal.close();
            $('#modalFicha').modal('show');
        } catch { Swal.fire('Erro', 'Erro ao carregar dados.', 'error'); }
    }
</script>
</body>
</html>