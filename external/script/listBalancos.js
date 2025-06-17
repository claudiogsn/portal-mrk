$(document).ready(function () {
    const baseUrl = window.location.hostname !== 'localhost' ?
        'https://portal.mrksolucoes.com.br/api/v1/index.php' :
        'http://localhost/portal-mrk/api/v1/index.php';

    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const unitId = urlParams.get('unit_id');

    let selectedDocs = [];
    let currentItems = [];

    function showLoader() {
        $('.page-loader-wrapper').fadeIn();
    }

    function hideLoader() {
        $('.page-loader-wrapper').fadeOut();
    }

    // Carregar balanços
    async function loadBalancos(dataInicial = null, dataFinal = null, doc = null) {
        if (dataInicial) {
            dataInicial += " 00:00:00";
        }

        if (dataFinal) {
            dataFinal += " 23:59:59";
        }

        showLoader();
        try {
            const response = await axios.post(baseUrl, {
                method: 'listBalance',
                token: token,
                data: {
                    system_unit_id: unitId,
                    data_inicial: dataInicial,
                    data_final: dataFinal,
                    doc: doc
                }
            });

            if (response.data && response.data.success) {
                renderBalancos(response.data.balances);
            } else {
                swal("Erro", "Erro ao carregar balanços", "error");
            }
        } catch (error) {
            console.error('Erro ao carregar balanços:', error);
        } finally {
            hideLoader();
        }
    }

    function renderBalancos(balances) {
        const tbody = $('#balancosTable tbody');
        tbody.empty();
        selectedDocs = [];  // Reinicializar a lista de documentos selecionados

        balances.forEach(balanco => {
            const row = $(`
                <tr>
                    <td><input type="checkbox" id="balanco-${balanco.doc}" class="chk-col-blue balanco-checkbox" data-doc="${balanco.doc}"><label for="balanco-${balanco.doc}"> </label></td>
                    <td>${balanco.doc}</td>
                    <td>${new Date(balanco.created_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}</td>
                    <td><button class="btn btn-info btnDetalhes" data-doc="${balanco.doc}">Ver Detalhes</button></td>
                </tr>
            `);

            tbody.append(row);

            // Evento para selecionar/desmarcar a linha e aplicar o estilo
            row.find('.balanco-checkbox').on('change', function () {
                const doc = $(this).data('doc');
                if ($(this).prop('checked')) {
                    row.addClass('selected-row');
                    if (!selectedDocs.includes(doc)) {
                        selectedDocs.push(doc);
                    }
                } else {
                    row.removeClass('selected-row');
                    selectedDocs = selectedDocs.filter(item => item !== doc);
                }
            });
        });

        // Adicionar evento para selecionar todos
        $('#selectAll').off('click').on('click', function () {
            const isChecked = $(this).prop('checked');
            $('.balanco-checkbox').prop('checked', isChecked).trigger('change');
        });

        // Adicionar evento para ver detalhes
        $('.btnDetalhes').off('click').on('click', function () {
            const doc = $(this).data('doc');
            loadDetalhesBalanco(doc);
        });
    }

    // Carregar detalhes do balanço
    async function loadDetalhesBalanco(doc) {
        showLoader();
        try {
            const response = await axios.post(baseUrl, {
                method: 'getBalanceByDoc',
                token: token,
                data: {
                    system_unit_id: unitId,
                    doc: doc
                }
            });

            if (response.data && response.data.success) {
                currentItems = response.data.balance.itens;
                renderDetalhesModal(response.data.balance);
                $('#balancoModal').modal('show');
            } else {
                swal("Erro", "Erro ao carregar detalhes do balanço", "error");
            }
        } catch (error) {
            console.error('Erro ao carregar detalhes:', error);
        } finally {
            hideLoader();
        }
    }

    function renderDetalhesModal(balanco) {
        const tbody = $('#detalhesBalanco');
        tbody.empty();

        $('#balancoModal .modal-title').text(`Detalhes do Balanço - Doc: ${balanco.doc}`);

        balanco.itens.forEach(item => {
            tbody.append(`
                <tr>
                    <td>${item.produto}</td>
                    <td>${item.quantidade}</td>
                    <td>${item.categoria}</td>
                </tr>
            `);
        });

        // Armazena o documento do balanço no modal para exportação
        $('#balancoModal').data('doc', balanco.doc);
    }


$// Exportar balanços selecionados para Excel
$('#btnExportarSelecionados').click(async function () {
    if (selectedDocs.length === 0) {
        swal("Atenção", "Nenhum balanço foi selecionado.", "warning");
        return;
    }

    swal({
        title: "Exportar",
        text: `Deseja exportar os ${selectedDocs.length} balanços selecionados para Excel?`,
        icon: "info",
        buttons: true,
        dangerMode: false,
    }, async function (willExport) {  // Correção aqui
        if (willExport) {
            let allItems = [];

            // Para cada documento selecionado, faça a requisição e agrupe os itens
            for (const doc of selectedDocs) {
                try {
                    const response = await axios.post(baseUrl, {
                        method: 'getBalanceByDoc',
                        token: token,
                        data: {
                            system_unit_id: unitId,
                            doc: doc
                        }
                    });

                    if (response.data && response.data.success) {
                        const balance = response.data.balance;
                        const items = balance.itens.map(item => ({
                            'Documento': doc,
                            'Codigo': item.codigo,
                            'Produto': item.produto,
                            'Quantidade': item.quantidade,
                            'Categoria': item.categoria
                        }));
                        allItems = allItems.concat(items);  // Agrupar os itens
                    }
                } catch (error) {
                    console.error(`Erro ao obter itens do doc ${doc}:`, error);
                }
            }

            if (allItems.length > 0) {
                exportToExcel(allItems, 'balancos_agrupados');
                swal("Sucesso", "Balanços exportados com sucesso!", "success");
            } else {
                swal("Erro", "Nenhum item para exportar.", "error");
            }
        }
    });
});



    $('#btnExportarModal').click(function () {
        if (currentItems.length > 0) {
            exportToExcel(currentItems, $('#balancoModal').data('doc'));
            swal("Sucesso", "Balanço exportado com sucesso!", "success");
        } else {
            swal("Erro", "Nenhum item para exportar.", "error");
        }
    });

    function exportToExcel(items, fileName) {
        const ws = XLSX.utils.json_to_sheet(items);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, fileName);
        XLSX.writeFile(wb, `${fileName}.xlsx`);
    }

    $('#btnImprimir').click(function () {
        const docNumber = $('#balancoModal').data('doc');
        const printContents = document.getElementById('detalhesBalanco').outerHTML;
        const originalContents = document.body.innerHTML;

        document.body.innerHTML = `
            <html>
            <head>
                <title>Impressão do Balanço</title>
                <link href="bsb/plugins/bootstrap/css/bootstrap.css" rel="stylesheet">
            </head>
            <body>
                <h2>Detalhes do Balanço - Doc: ${docNumber}</h2>
                <table class="table table-striped">${printContents}</table>
            </body>
            </html>
        `;

        window.print();
        document.body.innerHTML = originalContents;
        location.reload();
    });

    $('#btnBuscar').click(function () {
        const dataInicial = $('#dataInicial').val();
        const dataFinal = $('#dataFinal').val();
        const doc = $('#searchDoc').val();

        loadBalancos(dataInicial, dataFinal, doc);
    });

    loadBalancos();
});
