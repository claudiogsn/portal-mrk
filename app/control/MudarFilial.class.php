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
    protected $form;

    public function __construct($param)
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_MudarFilial');
        $this->form->setFormTitle('SELECIONE A UNIDADE');

        $system_unit_id = new TCombo('system_unit_id');
        $this->form->addFields([new TLabel('Unidade')], [$system_unit_id]);
        $system_unit_id->setSize('100%');

        $this->populateUnits($system_unit_id);

        $action = new TAction([$this, 'onSave']);
        if (!empty($param['callback'])) {
            $action->setParameter('callback', $param['callback']);
        }

        $this->form->addAction('Mudar', $action, 'fa:save green');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }

    public function populateUnits($combo)
    {
        try {
            TTransaction::open('permission');

            $repo = new TRepository('SystemUnit');
            $criteria = new TCriteria;

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

    public function onSave($param)
    {
        $this->form->validate();
        $data = $this->form->getData();
        TSession::setValue('userunitid', $data->system_unit_id);
        $IDUnidade = TSession::getValue('userunitid');

        // Consulta nome da unidade
        TTransaction::open('permission');
        $conn = TTransaction::get();
        $sth = $conn->prepare("SELECT name FROM system_unit WHERE id = :id LIMIT 1");
        $sth->bindParam(':id', $IDUnidade, PDO::PARAM_INT);
        $sth->execute();
        $result = $sth->fetch(PDO::FETCH_ASSOC);
        $NomeUnidade = $result['name'];
        TSession::setValue('userunitname', $NomeUnidade);

        TTransaction::close();

        new TMessage('info', "Modificado para Unidade $IDUnidade - $NomeUnidade");

        $callback = TSession::getValue('last_class') ?? 'WelcomeView';
        AdiantiCoreApplication::gotoPage($callback);    }
}
