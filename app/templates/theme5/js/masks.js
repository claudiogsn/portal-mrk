document.addEventListener('DOMContentLoaded', function() {
    var cpfCnpjInput = document.getElementById('cpf_cnpj');
    
    cpfCnpjInput.addEventListener('input', function() {
        var value = cpfCnpjInput.value.replace(/\D/g, ''); // Remove tudo que não é dígito
        if (value.length <= 11) {
            // Máscara de CPF
            cpfCnpjInput.setAttribute('maxlength', '14');
            cpfCnpjInput.setAttribute('placeholder', '999.999.999-99');
            cpfCnpjInput.value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        } else {
            // Máscara de CNPJ
            cpfCnpjInput.setAttribute('maxlength', '18');
            cpfCnpjInput.setAttribute('placeholder', '99.999.999/9999-99');
            cpfCnpjInput.value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
        }
    });
});
