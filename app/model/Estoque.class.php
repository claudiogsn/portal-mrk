<?php
/**
 * Estoque Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class Estoque extends TRecord
{
    const TABLENAME = 'estoque';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('insumo_id');
        parent::addAttribute('quantity_available');
        parent::addAttribute('last_updated');
        parent::addAttribute('unit_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
