<?php
/**
 * Sales Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class Sales extends TRecord
{
    const TABLENAME = 'sales';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('insumo_id');
        parent::addAttribute('quantity_sold');
        parent::addAttribute('sale_date');
        parent::addAttribute('unit_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
