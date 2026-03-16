// planoContasTree.js

const PlanoContasTree = {
    cache: null,
    eventosAtachados: false,

    // 1. Carrega os dados da API apenas uma vez por tela
    async carregarDados(baseUrl, token, unit_id) {
        if (this.cache) return this.cache;

        try {
            const res = await axios.post(baseUrl, {
                method: 'listPlanos',
                token: token,
                data: { system_unit_id: Number(unit_id) }
            });

            let planos = Array.isArray(res.data) ? res.data : (res.data?.data || []);
            planos.sort((a,b) => a.codigo.localeCompare(b.codigo));

            // Define Níveis e Pais
            planos.forEach(p => {
                p.nivel = Math.floor((p.codigo.length - 2) / 2);
                p.isParent = planos.some(child => child.codigo.startsWith(p.codigo) && child.codigo.length > p.codigo.length);
            });

            this.cache = planos;
            this._iniciarEventos(); // Garante que o evento de clique na árvore exista

            return this.cache;
        } catch(e) {
            console.error("Erro ao carregar Planos de Contas", e);
            return [];
        }
    },

    // 2. Transforma qualquer <select> no componente de árvore
    renderizar(seletor) {
        const $select = $(seletor);
        if (!$select.length || !this.cache) return;

        if ($select.hasClass("select2-hidden-accessible")) {
            $select.select2('destroy');
        }

        $select.empty().append(new Option('Selecione...', ''));

        this.cache.forEach(p => {
            let $opt = $('<option>', {
                value: p.codigo,
                text: p.codigo + ' - ' + p.descricao,
                'data-codigo': p.codigo,
                'data-parent': p.isParent,
                'data-nivel': p.nivel
            });
            $select.append($opt);
        });

        $select.select2({
            placeholder: 'Selecione o plano',
            allowClear: true,
            width: '100%',
            templateResult: this._formatarTemplate
        });
    },

    // 3. (Privado) Formatação visual do Select2
    _formatarTemplate(data) {
        if (!data.id) return data.text;

        let $el = $(data.element);
        let codigo = $el.data('codigo');
        let isParent = $el.data('parent') === true || $el.data('parent') === "true";
        let nivel = parseInt($el.data('nivel')) || 0;

        let padding = nivel * 20;
        let fontWeight = isParent ? 'bold' : 'normal';

        let icon = isParent
            ? `<i class="fas fa-chevron-down tree-toggle" data-codigo="${codigo}" style="cursor:pointer; color:#2196F3; margin-right:8px; width:15px; text-align:center;"></i>`
            : `<i class="fas fa-circle" style="color:#ddd; margin-right:8px; font-size:6px; width:15px; text-align:center; vertical-align:middle;"></i>`;

        return $(`
            <div class="tree-node" data-codigo="${codigo}" style="padding-left: ${padding}px; display:flex; align-items:center; font-weight: ${fontWeight};">
                ${icon}
                <span>${data.text}</span>
            </div>
        `);
    },

    // 4. (Privado) Evento global para expandir/recolher
    _iniciarEventos() {
        if (this.eventosAtachados) return;

        $(document).on('mouseup', '.tree-toggle', function(e) {
            e.stopPropagation();
            e.preventDefault();

            let $icon = $(this);
            let codigo = $icon.data('codigo');
            let isExpanded = $icon.hasClass('fa-chevron-down');

            if (isExpanded) {
                $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                $('.tree-node').each(function() {
                    let childCode = $(this).data('codigo');
                    if (childCode && childCode.startsWith(codigo) && childCode.length > codigo.length) {
                        $(this).closest('.select2-results__option').hide();
                        let $childIcon = $(this).find('.tree-toggle');
                        if ($childIcon.length) $childIcon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                    }
                });
            } else {
                $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                $('.tree-node').each(function() {
                    let childCode = $(this).data('codigo');
                    if (childCode && childCode.startsWith(codigo) && childCode.length > codigo.length) {
                        $(this).closest('.select2-results__option').show();
                        let $childIcon = $(this).find('.tree-toggle');
                        if ($childIcon.length) $childIcon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                    }
                });
            }
        });

        this.eventosAtachados = true;
    }
};