<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Ordem</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 p-6">

<div class="container mx-auto">
    <form id="order-form" class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-4">Cadastrar Ordem</h2>

        <!-- Client and Event -->
        <div class="mb-4">
            <label for="cliente_id" class="block text-gray-700">Cliente</label>
            <select id="cliente_id" name="cliente_id" class="select2 block w-full mt-1" required></select>
        </div>
        <div class="mb-4">
            <label for="evento_id" class="block text-gray-700">Evento</label>
            <select id="evento_id" name="evento_id" class="select2 block w-full mt-1" required></select>
        </div>

        <!-- New Fields -->
        <div class="mb-4">
            <label for="data_montagem" class="block text-gray-700">Data de Montagem</label>
            <input type="date" id="data_montagem" name="data_montagem" class="block w-full mt-1" required>
        </div>
        <div class="mb-4">
            <label for="data_recolhimento" class="block text-gray-700">Data de Recolhimento</label>
            <input type="date" id="data_recolhimento" name="data_recolhimento" class="block w-full mt-1" required>
        </div>
        <div class="mb-4">
            <label for="contato_montagem" class="block text-gray-700">Contato de Montagem</label>
            <input type="text" id="contato_montagem" name="contato_montagem" class="block w-full mt-1" required>
        </div>
        <div class="mb-4">
            <label for="local_montagem" class="block text-gray-700">Local de Montagem</label>
            <input type="text" id="local_montagem" name="local_montagem" class="block w-full mt-1" required>
        </div>
        <div class="mb-4">
            <label for="endereco" class="block text-gray-700">Endereço</label>
            <input type="text" id="endereco" name="endereco" class="block w-full mt-1">
        </div>

        <!-- Order Items -->
        <h3 class="text-xl font-semibold mb-2">Itens da Ordem</h3>
        <div id="order-items-container">
            <!-- Items will be added here dynamically -->
        </div>
        <button type="button" id="add-item" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Adicionar Item</button>

        <!-- Order Services -->
        <h3 class="text-xl font-semibold mt-6 mb-2">Serviços da Ordem</h3>
        <div id="order-services-container">
            <!-- Services will be added here dynamically -->
        </div>
        <button type="button" id="add-service" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Adicionar Serviço</button>

        <!-- Order Payments -->
        <h3 class="text-xl font-semibold mt-6 mb-2">Pagamentos da Ordem</h3>
        <div id="order-payments-container">
            <!-- Payments will be added here dynamically -->
        </div>
        <button type="button" id="add-payment" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Adicionar Pagamento</button>

        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 mt-4">Salvar Ordem</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
    const requestToken = new URLSearchParams(window.location.search).get('token');
    const baseUrl = window.location.hostname !== 'localhost' ? 'https://binetecnologia.com.br/crm' : 'http://localhost/bione';

    // Function to fetch data from API
    async function fetchData(method) {
        try {
            const response = await axios.post(`${baseUrl}/api/v1/index.php`, {
                method: method,
                token: requestToken,
                data: {}
            });
            return response.data;
        } catch (error) {
            console.error('Error fetching data:', error);
            return { success: false, message: 'Erro ao carregar dados.' };
        }
    }

    // Initialize Select2 and populate fields
    async function initializeSelects() {
        const clientsResponse = await fetchData('listClients');
        const eventsResponse = await fetchData('listEvents');
        const materialsResponse = await fetchData('listMaterials');
        const servicesResponse = await fetchData('listServices');
        const paymentMethodsResponse = await fetchData('listPaymentMethods');

        if (clientsResponse.success) {
            $('#cliente_id').select2({
                data: clientsResponse.clients.map(client => ({
                    id: client.id,
                    text: client.nome
                })),
                placeholder: 'Selecione um cliente'
            });
        }

        if (eventsResponse.success) {
            $('#evento_id').select2({
                data: eventsResponse.events.map(event => ({
                    id: event.id,
                    text: event.nome
                })),
                placeholder: 'Selecione um evento'
            });
        }

        if (materialsResponse.success) {
            window.materials = materialsResponse.materials;
        }

        if (servicesResponse.success) {
            window.services = servicesResponse.services;
        }

        if (paymentMethodsResponse.success) {
            window.paymentMethods = paymentMethodsResponse.payment_methods;
        }
    }

    // Function to create a new item row
    function createItemRow() {
        return `
            <div class="flex items-center mb-2">
                <select name="material_id[]" class="select2 block w-1/4 mr-2" required>
                    ${window.materials.map(material => `<option value="${material.id}">${material.nome}</option>`).join('')}
                </select>
                <input type="number" name="quantidade[]" placeholder="Quantidade" class="block w-1/4 mr-2" required />
                <input type="text" name="descricao[]" placeholder="Descrição" class="block w-1/4 mr-2" required />
                <button type="button" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 remove-item">Remover</button>
            </div>
        `;
    }

    // Function to create a new service row
    function createServiceRow() {
        return `
            <div class="flex items-center mb-2">
                <select name="servico_id[]" class="select2 block w-1/4 mr-2" required>
                    ${window.services.map(service => `<option value="${service.id}">${service.descricao}</option>`).join('')}
                </select>
                <input type="number" name="quantidade[]" placeholder="Quantidade" class="block w-1/4 mr-2" required />
                <input type="text" name="descricao[]" placeholder="Descrição" class="block w-1/4 mr-2" required />
                <button type="button" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 remove-service">Remover</button>
            </div>
        `;
    }

    // Function to create a new payment row
    function createPaymentRow() {
        return `
            <div class="flex items-center mb-2">
                <select name="forma_pg[]" class="select2 block w-1/4 mr-2" required>
                    ${window.paymentMethods.map(paymentMethod => `<option value="${paymentMethod.id}">${paymentMethod.nome}</option>`).join('')}
                </select>
                <input type="number" name="valor[]" placeholder="Valor" class="block w-1/4 mr-2" required />
                <input type="text" name="descricao[]" placeholder="Descrição" class="block w-1/4 mr-2" required />
                <button type="button" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 remove-payment">Remover</button>
            </div>
        `;
    }

    // Event listener for adding new item
    document.getElementById('add-item').addEventListener('click', () => {
        document.getElementById('order-items-container').insertAdjacentHTML('beforeend', createItemRow());
        $('.select2').select2(); // Reinitialize Select2 for newly added rows
    });

    // Event listener for adding new service
    document.getElementById('add-service').addEventListener('click', () => {
        document.getElementById('order-services-container').insertAdjacentHTML('beforeend', createServiceRow());
        $('.select2').select2(); // Reinitialize Select2 for newly added rows
    });

    // Event listener for adding new payment
    document.getElementById('add-payment').addEventListener('click', () => {
        document.getElementById('order-payments-container').insertAdjacentHTML('beforeend', createPaymentRow());
        $('.select2').select2(); // Reinitialize Select2 for newly added rows
    });

    // Event delegation for removing items, services, and payments
    document.getElementById('order-items-container').addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-item')) {
            e.target.closest('div').remove();
        }
    });

    document.getElementById('order-services-container').addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-service')) {
            e.target.closest('div').remove();
        }
    });

    document.getElementById('order-payments-container').addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-payment')) {
            e.target.closest('div').remove();
        }
    });

    // Form submission handler
    document.getElementById('order-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);

        const orderData = {
            cliente_id: formData.get('cliente_id'),
            evento_id: formData.get('evento_id'),
            data_montagem: formData.get('data_montagem'),
            data_recolhimento: formData.get('data_recolhimento'),
            contato_montagem: formData.get('contato_montagem'),
            local_montagem: formData.get('local_montagem'),
            endereco: formData.get('endereco') || null
        };

        try {
            // Cadastrar a ordem
            const orderResponse = await axios.post(`${baseUrl}/api/v1/index.php`, {
                method: 'createOrder',
                token: requestToken,
                data: orderData
            });

            if (orderResponse.data.success) {
                const orderId = orderResponse.data.order_id; // Supondo que a API retorne o ID da ordem criada
                const orderItems = [];
                const orderServices = [];
                const orderPayments = [];

                // Preparar dados dos itens da ordem
                const itemElements = document.querySelectorAll('#order-items-container .flex');
                itemElements.forEach(item => {
                    const materialId = item.querySelector('[name="material_id[]"]').value;
                    const quantidade = item.querySelector('[name="quantidade[]"]').value;
                    const descricao = item.querySelector('[name="descricao[]"]').value;
                    orderItems.push({ material_id: materialId, quantidade, descricao });
                });

                // Preparar dados dos serviços da ordem
                const serviceElements = document.querySelectorAll('#order-services-container .flex');
                serviceElements.forEach(service => {
                    const servicoId = service.querySelector('[name="servico_id[]"]').value;
                    const quantidade = service.querySelector('[name="quantidade[]"]').value;
                    const descricao = service.querySelector('[name="descricao[]"]').value;
                    orderServices.push({ servico_id: servicoId, quantidade, descricao });
                });

                // Preparar dados dos pagamentos da ordem
                const paymentElements = document.querySelectorAll('#order-payments-container .flex');
                paymentElements.forEach(payment => {
                    const formaPg = payment.querySelector('[name="forma_pg[]"]').value;
                    const valor = payment.querySelector('[name="valor[]"]').value;
                    const descricao = payment.querySelector('[name="descricao[]"]').value;
                    orderPayments.push({ forma_pg: formaPg, valor, descricao });
                });

                // Cadastrar itens da ordem
                await axios.post(`${baseUrl}/api/v1/index.php`, {
                    method: 'createOrderItems',
                    token: requestToken,
                    data: {
                        order_id: orderId,
                        items: orderItems
                    }
                });

                // Cadastrar serviços da ordem
                await axios.post(`${baseUrl}/api/v1/index.php`, {
                    method: 'createOrderServices',
                    token: requestToken,
                    data: {
                        order_id: orderId,
                        services: orderServices
                    }
                });

                // Cadastrar pagamentos da ordem
                await axios.post(`${baseUrl}/api/v1/index.php`, {
                    method: 'createOrderPayments',
                    token: requestToken,
                    data: {
                        order_id: orderId,
                        payments: orderPayments
                    }
                });

                alert('Ordem cadastrada com sucesso!');
                e.target.reset();
                $('.select2').val(null).trigger('change');
            } else {
                alert(orderResponse.data.message || 'Erro ao criar a ordem.');
            }
        } catch (error) {
            alert('Erro ao enviar os dados.');
            console.error(error);
        }
    });

    // Inicializa os selects e carrega dados
    $(document).ready(() => {
        initializeSelects();
        $('.select2').select2(); // Inicializa o Select2
    });
</script>
</body>
</html>
