$(document).ready(function() {
    const baseUrl = window.location.hostname !== 'localhost' ?
        'https://portal.mrksolucoes.com.br/api/v1/index.php' :
        'http://localhost/portal-mrk/api/v1/index.php';

    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const unitId = urlParams.get('unit_id');
    const username = urlParams.get('username');

    let productsInBalance = []; // Array para armazenar produtos adicionados ao balanço

    // Função para abrir modal e listar categorias
    async function loadCategories() {
        try {
            const response = await axios.post(baseUrl, {
                method: 'listProductsByCategory', // Endpoint atualizado
                token: token,
                data: { unit_id: unitId }
            });

            // Verifique o que está sendo retornado
            console.log('Response from API:', response.data);

            // Verifique se a propriedade 'products_by_category' existe e é um array
            if (response.data && response.data.success) {
                const productsByCategory = response.data.products_by_category;

                if (Array.isArray(productsByCategory)) {
                    // Itera sobre cada categoria no array
                    productsByCategory.forEach(category => {
                        // Adiciona o nome da categoria na tabela
                        $('#categoriasTable tbody').append(`<tr><td colspan="4"><strong>${category.categoria}</strong></td></tr>`);

                        // Itera sobre os itens da categoria e adiciona à tabela
                        category.itens.forEach(item => {
                            $('#categoriasTable tbody').append(`
                                <tr>
                                    <td>${item.codigo}</td>
                                    <td>${item.nome}</td>
                                    <td>${item.und}</td>
                                    <td><button class="btn btn-primary add-product" data-codigo="${item.codigo}" data-nome="${item.nome}" data-und="${item.und}">Adicionar</button></td>
                                </tr>
                            `);
                        });
                    });
                }
            }
        } catch (error) {
            console.error('Erro ao carregar categorias:', error);
        }
    }

    // Abrir o modal ao clicar em "Adicionar Categorias"
    $('#btnAddCategory').click(function() {
        // Limpar a tabela de categorias antes de carregar
        $('#categoriasTable tbody').empty();
        loadCategories(); // Carregar as categorias
        $('#modalCategoria').modal('show'); // Mostrar o modal
    });

    // Função para adicionar produtos ao balanço
    $(document).on('click', '.add-product', function() {
        const codigo = $(this).data('codigo');
        const nome = $(this).data('nome');
        const und = $(this).data('und');

        // Adiciona o produto ao array
        productsInBalance.push({ codigo, nome, und });

        // Adiciona o produto à tabela do balanço
        $('#balancoTable tbody').append(`
            <tr>
                <td>${codigo}</td>
                <td>${nome}</td>
                <td>${und}</td>
                <td><button class="btn btn-danger remove-product">Remover</button></td>
            </tr>
        `);

        // Fecha o modal após adicionar
        $('#modalCategoria').modal('hide');
    });

    // Função para remover produtos do balanço
    $(document).on('click', '.remove-product', function() {
        const row = $(this).closest('tr');
        const codigo = row.find('td:first').text(); // Obtém o código do produto

        // Remove o produto do array
        productsInBalance = productsInBalance.filter(product => product.codigo !== codigo);

        // Remove a linha da tabela
        row.remove();
    });

    // Função para finalizar o lançamento
    $('#finalizarLancamento').click(async function() {
        const modelName = $('#modelName').val();
        const modelTag = $('#modelTag').val();
        const currentDate = $('#currentDate').val();

        if (modelName && modelTag && currentDate && productsInBalance.length > 0) {
            try {
                const response = await axios.post(baseUrl, {
                    method: 'createModelo', // Endpoint para finalizar balanço
                    token: token,
                    data: {
                        system_unit_id: unitId,
                        nome: modelName,
                        tag: modelTag,
                        data: currentDate,
                        usuario_id: username,
                        itens: productsInBalance
                    }
                });

                console.log('Finalização de Balanço:', response.data);

                if (response.data.success) {
                    alert('Lançamento finalizado com sucesso!');
                    // Limpar tabela e campos
                    $('#balancoTable tbody').empty();
                    $('#modelName').val('');
                    $('#modelTag').val('');
                    $('#currentDate').val('');
                    productsInBalance = []; // Limpa o array
                } else {
                    alert('Erro ao finalizar o lançamento: ' + response.data.message);
                }
            } catch (error) {
                console.error('Erro ao finalizar lançamento:', error);
            }
        } else {
            alert('Por favor, preencha todos os campos e adicione produtos ao balanço antes de finalizar.');
        }
    });

    // Chamar função para carregar categorias
    loadCategories();
});
