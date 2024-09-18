<?php
/**
 * Products Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class Products extends TRecord
{
    const TABLENAME = 'products';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('name');
        parent::addAttribute('description');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
