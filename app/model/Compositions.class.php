<?php
/**
 * Compositions Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class Compositions extends TRecord
{
    const TABLENAME = 'compositions';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('product_id');
        parent::addAttribute('insumo_id');
        parent::addAttribute('quantity');
        parent::addAttribute('unit_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
