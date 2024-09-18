<?php
/**
 * Insumos Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class Insumos extends TRecord
{
    const TABLENAME = 'insumos';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('name');
        parent::addAttribute('description');
        parent::addAttribute('unit_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
