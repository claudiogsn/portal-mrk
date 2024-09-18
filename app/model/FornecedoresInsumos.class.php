<?php
/**
 * FornecedoresInsumos Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class FornecedoresInsumos extends TRecord
{
    const TABLENAME = 'fornecedores_insumos';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('fornecedor_id');
        parent::addAttribute('insumo_id');
        parent::addAttribute('unit_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
