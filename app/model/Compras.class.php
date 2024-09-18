<?php
/**
 * Compras Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class Compras extends TRecord
{
    const TABLENAME = 'compras';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('insumo_id');
        parent::addAttribute('quantity_needed');
        parent::addAttribute('order_date');
        parent::addAttribute('unit_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
