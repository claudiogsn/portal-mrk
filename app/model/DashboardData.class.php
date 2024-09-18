<?php
/**
 * DashboardData Active Record
 * @author  Claudio Gomes da Silva Neto
 */
class DashboardData extends TRecord
{
    const TABLENAME = 'dashboard_data';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('unit_id');
        parent::addAttribute('total_sales');
        parent::addAttribute('total_stock');
        parent::addAttribute('pending_transfers');
        parent::addAttribute('update_date');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
?>
