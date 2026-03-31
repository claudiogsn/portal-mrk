<?php
session_name('PHPSESSID_MRKSolutions');
session_start();

if (!isset($_SESSION['MRKSolutions'])) {
    die("Sessão expirada. Faça login novamente.");
}

$appData = $_SESSION['MRKSolutions'];

$token   = $appData['sessionid']  ?? '';
$unit_id = $appData['userunitid'] ?? '';
$user_id = $appData['userid']     ?? '';

if (empty($token)) {
    die("Acesso negado.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Execução de Manipulação | Portal MRK</title>
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
        :root {
            --mrk-blue: #0B46AC;
            --mrk-green: #08A794;
            --mrk-amber: #F5A623;
            --mrk-red: #E53935;
            --mrk-gray: #F4F7F6;
            --mrk-text: #2b2b2b;
        }

        html, body { background: transparent !important; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F4F7F6;
            color: var(--mrk-text);
        }

        .container-fluid { padding-top: 15px; }

        .card {
            background: rgba(255, 255, 255, 0.98) !important;
            border-top: 2px solid var(--mrk-amber) !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
        }

        .card .header h2 {
            font-family: 'Kanit', sans-serif;
            font-weight: 600;
            color: var(--mrk-amber);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .table thead th {
            background-color: #f8f9fa;
            color: var(--mrk-blue);
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: 1px solid #eee;
        }

        .table tbody td {
            vertical-align: middle !important;
        }

        label {
            font-family: 'Kanit', sans-serif;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .form-control {
            height: 38px;
            border-radius: 6px;
            border: 1px solid #ddd;
            box-shadow: none;
        }

        .form-control:focus {
            border-color: var(--mrk-amber);
            box-shadow: 0 0 0 2px rgba(245,166,35,.12);
        }

        .select2-container--default .select2-selection--single {
            height: 38px !important;
            border: 1px solid #ddd !important;
            border-radius: 6px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
            padding-left: 12px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }

        .box-resumo {
            background: #fafafa;
            border: 1px solid #ececec;
            border-radius: 10px;
            padding: 15px;
        }

        .box-resumo h5 {
            font-family: 'Kanit', sans-serif;
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--mrk-blue);
            font-weight: 600;
        }

        .metric-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 7px 0;
            border-bottom: 1px dashed #ececec;
            font-size: 13px;
        }

        .metric-line:last-child { border-bottom: none; }

        .perda-valor {
            font-family: 'Kanit', sans-serif;
            font-size: 22px;
            font-weight: 700;
        }

        .perda-ok { color: var(--mrk-green); }
        .perda-alerta { color: var(--mrk-red); }

        .input-grid-qtd {
            min-width: 90px;
            font-weight: 700;
            text-align: center;
            height: 34px;
            padding: 4px 8px;
        }

        .btn-mrk-primary {
            background-color: var(--mrk-amber) !important;
            border-color: var(--mrk-amber) !important;
            color: #fff !important;
        }

        .btn-mrk-primary:hover,
        .btn-mrk-primary:focus {
            background-color: #e49314 !important;
            border-color: #e49314 !important;
            color: #fff !important;
        }

        .alert-mrk {
            background: #fffaf0;
            border: 1px solid #ffe0a3;
            border-radius: 8px;
            padding: 12px 14px;
            color: #8a5a00;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
            border-radius: 4px;
            height: 15px;
            width: 100%;
            display: inline-block;
        }

        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        #secaoFicha {
            display: none;
        }

        #secaoFicha.ativo {
            display: block;
        }

        .sem-ficha-msg {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-size: 14px;
        }

        .sem-ficha-msg iconify-icon {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
            color: #ccc;
        }

        @media (max-width: 768px) {
            .perda-valor { font-size: 18px; }
        }
    </style>
</head>
<body class="theme-blue">

<div class="container-fluid">
    <div class="card">
        <div class="header">
            <h2>
                <iconify-icon icon="icon-park-outline:experiment"></iconify-icon>
                EXECUÇÃO DE MANIPULAÇÃO
            </h2>
        </div>

        <div class="body">

            <div class="row clearfix" style="margin-bottom: 20px;">
                <div class="col-md-6 mb-2">
                    <label>Selecione a Matéria-Prima</label>
                    <select id="selectInsumo" class="form-control"></select>
                </div>

                <div class="col-md-3 mb-2">
                    <label>Quantidade Manipulada (KG)</label>
                    <input type="text" id="quantidadeManipulada" class="form-control text-center" placeholder="0,000" inputmode="decimal">
                </div>

                <div class="col-md-3 mb-2">
                    <label>Data</label>
                    <input type="date" id="dataManipulacao" class="form-control">
                </div>
            </div>

            <div class="alert-mrk">
                Selecione uma matéria-prima com ficha configurada. Os itens de saída serão carregados automaticamente da ficha.
                Informe a quantidade manipulada e ajuste o <b>peso unitário (em KG)</b> e a quantidade produzida para calcular a perda exata.
            </div>

            <div id="secaoFicha">

                <div class="row clearfix" style="margin-bottom: 20px;">
                    <div class="col-md-8">
                        <div class="box-resumo">
                            <h5>
                                <iconify-icon icon="icon-park-outline:list-view" style="vertical-align: middle;"></iconify-icon>
                                Itens de Saída da Ficha
                            </h5>

                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table table-hover" id="tabelaItensFicha">
                                    <thead>
                                    <tr>
                                        <th style="width: 70px;">Código</th>
                                        <th>Produto</th>
                                        <th style="width: 80px;" class="text-center">Unid.</th>
                                        <th style="width: 120px;" class="text-center" title="Quanto cada unidade representa em KG">Peso Unit. (KG)</th>
                                        <th style="width: 120px;" class="text-center">Qtd. Produzida</th>
                                    </tr>
                                    </thead>
                                    <tbody id="gridItensFicha"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="box-resumo">
                            <h5>
                                <iconify-icon icon="icon-park-outline:chart-pie-one" style="vertical-align: middle;"></iconify-icon>
                                Resumo (em KG)
                            </h5>

                            <div class="metric-line">
                                <span>Qtd. Manipulada</span>
                                <div><strong id="metricQtdManipulada">0,000</strong> KG</div>
                            </div>

                            <div class="metric-line">
                                <span>Total Convertido (Saída)</span>
                                <div><strong id="metricTotalSaida">0,000</strong> KG</div>
                            </div>

                            <div class="metric-line">
                                <span>Perda (Qtd)</span>
                                <div><strong class="perda-valor perda-ok" id="valorPerda">0,000</strong> KG</div>
                            </div>

                            <div class="metric-line">
                                <span>Perda (%)</span>
                                <strong class="perda-valor perda-ok" id="percPerda">0,00%</strong>
                            </div>
                        </div>

                        <div style="margin-top: 15px;">
                            <button id="btnExecutar" class="btn btn-mrk-primary btn-block" style="padding: 12px; font-size: 14px;">
                                <iconify-icon icon="icon-park-outline:play-one" style="vertical-align: middle; margin-right: 5px;"></iconify-icon>
                                EXECUTAR MANIPULAÇÃO
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <div id="msgSemFicha" style="display: none;">
                <div class="sem-ficha-msg">
                    <iconify-icon icon="icon-park-outline:caution"></iconify-icon>
                    Esta matéria-prima não possui ficha de manipulação configurada.
                </div>
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

    const baseUrl = window.location.hostname !== 'localhost'
        ? 'https://portal.mrksolucoes.com.br/api/v1/index.php'
        : 'http://localhost/portal-mrk/api/v1/index.php';

    const token          = "<?php echo $token; ?>";
    const system_unit_id = "<?php echo $unit_id; ?>";
    const user_id        = "<?php echo $user_id; ?>";

    let fichaItens = [];
    let insumosDisponiveis = [];

    // Unidades que usam 3 casas decimais (além do que já forçamos como KG)
    const UNIDADES_DECIMAL = ['KG', 'LT', 'L', 'M', 'M2', 'M3'];

    /* ===========================
       HELPERS
    =========================== */

    function toFloat(value) {
        if (value === null || value === undefined || value === '') return 0;
        var txt = String(value).replace(/\./g, '').replace(',', '.');
        var n = parseFloat(txt);
        return isNaN(n) ? 0 : n;
    }

    function formatBR(value, unit) {
        var unidade = String(unit || '').toUpperCase().trim();
        var decimals = (UNIDADES_DECIMAL.includes(unidade) || unidade === 'KG') ? 3 : 0;
        var num = parseFloat(value || 0);
        return num.toLocaleString('pt-BR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    // ====== MÁSCARA INTELIGENTE MELHORADA ======
    function applyQuantityMask(val, unit) {
        var unidade = String(unit || '').toUpperCase().trim();
        var v = val.replace(/\D/g, ''); // Remove tudo que não for número

        // Se for forçado como KG, aplica máscara de 3 casas decimais com vírgula de trás pra frente
        if (UNIDADES_DECIMAL.includes(unidade) || unidade === 'KG') {
            if (v === '') return '';
            var num = parseInt(v, 10);
            if (isNaN(num)) return '';

            var str = num.toString();
            // Limita a 5 dígitos inteiros + 3 decimais = 8 dígitos totais (Ex: 99999,999)
            if (str.length > 8) {
                str = str.substring(0, 8);
            }

            // Garante que tenha pelo menos 4 dígitos para formatar 0,xxx
            while (str.length < 4) {
                str = '0' + str;
            }

            var intPart = str.substring(0, str.length - 3);
            var decPart = str.substring(str.length - 3);

            // Remove zeros à esquerda da parte inteira desnecessários
            intPart = parseInt(intPart, 10).toString();

            return intPart + ',' + decPart;
        } else {
            // Unidades inteiras (UN, PCT, etc)
            if (v.length > 5) v = v.substring(0, 5);
            return v;
        }
    }

    function showLoading(title) {
        title = title || 'Carregando...';
        Swal.fire({
            title: title,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function() { Swal.showLoading(); }
        });
    }

    function closeLoading() {
        Swal.close();
    }

    /* ===========================
       CARREGAR MATÉRIAS-PRIMAS
    =========================== */

    async function carregarInsumos() {
        try {
            var res = await axios.post(baseUrl, {
                method: 'listInsumosComFichaStatus',
                token: token,
                data: { unit_id: system_unit_id }
            });

            if (!res.data.success) {
                throw new Error(res.data.message || 'Falha ao carregar matérias-primas');
            }

            insumosDisponiveis = (res.data.produtos || []).filter(function(p) {
                return Number(p.tem_ficha) === 1;
            });

            preencherSelectInsumo();
        } catch (err) {
            console.error(err);
            Swal.fire('Erro', 'Erro ao carregar matérias-primas.', 'error');
        }
    }

    function preencherSelectInsumo() {
        var $select = $('#selectInsumo');

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.empty();
        $select.append(new Option('', '', true, true));

        insumosDisponiveis.forEach(function(item) {
            var label = item.codigo + ' - ' + item.nome;
            var opt = new Option(label, item.codigo);
            $select.append(opt);
        });

        $select.select2({
            width: '100%',
            placeholder: 'Pesquisar matéria-prima...',
            allowClear: true
        });
    }

    /* ===========================
       CARREGAR FICHA DO INSUMO
    =========================== */

    async function carregarFichaDoInsumo(codigoInsumo) {
        fichaItens = [];
        $('#secaoFicha').removeClass('ativo');
        $('#msgSemFicha').hide();

        if (!codigoInsumo) {
            renderGrid();
            return;
        }

        showLoading('Carregando ficha...');

        try {
            var res = await axios.post(baseUrl, {
                method: 'listManipulacoes',
                token: token,
                data: { unit_id: system_unit_id }
            });

            if (!res.data.success) {
                throw new Error(res.data.message || 'Erro ao carregar ficha');
            }

            var producoes = res.data.producoes || [];
            var itensEncontrados = [];

            producoes.forEach(function(prodFinal) {
                (prodFinal.insumos || []).forEach(function(insumo) {
                    if (Number(insumo.insumo_id) === Number(codigoInsumo)) {
                        itensEncontrados.push({
                            product_id: prodFinal.produto,
                            nome: prodFinal.nome,
                            unidade: prodFinal.unidade || '',
                            quantidade: parseFloat(insumo.quantity || 0),
                            peso_conversao: 0 // Inicializa o fator de conversão como 1,000 KG
                        });
                    }
                });
            });

            closeLoading();

            if (itensEncontrados.length === 0) {
                $('#msgSemFicha').show();
                return;
            }

            fichaItens = itensEncontrados;
            $('#secaoFicha').addClass('ativo');
            renderGrid();

        } catch (err) {
            console.error(err);
            closeLoading();
            Swal.fire('Erro', err.message || 'Erro ao carregar ficha.', 'error');
        }
    }

    /* ===========================
       GRID E RESUMO
    =========================== */

    function renderGrid() {
        var tbody = $('#gridItensFicha').empty();

        if (fichaItens.length === 0) {
            tbody.append(
                '<tr><td colspan="5" class="text-center" style="padding:30px; color:#999;">Nenhum item de saída na ficha.</td></tr>'
            );
            atualizarResumo();
            return;
        }

        fichaItens.forEach(function(item, index) {
            var und = item.unidade || '';
            var valorFormatado = formatBR(item.quantidade, und); // Mantém a regra da unidade original na producao
            var pesoFormatado = formatBR(item.peso_conversao, 'KG'); // Força formatar peso como KG

            tbody.append(
                '<tr>' +
                '<td><b>' + item.product_id + '</b></td>' +
                '<td>' + item.nome + '</td>' +
                '<td class="text-center">' + (und || '-') + '</td>' +

                // Campo de Peso Unitário (Sempre tratado com vírgula / KG)
                '<td class="text-center">' +
                '<input type="text" ' +
                'class="form-control input-grid-qtd input-conversao-item" ' +
                'data-index="' + index + '" ' +
                'value="' + pesoFormatado + '" ' +
                'inputmode="decimal" title="Peso em KG por unidade">' +
                '</td>' +

                // Campo Quantidade Produzida (Respeita a unidade original)
                '<td class="text-center">' +
                '<input type="text" ' +
                'class="form-control input-grid-qtd input-qtd-item" ' +
                'data-index="' + index + '" ' +
                'data-und="' + und + '" ' +
                'value="' + valorFormatado + '" ' +
                'inputmode="decimal">' +
                '</td>' +
                '</tr>'
            );
        });

        atualizarResumo();
    }

    function atualizarResumo() {
        var qtdManipulada = toFloat($('#quantidadeManipulada').val());
        var totalSaidaConvertida = 0;

        // Calcula a saída multiplicando a Quantidade pelo Peso Unitário (Fator em KG)
        fichaItens.forEach(function(item) {
            var qtd = parseFloat(item.quantidade) || 0;
            var conversao = parseFloat(item.peso_conversao) || 0;
            totalSaidaConvertida += (qtd * conversao);
        });

        totalSaidaConvertida = Math.round(totalSaidaConvertida * 10000) / 10000;
        var perda = Math.round((qtdManipulada - totalSaidaConvertida) * 10000) / 10000;

        // Calcula a % da perda
        var perdaPerc = 0;
        if (qtdManipulada > 0) {
            perdaPerc = (perda / qtdManipulada) * 100;
        }

        // Atualiza a tela (Forçando tudo a usar KG)
        $('#metricQtdManipulada').text(formatBR(qtdManipulada, 'KG'));
        $('#metricTotalSaida').text(formatBR(totalSaidaConvertida, 'KG'));
        $('#valorPerda').text(formatBR(perda, 'KG'));
        $('#percPerda').text(perdaPerc.toFixed(2).replace('.', ',') + '%');

        // Cores de alerta
        var $perdaValor = $('#valorPerda');
        var $perdaPerc = $('#percPerda');

        $perdaValor.removeClass('perda-ok perda-alerta');
        $perdaPerc.removeClass('perda-ok perda-alerta');

        if (perda < 0) {
            $perdaValor.addClass('perda-alerta');
            $perdaPerc.addClass('perda-alerta');
        } else {
            $perdaValor.addClass('perda-ok');
            $perdaPerc.addClass('perda-ok');
        }
    }

    /* ===========================
       EXECUTAR MANIPULAÇÃO
    =========================== */

    async function executarManipulacao() {
        var insumo_id = Number($('#selectInsumo').val());
        var quantidade_manipulada = toFloat($('#quantidadeManipulada').val());
        var data = $('#dataManipulacao').val();

        var itens_saida = fichaItens
            .filter(function(item) { return (parseFloat(item.quantidade) || 0) > 0; })
            .map(function(item) {
                return {
                    product_id: Number(item.product_id),
                    quantidade: parseFloat(item.quantidade) || 0,
                    peso_conversao: parseFloat(item.peso_conversao) || 1
                };
            });

        // Recalcula perda para validação
        var totalSaidaConvertida = 0;
        itens_saida.forEach(function(item) {
            var fichaItem = fichaItens.find(function(f) { return Number(f.product_id) === Number(item.product_id); });
            var conversao = fichaItem ? (parseFloat(fichaItem.peso_conversao) || 0) : 1;
            totalSaidaConvertida += (item.quantidade * conversao);
        });

        var perda = Math.round((quantidade_manipulada - totalSaidaConvertida) * 10000) / 10000;
        var perdaPerc = quantidade_manipulada > 0 ? (perda / quantidade_manipulada) * 100 : 0;

        // Validações
        if (!insumo_id) {
            Swal.fire('Atenção', 'Selecione uma matéria-prima.', 'warning');
            return;
        }

        if (!quantidade_manipulada || quantidade_manipulada <= 0) {
            Swal.fire('Atenção', 'Informe a quantidade manipulada.', 'warning');
            return;
        }

        if (itens_saida.length === 0) {
            Swal.fire('Atenção', 'Nenhum item de saída com quantidade válida.', 'warning');
            return;
        }

        if (perda < 0) {
            Swal.fire('Atenção', 'A soma convertida dos itens de saída (' + formatBR(totalSaidaConvertida, 'KG') + ' KG) não pode ser maior que a quantidade manipulada (' + formatBR(quantidade_manipulada, 'KG') + ' KG).', 'warning');
            return;
        }

        var nomeInsumo = $('#selectInsumo option:selected').text();

        // Resumo HTML no Modal (Tudo forçado em KG)
        var listaItens = '';
        itens_saida.forEach(function(item) {
            var fichaItem = fichaItens.find(function(f) { return Number(f.product_id) === Number(item.product_id); });
            var nome = fichaItem ? fichaItem.nome : item.product_id;
            var und = fichaItem ? fichaItem.unidade : '';
            var conversao = fichaItem ? fichaItem.peso_conversao : 1;
            var subtotalConvertido = item.quantidade * conversao;

            listaItens += '<tr>' +
                '<td>' + nome + '</td>' +
                '<td class="text-right">' + formatBR(item.quantidade, und) + ' ' + und + '</td>' +
                '<td class="text-right">' + formatBR(subtotalConvertido, 'KG') + ' KG</td>' +
                '</tr>';
        });

        var htmlResumo =
            '<div style="text-align:left; font-size:13px;">' +
            '<p><b>Matéria-prima:</b> ' + nomeInsumo + '</p>' +
            '<p><b>Qtd. manipulada:</b> ' + formatBR(quantidade_manipulada, 'KG') + ' KG</p>' +
            '<table class="table table-condensed" style="font-size:12px; margin-top:10px;">' +
            '<thead><tr><th>Item Saída</th><th class="text-right">Qtd</th><th class="text-right">Convertido</th></tr></thead>' +
            '<tbody>' + listaItens + '</tbody>' +
            '</table>' +
            '<p><b>Perda Total:</b> ' + formatBR(perda, 'KG') + ' KG (' + perdaPerc.toFixed(2).replace('.', ',') + '%)</p>' +
            '</div>';

        var confirmacao = await Swal.fire({
            title: 'Confirmar execução?',
            html: htmlResumo,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, executar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#F5A623',
            width: 550
        });

        if (!confirmacao.isConfirmed) return;

        showLoading('Executando manipulação...');

        try {
            var payload = {
                system_unit_id: system_unit_id,
                insumo_id: insumo_id,
                quantidade_manipulada: quantidade_manipulada,
                itens_saida: itens_saida,
                usuario_id: user_id,
                data: data || undefined
            };

            var res = await axios.post(baseUrl, {
                method: 'executeManipulacao',
                token: token,
                data: payload
            });

            closeLoading();

            if (!res.data.success) {
                throw new Error(res.data.message || 'Falha ao executar manipulação');
            }

            await Swal.fire({
                icon: 'success',
                title: 'Manipulação executada!',
                html:
                    '<div style="font-size:14px;">' +
                    '<p><b>Documento:</b> ' + (res.data.doc || '-') + '</p>' +
                    (res.data.doc_perda ? '<p><b>Doc. Perda:</b> ' + res.data.doc_perda + '</p>' : '') +
                    '<p><b>Perda calculada:</b> ' + formatBR(perda, 'KG') + ' KG (' + perdaPerc.toFixed(2).replace('.', ',') + '%)</p>' +
                    '</div>'
            });

            // Limpa formulário
            $('#selectInsumo').val(null).trigger('change');
            $('#quantidadeManipulada').val('');
            fichaItens = [];
            $('#secaoFicha').removeClass('ativo');
            renderGrid();

        } catch (err) {
            console.error(err);
            closeLoading();
            Swal.fire('Erro', err.message || 'Erro ao executar manipulação.', 'error');
        }
    }

    /* ===========================
       EVENTOS
    =========================== */

    $(document).ready(function() {

        var hoje = new Date().toISOString().split('T')[0];
        $('#dataManipulacao').val(hoje);

        carregarInsumos();

        $('#selectInsumo').on('change', function() {
            var val = $(this).val();
            $('#quantidadeManipulada').val('');
            carregarFichaDoInsumo(val);
        });

        // Forçando o parâmetro 'KG' para aplicar a máscara correta independente da unidade
        $('#quantidadeManipulada').on('input', function() {
            var masked = applyQuantityMask($(this).val(), 'KG');
            $(this).val(masked);
            atualizarResumo();
        });

        // Forçando 'KG' para o Peso Unitário / Fator Conversão
        $('#gridItensFicha').on('input', '.input-conversao-item', function() {
            var index = Number($(this).data('index'));
            var masked = applyQuantityMask($(this).val(), 'KG');
            $(this).val(masked);

            var valor = toFloat(masked);
            if (fichaItens[index]) {
                fichaItens[index].peso_conversao = valor;
            }
            atualizarResumo();
        });

        // Único campo que respeita a unidade original
        $('#gridItensFicha').on('input', '.input-qtd-item', function() {
            var index = Number($(this).data('index'));
            var und = $(this).data('und') || '';
            var masked = applyQuantityMask($(this).val(), und);
            $(this).val(masked);

            var valor = toFloat(masked);
            if (fichaItens[index]) {
                fichaItens[index].quantidade = valor;
            }
            atualizarResumo();
        });

        $('#btnExecutar').on('click', function() {
            executarManipulacao();
        });
    });
</script>
</body>
</html>