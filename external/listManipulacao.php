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
    <title>Manipulação | Portal MRK</title>
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
            border-top: 2px solid var(--mrk-amber) !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
        }
        .card .header h2 { font-family: 'Kanit', sans-serif; font-weight: 600; color: var(--mrk-amber); display: flex; align-items: center; gap: 8px; }

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

        /* ====== MODAL ====== */
        .modal-content { border-radius: 12px; border: none; overflow: hidden; }
        .modal-header { position: relative; border-bottom: 2px solid var(--mrk-amber); padding: 18px 25px; background: #fff; }
        .modal-title { font-family: 'Kanit', sans-serif; color: var(--mrk-amber); font-weight: 600; padding-right: 35px; }
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
            <h2><iconify-icon icon="icon-park-outline:knife-fork" width="24"></iconify-icon> FICHAS DE MANIPULAÇÃO</h2>
        </div>
        <div class="body">
            <div class="row clearfix" style="margin-bottom: 20px;">
                <div class="col-md-6 mb-2">
                    <label>Pesquisar Matéria-Prima (Insumo Original)</label>
                    <input type="text" id="filtroNome" class="form-control" placeholder="Nome ou código...">
                </div>
                <div class="col-md-3 mb-2">
                    <label>Status da Ficha</label>
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
                        <th>Insumo</th>
                        <th class="text-center">Status da Ficha</th>
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
                <h4 class="modal-title" id="modalTituloFicha">Manipulacao</h4>
                <button class="btn-close-mrk" data-dismiss="modal"><iconify-icon icon="icon-park-outline:close-small"></iconify-icon></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="insumoCodigoBase">

                <div class="row clearfix mb-3">
                    <div class="col-md-4">
                        <label title="Quantidade base desta matéria-prima a ser desmembrada">Rendimento / Qtd Base da Matéria-Prima</label>
                        <input type="text" id="rendimentoGlobal" class="form-control text-center mascara-qtd" value="1,000" style="font-weight:bold; font-size:16px; color:var(--mrk-amber);">
                    </div>
                </div>

                <hr style="margin: 10px 0 20px 0;">

                <div class="row clearfix" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 0 0 20px 0; border: 1px solid #eee;">
                    <div class="col-md-6 mb-2">
                        <label>Subproduto Gerado</label>
                        <select id="selectProdutoFinal" class="form-control"></select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>Quantidade</label>
                        <input type="text" id="quantidadeItem" class="form-control text-center mascara-qtd" placeholder="0,000">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label>&nbsp;</label>
                        <button id="adicionarSubproduto" class="btn btn-success btn-block" style="height:38px; background-color: var(--mrk-green) !important; padding:0;">
                            <iconify-icon icon="icon-park-outline:plus" style="vertical-align:middle;"></iconify-icon> INCLUIR
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 350px;">
                    <table class="table table-hover" id="tabela-subprodutos">
                        <thead>
                        <tr style="background:#f1f1f1;">
                            <th class="col-action">Excluir</th>
                            <th>Subproduto</th>
                            <th style="width: 150px;" class="text-center">Quantidade</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #eee;">
                <button id="salvarFicha" class="btn btn-primary" style="background-color: var(--mrk-amber) !important; border-color: var(--mrk-amber); padding: 10px 30px;">
                    <iconify-icon icon="icon-park-outline:save" style="vertical-align:middle; margin-right:5px;"></iconify-icon> SALVAR MANIPULAÇÃO
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

    let todasMateriasPrimas = [];

    // ====== MÁSCARA INTELIGENTE ======
    function applyQtyMask(input) {
        let value = input.value.replace(/\D/g, '');
        if (value === "") return;
        value = parseInt(value, 10) / 1000;
        input.value = value.toFixed(3).replace('.', ',');
    }

    $(document).ready(() => {
        carregarMateriasPrimas();

        $('#filtroNome').on('input', aplicarFiltros);
        $('#filtroFicha').on('change', aplicarFiltros);

        // Aplica a máscara em qualquer input que tiver a classe 'mascara-qtd'
        $(document).on('input', '.mascara-qtd', function() {
            applyQtyMask(this);
        });

        $('#adicionarSubproduto').click(() => {
            const id = $('#selectProdutoFinal').val();
            const nome = $('#selectProdutoFinal option:selected').text();
            const quantidade = $('#quantidadeItem').val();

            if (!id || !quantidade || quantidade === '0,000') {
                Swal.fire('Atenção', 'Selecione um subproduto e informe a quantidade.', 'warning');
                return;
            }

            $('#tabela-subprodutos tbody').prepend(`
                <tr data-product-id="${id}">
                    <td class="col-action"><a class="action-icon removerSubproduto"><iconify-icon icon="icon-park-outline:delete-five" style="color:var(--mrk-red)"></iconify-icon></a></td>
                    <td>${nome}</td>
                    <td><input type="text" class="form-control text-center mascara-qtd quantidade-grid" value="${quantidade}" style="font-weight:bold; height:30px;"></td>
                </tr>
            `);

            $('#quantidadeItem').val('');
            $('#selectProdutoFinal').val(null).trigger('change');
        });

        $('#tabela-subprodutos').on('click', '.removerSubproduto', function () { $(this).closest('tr').remove(); });

        $('#salvarFicha').click(async () => {
            const insumo_id = $('#insumoCodigoBase').val(); // A matéria prima fixa
            const rendimentoGlobal = parseFloat($('#rendimentoGlobal').val().replace(',', '.')) || 1;
            const manipulacoes = [];

            $('#tabela-subprodutos tbody tr').each(function () {
                const product_id = $(this).data('product-id');
                const quantity = parseFloat($(this).find('.quantidade-grid').val().replace(',', '.'));

                manipulacoes.push({
                    product_id: product_id,
                    insumo_id: insumo_id,
                    quantity: quantity,
                    rendimento: rendimentoGlobal, // O rendimento do topo é associado a todos os itens
                    system_unit_id: system_unit_id
                });
            });

            if(manipulacoes.length === 0) {
                Swal.fire('Atenção', 'Adicione pelo menos um subproduto gerado.', 'warning');
                return;
            }

            try {
                Swal.fire({ title: 'Salvando Manipulação...', didOpen: () => Swal.showLoading() });

                await axios.post(baseUrl, {
                    method: 'createManipulacao',
                    token,
                    data: { items: manipulacoes }
                });

                Swal.fire({ icon:'success', title:'Manipulação Salva!', timer:1500, showConfirmButton:false });
                $('#modalFicha').modal('hide');
                carregarMateriasPrimas();
            } catch (err) {
                Swal.fire('Erro', 'Erro ao salvar manipulação. Verifique os dados.', 'error');
            }
        });
    });

    function showMainSkeletons() {
        const tb = $('#listaProdutosBody').empty();
        for(let i=0; i<8; i++) {
            tb.append(`<tr><td class="col-action"><div class="skeleton" style="width:25px;height:25px;border-radius:50%"></div></td><td><div class="skeleton"></div></td><td><div class="skeleton"></div></td><td><div class="skeleton"></div></td></tr>`);
        }
    }

    async function carregarMateriasPrimas() {
        showMainSkeletons();
        try {
            // Mudar para o endpoint que lista Insumos disponíveis para manipulação no seu backend
            const res = await axios.post(baseUrl, { method: 'listInsumosComFichaStatus', token, data: { unit_id: system_unit_id } });
            todasMateriasPrimas = res.data.produtos;
            aplicarFiltros();
        } catch { Swal.fire('Erro', 'Erro ao carregar dados.', 'error'); }
    }

    function aplicarFiltros() {
        const nome = $('#filtroNome').val().toLowerCase(), ficha = $('#filtroFicha').val();
        let filtrados = todasMateriasPrimas.filter(p => {
            return p.nome.toLowerCase().includes(nome) && (ficha === "" || String(p.tem_ficha) === ficha);
        });

        const tbody = $('#listaProdutosBody').empty();
        filtrados.forEach(p => {
            const labelFicha = p.tem_ficha == 1 ? '<span class="label-mrk" style="background:#e8f5e9; color:#2e7d32;">Configurada</span>' : '<span class="label-mrk" style="background:#ffebee; color:#d32f2f;">Pendente</span>';

            tbody.append(`
                <tr>
                    <td class="col-action"><a class="action-icon" onclick="abrirFichaManipulacao(${p.codigo}, '${p.nome}')"><iconify-icon icon="icon-park-outline:edit-two"></iconify-icon></a></td>
                    <td><b>${p.codigo}</b></td>
                    <td>${p.nome}</td>
                    <td class="text-center">${labelFicha}</td>
                </tr>
            `);
        });
    }

    async function abrirFichaManipulacao(codigo_insumo, nome_insumo) {
        $('#modalTituloFicha').text(`${codigo_insumo} - ${nome_insumo}`);
        $('#insumoCodigoBase').val(codigo_insumo);
        $('#rendimentoGlobal').val('1,000'); // Reseta para 1kg ou 1 und padrão
        $('#tabela-subprodutos tbody').empty();

        if ($('#selectProdutoFinal').hasClass('select2-hidden-accessible')) $('#selectProdutoFinal').select2('destroy');
        $('#selectProdutoFinal').empty();

        Swal.fire({ title: 'Carregando...', didOpen: () => Swal.showLoading() });

        try {
            const [resFicha, resProdutos] = await Promise.all([
                axios.post(baseUrl, { method: 'listManipulacoes', token, data: { unit_id: system_unit_id } }),
                axios.post(baseUrl, { method: 'listInsumosDisponiveis', token, data: { system_unit_id } }) // Produtos finais disponíveis
            ]);

            const todasManipulacoes = resFicha.data.producoes || [];
            let itensDesmembrados = [];
            let rendimentoBase = 1;

            // Loop para buscar onde este item atual é a Matéria-Prima
            todasManipulacoes.forEach(prodFinal => {
                prodFinal.insumos.forEach(ins => {
                    if(ins.insumo_id == codigo_insumo) {
                        itensDesmembrados.push({
                            product_id: prodFinal.produto,
                            product_nome: prodFinal.nome,
                            quantity: ins.quantity,
                        });
                        rendimentoBase = ins.rendimento; // Pega o rendimento gravado
                    }
                });
            });

            // Se encontrou dados, preenche a tela
            if (itensDesmembrados.length > 0) {
                $('#rendimentoGlobal').val(parseFloat(rendimentoBase).toFixed(3).replace('.',','));

                itensDesmembrados.forEach(item => {
                    $('#tabela-subprodutos tbody').append(`
                        <tr data-product-id="${item.product_id}">
                            <td class="col-action"><a class="action-icon removerSubproduto"><iconify-icon icon="icon-park-outline:delete-five" style="color:var(--mrk-red)"></iconify-icon></a></td>
                            <td>${item.product_nome}</td>
                            <td><input type="text" class="form-control text-center mascara-qtd quantidade-grid" value="${parseFloat(item.quantity).toFixed(3).replace('.',',')}" style="font-weight:bold; height:30px;"></td>
                        </tr>
                    `);
                });
            }

            // Popula o select de produtos disponíveis
            $('#selectProdutoFinal').append(new Option('', '', true, true));
            resProdutos.data.forEach(p => $('#selectProdutoFinal').append(new Option(`${p.codigo} - ${p.nome}`, p.codigo)));

            $('#selectProdutoFinal').select2({ dropdownParent: $('#modalFicha'), width: '100%', placeholder: 'Pesquisar subproduto...' });

            Swal.close();
            $('#modalFicha').modal('show');
        } catch { Swal.fire('Erro', 'Erro ao carregar dados.', 'error'); }
    }
</script>
</body>
</html>