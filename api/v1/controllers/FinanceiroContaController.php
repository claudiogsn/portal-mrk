<?php

ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');


require_once __DIR__ . '/../database/db.php';


class FinanceiroContaController {

    public static function importaContaByNota($data) {
        global $pdo;

        try {
            $sql = "INSERT INTO financeiro_conta
            (system_unit_id, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt, valor, plano_contas, banco, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento)
            VALUES
            (:system_unit_id, :nome, :entidade, :cgc, :tipo, :doc, :emissao, :vencimento, NULL, :valor, :plano_contas, NULL, :obs, NULL, NULL, NULL, :adic, :comissao, NULL, NULL, NULL, NULL)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':system_unit_id' => $data['system_unit_id'],
                ':nome'           => $data['nome'],
                ':entidade'       => $data['entidade'],
                ':cgc'            => $data['cgc'],
                ':tipo'           => $data['tipo'],
                ':doc'            => $data['doc'],
                ':emissao'        => $data['emissao'],
                ':vencimento'     => $data['vencimento'],
                ':valor'          => $data['valor'],
                ':plano_contas'   => $data['plano_contas'] ?? null,
                ':obs'            => $data['obs'] ?? null,
                ':adic'           => $data['adic'] ?? 0,
                ':comissao'       => $data['comissao'] ?? 0,
            ]);

            return [
                'success'  => true,
                'message'  => 'Conta importada com sucesso',
                'conta_id' => (int)$pdo->lastInsertId()
            ];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao inserir: '.$e->getMessage()];
        }
    }

    public static function createConta($data) {
        global $pdo;

        $stmt = $pdo->prepare("INSERT INTO financeiro_conta (system_unit_id, codigo, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt, valor, plano_contas, banco, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['system_unit_id'],
            $data['codigo'],
            $data['nome'],
            $data['entidade'],
            $data['cgc'],
            $data['tipo'],
            $data['doc'],
            $data['emissao'],
            $data['vencimento'],
            $data['baixa_dt'],
            $data['valor'],
            $data['plano_contas'],
            $data['banco'],
            $data['obs'],
            $data['inc_ope'],
            $data['bax_ope'],
            $data['comp_dt'],
            $data['adic'],
            $data['comissao'],
            $data['local'],
            $data['cheque'],
            $data['dt_cheque'],
            $data['segmento']
        ]);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Conta criada com sucesso', 'conta_id' => $pdo->lastInsertId());
        } else {
            return array('success' => false, 'message' => 'Falha ao criar conta');
        }
    }

    public static function updateConta($id, $data) {
        global $pdo;

        $sql = "UPDATE financeiro_conta SET ";
        $values = [];
        foreach ($data as $key => $value) {
            $sql .= "$key = :$key, ";
            $values[":$key"] = $value;
        }
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE id = :id";
        $values[':id'] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Conta atualizada com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao atualizar conta');
        }
    }

    public static function getContaById($id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM financeiro_conta WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function deleteConta($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM financeiro_conta WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Conta excluída com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir conta');
        }
    }

    public static function listContas($system_unit_id, $data_inicial, $data_final, $tipoData = 'emissao', $tipo = null) {
        global $pdo;

        // Coluna de filtro de data
        $colunaData = ($tipoData === 'vencimento') ? 'vencimento' : 'emissao';

        $sql = "SELECT *
            FROM financeiro_conta
            WHERE system_unit_id = :system_unit_id
              AND {$colunaData} BETWEEN :data_inicial AND :data_final ORDER BY {$colunaData} ASC";

        // Se o tipo for informado, aplica no WHERE
        if (!empty($tipo)) {
            $sql .= " AND tipo = :tipo";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->bindValue(':data_inicial', $data_inicial);
        $stmt->bindValue(':data_final', $data_final);

        if (!empty($tipo)) {
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

     public static function importarContaApi($system_unit_id) {
        global $pdo;

        try {
            // 1) Pega custom_code do unit
            $stmt = $pdo->prepare("SELECT custom_code AS estabelecimento FROM system_unit WHERE id = :id");
            $stmt->bindParam(':id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                throw new Exception("System Unit ID inválido ou não encontrado.");
            }
            $estabelecimento = $result['estabelecimento'];

            // 2) Tipos a importar
            $tipos = ['d', 'c'];

            // 3) Preparar statements (SELECT/INSERT/UPDATE) e iniciar transação
            $pdo->beginTransaction();

            $selectStmt = $pdo->prepare(
                "SELECT id FROM financeiro_conta WHERE system_unit_id = ? AND codigo = ? LIMIT 1"
            );

            $insertStmt = $pdo->prepare(
                "INSERT INTO financeiro_conta
            (system_unit_id, codigo, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt, valor,
             plano_contas, banco, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento)
             VALUES
            (:system_unit_id, :codigo, :nome, :entidade, :cgc, :tipo, :doc, :emissao, :vencimento, :baixa_dt, :valor,
             :plano_contas, :banco, :obs, :inc_ope, :bax_ope, :comp_dt, :adic, :comissao, :local, :cheque, :dt_cheque, :segmento)"
            );

            $updateStmt = $pdo->prepare(
                "UPDATE financeiro_conta SET
                nome = :nome,
                entidade = :entidade,
                cgc = :cgc,
                tipo = :tipo,
                doc = :doc,
                emissao = :emissao,
                vencimento = :vencimento,
                baixa_dt = :baixa_dt,
                valor = :valor,
                plano_contas = :plano_contas,
                banco = :banco,
                obs = :obs,
                inc_ope = :inc_ope,
                bax_ope = :bax_ope,
                comp_dt = :comp_dt,
                adic = :adic,
                comissao = :comissao,
                local = :local,
                cheque = :cheque,
                dt_cheque = :dt_cheque,
                segmento = :segmento
             WHERE id = :id"
            );

            $totInseridos = 0;
            $totAtualizados = 0;

            foreach ($tipos as $tipo) {
                $contas = FinanceiroApiMenewController::fetchFinanceiroConta($estabelecimento, $tipo);

                if (!$contas['success']) {
                    throw new Exception("Erro ao buscar contas da API para tipo $tipo: " . $contas['message']);
                }

                foreach ($contas['contas'] as $conta) {
                    // Normalizações/Defaults leves
                    $codigo        = $conta['id']; // mantém a mesma semântica usada antes
                    $plano_contas  = isset($conta['plano_contas']) ? ('0' . $conta['plano_contas']) : null;

                    // 4) Existe?
                    $selectStmt->execute([$system_unit_id, $codigo]);
                    $row = $selectStmt->fetch(PDO::FETCH_ASSOC);

                    if ($row) {
                        // UPDATE
                        $updateStmt->execute([
                            ':nome'         => $conta['nome'],
                            ':entidade'     => $conta['entidade'],
                            ':cgc'          => $conta['cgc'] ?? '',
                            ':tipo'         => $conta['tipo'],
                            ':doc'          => $conta['doc'],
                            ':emissao'      => $conta['emissao'],
                            ':vencimento'   => $conta['vencimento'],
                            ':baixa_dt'     => $conta['baixa_dt'] ?? null,
                            ':valor'        => $conta['valor'],
                            ':plano_contas' => $plano_contas,
                            ':banco'        => $conta['banco'] ?? null,
                            ':obs'          => $conta['obs'] ?? null,
                            ':inc_ope'      => $conta['inc_ope'] ?? null,
                            ':bax_ope'      => $conta['bax_ope'] ?? null,
                            ':comp_dt'      => $conta['comp_dt'] ?? null,
                            ':adic'         => $conta['adic'] ?? 0,
                            ':comissao'     => $conta['comissao'] ?? 0,
                            ':local'        => $conta['local'] ?? null,
                            ':cheque'       => $conta['cheque'] ?? null,
                            ':dt_cheque'    => $conta['dt_cheque'] ?? null,
                            ':segmento'     => $conta['segmento'] ?? null,
                            ':id'           => (int)$row['id'],
                        ]);
                        $totAtualizados++;
                    } else {
                        // INSERT
                        $insertStmt->execute([
                            ':system_unit_id' => $system_unit_id,
                            ':codigo'         => $codigo,
                            ':nome'           => $conta['nome'],
                            ':entidade'       => $conta['entidade'],
                            ':cgc'            => $conta['cgc'] ?? '',
                            ':tipo'           => $conta['tipo'],
                            ':doc'            => $conta['doc'],
                            ':emissao'        => $conta['emissao'],
                            ':vencimento'     => $conta['vencimento'],
                            ':baixa_dt'       => $conta['baixa_dt'] ?? null,
                            ':valor'          => $conta['valor'],
                            ':plano_contas'   => $plano_contas,
                            ':banco'          => $conta['banco'] ?? null,
                            ':obs'            => $conta['obs'] ?? null,
                            ':inc_ope'        => $conta['inc_ope'] ?? null,
                            ':bax_ope'        => $conta['bax_ope'] ?? null,
                            ':comp_dt'        => $conta['comp_dt'] ?? null,
                            ':adic'           => $conta['adic'] ?? 0,
                            ':comissao'       => $conta['comissao'] ?? 0,
                            ':local'          => $conta['local'] ?? null,
                            ':cheque'         => $conta['cheque'] ?? null,
                            ':dt_cheque'      => $conta['dt_cheque'] ?? null,
                            ':segmento'       => $conta['segmento'] ?? null,
                        ]);
                        $totInseridos++;
                    }
                }
            }

            $pdo->commit();

            return [
                "success"       => true,
                "message"       => "Contas importadas/atualizadas com sucesso",
                "inseridos"     => $totInseridos,
                "atualizados"   => $totAtualizados
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public static function getDreGerencial($system_unit_id, $data_inicial, $data_final) {
        global $pdo;

        try {
            // 1. Consultar as contas dentro do período
            $stmt = $pdo->prepare("SELECT codigo, plano_contas, valor, emissao FROM financeiro_conta WHERE system_unit_id = :system_unit_id AND emissao BETWEEN :data_inicial AND :data_final");
            $stmt->execute([
                ':system_unit_id' => $system_unit_id,
                ':data_inicial' => $data_inicial,
                ':data_final' => $data_final
            ]);

            $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $categorias = [];



            foreach ($contas as $conta) {
                // 2. Verificar se existe rateio para a conta
                $stmtRateio = $pdo->prepare("SELECT idconta AS codigo, rateio_plano, rateio_valor, emissao FROM financeiro_rateio WHERE system_unit_id = :system_unit_id AND idconta = :conta");
                $stmtRateio->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':conta' => $conta['codigo']
                ]);

                $rateios = $stmtRateio->fetchAll(PDO::FETCH_ASSOC);



                if (!empty($rateios)) {
                    // Substituir a conta pelo rateio
                    foreach ($rateios as $rateio) {
                        $categorias[] = [
                            'plano_contas' => $rateio['rateio_plano'],
                            'valor' => $rateio['rateio_valor'],
                            'emissao' => $rateio['emissao']
                        ];
                    }
                } else {
                    // Adicionar a conta original se não houver rateio
                    $categorias[] = [
                        'plano_contas' => $conta['plano_contas'],
                        'valor' => $conta['valor'],
                        'emissao' => $conta['emissao']
                    ];
                }
            }

            // 3. Somar valores por plano com base em LIKE
            $stmtPlano = $pdo->prepare("SELECT codigo, descricao FROM financeiro_plano WHERE system_unit_id = :system_unit_id and descricao not like '%INATIVO%'");
            $stmtPlano->execute([':system_unit_id' => $system_unit_id]);
            $planos = $stmtPlano->fetchAll(PDO::FETCH_ASSOC);

            $somaCategorias = [];

            foreach ($planos as $plano) {
                $codigoPlano = $plano['codigo'];
                $descricaoPlano = $plano['descricao'];

                // Inicializar valores mensais para o plano atual
                $valoresMensais = array_fill(1, 12, 0);

                foreach ($categorias as $categoria) {
                    if (strpos($categoria['plano_contas'], $codigoPlano) === 0) {
                        $mesIndex = (int)date('m', strtotime($categoria['emissao']));
                        $valoresMensais[$mesIndex] += $categoria['valor'];
                    }
                }

                $somaCategorias[$codigoPlano] = [
                    'descricao' => $descricaoPlano,
                    'mensal' => $valoresMensais
                ];
            }


            // 4. Formatar o retorno final
            $resultadoFinal = [
                'title' => 'DRE Gerencial',
                'period' => [
                    'start' => $data_inicial,
                    'end' => $data_final,
                    'view' => 'Mensal',
                    'regime' => 'Competência'
                ],
                'categories' => []
            ];

            foreach ($somaCategorias as $codigo => $dados) {
                $resultadoFinal['categories'][] = [
                    'name' => $dados['descricao'],
                    'code' => $codigo,
                    'monthly_values' => array_values($dados['mensal'])
                ];
            }

            return [
                'success' => true,
                'data' => $resultadoFinal
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public static function getContaByMonth($system_unit_id, $month, $year, $plano_contas) {
        global $pdo;

        try {
            $data_inicial = date("$year-$month-01");
            $data_final = date("Y-m-t", strtotime($data_inicial));

            // Consulta para obter as contas
            $stmtContas = $pdo->prepare(
                "SELECT 
                codigo, 
                nome, 
                entidade, 
                cgc, 
                tipo, 
                doc, 
                emissao, 
                vencimento, 
                baixa_dt, 
                valor, 
                plano_contas
            FROM financeiro_conta
            WHERE system_unit_id = :system_unit_id 
                AND codigo not in (select idconta from financeiro_rateio r where r.system_unit_id = :system_unit_id  AND rateio_plano LIKE :plano_contas AND r.emissao BETWEEN :data_inicial AND :data_final  )
                AND emissao BETWEEN :data_inicial AND :data_final
                AND plano_contas LIKE :plano_contas"
            );

            $stmtContas->execute([
                ':system_unit_id' => $system_unit_id,
                ':data_inicial' => $data_inicial,
                ':data_final' => $data_final,
                ':plano_contas' => $plano_contas . '%'
            ]);

            $contas = $stmtContas->fetchAll(PDO::FETCH_ASSOC);

            // Consulta para obter os rateios
            $stmtRateios = $pdo->prepare(
                "SELECT 
                r.idconta AS codigo, 
                r.nome, 
                r.entidade, 
                r.cgc, 
                r.tipo, 
                c.doc, 
                r.emissao, 
                r.vencimento, 
                r.baixa_dt, 
                r.rateio_valor AS valor, 
                r.rateio_plano AS plano_contas
            FROM financeiro_rateio r
            INNER JOIN financeiro_conta c ON r.idconta = c.codigo AND r.system_unit_id = c.system_unit_id
            WHERE r.system_unit_id = :system_unit_id 
                AND r.emissao BETWEEN :data_inicial AND :data_final
                AND r.rateio_plano LIKE :plano_contas"
            );

            $stmtRateios->execute([
                ':system_unit_id' => $system_unit_id,
                ':data_inicial' => $data_inicial,
                ':data_final' => $data_final,
                ':plano_contas' => $plano_contas . '%'
            ]);

            $rateios = $stmtRateios->fetchAll(PDO::FETCH_ASSOC);

            // Adicionando campo "origem" para distinguir os dados
            $contas = array_map(function ($conta) {
                $conta['origem'] = 'conta';
                return $conta;
            }, $contas);

            $rateios = array_map(function ($rateio) {
                $rateio['origem'] = 'rateio';
                return $rateio;
            }, $rateios);

            // Combina as duas listas
            $resultado = array_merge($contas, $rateios);

            return [
                'success' => true,
                'data' => $resultado
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public static function lancarNotaNoFinanceiroConta(array $data): array
    {
        global $pdo;

        $gotLock = false; // visível no finally

        try {
            // ===== Helpers =====
            $parseDate = function (?string $s) {
                if (!$s) return null;
                $s = trim($s);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;           // YYYY-MM-DD
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {                  // DD/MM/YYYY
                    [$d,$m,$y] = explode('/',$s);
                    return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                }
                throw new Exception("Data inválida: {$s}");
            };

            $parseMoney = function ($v) {
                if ($v === null || $v === '') return 0.0;
                if (is_string($v)) {
                    $v = str_replace(['.', ' '], ['', ''], $v);
                    $v = str_replace(',', '.', $v);
                }
                if (!is_numeric($v)) throw new Exception("Valor numérico inválido: {$v}");
                return round((float)$v, 2);
            };

            // ===== Validação obrigatória =====
            $required = ['system_unit_id','fornecedor_id','documento','emissao','vencimento','valor'];
            foreach ($required as $k) {
                if (!isset($data[$k]) || $data[$k] === '' || $data[$k] === null) {
                    throw new Exception("Campo obrigatório ausente: {$k}");
                }
            }

            $system_unit_id  = (int)$data['system_unit_id'];
            $entidade        = (int)$data['fornecedor_id'];
            $doc             = trim((string)$data['documento']);
            $emissao         = $parseDate($data['emissao']);
            $vencimento      = $parseDate($data['vencimento']);
            $valorBruto      = $parseMoney($data['valor']);
            $adicional       = isset($data['adicional']) ? $parseMoney($data['adicional']) : 0.0;
            $desconto        = isset($data['desconto'])  ? $parseMoney($data['desconto'])  : 0.0;
            $planoContas     = isset($data['plano_contas']) ? trim((string)$data['plano_contas']) : null;
            $formaPgtoId     = isset($data['forma_pagamento_id']) ? (int)$data['forma_pagamento_id'] : null; // mapeado p/ "banco"
            $chaveAcesso     = isset($data['chave_acesso']) ? trim((string)$data['chave_acesso']) : null;

            // ===== Busca fornecedor (cgc e nome) — NÃO pode faltar =====
            $stmtF = $pdo->prepare("
            SELECT cnpj_cpf, nome
            FROM financeiro_fornecedor
            WHERE id = :id AND system_unit_id = :unit
            LIMIT 1
        ");
            $stmtF->execute([':id'=>$entidade, ':unit'=>$system_unit_id]);
            $forn = $stmtF->fetch(PDO::FETCH_ASSOC);
            if (!$forn) {
                throw new Exception("Fornecedor não encontrado para a unidade informada.");
            }

            $cgc  = !empty($forn['cnpj_cpf']) ? $forn['cnpj_cpf'] : '';
            $nome = !empty($forn['nome'])     ? $forn['nome']     : '';
            if ($nome === '') {
                $nome = "NF {$doc} – Fornecedor {$entidade}";
            }

            // Valor final gravado
            $valorFinal = round($valorBruto + $adicional - $desconto, 2);
            if ($valorFinal < 0) throw new Exception("Valor final não pode ser negativo.");

            // Observação (breakdown)
            $obsPartes = [];
            $obsPartes[] = "NF {$doc}";
            $obsPartes[] = "Bruto: " . number_format($valorBruto, 2, ',', '.');
            $obsPartes[] = "Adic: "  . number_format($adicional, 2, ',', '.');
            $obsPartes[] = "Desc: "  . number_format($desconto, 2, ',', '.');
            $obsPartes[] = "Final: " . number_format($valorFinal, 2, ',', '.');
            if ($planoContas) $obsPartes[] = "Plano: {$planoContas}";
            if (!empty($data['obs_extra'])) $obsPartes[] = trim((string)$data['obs_extra']);
            if ($chaveAcesso && strpos(implode(' ', $obsPartes), 'Chave NFe:') === false) {
                $obsPartes[] = "Chave NFe: {$chaveAcesso}";
            }
            $obsPartes[] = "[ORIGEM: LOCAL]";
            $obs = implode(' | ', $obsPartes);

            // ===== Transação =====
            $pdo->beginTransaction();

            // Evita duplicidade (system_unit_id + entidade + doc)
            $chk = $pdo->prepare("
            SELECT id FROM financeiro_conta 
            WHERE system_unit_id = :unit AND entidade = :ent AND doc = :doc 
            LIMIT 1
        ");
            $chk->execute([':unit'=>$system_unit_id, ':ent'=>$entidade, ':doc'=>$doc]);
            if ($chk->fetchColumn()) {
                $pdo->rollBack();
                return ['success'=>false, 'error'=>'Já existe lançamento para este fornecedor/documento nesta unidade.'];
            }

            // ===== Lock lógico para geração do código (evita corrida) =====
            $lockName = 'financeiro_conta_codigo_local_lock';
            $stLock = $pdo->prepare("SELECT GET_LOCK(:name, 5)");
            $stLock->execute([':name'=>$lockName]);
            $gotLock = ((int)$stLock->fetchColumn() === 1);

            // ===== Gera CODIGO "de zero para baixo" =====
            $stMin = $pdo->query("SELECT MIN(codigo) FROM financeiro_conta WHERE codigo <= 0");
            $minCodigo = $stMin->fetchColumn();
            if ($minCodigo !== null) {
                $nextCodigo = ((int)$minCodigo) - 1;  // 0 -> -1 -> -2 -> ...
            } else {
                $nextCodigo = 0;                      // primeiro local
            }

            // ===== Insert =====
            $stmt = $pdo->prepare("
            INSERT INTO financeiro_conta
            (system_unit_id, codigo, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt,
             valor, plano_contas, banco, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento)
            VALUES
            (:system_unit_id, :codigo, :nome, :entidade, :cgc, :tipo, :doc, :emissao, :vencimento, NULL,
             :valor, :plano_contas, :banco, :obs, NULL, NULL, NULL, :adic, 0.00, NULL, NULL, NULL, NULL)
        ");
            $stmt->execute([
                ':system_unit_id' => $system_unit_id,
                ':codigo'         => $nextCodigo,   // 0, -1, -2, ...
                ':nome'           => $nome,
                ':entidade'       => $entidade,
                ':cgc'            => $cgc,
                ':tipo'           => 'd',           // a pagar (default)
                ':doc'            => $doc,
                ':emissao'        => $emissao,
                ':vencimento'     => $vencimento,
                ':valor'          => $valorFinal,   // <-- valor FINAL
                ':plano_contas'   => $planoContas,
                ':banco'          => $formaPgtoId,
                ':obs'            => $obs,
                ':adic'           => $adicional
            ]);

            $id = (int)$pdo->lastInsertId();

            // ===== UPDATE da nota: incluida_financeiro = 1 (por system_unit_id + chave_acesso) =====
            // Obs.: ajuste o nome da coluna se o seu schema usar outro (ex.: 'incluida_fin')
            $notaAtualizada = null;
            if ($chaveAcesso) {
                $stU = $pdo->prepare("
                UPDATE estoque_nota
                SET incluida_financeiro = 1, updated_at = CURRENT_TIMESTAMP
                WHERE system_unit_id = :unit AND chave_acesso = :chave
                LIMIT 1
            ");
                $stU->execute([':unit' => $system_unit_id, ':chave' => $chaveAcesso]);
                $notaAtualizada = ($stU->rowCount() > 0);
            } else {
                $notaAtualizada = false; // sem chave, não conseguimos marcar
            }

            $pdo->commit();

            return [
                'success'         => true,
                'id'              => $id,
                'codigo'          => $nextCodigo,
                'nota_atualizada' => (bool)$notaAtualizada
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success'=>false, 'error'=>$e->getMessage()];
        } finally {
            // Libera o lock mesmo em erro
            if (!empty($gotLock)) {
                try { $pdo->query("SELECT RELEASE_LOCK('financeiro_conta_codigo_local_lock')"); } catch (\Throwable $t) {}
            }
        }
    }







}
