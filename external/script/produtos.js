const baseUrl = window.location.hostname !== 'localhost' ? 'https://portal.mrksolucoes.com.br/api/v1/index.php' : 'http://localhost/portal-mrk/api/v1/index.php';
const token = new URLSearchParams(window.location.search).get('token');
const unitId = new URLSearchParams(window.location.search).get('unit_id');

$(document).ready(function() {
    const productsTable = $('#productsTable').DataTable({
        "pageLength": -1,
        "lengthChange": false,
        "language": {
            "search": "Pesquisar:"
        },
        buttons: [
            {
                extend: 'excelHtml5',
                text: 'Exportar para Excel',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4]
                }
            },
            {
                extend: 'pdfHtml5',
                text: 'Exportar para PDF',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4],
                    modifier: {
                        page: 'all'
                    }
                }
            },
            'print'
        ],
        dom: 'fBrtip'
    });


    loadProducts();

    async function loadProducts() {
        const loadingSpinner = document.getElementById('loadingSpinner');
        loadingSpinner.classList.remove('hidden');

        try {
            const response = await axios.post(baseUrl, {
                method: 'listProducts',
                token: token,
                data: {
                    unit_id: unitId
                }
            });

            let products = response.data.products;


            productsTable.clear();


            products.forEach(product => {
                const categoriaNome = product.nome_categoria || 'Desconhecida';
                const tipo = [];
                if (product.venda) tipo.push('Venda');
                if (product.composicao) tipo.push('Composição');
                if (product.insumo) tipo.push('Insumo');

                const precoFormatado = product.preco != null && !isNaN(product.preco)
                    ? `R$ ${product.preco.toFixed(2)}`
                    : 'R$ 0,00';

                productsTable.row.add([
                    product.codigo,
                    product.nome,
                    tipo.join(' | '),
                    categoriaNome,
                    precoFormatado,
                    `<button class="text-blue-500" onclick="editProduct(${product.id})">✏️</button>`
                ]).draw();
            });


            loadCategories(products);

        } catch (error) {
            console.error("Erro ao carregar produtos:", error);
            productsTable.clear().row.add(['Erro ao carregar produtos.', '', '', '', '', '']).draw();
        } finally {
            loadingSpinner.classList.add('hidden');
        }
    }

    async function loadCategories(products) {
        const categorias = [...new Set(products.map(product => product.nome_categoria))];

        const filterCategoria = $('#filterCategoria');
        filterCategoria.empty();
        filterCategoria.append('<option value="">Todos</option>');

        categorias.forEach(categoria => {
            filterCategoria.append(`<option value="${categoria}">${categoria}</option>`);
        });
    }


    function applyFilters() {
        const tipoFilter = $('#filterTipo').val();
        const categoriaFilter = $('#filterCategoria').val();

        productsTable.column(2).search(tipoFilter ? '^' + tipoFilter + '$' : '', true, false);
        productsTable.column(3).search(categoriaFilter ? '^' + categoriaFilter + '$' : '', true, false);

        productsTable.draw();
    }


    $('#filterTipo, #filterCategoria').change(applyFilters);


    window.editProduct = function(productId) {
        console.log('Editando produto com ID:', productId);
    };
});
