<?php
/**
 * AccessLog
 *
 * Model da tabela mrk_access_log.
 * Padrão Adianti TRecord.
 *
 * @version    1.0
 * @package    model
 */
class AccessLog extends TRecord
{
    const TABLENAME  = 'mrk_access_log';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    /**
     * Construtor
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('user_id');
        parent::addAttribute('unit_id');
        parent::addAttribute('class_name');
        parent::addAttribute('action');
        parent::addAttribute('url');
        parent::addAttribute('ip');
        parent::addAttribute('user_agent');
        parent::addAttribute('data_entrada');
        parent::addAttribute('data_saida');
        parent::addAttribute('duracao_seg');
        parent::addAttribute('session_id');
    }
}