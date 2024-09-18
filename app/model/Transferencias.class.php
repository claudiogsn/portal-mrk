<?php
/**
 * Transferencias Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class Transferencias extends TRecord
{
    const TABLENAME = 'transferencias';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('insumo_id');
        parent::addAttribute('quantity_transferred');
        parent::addAttribute('source_unit_id');
        parent::addAttribute('target_unit_id');
        parent::addAttribute('transfer_date');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
