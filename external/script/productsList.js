$(document).ready(function () {
    const baseUrl = window.location.hostname !== 'localhost' ?
        'https://portal.mrksolucoes.com.br/api/v1/index.php' :
        'http://localhost/portal-mrk/api/v1/index.php';

    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const unitId = urlParams.get('unit_id');

    // Função para carregar categorias e preencher o select
    async function loadCategories(selectedCategory = null) {
        try {
            const response = await axios.post(baseUrl, {
                method: 'listCategorias',
                token: token,
                data: { unit_id: unitId }
            });

            if (response.data && response.data.success) {
                const categorias = response.data.categorias;
                const categoriaSelect = $('#filterCategory, #categoria');
                categoriaSelect.empty();
                categoriaSelect.append('<option value="">Todas as Categorias</option>');
                categorias.forEach(categoria => {
                    categoriaSelect.append(`<option value="${categoria.id}">${categoria.nome}</option>`);
                });

                if (selectedCategory) {
                    categoriaSelect.val(selectedCategory);
                }
            } else {
                Swal.fire("Erro", "Falha ao carregar categorias: " + response.data.message, "error");
            }
        } catch (error) {
            console.error('Erro ao carregar categorias:', error);
            Swal.fire("Erro", "Erro ao carregar categorias. Tente novamente.", "error");
        }
    }

    // Função para carregar os produtos e preencher a página
    async function loadProducts() {
        try {
            const response = await axios.post(baseUrl, {
                method: 'getProductCards',
                token: token,
                data: { system_unit_id: unitId }
            });

            if (response.data && response.data.success) {
                renderProductCards(response.data.product_cards);
            } else {
                Swal.fire("Erro", "Falha ao carregar produtos: " + response.data.message, "error");
            }
        } catch (error) {
            console.error('Erro ao carregar produtos:', error);
            Swal.fire("Erro", "Erro ao carregar produtos. Tente novamente.", "error");
        }
    }

    // Função para renderizar os cartões dos produtos
    function renderProductCards(products) {
        const container = $('#productsContainer');
        container.empty();

        const selectedCategory = $('#filterCategory').val();
        const selectedType = $('#filterType').val();
        const searchName = $('#filterName').val().toLowerCase();

        let filteredProducts = products.filter(product => {

            let categoryMatch = !selectedCategory || parseInt(product.categoria_id) === parseInt(selectedCategory);
            let typeMatch = !selectedType ||
                (selectedType === 'Venda' && product.venda === 1) ||
                (selectedType === 'Insumo' && product.insumo === 1) ||
                (selectedType === 'Composição' && product.composicao === 1);
            let nameMatch = !searchName || product.nome.toLowerCase().includes(searchName);

            return categoryMatch && typeMatch && nameMatch;
        });

        filteredProducts.forEach(product => {
            const movimentacoes = product.atividade_recente.map(mov => `
                <div class="activity">
                    <i class="material-icons">${mov.tipo_mov === 'entrada' ? 'arrow_forward' : 'arrow_back'}</i>
                    ${new Date(mov.data).toLocaleDateString('pt-BR')} - Doc: ${mov.doc} - Quantidade: ${mov.quantidade}
                </div>
            `).join('');

            const fichaTecnica = product.ficha_tecnica.map(insumo => `
                <tr>
                    <td>${insumo.insumo_nome}</td>
                    <td>${insumo.quantity}</td>
                </tr>
            `).join('');

            const tags = `
                ${product.venda ? '<span class="label bg-blue">Venda</span>' : ''}
                ${product.insumo ? '<span class="label bg-green">Insumo</span>' : ''}
                ${product.composicao ? '<span class="label bg-red">Composição</span>' : ''}
            `;

            const cardHtml = `
                <div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
                    <div class="card">
                        <div class="header">
                            <h2>
                                ${product.nome}
                                <small><strong>Categoria:</strong> ${product.categ}</small>
                                <br>
                                ${tags}
                            </h2>
                            <ul class="header-dropdown m-r--5">
                                <li class="dropdown">
                                    <a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                                        <i class="material-icons">more_vert</i>
                                    </a>
                                    <ul class="dropdown-menu pull-right">
                                        <li><a href="javascript:void(0);" class="btn-editar" data-id="${product.codigo}">Editar</a></li>
                                        <li><a href="javascript:void(0);" class="btn-excluir" data-id="${product.codigo}">Excluir</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                        <div class="body">
                            <strong> Saldo: </strong> ${product.quantidade}<br>
                            <strong> Custo unitário: </strong> ${product.custo_unitario}<br>
                            <strong> Valor em estoque: </strong> ${product.valor_estoque}<br>
                            <br>
                            <div class="panel-group" id="accordion_${product.codigo}" role="tablist" aria-multiselectable="true">
                                <div class="panel panel-primary">
                                    <div class="panel-heading" role="tab" id="heading_activity_${product.codigo}">
                                        <h4 class="panel-title">
                                            <a role="button" data-toggle="collapse" data-parent="#accordion_${product.codigo}" href="#collapse_activity_${product.codigo}" aria-expanded="true" aria-controls="collapse_activity_${product.codigo}">
                                                Atividade Recente
                                            </a>
                                        </h4>
                                    </div>
                                    <div id="collapse_activity_${product.codigo}" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading_activity_${product.codigo}">
                                        <div class="panel-body">
                                            ${movimentacoes || '<p>Sem movimentações recentes.</p>'}
                                        </div>
                                    </div>
                                </div>
                                <div class="panel panel-primary">
                                    <div class="panel-heading" role="tab" id="heading_ficha_${product.codigo}">
                                        <h4 class="panel-title">
                                            <a role="button" data-toggle="collapse" data-parent="#accordion_${product.codigo}" href="#collapse_ficha_${product.codigo}" aria-expanded="true" aria-controls="collapse_ficha_${product.codigo}">
                                                Ficha Técnica
                                            </a>
                                        </h4>
                                    </div>
                                    <div id="collapse_ficha_${product.codigo}" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading_ficha_${product.codigo}">
                                        <div class="panel-body">
                                            <table class="table table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Insumo</th>
                                                        <th>Quantidade</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${fichaTecnica || '<tr><td colspan="2">Sem dados de ficha técnica.</td></tr>'}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.append(cardHtml);
        });
    }

    // Função para abrir o modal de produto
    function openProductModal(product = null) {
        if (product) {
            $('#modalTitle').text('Editar Produto');
            $('#nome').val(product.nome);
            $('#saldo').val(product.saldo);
            $('#und').val(product.und);
            $('#preco_custo').val(product.preco_custo);
            loadCategories(product.categoria);
            $('#saveProduct').data('id', product.codigo);
            $('#tipo_venda').prop('checked', product.venda === 1);
            $('#tipo_insumo').prop('checked', product.insumo === 1);
            $('#tipo_composicao').prop('checked', product.composicao === 1);
        } else {
            $('#modalTitle').text('Criar Produto');
            $('#productForm')[0].reset();
            $('#saveProduct').removeData('id');
            loadCategories();
        }

        $('#productModal').modal('show');
    }

    // Evento para salvar o produto
    $('#saveProduct').click(async function () {
        const produtoId = $(this).data('id');

        const data = {
            codigo: produtoId || $('#codigo').val(),
            nome: $('#nome').val(),
            categoria: $('#categoria').val(),
            preco: parseFloat($('#preco_custo').val()) || 0,
            und: $('#und').val(),
            venda: $('#tipo_venda').is(':checked') ? 1 : 0,
            composicao: $('#tipo_composicao').is(':checked') ? 1 : 0,
            insumo: $('#tipo_insumo').is(':checked') ? 1 : 0,
            system_unit_id: unitId,
            preco_custo: parseFloat($('#preco_custo').val()) || 0,
            saldo: parseFloat($('#saldo').val()) || 0
        };

        try {
            let response;
            if (produtoId) {
                response = await axios.post(baseUrl, {
                    method: 'updateProduct',
                    token: token,
                    data: data
                });
            } else {
                response = await axios.post(baseUrl, {
                    method: 'createProduct',
                    token: token,
                    data: data
                });
            }

            if (response.data.success) {
                Swal.fire({
                    title: 'Sucesso!',
                    text: response.data.message,
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    $('#productModal').modal('hide');
                    loadProducts();
                });
            } else {
                Swal.fire({
                    title: 'Erro!',
                    text: response.data.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        } catch (error) {
            console.error('Erro:', error);
            Swal.fire({
                title: 'Erro!',
                text: 'Ocorreu um erro ao salvar o produto. Tente novamente.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });

    $('#btnNovoProduto').click(function () {

        openProductModal();
    });

    $(document).on('click', '.btn-editar', function () {
        const productId = $(this).data('id');
        axios.post(baseUrl, {
            method: 'getProductById',
            token: token,
            data: {
                codigo: productId,
                system_unit_id: unitId
            }
        })
        .then(response => {
            if (response.data) {
                console.log('Produto:', response.data);
                openProductModal(response.data);
            }
        })
        .catch(error => {
            console.error('Erro ao buscar produto:', error);
            Swal.fire({
                title: 'Erro!',
                text: 'Ocorreu um erro ao buscar o produto. Tente novamente.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    });

    // Evento para aplicar filtros
    $('#filterCategory, #filterType, #filterName').on('change keyup', function () {
        loadProducts();
    });

    loadCategories();
    loadProducts();
});
