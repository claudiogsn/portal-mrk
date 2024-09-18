<?php
/**
 * Fornecedores Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class Fornecedores extends TRecord
{
    const TABLENAME = 'fornecedores';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('name');
        parent::addAttribute('contact_info');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
