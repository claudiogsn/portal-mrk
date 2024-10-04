$(document).ready(function () {
    const baseUrl = window.location.hostname !== 'localhost' ?
        'https://portal.mrksolucoes.com.br/api/v1/index.php' :
        'http://localhost/portal-mrk/api/v1/index.php';

    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const unitId = urlParams.get('unit_id');
    const username = urlParams.get('username');

    let categories = []; // Array para armazenar as categorias disponíveis
    let selectedCategories = {}; // Objeto para armazenar as categorias e seus produtos selecionados
    let productsInBalance = []; // Array para armazenar produtos adicionados ao balanço

    // Função para carregar as categorias e produtos
    async function loadCategories() {''
        try {
            const response = await axios.post(baseUrl, {
                method: 'listProductsByCategory',
                token: token,
                data: { unit_id: unitId }
            });

            if (response.data && response.data.success) {
                categories = response.data.products_by_category;
                renderCategoriesList();
            }
        } catch (error) {
            console.error('Erro ao carregar categorias:', error);
        }
    }

    // Função para renderizar a lista de categorias disponíveis (esquerda)
    function renderCategoriesList() {
        const categoriesList = $('#categoriesList');
        categoriesList.empty();
        categories.forEach(category => {
            categoriesList.append(`
                <li class="list-group-item category-item" data-category='${JSON.stringify(category)}'>
                    <span>${category.categoria}</span>
                </li>
            `);
        });
    }

    // Função para renderizar a lista de categorias selecionadas (direita)
    function renderSelectedCategories() {
        const selectedCategoriesList = $('#selectedCategoriesList');
        selectedCategoriesList.empty();

        Object.keys(selectedCategories).forEach(categoryName => {
            const category = selectedCategories[categoryName];
            selectedCategoriesList.append(`
                <li class="list-group-item selected-category-item" data-category="${categoryName}">
                    <strong>${categoryName}</strong>
                    <div class="sub-items">
                        <ul>
                            ${category.itens.map(item => `
                                <li>
                                    <input type="checkbox" class="select-item" data-category="${categoryName}" data-codigo="${item.codigo}" ${item.selected ? 'checked' : ''}>
                                    ${item.nome} (${item.und})
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                </li>
            `);
        });
    }

    // Evento para adicionar/remover categoria à lista selecionada ao clicar
    $(document).on('click', '.category-item', function () {
        const category = JSON.parse($(this).attr('data-category'));

        if (selectedCategories[category.categoria]) {
            delete selectedCategories[category.categoria];
        } else {
            selectedCategories[category.categoria] = {
                itens: category.itens.map(item => ({
                    ...item,
                    selected: true // Define os itens como selecionados ao adicionar a categoria inteira
                }))
            };
        }
        renderSelectedCategories();
    });

    // Evento para remover categoria da lista selecionada ao clicar
    $(document).on('click', '.selected-category-item', function () {
        const categoryName = $(this).attr('data-category');
        delete selectedCategories[categoryName];
        renderSelectedCategories();
    });

    // Evento para selecionar/desmarcar um item específico
    $(document).on('change', '.select-item', function () {
        const categoryName = $(this).attr('data-category');
        const codigo = $(this).attr('data-codigo');
        const isChecked = $(this).is(':checked');
        const item = selectedCategories[categoryName].itens.find(i => i.codigo == codigo);
        if (item) {
            item.selected = isChecked;
        }
    });

    // Abrir modal e carregar categorias
    $('#btnAddCategory').click(function () {
        loadCategories();
        $('#modalCategoriaProduto').modal('show');
    });

    // Confirmar adição de categorias e produtos
    $('#btnConfirmarAdicionar').click(function () {
        const itemsToAdd = [];
        Object.keys(selectedCategories).forEach(categoryName => {
            selectedCategories[categoryName].itens
                .filter(item => item.selected)
                .forEach(item => itemsToAdd.push(item));
        });

        // Adiciona os itens selecionados ao array e tabela do balanço
        itemsToAdd.forEach(item => {
            if (!productsInBalance.some(p => p.codigo === item.codigo)) {
                productsInBalance.push(item);

                $('#balancoTable tbody').append(`
                    <tr>
                        <td>${item.codigo}</td>
                        <td>${item.nome}</td>
                        <td>${item.und}</td>
                        <td><button class="btn btn-danger remove-product" data-codigo="${item.codigo}">Remover</button></td>
                    </tr>
                `);
            }
        });

        $('#modalCategoriaProduto').modal('hide');
    });

    // Função para remover produtos do balanço
    $(document).on('click', '.remove-product', function () {
        const codigo = $(this).data('codigo');

        // Remove o produto do array
        productsInBalance = productsInBalance.filter(product => product.codigo !== codigo);

        // Remove a linha da tabela
        $(this).closest('tr').remove();
    });

    // Função para validar a tag
    async function validateTag(tag) {
        try {
            const response = await axios.post(baseUrl, {
                method: 'validateTagExists',
                token: token,
                data: { tag: tag }
            });

            return response.data.success;
        } catch (error) {
            console.error('Erro ao validar a tag:', error);
            return false;
        }
    }

    // Função para gerar a tag a partir do nome do modelo e atualizar a URL
    window.generateTag = async function () {
        const modelName = $('#modelName').val().trim();
        const modelTag = `${unitId}-${modelName.toLowerCase().replace(/\s+/g, '-')}`;
        const modelUrl = `https://portal.mrksolucoes.com.br/balanco?tag=${modelTag}`;

        $('#modelTag').val(modelTag);
        $('#modelUrl').val(modelUrl);

        const isValid = await validateTag(modelTag);
        if (!isValid) {
            $('#tagValidationMessage').show();
            $('#finalizarLancamento').prop('disabled', true);
        } else {
            $('#tagValidationMessage').hide();
            $('#finalizarLancamento').prop('disabled', false);
        }
    };

    // Copiar URL para a área de transferência
    $('#btnCopyUrl').click(function () {
        const url = $('#modelUrl').val();
        navigator.clipboard.writeText(url).then(() => {
            alert('URL copiada para a área de transferência');
        }).catch(err => {
            console.error('Erro ao copiar URL:', err);
        });
    });

    // Compartilhar URL via WhatsApp
    $('#btnShareWhatsapp').click(function () {
        const url = $('#modelUrl').val();
        const whatsappUrl = `https://wa.me/?text=${encodeURIComponent('Confira o modelo de balanço: ' + url)}`;
        window.open(whatsappUrl, '_blank');
    });

    // Função para finalizar o lançamento
    $('#finalizarLancamento').click(async function () {
        const modelName = $('#modelName').val();
        const modelTag = $('#modelTag').val();

        if (modelName && modelTag && productsInBalance.length > 0) {
            // Exibir spinner de carregamento
            $('#pageLoader').show();

            try {
                const response = await axios.post(baseUrl, {
                    method: 'createModelo',
                    token: token,
                    data: {
                        system_unit_id: unitId,
                        nome: modelName,
                        tag: modelTag,
                        usuario_id: username,
                        itens: productsInBalance
                    }
                });

                if (response.data.success) {
                    // Limpar tabela e campos
                    $('#balancoTable tbody').empty();
                    $('#modelName').val('');
                    $('#modelTag').val('');
                    $('#modelUrl').val('');
                    productsInBalance = [];
                    selectedCategories = {};
                    renderSelectedCategories(); // Limpa a lista de categorias selecionadas
                    $('#finalizarLancamento').prop('disabled', true);

                    // Mostrar modal de sucesso do AdminBSB
                    showDialog('Lançamento finalizado com sucesso!', 'success');
                } else {
                    showDialog('Erro ao finalizar o lançamento: ' + response.data.message, 'danger');
                }
            } catch (error) {
                console.error('Erro ao finalizar lançamento:', error);
                showDialog('Erro ao finalizar lançamento. Tente novamente.', 'danger');
            } finally {
                // Ocultar spinner de carregamento
                $('#pageLoader').hide();
            }
        } else {
            showDialog('Por favor, preencha todos os campos e adicione produtos ao balanço antes de finalizar.', 'warning');
        }
    });

    // Função para mostrar o diálogo de mensagem usando AdminBSB
    // Função para mostrar o diálogo de mensagem usando SweetAlert
    function showDialog(message, type) {
        Swal.fire({
            title: type === 'success' ? "Sucesso" : "Erro",
            text: message,
            icon: type,
            confirmButtonText: "OK",
        });
    }


    // Função para limpar todos os campos e variáveis ao clicar em "Limpar"
    $('#limparBalanco').click(function () {
        $('#balancoTable tbody').empty();
        $('#modelName').val('');
        $('#modelTag').val('');
        $('#modelUrl').val('');
        productsInBalance = [];
        selectedCategories = {};
        renderSelectedCategories();
        $('#finalizarLancamento').prop('disabled', true);
        console.log('Balanço limpo!');
    });

    loadCategories();
});
