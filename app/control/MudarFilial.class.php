<?php

use Adianti\Registry\TSession;
use Adianti\Database\TTransaction;
use Adianti\Database\TCriteria;
use Adianti\Database\TRepository;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Container\TVBox;

/**
 * MudarFilial Form
 */
class MudarFilial extends \Adianti\Control\TWindow
{
    protected $form; // form

    /**
     * Form constructor
     * @param $param Request
     */
    public function __construct($param)
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_MudarFilial');
        $this->form->setFormTitle('SELECIONE A UNIDADE');

        // Create TCombo
        $system_unit_id = new TCombo('system_unit_id');
        $this->form->addFields([new TLabel('Unidade')], [$system_unit_id]);

        $system_unit_id->setSize('100%');

        // Load options for TCombo with filtering
        $this->populateUnits($system_unit_id);

        $this->form->addAction('Mudar', new TAction([$this, 'onSave']), 'fa:save green');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }

    /**
     * Populate units into the TCombo
     */
    public function populateUnits($combo)
    {
        try {
            TTransaction::open('communication');

            $repo = new TRepository('SystemUnit');
            $criteria = new TCriteria;

            // Get user units from session
            $units = TSession::getValue('userunitids');
            if ($units && is_array($units)) {
                $criteria->add(new TFilter('id', 'IN', $units));
            }

            $unitsList = $repo->load($criteria);

            $options = [];
            foreach ($unitsList as $unit) {
                $options[$unit->id] = $unit->name;
            }

            $combo->addItems($options);

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Save form data
     * @param $param Request
     */
    public function onSave($param)
    {
        $this->form->validate(); // validate form data
        $data = $this->form->getData(); // get form data as array
        TSession::setValue('userunitid', $data->system_unit_id);
        $IDUnidade = TSession::getValue('userunitid');

        // Consultar Nome da Unidade
        TTransaction::open('communication');
        $conn = TTransaction::get();
        $sth = $conn->prepare("SELECT name FROM system_unit WHERE id = :id LIMIT 1");
        $sth->bindParam(':id', $IDUnidade, PDO::PARAM_INT);
        $sth->execute();
        $result = $sth->fetch(PDO::FETCH_ASSOC);
        $NomeUnidade = $result['name'];
        TSession::setValue('userunitname', $NomeUnidade);

        new TMessage('info', "Modificado para Unidade $IDUnidade - $NomeUnidade");

        AdiantiCoreApplication::gotoPage('WelcomeView');
    }
}
