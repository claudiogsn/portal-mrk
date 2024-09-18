<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Cliente</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

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
        <h2 class="text-2xl font-bold mb-4">Cadastro de Cliente</h2>
        <div class="flex justify-start items-center space-x-4">
            <div class="flex items-center border border-gray-200 rounded dark:border-gray-700">
                <input id="cpfToggle" type="radio" value="cpf" name="docType" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                <label for="cpfToggle" class="w-full py-4 ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">CPF</label>
            </div>
            <div class="flex items-center border border-gray-200 rounded dark:border-gray-700">
                <input id="cnpjToggle" type="radio" value="cnpj" name="docType" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                <label for="cnpjToggle" class="w-full py-4 ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">CNPJ</label>
            </div>
        </div>
        
        
        <form id="clienteForm" class="space-y-6 mt-6">
            <fieldset class="space-y-2">
                <label for="cpf" class="block text-sm font-medium text-gray-700">CPF/CNPJ</label>
                <input type="text" id="cpf" name="cpf_cnpj" class="mt-1 block w-full p-2 border border-gray-300 rounded-md">
            </fieldset>
            <fieldset id="personalInfo" class="space-y-4">
                <div class="space-y-2">
                    <label for="nome" class="block text-sm font-medium text-gray-700">Nome</label>
                    <input type="text" id="nome" name="nome" class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label for="telefone" class="block text-sm font-medium text-gray-700">Telefone</label>
                        <input type="text" id="telefone" name="telefone" class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required>
                    </div>
                    <div class="space-y-2">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="email" name="email" class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required>
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-2">
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
        const cpfToggle = document.getElementById('cpfToggle');
        const cnpjToggle = document.getElementById('cnpjToggle');
        const cpfCnpjInput = document.getElementById('cpf');

        function toggleInput() {
            if (cpfToggle.checked) {
                cpfCnpjInput.id = 'cpf';
                console.log('CPF selecionado');
            } else {
                cpfCnpjInput.id = 'cnpj';
                console.log('CNPJ selecionado');
            }
        }

        cpfToggle.addEventListener('change', toggleInput);
        cnpjToggle.addEventListener('change', toggleInput);

    </script>
</body>
<script>
    const baseUrl = window.location.hostname !== 'localhost' ? 'https://binetecnologia.com.br/crm' : 'http://localhost/bione';
    const urlParams = new URLSearchParams(window.location.search);
    const requestToken = urlParams.get('token');

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('clienteForm');
    const cpfToggle = document.getElementById('cpfToggle');
    const cnpjToggle = document.getElementById('cnpjToggle');
    const cpfCnpjInput = document.getElementById('cpf');

    const nomeInput = document.getElementById('nome');
    const emailInput = document.getElementById('email');
    const telefoneInput = document.getElementById('telefone');

    const cepInput = document.getElementById('cep');
    const enderecoInput = document.getElementById('endereco');
    const numeroInput = document.getElementById('numero');
    const bairroInput = document.getElementById('bairro');
    const cidadeInput = document.getElementById('cidade');
    const estadoInput = document.getElementById('estado');

    const personalInfo = document.getElementById('personalInfo');
    const addressInfo = document.getElementById('addressInfo');

    function applyCpfCnpjMask(value) {
        value = value.replace(/\D/g, '');
        if (cpfToggle.checked) {
            // Apply CPF mask
            value = value.replace(/(\d{3})(\d)/, "$1.$2");
            value = value.replace(/(\d{3})(\d)/, "$1.$2");
            value = value.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
        } else {
            // Apply CNPJ mask
            value = value.replace(/^(\d{2})(\d)/, "$1.$2");
            value = value.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
            value = value.replace(/\.(\d{3})(\d)/, ".$1/$2");
            value = value.replace(/(\d{4})(\d{1,2})$/, "$1-$2");
        }
        return value;
    }

    function applyCepMask(value) {
        value = value.replace(/\D/g, '');
        value = value.replace(/^(\d{5})(\d)/, "$1-$2");
        return value;
    }

    function applyTelefoneMask(value) {
        value = value.replace(/\D/g, '');
        if (value.length > 10) {
            value = value.replace(/^(\d{2})(\d{5})(\d{4})$/, "($1) $2-$3");
        } else {
            value = value.replace(/^(\d{2})(\d{4})(\d{4})$/, "($1) $2-$3");
        }
        return value;
    }

    function cleanCpfCnpj(value) {
        return value.replace(/\D/g, '');
    }

    function cleanCep(value) {
        return value.replace(/\D/g, '');
    }

    function cleanTelefone(value) {
        return value.replace(/\D/g, '');
    }

    function fetchCpfData(cpf) {
        return axios.post(`${baseUrl}/api/v1/index.php`, {
            token: requestToken,
            method: 'validateCPF',
            data: { cpf }
        }).then(response => response.data);
    }

    function fetchCnpjData(cnpj) {
        return axios.post(`${baseUrl}/api/v1/index.php`, {
            token: requestToken,
            method: 'validateCNPJ',
            data: { cnpj }
        }).then(response => response.data);
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

    function handleCpfCnpjInput() {
        cpfCnpjInput.value = applyCpfCnpjMask(cpfCnpjInput.value);
    }

    function handleCepInput() {
        cepInput.value = applyCepMask(cepInput.value);
    }

    function handleTelefoneInput() {
        telefoneInput.value = applyTelefoneMask(telefoneInput.value);
    }

    function toggleInput() {
        cpfCnpjInput.value = '';
        if (cpfToggle.checked) {
            cpfCnpjInput.id = 'cpf';
            cpfCnpjInput.maxLength = 14;  // CPF: 11 digits + 3 mask characters
            console.log('CPF selecionado');
        } else {
            cpfCnpjInput.id = 'cnpj';
            cpfCnpjInput.maxLength = 18;  // CNPJ: 14 digits + 4 mask characters
            console.log('CNPJ selecionado');
        }
    }

    async function validateAndFillData() {
        const docValue = cleanCpfCnpj(cpfCnpjInput.value);

        if (!docValue) return;  // Não validar se o campo estiver vazio

        try {
            toggleLoading(personalInfo, true);
            let responseData;
            if (cpfToggle.checked) {
                responseData = await fetchCpfData(docValue);
            } else {
                responseData = await fetchCnpjData(docValue);
            }
            toggleLoading(personalInfo, false);

            if (responseData.success) {
                const { nome, email, telefone } = responseData.data;
                if (nome) nomeInput.value = nome;
                if (email) emailInput.value = email;
                if (telefone) telefoneInput.value = telefone;
            } else {
                alert(responseData.message || 'Erro ao validar o documento.');
            }
        } catch (error) {
            toggleLoading(personalInfo, false);
            alert('Erro ao validar os dados.');
        }
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

    cpfToggle.addEventListener('change', toggleInput);
    cnpjToggle.addEventListener('change', toggleInput);
    cpfCnpjInput.addEventListener('input', handleCpfCnpjInput);
    cpfCnpjInput.addEventListener('blur', validateAndFillData);
    cepInput.addEventListener('input', handleCepInput);
    cepInput.addEventListener('blur', validateAndFillCep);
    telefoneInput.addEventListener('input', handleTelefoneInput);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const docValue = cleanCpfCnpj(formData.get('cpf_cnpj'));
        const cepValue = cleanCep(formData.get('cep'));
        const telefoneValue = cleanTelefone(formData.get('telefone'));

        try {
            let responseData = { success: true };  // Default success response

            if (docValue) {
                toggleLoading(form, true);
                if (cpfToggle.checked) {
                    responseData = await fetchCpfData(docValue);
                } else {
                    responseData = await fetchCnpjData(docValue);
                }
                toggleLoading(form, false);

                if (!responseData.success) {
                    alert(responseData.error || 'Erro ao validar o documento.');
                    return;
                }
            }
           

            const data = {
                token: requestToken,
                method: 'createCliente',
                data: {
                    nome: formData.get('nome'),
                    telefone: telefoneValue,
                    email: formData.get('email'),
                    cpf_cnpj: docValue,
                    status: 'ativo',
                    endereco: `${formData.get('endereco')}, ${formData.get('numero')}`,
                    bairro: formData.get('bairro'),
                    cidade: formData.get('cidade'),
                    estado: formData.get('estado'),
                    cep: cepValue
                }
            };

            toggleLoading(form, true);
            const response = await axios.post(`${baseUrl}/api/v1/index.php`, data);
            const result = response.data;
            toggleLoading(form, false);

            if (result.success) {
                alert('Cadastro realizado com sucesso!');
                form.reset();
            } else {
                alert(result.error || 'Erro ao realizar o cadastro.');
            }
        } catch (error) {
            toggleLoading(form, false);
            alert('Erro ao enviar os dados.');
        }
    });
});

</script>
</html>
