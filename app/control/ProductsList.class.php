<?php
/**
 * ProductsList Listing
 * @author  <your name here>
 */
class ProductsList extends TPage
{
    private $form; // form
    private $datagrid; // listing
    private $pageNavigation;
    private $formgrid;
    private $loaded;
    private $deleteButton;
    
    /**
     * Class constructor
     * Creates the page, the form and the listing
     */
    public function __construct()
    {
        parent::__construct();
        
        // creates the form
        $this->form = new BootstrapFormBuilder('form_search_Products');
        $this->form->setFormTitle('Products');
        

        // create the form fields
        $system_unit_id = new TDBUniqueSearch('system_unit_id', 'communication', 'SystemUnit', 'id', 'name');
        $codigo = new TEntry('codigo');
        $nome = new TEntry('nome');
        $categ = new TEntry('categ');
        $venda = new TEntry('venda');
        $composicao = new TEntry('composicao');


        // add the fields
        $this->form->addFields( [ new TLabel('System Unit Id') ], [ $system_unit_id ] );
        $this->form->addFields( [ new TLabel('Codigo') ], [ $codigo ] );
        $this->form->addFields( [ new TLabel('Nome') ], [ $nome ] );
        $this->form->addFields( [ new TLabel('Categ') ], [ $categ ] );
        $this->form->addFields( [ new TLabel('Venda') ], [ $venda ] );
        $this->form->addFields( [ new TLabel('Composicao') ], [ $composicao ] );


        // set sizes
        $system_unit_id->setSize('100%');
        $codigo->setSize('100%');
        $nome->setSize('100%');
        $categ->setSize('100%');
        $venda->setSize('100%');
        $composicao->setSize('100%');

        
        // keep the form filled during navigation with session data
        $this->form->setData( TSession::getValue(__CLASS__ . '_filter_data') );
        
        // add the search form actions
        $btn = $this->form->addAction(_t('Find'), new TAction([$this, 'onSearch']), 'fa:search');
        $btn->class = 'btn btn-sm btn-primary';
        $this->form->addActionLink(_t('New'), new TAction(['ProductsForm', 'onEdit']), 'fa:plus green');
        
        // creates a Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';
        // $this->datagrid->enablePopover('Popover', 'Hi <b> {name} </b>');
        

        // creates the datagrid columns
        $column_system_unit_id = new TDataGridColumn('system_unit_id', 'System Unit Id', 'right');
        $column_codigo = new TDataGridColumn('codigo', 'Codigo', 'center');
        $column_nome = new TDataGridColumn('nome', 'Nome', 'center');
        $column_preco = new TDataGridColumn('preco', 'Preco', 'center');
        $column_categ = new TDataGridColumn('categ', 'Categ', 'center');
        $column_und = new TDataGridColumn('und', 'Und', 'center');
        $column_venda = new TDataGridColumn('venda', 'Venda', 'center');
        $column_composicao = new TDataGridColumn('composicao', 'Composicao', 'center');
        $column_insumo = new TDataGridColumn('insumo', 'Insumo', 'center');


        // add the columns to the DataGrid
        $this->datagrid->addColumn($column_system_unit_id);
        $this->datagrid->addColumn($column_codigo);
        $this->datagrid->addColumn($column_nome);
        $this->datagrid->addColumn($column_preco);
        $this->datagrid->addColumn($column_categ);
        $this->datagrid->addColumn($column_und);
        $this->datagrid->addColumn($column_venda);
        $this->datagrid->addColumn($column_composicao);
        $this->datagrid->addColumn($column_insumo);


        // creates the datagrid column actions
        $column_codigo->setAction(new TAction([$this, 'onReload']), ['order' => 'codigo']);
        $column_nome->setAction(new TAction([$this, 'onReload']), ['order' => 'nome']);
        $column_categ->setAction(new TAction([$this, 'onReload']), ['order' => 'categ']);

        // define the transformer method over image
        $column_preco->setTransformer( function($value, $object, $row) {
            if (is_numeric($value))
            {
                return 'R$ ' . number_format($value, 2, ',', '.');
            }
            return $value;
        });



        $action1 = new TDataGridAction(['ProductsForm', 'onEdit'], ['id'=>'{id}']);
        $action2 = new TDataGridAction([$this, 'onDelete'], ['id'=>'{id}']);
        
        $this->datagrid->addAction($action1, _t('Edit'),   'far:edit blue');
        $this->datagrid->addAction($action2 ,_t('Delete'), 'far:trash-alt red');
        
        // create the datagrid model
        $this->datagrid->createModel();
        
        // creates the page navigation
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());
        
        // vertical box container
        $container = new TVBox;
        $container->style = 'width: 100%';
        // $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);
        $container->add(TPanelGroup::pack('', $this->datagrid, $this->pageNavigation));
        
        parent::add($container);
    }
    
    /**
     * Inline record editing
     * @param $param Array containing:
     *              key: object ID value
     *              field name: object attribute to be updated
     *              value: new attribute content 
     */
    public function onInlineEdit($param)
    {
        try
        {
            // get the parameter $key
            $field = $param['field'];
            $key   = $param['key'];
            $value = $param['value'];
            
            TTransaction::open('communication'); // open a transaction with database
            $object = new Products($key); // instantiates the Active Record
            $object->{$field} = $value;
            $object->store(); // update the object in the database
            TTransaction::close(); // close the transaction
            
            $this->onReload($param); // reload the listing
            new TMessage('info', "Record Updated");
        }
        catch (Exception $e) // in case of exception
        {
            new TMessage('error', $e->getMessage()); // shows the exception error message
            TTransaction::rollback(); // undo all pending operations
        }
    }
    
    /**
     * Register the filter in the session
     */
    public function onSearch()
    {
        // get the search form data
        $data = $this->form->getData();
        
        // clear session filters
        TSession::setValue(__CLASS__.'_filter_system_unit_id',   NULL);
        TSession::setValue(__CLASS__.'_filter_codigo',   NULL);
        TSession::setValue(__CLASS__.'_filter_nome',   NULL);
        TSession::setValue(__CLASS__.'_filter_categ',   NULL);
        TSession::setValue(__CLASS__.'_filter_venda',   NULL);
        TSession::setValue(__CLASS__.'_filter_composicao',   NULL);

        if (isset($data->system_unit_id) AND ($data->system_unit_id)) {
            $filter = new TFilter('system_unit_id', '=', $data->system_unit_id); // create the filter
            TSession::setValue(__CLASS__.'_filter_system_unit_id',   $filter); // stores the filter in the session
        }


        if (isset($data->codigo) AND ($data->codigo)) {
            $filter = new TFilter('codigo', 'like', "%{$data->codigo}%"); // create the filter
            TSession::setValue(__CLASS__.'_filter_codigo',   $filter); // stores the filter in the session
        }


        if (isset($data->nome) AND ($data->nome)) {
            $filter = new TFilter('nome', 'like', "%{$data->nome}%"); // create the filter
            TSession::setValue(__CLASS__.'_filter_nome',   $filter); // stores the filter in the session
        }


        if (isset($data->categ) AND ($data->categ)) {
            $filter = new TFilter('categ', 'like', "%{$data->categ}%"); // create the filter
            TSession::setValue(__CLASS__.'_filter_categ',   $filter); // stores the filter in the session
        }


        if (isset($data->venda) AND ($data->venda)) {
            $filter = new TFilter('venda', 'like', "%{$data->venda}%"); // create the filter
            TSession::setValue(__CLASS__.'_filter_venda',   $filter); // stores the filter in the session
        }


        if (isset($data->composicao) AND ($data->composicao)) {
            $filter = new TFilter('composicao', 'like', "%{$data->composicao}%"); // create the filter
            TSession::setValue(__CLASS__.'_filter_composicao',   $filter); // stores the filter in the session
        }

        
        // fill the form with data again
        $this->form->setData($data);
        
        // keep the search data in the session
        TSession::setValue(__CLASS__ . '_filter_data', $data);
        
        $param = array();
        $param['offset']    =0;
        $param['first_page']=1;
        $this->onReload($param);
    }
    
    /**
     * Load the datagrid with data
     */
    public function onReload($param = NULL)
    {
        try
        {
            // open a transaction with database 'communication'
            TTransaction::open('communication');
            
            // creates a repository for Products
            $repository = new TRepository('Products');
            $limit = 100;
            // creates a criteria
            $criteria = new TCriteria;
            
            // default order
            if (empty($param['order']))
            {
                $param['order'] = 'id';
                $param['direction'] = 'asc';
            }
            $criteria->setProperties($param); // order, offset
            $criteria->setProperty('limit', $limit);
            

            if (TSession::getValue(__CLASS__.'_filter_system_unit_id')) {
                $criteria->add(TSession::getValue(__CLASS__.'_filter_system_unit_id')); // add the session filter
            }


            if (TSession::getValue(__CLASS__.'_filter_codigo')) {
                $criteria->add(TSession::getValue(__CLASS__.'_filter_codigo')); // add the session filter
            }


            if (TSession::getValue(__CLASS__.'_filter_nome')) {
                $criteria->add(TSession::getValue(__CLASS__.'_filter_nome')); // add the session filter
            }


            if (TSession::getValue(__CLASS__.'_filter_categ')) {
                $criteria->add(TSession::getValue(__CLASS__.'_filter_categ')); // add the session filter
            }


            if (TSession::getValue(__CLASS__.'_filter_venda')) {
                $criteria->add(TSession::getValue(__CLASS__.'_filter_venda')); // add the session filter
            }


            if (TSession::getValue(__CLASS__.'_filter_composicao')) {
                $criteria->add(TSession::getValue(__CLASS__.'_filter_composicao')); // add the session filter
            }

            
            // load the objects according to criteria
            $objects = $repository->load($criteria, FALSE);
            
            if (is_callable($this->transformCallback))
            {
                call_user_func($this->transformCallback, $objects, $param);
            }
            
            $this->datagrid->clear();
            if ($objects)
            {
                // iterate the collection of active records
                foreach ($objects as $object)
                {
                    // add the object inside the datagrid
                    $this->datagrid->addItem($object);
                }
            }
            
            // reset the criteria for record count
            $criteria->resetProperties();
            $count= $repository->count($criteria);
            
            $this->pageNavigation->setCount($count); // count of records
            $this->pageNavigation->setProperties($param); // order, page
            $this->pageNavigation->setLimit($limit); // limit
            
            // close the transaction
            TTransaction::close();
            $this->loaded = true;
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
    
    /**
     * Ask before deletion
     */
    public static function onDelete($param)
    {
        // define the delete action
        $action = new TAction([__CLASS__, 'Delete']);
        $action->setParameters($param); // pass the key parameter ahead
        
        // shows a dialog to the user
        new TQuestion(AdiantiCoreTranslator::translate('Do you really want to delete ?'), $action);
    }
    
    /**
     * Delete a record
     */
    public static function Delete($param)
    {
        try
        {
            $key=$param['key']; // get the parameter $key
            TTransaction::open('communication'); // open a transaction with database
            $object = new Products($key, FALSE); // instantiates the Active Record
            $object->delete(); // deletes the object from the database
            TTransaction::close(); // close the transaction
            
            $pos_action = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', AdiantiCoreTranslator::translate('Record deleted'), $pos_action); // success message
        }
        catch (Exception $e) // in case of exception
        {
            new TMessage('error', $e->getMessage()); // shows the exception error message
            TTransaction::rollback(); // undo all pending operations
        }
    }
    
    /**
     * method show()
     * Shows the page
     */
    public function show()
    {
        // check if the datagrid is already loaded
        if (!$this->loaded AND (!isset($_GET['method']) OR !(in_array($_GET['method'],  array('onReload', 'onSearch')))) )
        {
            if (func_num_args() > 0)
            {
                $this->onReload( func_get_arg(0) );
            }
            else
            {
                $this->onReload();
            }
        }
        parent::show();
    }
}
