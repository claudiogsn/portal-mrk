<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Evento</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <!-- Adiciona jQuery antes do Select2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        .loading {
            position: relative;
        }
        .loading::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7) url('https://i.gifer.com/origin/4d/4dc11d17f5292fd463a60aa2bbb41f6a.gif') no-repeat center center;
            background-size: 50px 50px;
            display: none;
        }
        .loading.active::after {
            display: block;
        }
        input[type="radio"] {
            width: 200px;
            height: 10px;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-lg">
    <h2 class="text-2xl font-bold mb-4">Cadastro de Evento</h2>
        <form id="eventoForm" class="space-y-4">
            <fieldset class="space-y-2">
                <label for="cliente_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                <select id="cliente_id" name="cliente_id" class="cliente_id mt-1 block w-full p-2 border border-gray-300 rounded-md"></select>
            </fieldset>

            <fieldset class="space-y-2">
                <div class="space-y-2">
                    <label for="nome" class="block text-sm font-medium text-gray-700">Nome do Evento</label>
                    <input type="text" id="nome" name="nome" class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required>
                </div>
                <div class="space-y-2">
                    <label for="capacidade" class="block text-sm font-medium text-gray-700">Capacidade</label>
                    <input type="number" id="capacidade" name="capacidade" class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label for="data_inicio" class="block text-sm font-medium text-gray-700">Data de Início</label>
                        <input type="datetime-local" id="data_inicio" name="data_inicio" class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required>
                    </div>
                    <div class="space-y-2">
                        <label for="data_fim" class="block text-sm font-medium text-gray-700">Data de Término</label>
                        <input type="datetime-local" id="data_fim" name="data_fim" class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required>
                    </div>
                </div>
                <div class="space-y-2">
                    <label for="local" class="block text-sm font-medium text-gray-700">Local do Evento</label>
                    <input type="text" id="local" name="local" class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required>
                </div>
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="block text-sm font-medium text-gray-700">Endereço</legend>
                <div class="space-y-2">
                    <label for="cep" class="block text-sm font-medium text-gray-700">CEP</label>
                    <input type="text" id="cep" name="cep" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
                </div>
                <div id="addressInfo" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="col-span-3 space-y-2">
                        <label for="endereco" class="block text-sm font-medium text-gray-700">Endereço</label>
                        <input type="text" id="endereco" name="endereco" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="space-y-2">
                        <label for="numero" class="block text-sm font-medium text-gray-700">Número</label>
                        <input type="text" id="numero" name="numero" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="col-span-2 space-y-2">
                        <label for="bairro" class="block text-sm font-medium text-gray-700">Bairro</label>
                        <input type="text" id="bairro" name="bairro" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="space-y-2">
                        <label for="cidade" class="block text-sm font-medium text-gray-700">Cidade</label>
                        <input type="text" id="cidade" name="cidade" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="space-y-2">
                        <label for="estado" class="block text-sm font-medium text-gray-700">Estado</label>
                        <input type="text" id="estado" name="estado" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
                    </div>
                </div>
            </fieldset>

            <div class="pt-4">
                <button type="submit" class="w-full py-2 px-4 bg-blue-900 text-white font-medium rounded-md hover:bg-blue-600">Cadastrar</button>
            </div>
        </form>
    </div>
    <script>
        const baseUrl = window.location.hostname !== 'localhost' ? 'https://binetecnologia.com.br/gestao' : 'http://localhost/bione';
        const urlParams = new URLSearchParams(window.location.search);
        const requestToken = urlParams.get('token');

        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('eventoForm');
    
            const cepInput = document.getElementById('cep');
            const enderecoInput = document.getElementById('endereco');
            const numeroInput = document.getElementById('numero');
            const bairroInput = document.getElementById('bairro');
            const cidadeInput = document.getElementById('cidade');
            const estadoInput = document.getElementById('estado');
            const addressInfo = document.getElementById('addressInfo');

            function applyCepMask(value) {
        value = value.replace(/\D/g, '');
        value = value.replace(/^(\d{5})(\d)/, "$1-$2");
        return value;
    }

    function cleanCep(value) {
        return value.replace(/\D/g, '');
    }

    function fetchCepData(cep) {
        return axios.get(`https://viacep.com.br/ws/${cep}/json`).then(response => response.data);
    }

    function toggleLoading(element, isLoading) {
        if (isLoading) {
            element.classList.add('loading', 'active');
        } else {
            element.classList.remove('loading', 'active');
        }
    }

    function handleCepInput() {
        cepInput.value = applyCepMask(cepInput.value);
    }

    async function validateAndFillCep() {
        const cepValue = cleanCep(cepInput.value);
        if (cepValue.length === 8) {
            try {
                toggleLoading(addressInfo, true);
                const responseData = await fetchCepData(cepValue);
                toggleLoading(addressInfo, false);

                if (!responseData.erro) {
                    enderecoInput.value = responseData.logradouro;
                    bairroInput.value = responseData.bairro;
                    cidadeInput.value = responseData.localidade;
                    estadoInput.value = responseData.uf;
                } else {
                    alert('CEP não encontrado.');
                }
            } catch (error) {
                toggleLoading(addressInfo, false);
                alert('Erro ao buscar o CEP.');
            }
        }
    }

    cepInput.addEventListener('input', handleCepInput);
    cepInput.addEventListener('blur', validateAndFillCep);

    
    // SELECT2
    $('#cliente_id').select2({
        placeholder: 'Selecione um cliente',
        allowClear: true,
        ajax: {
            url: `${baseUrl}/api/v1/index.php`,
            dataType: 'json',
            type: 'POST', 
            contentType: 'application/json', 
            delay: 250,
            data: function (params) {
                return JSON.stringify({ 
                    token: requestToken,
                    method: 'listClients',
                    data: {} 
                });
            },
            processResults: function (data) {
                return {
                    results: data.map(cliente => ({
                        id: cliente.id,
                        text: `${cliente.nome} - ${cliente.cpf_cnpj}`
                    }))
                };
            },
            cache: true
        }
    });

    

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const clienteId = formData.get('cliente_id');
        const nome = formData.get('nome');
        const capacidade = formData.get('capacidade');
        const dataInicio = formData.get('data_inicio');
        const dataFim = formData.get('data_fim');
        const local = formData.get('local');
        const cep = formData.get('cep');
        const endereco = formData.get('endereco');
        const numero = formData.get('numero');
        const bairro = formData.get('bairro');
        const cidade = formData.get('cidade');
        const estado = formData.get('estado');
        

        try {
           
            const data = {
                token: requestToken,
                method: 'createEvent',
                data: {
                    cliente_id: clienteId,
                    nome: nome,
                    capacidade: capacidade,
                    data_inicio: dataInicio,
                    data_fim: dataFim,
                    local: local,
                    cep: cep.replace(/\D/g, ''),
                    endereco: endereco,
                    numero: numero,
                    bairro: bairro,
                    cidade: cidade,
                    estado: estado
                }
            };

            const response = await axios.post(`${baseUrl}/api/v1/index.php`, data);
            const result = response.data;

            if (result.success) {
                alert('Evento criado com sucesso!');
                form.reset();
            } else {
                alert(result.message || 'Erro ao criar o evento.');
            }
        } catch (error) {
            alert('Erro ao enviar os dados.');
            console.error(error);
        }
    });
});

</script>

</body>
</html>