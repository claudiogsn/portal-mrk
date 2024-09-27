$(document).ready(function() {
    const baseUrl = window.location.hostname !== 'localhost' ?
        'https://portal.mrksolucoes.com.br/api/v1/index.php' :
        'http://localhost/portal-mrk/api/v1/index.php';

    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const unitId = urlParams.get('unit_id');
    const username = urlParams.get('username');

    let currentDocNumber = ''; // Para armazenar o número do documento gerado
    let productsInBalance = []; // Array para armazenar produtos adicionados ao balanço

    // Função para carregar o último número de lançamento
    async function loadLastMovement() {
        try {
            const response = await axios.post(baseUrl, {
                method: 'getLastMov',
                token: token,
                data: {
                    system_unit_id: unitId,
                    tipo: 'b'
                }
            });
            currentDocNumber = generateNextDoc(response.data);
            $('#documentNumber').val(currentDocNumber); // Corrigido para campo de entrada
            $('#currentDate').val(new Date().toISOString().split('T')[0]); // Definindo a data de hoje
        } catch (error) {
            console.error('Erro ao carregar último lançamento:', error);
        }
    }

    // Função para gerar o próximo número do documento
    function generateNextDoc(lastDoc) {
        let lastNumber = parseInt(lastDoc.split('-')[1], 10);
        lastNumber++; // Incrementa o número
        return `b-${String(lastNumber).padStart(5, '0')}`; // Retorna o novo número formatado
    }

    // Função para abrir modal e listar insumos
    async function loadInsumos() {
        try {
            const response = await axios.post(baseUrl, {
                method: 'listInsumos',
                token: token,
                data: { unit_id: unitId }
            });
            populateInsumosModal(response.data.insumos);
        } catch (error) {
            console.error('Erro ao listar insumos:', error);
        }
    }

    // Função para popular o modal com a lista de insumos
    function populateInsumosModal(insumos) {
        const insumosTableBody = $('#produtosTable tbody');
        insumosTableBody.empty(); // Limpa a tabela antes de adicionar novos dados
        insumos.forEach(insumo => {
            insumosTableBody.append(`
                <tr>
                    <td>${insumo.codigo}</td>
                    <td>${insumo.nome}</td>
                    <td>${insumo.und}</td>
                    <td><button class="btn btn-primary" onclick="addProductToBalance('${insumo.codigo}', '${insumo.nome}', '${insumo.und}')">Adicionar</button></td>
                </tr>
            `);
        });
        $('#modalProduto').modal('show'); // Exibe o modal após popular a tabela
    }

    // Função para adicionar produto ao balanço
    window.addProductToBalance = function(codigo, nome, unidade) {
        productsInBalance.push({ codigo, nome, unidade, quantidade: 0 }); // Adiciona produto ao array
        updateBalanceTable();
        $('#modalProduto').modal('hide'); // Fecha o modal
    }

    // Função para atualizar a tabela do balanço
    function updateBalanceTable() {
        const balanceTableBody = $('#balancoTable tbody'); // ID corrigido
        balanceTableBody.empty(); // Limpa a tabela antes de adicionar novos dados
        productsInBalance.forEach((product, index) => {
            balanceTableBody.append(`
                <tr>
                    <td>${product.codigo}</td>
                    <td>${product.nome}</td>
                    <td>${product.unidade}</td>
                    <td><input type="number" value="${product.quantidade}" onchange="updateProductQuantity(${index}, this.value)" /></td>
                    <td><button onclick="removeProductFromBalance(${index})">Excluir</button></td>
                </tr>
            `);
        });
    }

    // Função para atualizar a quantidade de um produto
    window.updateProductQuantity = function(index, quantity) {
        productsInBalance[index].quantidade = parseFloat(quantity); // Atualiza a quantidade
    }

    // Função para remover produto do balanço
    window.removeProductFromBalance = function(index) {
        productsInBalance.splice(index, 1); // Remove o produto do array
        updateBalanceTable(); // Atualiza a tabela
    }

    // Função para finalizar o lançamento
    $('#finalizarLançamento').click(async function() {
        const movements = productsInBalance.map((product, index) => ({
            system_unit_id: unitId,
            system_unit_id_destino: unitId,
            doc: currentDocNumber,
            tipo: 'entrada', // Ajustar se necessário
            produto: product.nome,
            seq: index + 1,
            data: $('#currentDate').val(),
            valor: 0, // Ajustar se necessário
            quantidade: product.quantidade,
            usuario_id: username
        }));

        try {
            const response = await axios.post(baseUrl, {
                method: 'createMovimentacao',
                token: token,
                data: movements
            });
            alert('Lançamento finalizado com sucesso!');
            // Limpar tabela e resetar estado
            productsInBalance = [];
            updateBalanceTable();
        } catch (error) {
            console.error('Erro ao finalizar lançamento:', error);
            alert('Erro ao finalizar lançamento. Tente novamente.');
        }
    });

    // Evento para inicializar o balanço
    $('#btnNovoLancamento').click(function() {
        console.log("Botão 'Novo Lançamento' foi clicado!");
        loadLastMovement(); // Carrega o último movimento
        $('#balanceSection').show(); // Mostra a seção do balanço
    });

    // Carregar a lista de insumos ao abrir o modal
    $('#btnAddProduct').click(loadInsumos);

    // Inicializa a tela ao carregar
    loadLastMovement();
});
