const baseUrl = window.location.hostname !== 'localhost' ? 'https://binetecnologia.com.br/gestao' : 'http://localhost/bione';

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
            method: 'validateCPF',
            data: { cpf }
        }).then(response => response.data);
    }

    function fetchCnpjData(cnpj) {
        return axios.post(`${baseUrl}/api/v1/index.php`, {
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
