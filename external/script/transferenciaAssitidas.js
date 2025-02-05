document.addEventListener('DOMContentLoaded', function () {
    const baseUrl = window.location.hostname !== 'localhost' ?
        'https://portal.mrksolucoes.com.br/api/v1/index.php' :
        'http://localhost/portal-mrk/api/v1/index.php';

    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const username = urlParams.get('username');

    // Inicializar o Form Wizard
    const form = $("#wizard-form");

    form.validate({
        errorPlacement: function (error, element) {
            element.before(error); // Posiciona o erro antes do elemento
        },
        rules: {
            "unit-select": "required",
            "start-date": "required",
            "end-date": "required",
            "product-select": {
                required: function () {
                    return $("#product-select option:selected").length > 0; // Pelo menos um produto deve ser selecionado
                }
            }
        }
    });

    $("#wizard").steps({
        headerTag: "h2",
        bodyTag: "section",
        transitionEffect: "slideLeft",
        labels: {
            finish: "Finalizar",
            next: "Próximo",
            previous: "Anterior"
        },
        onStepChanging: function (event, currentIndex, newIndex) {
            form.validate().settings.ignore = ":disabled,:hidden";

            // Verifica se está tentando voltar (newIndex < currentIndex)
            if (newIndex < currentIndex) {
                window.location.reload();  // Força o reload da página
                return false; // Impede que o step volte normalmente
            }

            return form.valid(); // Continua para o próximo step normalmente
        },
        onStepChanged: function (event, currentIndex, priorIndex) {
            console.log("Step changed to", currentIndex);
            if (currentIndex === 3) {
                console.log("Chamando função calcularNecessidades");
                calcularNecessidades();
            } else if (currentIndex === 2) {
                const selectedUnitId = document.getElementById('unit-select').value;
                if (selectedUnitId) {
                    console.log("Carregando produtos e modelos para a unidade:", selectedUnitId);
                    loadProdutos(selectedUnitId);
                    loadModelos(selectedUnitId);  // Carregar modelos de balanço
                } else {
                    alert("Por favor, selecione uma unidade de destino.");
                }
            }
        },
        onFinished: function (event, currentIndex) {
            // Finalização do wizard
        }
    });

    // Exibir Modal de Carregamento
    function showLoadingModal() {
        $('#defaultModal').modal('show');
    }

    // Ocultar Modal de Carregamento
    function hideLoadingModal() {
        $('#defaultModal').modal('hide');
    }

    // Carregar Unidades Vinculadas
    async function loadUnidades() {
        const response = await fetch(baseUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                method: 'getFiliaisByMatriz',
                token: token,
                data: { unit_matriz_id: username }
            })
        });

        const unidades = await response.json();
        const select = document.getElementById('unit-select');
        unidades.forEach(filial => {
            const option = new Option(filial.filial_nome, filial.filial_id);
            select.appendChild(option);
        });
    }

    // Carregar Insumos/Produtos
    async function loadProdutos(unitId) {
        const response = await fetch(baseUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                method: 'getInsumosUsage',
                token: token,
                data: { system_unit_id: unitId }
            })
        });

        const produtos = await response.json();
        const select = document.getElementById('product-select');
        select.innerHTML = ''; // Limpa produtos antigos
        produtos.forEach(produto => {
            const option = new Option(produto.insumo_nome, produto.insumo_id);
            select.appendChild(option);
        });
        $('#product-select').multiSelect();
    }

    // Carregar Modelos para "Importar Produtos do Balanço"
    async function loadModelos(unitId) {
        const response = await fetch(baseUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                method: 'listModelosWithProducts',
                token: token,
                data: { unit_id: unitId }
            })
        });

        const data = await response.json();
        const select = document.getElementById('modelos-select');
        select.innerHTML = ''; // Limpa opções antigas

        data.modelos.forEach(modelo => {
            const option = new Option(modelo.nome, modelo.id);
            select.appendChild(option);
        });

        // Carregar os produtos do modelo selecionado
        select.addEventListener('change', function () {
            const modeloSelecionado = data.modelos.find(m => m.id == this.value);
            const selectProdutos = document.getElementById('product-select');
            selectProdutos.innerHTML = ''; // Limpa produtos anteriores

            modeloSelecionado.itens.forEach(item => {
                const option = new Option(item.nome_produto, item.id_produto);
                selectProdutos.appendChild(option);
            });
            $('#product-select').multiSelect('refresh');
        });
    }

    // Calcular necessidades após o envio de datas e produtos
    async function calcularNecessidades() {
        try {
            const selectedUnitId = document.getElementById('unit-select').value;
            const selectedProducts = $('#product-select').val(); // Produtos selecionados
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;

            console.log("Chamando cálculo de necessidades com produtos:", selectedProducts);
            console.log("Datas selecionadas:", startDate, endDate);

            const dates = calcularUltimas4DatasPorSemana(startDate, endDate);
            console.log("Últimas 4 datas calculadas:", dates);

            showLoadingModal(); // Exibir o modal enquanto os dados estão sendo carregados

            const response = await fetch(baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    method: 'getInsumoConsumption',
                    token: token,
                    data: {
                        system_unit_id: selectedUnitId,
                        dates: dates,
                        productCodes: selectedProducts
                    }
                })
            });

            const consumptions = await response.json();
            console.log("Resultado de consumo retornado:", consumptions);

            const tableBody = document.getElementById('result-table').querySelector('tbody');
            tableBody.innerHTML = ''; // Limpa o conteúdo anterior

            // Adicionar a lógica para obter os nomes dos produtos e mostrar na tabela
            const productSelect = document.getElementById('product-select');
            const productNames = Array.from(productSelect.selectedOptions).map(option => option.text);

            for (const [index, [productId, quantity]] of Object.entries(Object.entries(consumptions))) {
                const productName = productNames[index];
                const row = `
                    <tr>
                        <td>${productId}</td>
                        <td>${productName}</td>
                        <td>${quantity}</td>
                        <td>
                            <button type="button" class="btn btn-primary btn-apply" data-quantity="${quantity}">➡</button>
                        </td>
                        <td><input type="number" class="form-control" value=""></td>
                    </tr>
                `;
                tableBody.insertAdjacentHTML('beforeend', row);
            }

            document.querySelectorAll('.btn-apply').forEach(button => {
                button.addEventListener('click', function () {
                    const quantity = this.getAttribute('data-quantity');
                    const input = this.closest('tr').querySelector('input');
                    input.value = quantity; // Aplicar a quantidade sugerida no campo de input
                });
            });

            hideLoadingModal(); // Ocultar o modal após o cálculo
            document.getElementById('result-section').style.display = 'block'; // Mostrar a tabela de resultados
        } catch (error) {
            console.error("Erro ao calcular necessidades:", error);
            hideLoadingModal();
        }
    }

    // Função para isolar dias da semana de um período
    function isolarDiasDaSemana(startDateInput, endDateInput) {
        const startDate = new Date(startDateInput);
        const endDate = new Date(endDateInput);
        let diasUnicos = new Set();

        // Percorrer o intervalo de datas e identificar os dias da semana únicos
        for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            diasUnicos.add(d.getDay()); // Adiciona o índice do dia da semana (0 = Domingo, 6 = Sábado)
        }

        console.log("Dias da semana isolados no período:", Array.from(diasUnicos));
        return Array.from(diasUnicos);
    }

    // Função que retorna as últimas 4 ocorrências de um dia da semana específico
    function calcularUltimas4DatasParaDia(diaSemana) {
        let datasEncontradas = [];
        let dataAtual = new Date();

        // Loop para encontrar as últimas 4 ocorrências do dia da semana
        while (datasEncontradas.length < 4) {
            if (dataAtual.getDay() === diaSemana) {
                datasEncontradas.push(new Date(dataAtual).toISOString().slice(0, 10));
            }
            dataAtual.setDate(dataAtual.getDate() - 7); // Retrocede uma semana
        }

        console.log(`Últimas 4 datas para o dia ${diaSemana}:`, datasEncontradas);
        return datasEncontradas;
    }

    // Função principal que isola os dias da semana e calcula as últimas 4 datas de cada um
    function calcularUltimas4DatasPorSemana(startDateInput, endDateInput) {
        const diasDaSemana = isolarDiasDaSemana(startDateInput, endDateInput);
        let todasAsDatas = [];

        // Para cada dia da semana isolado, calcular as 4 últimas datas
        diasDaSemana.forEach(dia => {
            const ultimas4Datas = calcularUltimas4DatasParaDia(dia);
            todasAsDatas.push(...ultimas4Datas);
        });

        console.log("Todas as últimas 4 datas calculadas:", todasAsDatas);
        return todasAsDatas;
    }

    // Inicializar Unidades
    loadUnidades();
});
