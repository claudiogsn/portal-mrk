<?php

ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');


require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../controllers/BiController.php';
require_once __DIR__ . '/../controllers/UtilsController.php';
require_once __DIR__ . '/../controllers/FinanceiroContaController.php';


class FinanceiroRecorrenciaController {

    private const MAX_MESES = 120;
    /* =========================================================
     *  AUDITORIA
     * =========================================================*/
    private static function logAuditoria(
        string $acao,
        string $tabela,
        int $registro_id,
        ?array $dados_antes,
        ?array $dados_depois,
        ?int $system_unit_id = null,
        ?int $usuario_id = null
    ): void {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO financeiro_auditoria
                (acao, tabela, registro_id, dados_antes, dados_depois, system_unit_id, usuario_id)
                VALUES (:acao, :tabela, :registro_id, :antes, :depois, :unit, :user)
            ");
            $stmt->execute([
                ':acao'        => $acao,
                ':tabela'      => $tabela,
                ':registro_id' => $registro_id,
                ':antes'       => $dados_antes ? json_encode($dados_antes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
                ':depois'      => $dados_depois ? json_encode($dados_depois, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
                ':unit'        => $system_unit_id,
                ':user'        => $usuario_id
            ]);
        } catch (Exception $e) {
            error_log("[AUDITORIA RECORRENCIA ERRO] " . $e->getMessage());
        }
    }

    /* =========================================================
     *  HELPERS
     * =========================================================*/

    /**
     * Ajusta o dia para o último dia válido do mês quando o dia
     * informado não existir (ex.: 31 em fevereiro vira 28/29).
     */
    private static function montarData(int $ano, int $mes, int $dia): string
    {
        $ultimoDia = (int) date('t', mktime(0, 0, 0, $mes, 1, $ano));
        $diaFinal  = min($dia, $ultimoDia);
        return sprintf('%04d-%02d-%02d', $ano, $mes, $diaFinal);
    }

    /**
     * Gera o documento no padrão R-04.2026 (prefixo-MM.AAAA)
     * a partir da data de emissão.
     */
    private static function gerarDoc(string $prefixo, string $dataEmissao): string
    {
        $ts = strtotime($dataEmissao);
        return sprintf('%s-%02d.%04d', $prefixo, (int)date('m', $ts), (int)date('Y', $ts));
    }

    /**
     * Valida e normaliza os campos básicos da recorrência.
     */
    private static function validarPayload(array $data, bool $ehUpdate = false): array
    {
        if (!$ehUpdate) {
            $obrigatorios = ['system_unit_id', 'nome', 'tipo', 'valor', 'dia_vencimento', 'dia_emissao', 'qtd_meses', 'data_inicio'];
            foreach ($obrigatorios as $f) {
                if (!array_key_exists($f, $data) || $data[$f] === '' || $data[$f] === null) {
                    throw new Exception("Campo obrigatório ausente: {$f}");
                }
            }
        }

        // Tipo: aceita C/D ou R/D vindos do front
        if (isset($data['tipo'])) {
            $t = strtoupper(trim($data['tipo']));
            if ($t === 'R') $t = 'C';
            if (!in_array($t, ['C', 'D'], true)) {
                throw new Exception("Tipo inválido. Use 'C' (Receita) ou 'D' (Despesa).");
            }
            $data['tipo'] = $t;
        }

        // Dias entre 1 e 31
        foreach (['dia_vencimento', 'dia_emissao'] as $f) {
            if (isset($data[$f])) {
                $dia = (int)$data[$f];
                if ($dia < 1 || $dia > 31) {
                    throw new Exception("Campo {$f} deve estar entre 1 e 31.");
                }
                $data[$f] = $dia;
            }
        }

        // Quantidade de meses entre 1 e 60
        if (isset($data['qtd_meses'])) {
            $qtd = (int)$data['qtd_meses'];
            if ($qtd < 1 || $qtd > self::MAX_MESES) {
                throw new Exception("Quantidade de meses deve estar entre 1 e " . self::MAX_MESES . " (máx. 5 anos).");
            }
            $data['qtd_meses'] = $qtd;
        }

        // Valor positivo
        if (isset($data['valor'])) {
            $valor = (float)$data['valor'];
            if ($valor <= 0) {
                throw new Exception("Valor deve ser maior que zero.");
            }
            $data['valor'] = $valor;
        }

        // data_inicio aceita 'YYYY-MM' ou 'YYYY-MM-DD' — normaliza para o dia 1
        if (isset($data['data_inicio']) && $data['data_inicio'] !== '') {
            $di = trim((string)$data['data_inicio']);
            if (preg_match('/^\d{4}-\d{2}$/', $di)) {
                $di .= '-01';
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $di)) {
                throw new Exception("data_inicio inválida. Use o formato YYYY-MM ou YYYY-MM-DD.");
            }
            // força sempre dia 01 (referência de mês)
            $data['data_inicio'] = substr($di, 0, 7) . '-01';
        }

        // Prefixo do doc
        if (!empty($data['prefixo_doc'])) {
            $p = strtoupper(trim($data['prefixo_doc']));
            if (!preg_match('/^[A-Z0-9]{1,10}$/', $p)) {
                throw new Exception("prefixo_doc deve conter apenas letras/números (até 10 caracteres).");
            }
            $data['prefixo_doc'] = $p;
        }

        return $data;
    }

    /* =========================================================
     *  CRIAR RECORRÊNCIA (e gerar contas vinculadas)
     * =========================================================*/
    public static function createRecorrencia(array $data): array
    {
        global $pdo;

        $emTransacao = false;

        try {
            $data = self::validarPayload($data);

            $system_unit_id = (int)$data['system_unit_id'];
            $usuario_id     = $data['usuario_id'] ?? null;
            $prefixo        = $data['prefixo_doc'] ?? 'R';

            // Validação de plano de contas, se informado
            if (!empty($data['plano_contas'])) {
                $stPlano = $pdo->prepare("
                    SELECT id FROM financeiro_plano
                    WHERE system_unit_id = :unit AND codigo = :codigo LIMIT 1
                ");
                $stPlano->execute([':unit' => $system_unit_id, ':codigo' => $data['plano_contas']]);
                if (!$stPlano->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception("Plano de contas '{$data['plano_contas']}' não encontrado para esta unidade.");
                }
            }

            $pdo->beginTransaction();
            $emTransacao = true;

            // 1) Insere a recorrência
            $sqlRec = "
                INSERT INTO financeiro_recorrencia
                (system_unit_id, codigo, nome, entidade, cgc, tipo, valor,
                 dia_emissao, dia_vencimento, data_inicio, qtd_meses,
                 prefixo_doc, plano_contas, banco, forma_pagamento,
                 segmento, obs, ativo, usuario_id)
                VALUES
                (:system_unit_id, :codigo, :nome, :entidade, :cgc, :tipo, :valor,
                 :dia_emissao, :dia_vencimento, :data_inicio, :qtd_meses,
                 :prefixo_doc, :plano_contas, :banco, :forma_pagamento,
                 :segmento, :obs, 1, :usuario_id)
            ";
            $stRec = $pdo->prepare($sqlRec);
            $stRec->execute([
                ':system_unit_id'  => $system_unit_id,
                ':codigo'          => $data['codigo'] ?? null,
                ':nome'            => $data['nome'],
                ':entidade'        => $data['entidade'] ?? null,
                ':cgc'             => $data['cgc'] ?? null,
                ':tipo'            => $data['tipo'],
                ':valor'           => $data['valor'],
                ':dia_emissao'     => $data['dia_emissao'],
                ':dia_vencimento'  => $data['dia_vencimento'],
                ':data_inicio'     => $data['data_inicio'],
                ':qtd_meses'       => $data['qtd_meses'],
                ':prefixo_doc'     => $prefixo,
                ':plano_contas'    => $data['plano_contas'] ?? null,
                ':banco'           => $data['banco'] ?? null,
                ':forma_pagamento' => $data['forma_pagamento'] ?? null,
                ':segmento'        => $data['segmento'] ?? null,
                ':obs'             => $data['obs'] ?? null,
                ':usuario_id'      => $usuario_id,
            ]);

            $recorrenciaId = (int)$pdo->lastInsertId();

            // 2) Gera as contas mês a mês
            $contasGeradas = self::gerarContasParaRecorrencia($recorrenciaId, $data, $usuario_id);

            $pdo->commit();
            $emTransacao = false;

            // 3) Auditoria (após commit p/ não desfazer log)
            $stConsulta = $pdo->prepare("SELECT * FROM financeiro_recorrencia WHERE id = :id");
            $stConsulta->execute([':id' => $recorrenciaId]);
            $registro = $stConsulta->fetch(PDO::FETCH_ASSOC);

            self::logAuditoria(
                'CREATE',
                'financeiro_recorrencia',
                $recorrenciaId,
                null,
                $registro,
                $system_unit_id,
                $usuario_id
            );

            return [
                'success'         => true,
                'message'         => 'Recorrência criada com sucesso.',
                'recorrencia_id'  => $recorrenciaId,
                'contas_geradas'  => count($contasGeradas),
                'parcelas'        => $contasGeradas,
            ];

        } catch (Exception $e) {
            if ($emTransacao && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Erro ao criar recorrência: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Gera as N contas vinculadas a uma recorrência.
     * Usado tanto na criação quanto após edição (regenerar contas futuras).
     *
     * @return array lista de parcelas geradas (parcela, doc, emissao, vencimento, conta_id)
     */
    private static function gerarContasParaRecorrencia(int $recorrenciaId, array $rec, $usuario_id = null): array
    {
        global $pdo;

        $system_unit_id = (int)$rec['system_unit_id'];
        $diaEmissao     = (int)$rec['dia_emissao'];
        $diaVencimento  = (int)$rec['dia_vencimento'];
        $qtd            = (int)$rec['qtd_meses'];
        $prefixo        = $rec['prefixo_doc'] ?? 'R';

        // Mês/ano de partida (data_inicio sempre dia 01)
        $ts = strtotime($rec['data_inicio']);
        $mesAtual = (int)date('m', $ts);
        $anoAtual = (int)date('Y', $ts);

        $sql = "
            INSERT INTO financeiro_conta
            (system_unit_id, codigo, nome, entidade, cgc, tipo, doc,
             emissao, vencimento, valor, plano_contas, banco, forma_pagamento,
             segmento, obs, recorrencia_id, recorrencia_parcela)
            VALUES
            (:system_unit_id, :codigo, :nome, :entidade, :cgc, :tipo, :doc,
             :emissao, :vencimento, :valor, :plano_contas, :banco, :forma_pagamento,
             :segmento, :obs, :recorrencia_id, :recorrencia_parcela)
        ";
        $stIns = $pdo->prepare($sql);

        $parcelas = [];

        for ($i = 0; $i < $qtd; $i++) {
            // mês corrente da iteração
            $mes = $mesAtual + $i;
            $ano = $anoAtual + intdiv($mes - 1, 12);
            $mes = (($mes - 1) % 12) + 1;

            $emissao    = self::montarData($ano, $mes, $diaEmissao);
            $vencimento = self::montarData($ano, $mes, $diaVencimento);

            // Se o dia de vencimento for menor que o de emissão, joga p/ mês seguinte
            if ($diaVencimento < $diaEmissao) {
                $mesV = $mes + 1;
                $anoV = $ano + intdiv($mesV - 1, 12);
                $mesV = (($mesV - 1) % 12) + 1;
                $vencimento = self::montarData($anoV, $mesV, $diaVencimento);
            }

            $doc = self::gerarDoc($prefixo, $emissao);

            $stIns->execute([
                ':system_unit_id'      => $system_unit_id,
                ':codigo'              => $rec['codigo'] ?? null,
                ':nome'                => $rec['nome'],
                ':entidade'            => $rec['entidade'] ?? null,
                ':cgc'                 => $rec['cgc'] ?? null,
                ':tipo'                => $rec['tipo'],
                ':doc'                 => $doc,
                ':emissao'             => $emissao,
                ':vencimento'          => $vencimento,
                ':valor'               => $rec['valor'],
                ':plano_contas'        => $rec['plano_contas'] ?? null,
                ':banco'               => $rec['banco'] ?? null,
                ':forma_pagamento'     => $rec['forma_pagamento'] ?? null,
                ':segmento'            => $rec['segmento'] ?? null,
                ':obs'                 => $rec['obs'] ?? null,
                ':recorrencia_id'      => $recorrenciaId,
                ':recorrencia_parcela' => $i + 1,
            ]);

            $contaId = (int)$pdo->lastInsertId();

            $parcelas[] = [
                'parcela'    => $i + 1,
                'doc'        => $doc,
                'emissao'    => $emissao,
                'vencimento' => $vencimento,
                'conta_id'   => $contaId,
            ];
        }

        return $parcelas;
    }

    /* =========================================================
     *  ATUALIZAR RECORRÊNCIA
     *  - Atualiza o cadastro mestre
     *  - Propaga as alterações para as contas vinculadas
     *    (apenas as NÃO conciliadas e NÃO baixadas, por segurança)
     *  - Se mudar dia_vencimento/dia_emissao/qtd_meses/data_inicio,
     *    regenera as parcelas futuras pendentes
     * =========================================================*/
    public static function updateRecorrencia(int $id, array $data): array
    {
        global $pdo;

        $emTransacao = false;

        try {
            if ($id <= 0) {
                throw new Exception("ID inválido.");
            }

            // 🔍 Carrega registro atual
            $stCur = $pdo->prepare("SELECT * FROM financeiro_recorrencia WHERE id = :id LIMIT 1");
            $stCur->execute([':id' => $id]);
            $atual = $stCur->fetch(PDO::FETCH_ASSOC);

            if (!$atual) {
                throw new Exception("Recorrência não encontrada.");
            }

            $system_unit_id = (int)$atual['system_unit_id'];
            $usuario_id     = $data['usuario_id'] ?? null;

            // Normaliza payload (validação leve, em modo update)
            $data = self::validarPayload($data, true);

            // Validação de plano de contas, se informado
            if (!empty($data['plano_contas'])) {
                $stPlano = $pdo->prepare("
                    SELECT id FROM financeiro_plano
                    WHERE system_unit_id = :unit AND codigo = :codigo LIMIT 1
                ");
                $stPlano->execute([':unit' => $system_unit_id, ':codigo' => $data['plano_contas']]);
                if (!$stPlano->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception("Plano de contas '{$data['plano_contas']}' não encontrado para esta unidade.");
                }
            }

            // Campos editáveis no mestre
            $camposPermitidos = [
                'codigo', 'nome', 'entidade', 'cgc', 'tipo', 'valor',
                'dia_emissao', 'dia_vencimento', 'data_inicio', 'qtd_meses',
                'prefixo_doc', 'plano_contas', 'banco', 'forma_pagamento',
                'segmento', 'obs', 'ativo'
            ];

            // Detecta se algum campo "estrutural" mudou (afeta datas/doc/quantidade)
            $camposEstruturais = ['dia_emissao', 'dia_vencimento', 'data_inicio', 'qtd_meses', 'prefixo_doc'];
            $regenerarParcelas = false;
            foreach ($camposEstruturais as $ce) {
                if (array_key_exists($ce, $data) && (string)$data[$ce] !== (string)$atual[$ce]) {
                    $regenerarParcelas = true;
                    break;
                }
            }

            $setParts = [];
            $params   = [':id' => $id];
            $depois   = $atual;

            foreach ($camposPermitidos as $campo) {
                if (array_key_exists($campo, $data)) {
                    $setParts[]        = "$campo = :$campo";
                    $params[":$campo"] = $data[$campo];
                    $depois[$campo]    = $data[$campo];
                }
            }

            if (empty($setParts)) {
                throw new Exception("Nenhum campo válido foi informado para atualização.");
            }

            $pdo->beginTransaction();
            $emTransacao = true;

            // 1) Atualiza o mestre
            $sqlUpd = "UPDATE financeiro_recorrencia
                       SET " . implode(', ', $setParts) . ", updated_at = CURRENT_TIMESTAMP
                       WHERE id = :id";
            $stUpd = $pdo->prepare($sqlUpd);
            $stUpd->execute($params);

            // 2) Propaga para as contas vinculadas
            if ($regenerarParcelas) {
                // Apaga as contas pendentes (não conciliadas e não baixadas) e regenera
                $stDel = $pdo->prepare("
                    DELETE FROM financeiro_conta
                    WHERE recorrencia_id = :rid
                      AND (is_conciliated IS NULL OR is_conciliated = 0)
                      AND (baixa_dt IS NULL OR baixa_dt = '0000-00-00' OR baixa_dt = '')
                ");
                $stDel->execute([':rid' => $id]);
                $apagadas = $stDel->rowCount();

                // Conta quantas já estão conciliadas/baixadas (essas ficam intocadas)
                $stCntFix = $pdo->prepare("
                    SELECT COUNT(*) FROM financeiro_conta
                    WHERE recorrencia_id = :rid
                      AND (is_conciliated = 1 OR (baixa_dt IS NOT NULL AND baixa_dt != '0000-00-00' AND baixa_dt != ''))
                ");
                $stCntFix->execute([':rid' => $id]);
                $fixas = (int)$stCntFix->fetchColumn();

                // Regenera com base no estado novo (depois)
                // Atenção: regeneramos TODAS as parcelas conforme novo qtd_meses,
                // mas pulamos as parcelas cujo número já está "fixado" (conciliada/baixada).
                $parcelasFixadasNumeros = [];
                if ($fixas > 0) {
                    $stFix = $pdo->prepare("
                        SELECT recorrencia_parcela FROM financeiro_conta
                        WHERE recorrencia_id = :rid
                          AND (is_conciliated = 1 OR (baixa_dt IS NOT NULL AND baixa_dt != '0000-00-00' AND baixa_dt != ''))
                    ");
                    $stFix->execute([':rid' => $id]);
                    $parcelasFixadasNumeros = array_map('intval', $stFix->fetchAll(PDO::FETCH_COLUMN));
                }

                self::regenerarParcelasPulando($id, $depois, $parcelasFixadasNumeros);
            } else {
                // Apenas atualiza os campos "soltos" das contas vinculadas pendentes
                $camposSpread = [
                    'codigo', 'nome', 'entidade', 'cgc', 'tipo', 'valor',
                    'plano_contas', 'banco', 'forma_pagamento', 'segmento', 'obs'
                ];

                $setSpread = [];
                $paramsSpread = [':rid' => $id];
                foreach ($camposSpread as $cs) {
                    if (array_key_exists($cs, $data)) {
                        $setSpread[]            = "$cs = :$cs";
                        $paramsSpread[":$cs"]   = $data[$cs];
                    }
                }

                if (!empty($setSpread)) {
                    $sqlSpread = "
                        UPDATE financeiro_conta
                        SET " . implode(', ', $setSpread) . ", updated_at = CURRENT_TIMESTAMP
                        WHERE recorrencia_id = :rid
                          AND (is_conciliated IS NULL OR is_conciliated = 0)
                          AND (baixa_dt IS NULL OR baixa_dt = '0000-00-00' OR baixa_dt = '')
                    ";
                    $stSpread = $pdo->prepare($sqlSpread);
                    $stSpread->execute($paramsSpread);
                }
            }

            $pdo->commit();
            $emTransacao = false;

            // 3) Auditoria
            self::logAuditoria(
                'UPDATE',
                'financeiro_recorrencia',
                $id,
                $atual,
                $depois,
                $system_unit_id,
                $usuario_id
            );

            return [
                'success'             => true,
                'message'             => 'Recorrência atualizada e contas vinculadas sincronizadas.',
                'id'                  => $id,
                'regenerou_parcelas'  => $regenerarParcelas
            ];

        } catch (Exception $e) {
            if ($emTransacao && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Erro ao atualizar recorrência: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Regenera as parcelas de uma recorrência pulando os números já "fixados"
     * (conciliados/baixados). Mesma lógica de gerarContasParaRecorrencia,
     * porém respeitando parcelas já existentes.
     */
    private static function regenerarParcelasPulando(int $recorrenciaId, array $rec, array $parcelasFixadas): void
    {
        global $pdo;

        $system_unit_id = (int)$rec['system_unit_id'];
        $diaEmissao     = (int)$rec['dia_emissao'];
        $diaVencimento  = (int)$rec['dia_vencimento'];
        $qtd            = (int)$rec['qtd_meses'];
        $prefixo        = $rec['prefixo_doc'] ?? 'R';

        $ts = strtotime($rec['data_inicio']);
        $mesAtual = (int)date('m', $ts);
        $anoAtual = (int)date('Y', $ts);

        $sql = "
            INSERT INTO financeiro_conta
            (system_unit_id, codigo, nome, entidade, cgc, tipo, doc,
             emissao, vencimento, valor, plano_contas, banco, forma_pagamento,
             segmento, obs, recorrencia_id, recorrencia_parcela)
            VALUES
            (:system_unit_id, :codigo, :nome, :entidade, :cgc, :tipo, :doc,
             :emissao, :vencimento, :valor, :plano_contas, :banco, :forma_pagamento,
             :segmento, :obs, :recorrencia_id, :recorrencia_parcela)
        ";
        $stIns = $pdo->prepare($sql);

        for ($i = 0; $i < $qtd; $i++) {
            $numeroParcela = $i + 1;
            if (in_array($numeroParcela, $parcelasFixadas, true)) {
                continue; // pula parcelas já fixadas (conciliadas/baixadas)
            }

            $mes = $mesAtual + $i;
            $ano = $anoAtual + intdiv($mes - 1, 12);
            $mes = (($mes - 1) % 12) + 1;

            $emissao    = self::montarData($ano, $mes, $diaEmissao);
            $vencimento = self::montarData($ano, $mes, $diaVencimento);

            if ($diaVencimento < $diaEmissao) {
                $mesV = $mes + 1;
                $anoV = $ano + intdiv($mesV - 1, 12);
                $mesV = (($mesV - 1) % 12) + 1;
                $vencimento = self::montarData($anoV, $mesV, $diaVencimento);
            }

            $doc = self::gerarDoc($prefixo, $emissao);

            $stIns->execute([
                ':system_unit_id'      => $system_unit_id,
                ':codigo'              => $rec['codigo'] ?? null,
                ':nome'                => $rec['nome'],
                ':entidade'            => $rec['entidade'] ?? null,
                ':cgc'                 => $rec['cgc'] ?? null,
                ':tipo'                => $rec['tipo'],
                ':doc'                 => $doc,
                ':emissao'             => $emissao,
                ':vencimento'          => $vencimento,
                ':valor'               => $rec['valor'],
                ':plano_contas'        => $rec['plano_contas'] ?? null,
                ':banco'               => $rec['banco'] ?? null,
                ':forma_pagamento'     => $rec['forma_pagamento'] ?? null,
                ':segmento'            => $rec['segmento'] ?? null,
                ':obs'                 => $rec['obs'] ?? null,
                ':recorrencia_id'      => $recorrenciaId,
                ':recorrencia_parcela' => $numeroParcela,
            ]);
        }
    }

    /* =========================================================
     *  EXCLUIR (soft delete) RECORRÊNCIA
     *  - Marca como ativo=0
     *  - Apaga as contas pendentes vinculadas (não conciliadas/baixadas)
     * =========================================================*/
    public static function deleteRecorrencia(int $id, ?int $usuario_id = null): array
    {
        global $pdo;

        $emTransacao = false;

        try {
            if ($id <= 0) {
                throw new Exception("ID inválido.");
            }

            $stCur = $pdo->prepare("SELECT * FROM financeiro_recorrencia WHERE id = :id LIMIT 1");
            $stCur->execute([':id' => $id]);
            $atual = $stCur->fetch(PDO::FETCH_ASSOC);

            if (!$atual) {
                throw new Exception("Recorrência não encontrada.");
            }

            $system_unit_id = (int)$atual['system_unit_id'];

            $pdo->beginTransaction();
            $emTransacao = true;

            // Apaga apenas as contas pendentes (preserva as já baixadas/conciliadas)
            $stDel = $pdo->prepare("
                DELETE FROM financeiro_conta
                WHERE recorrencia_id = :rid
                  AND (is_conciliated IS NULL OR is_conciliated = 0)
                  AND (baixa_dt IS NULL OR baixa_dt = '0000-00-00' OR baixa_dt = '')
            ");
            $stDel->execute([':rid' => $id]);
            $excluidas = $stDel->rowCount();

            // Inativa o mestre
            $stUpd = $pdo->prepare("
                UPDATE financeiro_recorrencia
                SET ativo = 0, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stUpd->execute([':id' => $id]);

            $pdo->commit();
            $emTransacao = false;

            self::logAuditoria(
                'DELETE',
                'financeiro_recorrencia',
                $id,
                $atual,
                array_merge($atual, ['ativo' => 0]),
                $system_unit_id,
                $usuario_id
            );

            return [
                'success'             => true,
                'message'             => "Recorrência inativada. {$excluidas} contas pendentes foram removidas (as já baixadas/conciliadas foram preservadas).",
                'contas_removidas'    => $excluidas
            ];

        } catch (Exception $e) {
            if ($emTransacao && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Erro ao excluir recorrência: ' . $e->getMessage()
            ];
        }
    }

    /* =========================================================
     *  LISTAR RECORRÊNCIAS
     * =========================================================*/
    public static function listRecorrencias(array $data): array
    {
        global $pdo;

        try {
            $system_unit_id = (int)($data['system_unit_id'] ?? 0);
            if ($system_unit_id <= 0) {
                throw new Exception("system_unit_id é obrigatório.");
            }

            $apenasAtivos = isset($data['apenas_ativos']) ? (int)$data['apenas_ativos'] : 1;

            $sql = "
                SELECT r.*,
                       (SELECT COUNT(*) FROM financeiro_conta c WHERE c.recorrencia_id = r.id) AS total_contas,
                       (SELECT COUNT(*) FROM financeiro_conta c
                          WHERE c.recorrencia_id = r.id
                            AND c.baixa_dt IS NOT NULL
                            AND c.baixa_dt != '0000-00-00'
                            AND c.baixa_dt != '') AS contas_baixadas
                FROM financeiro_recorrencia r
                WHERE r.system_unit_id = :unit
            ";
            $params = [':unit' => $system_unit_id];

            if ($apenasAtivos === 1) {
                $sql .= " AND r.ativo = 1";
            }

            if (!empty($data['tipo'])) {
                $sql .= " AND r.tipo = :tipo";
                $params[':tipo'] = strtoupper($data['tipo']);
            }

            if (!empty($data['busca'])) {
                $sql .= " AND (r.nome LIKE :busca OR r.entidade LIKE :busca OR r.codigo LIKE :busca)";
                $params[':busca'] = '%' . $data['busca'] . '%';
            }

            $sql .= " ORDER BY r.created_at DESC";

            $st = $pdo->prepare($sql);
            $st->execute($params);

            return [
                'success' => true,
                'data'    => $st->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao listar recorrências: ' . $e->getMessage()];
        }
    }

    /* =========================================================
     *  DETALHAR RECORRÊNCIA + parcelas
     * =========================================================*/
    public static function getRecorrencia(int $id): array
    {
        global $pdo;

        try {
            if ($id <= 0) {
                throw new Exception("ID inválido.");
            }

            $stRec = $pdo->prepare("SELECT * FROM financeiro_recorrencia WHERE id = :id LIMIT 1");
            $stRec->execute([':id' => $id]);
            $rec = $stRec->fetch(PDO::FETCH_ASSOC);

            if (!$rec) {
                throw new Exception("Recorrência não encontrada.");
            }

            $stContas = $pdo->prepare("
                SELECT id, recorrencia_parcela, doc, emissao, vencimento, valor,
                       baixa_dt, is_conciliated
                FROM financeiro_conta
                WHERE recorrencia_id = :rid
                ORDER BY recorrencia_parcela ASC
            ");
            $stContas->execute([':rid' => $id]);
            $rec['contas'] = $stContas->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'data' => $rec];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar recorrência: ' . $e->getMessage()];
        }
    }

    /* =========================================================
     *  PRÉ-VISUALIZAÇÃO (não grava no banco)
     *  - Útil para mostrar ao usuário as parcelas que serão criadas
     *    ANTES de confirmar a recorrência.
     * =========================================================*/
    public static function previewParcelas(array $data): array
    {
        try {
            $data = self::validarPayload($data);

            $diaEmissao    = (int)$data['dia_emissao'];
            $diaVencimento = (int)$data['dia_vencimento'];
            $qtd           = (int)$data['qtd_meses'];
            $prefixo       = $data['prefixo_doc'] ?? 'R';

            $ts = strtotime($data['data_inicio']);
            $mesAtual = (int)date('m', $ts);
            $anoAtual = (int)date('Y', $ts);

            $parcelas = [];
            for ($i = 0; $i < $qtd; $i++) {
                $mes = $mesAtual + $i;
                $ano = $anoAtual + intdiv($mes - 1, 12);
                $mes = (($mes - 1) % 12) + 1;

                $emissao    = self::montarData($ano, $mes, $diaEmissao);
                $vencimento = self::montarData($ano, $mes, $diaVencimento);

                if ($diaVencimento < $diaEmissao) {
                    $mesV = $mes + 1;
                    $anoV = $ano + intdiv($mesV - 1, 12);
                    $mesV = (($mesV - 1) % 12) + 1;
                    $vencimento = self::montarData($anoV, $mesV, $diaVencimento);
                }

                $parcelas[] = [
                    'parcela'    => $i + 1,
                    'doc'        => self::gerarDoc($prefixo, $emissao),
                    'emissao'    => $emissao,
                    'vencimento' => $vencimento,
                    'valor'      => (float)$data['valor'],
                ];
            }

            return [
                'success'  => true,
                'total'    => count($parcelas),
                'valor_total' => round((float)$data['valor'] * count($parcelas), 2),
                'parcelas' => $parcelas
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao gerar prévia: ' . $e->getMessage()];
        }
    }
}