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
                min-width: 120px;
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

                <!-- SELEÇÃO DA MATÉRIA-PRIMA -->
                <div class="row clearfix" style="margin-bottom: 20px;">
                    <div class="col-md-6 mb-2">
                        <label>Selecione a Matéria-Prima</label>
                        <select id="selectInsumo" class="form-control"></select>
                    </div>

                    <div class="col-md-3 mb-2">
                        <label>Quantidade Manipulada</label>
                        <input type="text" id="quantidadeManipulada" class="form-control text-center" placeholder="0,000" inputmode="decimal">
                    </div>

                    <div class="col-md-3 mb-2">
                        <label>Data</label>
                        <input type="date" id="dataManipulacao" class="form-control">
                    </div>
                </div>

                <div class="alert-mrk">
                    Selecione uma matéria-prima com ficha configurada. Os itens de saída serão carregados automaticamente da ficha.
                    Informe a quantidade manipulada e ajuste as quantidades de cada item conforme necessário. A perda será calculada automaticamente.
                </div>

                <!-- SEÇÃO DA FICHA (aparece após selecionar insumo) -->
                <div id="secaoFicha">

                    <div class="row clearfix" style="margin-bottom: 20px;">
                        <!-- GRID DE ITENS -->
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
                                            <th style="width: 80px;">Código</th>
                                            <th>Produto</th>
                                            <th style="width: 100px;" class="text-center">Unidade</th>
                                            <th style="width: 170px;" class="text-center">Quantidade</th>
                                        </tr>
                                        </thead>
                                        <tbody id="gridItensFicha"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- RESUMO -->
                        <div class="col-md-4">
                            <div class="box-resumo">
                                <h5>
                                    <iconify-icon icon="icon-park-outline:chart-pie-one" style="vertical-align: middle;"></iconify-icon>
                                    Resumo
                                </h5>

                                <div class="metric-line">
                                    <span>Qtd. Manipulada</span>
                                    <strong id="metricQtdManipulada">0,000</strong>
                                </div>

                                <div class="metric-line">
                                    <span>Total Itens Saída</span>
                                    <strong id="metricTotalSaida">0,000</strong>
                                </div>

                                <div class="metric-line">
                                    <span>Perda</span>
                                    <strong class="perda-valor perda-ok" id="valorPerda">0,000</strong>
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

                <!-- MENSAGEM QUANDO NÃO TEM FICHA -->
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

        const baseUrl = MRK.apiUrl;
        const token = MRK.token;
        const system_unit_id = "<?= $_SESSION['system_unit_id'] ?? $_SESSION['MRK_USER_DETAILS']['system_unit_id'] ?>";
        const user_id        = MRK.userId;

        // Estado da Tela
        let fichaItens = [];
        let insumosDisponiveis = [];
        let unidadeInsumo = '';

        const UNIDADES_DECIMAL = ['KG', 'LT', 'L', 'M', 'M2', 'M3'];

        /* ===========================
           HELPERS E MÁSCARAS
        =========================== */
        function isDecimalUnit(unit) {
            return UNIDADES_DECIMAL.includes(String(unit || '').toUpperCase().trim());
        }

        function getDecimals(unit) {
            return isDecimalUnit(unit) ? 3 : 0;
        }

        function toFloat(value) {
            if (value === null || value === undefined || value === '') return 0;
            let txt = String(value).replace(/\./g, '').replace(',', '.');
            let n = parseFloat(txt);
            return isNaN(n) ? 0 : n;
        }

        function formatBR(value, unit) {
            let decimals = getDecimals(unit);
            let num = parseFloat(value || 0);
            return num.toLocaleString('pt-BR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        // ====== MÁSCARA INTELIGENTE (5 DÍGITOS LIMITE) ======
        function applyQuantityMask(val, unit) {
            var unidade = String(unit || '').toUpperCase().trim();
            var v = val.replace(/\D/g, '');

            if (UNIDADES_DECIMAL.includes(unidade)) {
                if (v === '') return '';
                if (v.length > 8) v = v.substring(0, 8); // 5 antes + 3 depois
                var valorNum = (parseInt(v) / 1000).toFixed(3);
                return valorNum.replace('.', ',');
            } else {
                if (v.length > 5) v = v.substring(0, 5); // 5 total inteiro
                return v;
            }
        }

        function showLoading(title = 'Carregando...') {
            Swal.fire({
                title: title,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });
        }

        /* ===========================
           INICIALIZAÇÃO E DADOS
        =========================== */
        $(document).ready(function() {
            // Inicializa Select2
            $('#selectInsumo').select2({
                placeholder: "Toque para buscar...",
                allowClear: true,
                width: '100%',
                dropdownCssClass: "text-sm"
            });

            // Set Date
            const hoje = new Date().toISOString().split('T')[0];
            $('#dataManipulacao').val(hoje);

            carregarInsumos();

            // Eventos
            $('#selectInsumo').on('change', function() {
                const val = $(this).val();
                $('#quantidadeManipulada').val('');

                if(val) {
                    $('#quantidadeManipulada').prop('disabled', false);
                    carregarFichaDoInsumo(val);
                } else {
                    $('#quantidadeManipulada').prop('disabled', true);
                    $('#secaoFicha').addClass('hidden');
                    $('#msgSemFicha').addClass('hidden');
                    $('#lblUndBase').text('');
                }
            });

            $('#quantidadeManipulada').on('input', function() {
                let masked = applyQuantityMask($(this).val(), unidadeInsumo);
                $(this).val(masked);
                atualizarResumo();
            });

            $('#gridItensFicha').on('input', '.input-qtd-item', function() {
                let index = Number($(this).data('index'));
                let und = $(this).data('und') || '';
                let masked = applyQuantityMask($(this).val(), und);
                $(this).val(masked);

                let valor = toFloat(masked);
                if (fichaItens[index]) {
                    fichaItens[index].quantidade = valor;
                }
                atualizarResumo();
            });

            $('#btnExecutar').on('click', executarManipulacao);
        });

        async function carregarInsumos() {
            showLoading('Buscando Matérias-Primas...');
            try {
                const res = await axios.post(baseUrl, {
                    method: 'listInsumosComFichaStatus',
                    token: token,
                    data: { unit_id: system_unit_id }
                });

                if (!res.data.success) throw new Error(res.data.message || 'Falha ao carregar');

                insumosDisponiveis = (res.data.produtos || []).filter(p => Number(p.tem_ficha) === 1);

                const $select = $('#selectInsumo');
                $select.empty().append(new Option('', '', true, true));

                insumosDisponiveis.forEach(item => {
                    let label = `${item.codigo} - ${item.nome}`;
                    let opt = new Option(label, item.codigo);
                    $(opt).data('und', item.und || '');
                    $select.append(opt);
                });

                Swal.close();
            } catch (err) {
                Swal.fire('Erro', 'Erro ao carregar matérias-primas.', 'error');
            }
        }

        async function carregarFichaDoInsumo(codigoInsumo) {
            fichaItens = [];
            $('#secaoFicha').addClass('hidden');
            $('#msgSemFicha').addClass('hidden');

            if (!codigoInsumo) return;

            let insumoObj = insumosDisponiveis.find(i => String(i.codigo) === String(codigoInsumo));
            unidadeInsumo = insumoObj ? (insumoObj.und || '') : '';
            $('#lblUndBase').text(`(${unidadeInsumo})`);

            showLoading('Carregando ficha...');

            try {
                const res = await axios.post(baseUrl, {
                    method: 'listManipulacoes',
                    token: token,
                    data: { unit_id: system_unit_id }
                });

                if (!res.data.success) throw new Error('Erro ao carregar ficha');

                let itensEncontrados = [];
                (res.data.producoes || []).forEach(prodFinal => {
                    (prodFinal.insumos || []).forEach(insumo => {
                        if (Number(insumo.insumo_id) === Number(codigoInsumo)) {
                            itensEncontrados.push({
                                product_id: prodFinal.produto,
                                nome: prodFinal.nome,
                                unidade: prodFinal.unidade || '',
                                quantidade: parseFloat(insumo.quantity || 0)
                            });
                        }
                    });
                });

                Swal.close();

                if (itensEncontrados.length === 0) {
                    $('#msgSemFicha').removeClass('hidden');
                    return;
                }

                fichaItens = itensEncontrados;
                $('#secaoFicha').removeClass('hidden');
                renderGrid();

            } catch (err) {
                Swal.fire('Erro', err.message || 'Erro ao carregar ficha.', 'error');
            }
        }

        /* ===========================
           RENDER E RESUMO
        =========================== */
        function renderGrid() {
            const container = $('#gridItensFicha').empty();

            fichaItens.forEach((item, index) => {
                let und = item.unidade || '';
                let valorFormatado = formatBR(item.quantidade, und);

                const card = `
                <div class="card p-3 flex flex-col gap-2">
                    <div class="flex justify-between items-start">
                        <div class="pr-2">
                            <div class="text-[10px] text-gray-400 font-bold mb-0.5">CÓD: ${item.product_id}</div>
                            <div class="text-xs font-semibold text-gray-800 leading-tight">${item.nome}</div>
                        </div>
                        <div class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-[10px] font-bold shrink-0">
                            ${und || 'UN'}
                        </div>
                    </div>

                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-xs text-gray-500 font-semibold w-full">Qtd. Consumida:</span>
                        <input type="text"
                               class="input-mobile input-qtd-item text-center font-bold !py-1.5"
                               data-index="${index}"
                               data-und="${und}"
                               value="${valorFormatado}"
                               inputmode="decimal">
                    </div>
                </div>
            `;
                container.append(card);
            });

            atualizarResumo();
        }

        function atualizarResumo() {
            let qtdManipulada = toFloat($('#quantidadeManipulada').val());
            let totalSaida = 0;

            fichaItens.forEach(item => {
                totalSaida += (parseFloat(item.quantidade) || 0);
            });

            totalSaida = Math.round(totalSaida * 10000) / 10000;
            let perda = Math.round((qtdManipulada - totalSaida) * 10000) / 10000;

            $('#metricTotalSaida').text(`${formatBR(totalSaida, unidadeInsumo)} ${unidadeInsumo}`);

            const $perda = $('#valorPerda');
            $perda.text(`${formatBR(perda, unidadeInsumo)} ${unidadeInsumo}`);

            $perda.removeClass('perda-ok perda-alerta');
            if (perda < 0) {
                $perda.addClass('perda-alerta');
            } else {
                $perda.addClass('perda-ok');
            }
        }

        /* ===========================
           EXECUÇÃO
        =========================== */
        async function executarManipulacao() {
            let insumo_id = Number($('#selectInsumo').val());
            let quantidade_manipulada = toFloat($('#quantidadeManipulada').val());
            let data = $('#dataManipulacao').val();

            let itens_saida = fichaItens
                .filter(item => (parseFloat(item.quantidade) || 0) > 0)
                .map(item => ({
                    product_id: Number(item.product_id),
                    quantidade: parseFloat(item.quantidade) || 0
                }));

            let totalSaida = 0;
            itens_saida.forEach(item => totalSaida += item.quantidade);
            let perda = Math.round((quantidade_manipulada - totalSaida) * 10000) / 10000;

            // Validações
            if (!insumo_id) return Swal.fire('Atenção', 'Selecione uma matéria-prima.', 'warning');
            if (!quantidade_manipulada || quantidade_manipulada <= 0) return Swal.fire('Atenção', 'Informe a quantidade manipulada.', 'warning');
            if (itens_saida.length === 0) return Swal.fire('Atenção', 'Nenhum item de saída válido.', 'warning');
            if (perda < 0) return Swal.fire('Atenção', `A soma da saída (${formatBR(totalSaida, unidadeInsumo)}) não pode ser maior que a base manipulada (${formatBR(quantidade_manipulada, unidadeInsumo)}).`, 'warning');

            let nomeInsumo = $('#selectInsumo option:selected').text();

            let listaItensHtml = itens_saida.map(item => {
                let f = fichaItens.find(x => Number(x.product_id) === Number(item.product_id));
                return `<div class="flex justify-between text-xs border-b border-gray-100 py-1">
                        <span class="truncate pr-2">${f ? f.nome : item.product_id}</span>
                        <strong class="shrink-0">${formatBR(item.quantidade, f?f.unidade:'')}</strong>
                    </div>`;
            }).join('');

            const htmlResumo = `
            <div class="text-left">
                <div class="mb-3 bg-gray-50 p-2 rounded text-xs">
                    <p class="mb-1"><span class="text-gray-500">Base:</span> <strong class="text-[var(--mrk-blue)]">${nomeInsumo}</strong></p>
                    <p><span class="text-gray-500">Qtd:</span> <strong class="text-[var(--mrk-blue)]">${formatBR(quantidade_manipulada, unidadeInsumo)} ${unidadeInsumo}</strong></p>
                </div>
                <div class="max-h-32 overflow-y-auto mb-3 bg-gray-50 p-2 rounded">
                    <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Itens de Saída</p>
                    ${listaItensHtml}
                </div>
                <div class="flex justify-between items-center bg-amber-50 text-amber-800 p-2 rounded text-sm font-bold">
                    <span>Perda Calculada:</span>
                    <span>${formatBR(perda, unidadeInsumo)} ${unidadeInsumo}</span>
                </div>
            </div>
        `;

            const confirmacao = await Swal.fire({
                title: '<span class="text-lg">Confirmar Execução?</span>',
                html: htmlResumo,
                showCancelButton: true,
                confirmButtonText: 'Sim, Executar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#f59e0b',
                reverseButtons: true
            });

            if (!confirmacao.isConfirmed) return;

            showLoading('Executando manipulação...');

            try {
                const payload = {
                    system_unit_id: system_unit_id,
                    insumo_id: insumo_id,
                    quantidade_manipulada: quantidade_manipulada,
                    itens_saida: itens_saida,
                    usuario_id: user_id,
                    data: data || undefined
                };

                const res = await axios.post(baseUrl, {
                    method: 'executeManipulacao',
                    token: token,
                    data: payload
                });

                if (!res.data.success) throw new Error(res.data.message || 'Falha ao executar');

                await Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    html: `<div class="text-sm">
                        <p>Documento: <b>${res.data.doc || '-'}</b></p>
                        ${res.data.doc_perda ? `<p>Doc. Perda: <b>${res.data.doc_perda}</b></p>` : ''}
                        <p class="mt-2 text-green-600 font-bold">Perda: ${formatBR(res.data.perda || 0, unidadeInsumo)} ${unidadeInsumo}</p>
                       </div>`
                });

                // Reset
                $('#selectInsumo').val(null).trigger('change');
                $('#quantidadeManipulada').val('');

            } catch (err) {
                Swal.fire('Erro', err.message || 'Erro ao executar manipulação.', 'error');
            }
        }
    </script>
    </body>
    </html>