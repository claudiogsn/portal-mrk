<?php
/**
 * Necessidades Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class Necessidades extends TRecord
{
    const TABLENAME = 'necessidades';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('insumo_id');
        parent::addAttribute('estimated_need');
        parent::addAttribute('sobras');
        parent::addAttribute('date');
        parent::addAttribute('unit_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
