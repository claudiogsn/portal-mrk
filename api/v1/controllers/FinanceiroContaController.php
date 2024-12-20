<?php

ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');


require_once __DIR__ . '/../database/db.php';


class FinanceiroContaController {
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

    public static function listContas() {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM financeiro_conta");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function importarContaApi($system_unit_id) {
        global $pdo;

        try {
            // Obtém o custom_code a partir do system_unit_id
            $stmt = $pdo->prepare("SELECT custom_code AS estabelecimento FROM system_unit WHERE id = :id");
            $stmt->bindParam(':id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                throw new Exception("System Unit ID inválido ou não encontrado.");
            }

            $estabelecimento = $result['estabelecimento'];

            // Chama o método da API para buscar as contas (Débito e Crédito)
            $tipos = ['d', 'c'];
            $contasImportadas = [];

            foreach ($tipos as $tipo) {
                $contas = FinanceiroApiMenewController::fetchFinanceiroConta($estabelecimento, $tipo);

                if (!$contas['success']) {
                    throw new Exception("Erro ao buscar contas da API para tipo $tipo: " . $contas['message']);
                }

                foreach ($contas['contas'] as $conta) {
                    $stmtInsert = $pdo->prepare(
                        "INSERT INTO financeiro_conta (system_unit_id, codigo, nome, entidade, cgc, tipo, doc, emissao, vencimento, baixa_dt, valor, plano_contas, banco, obs, inc_ope, bax_ope, comp_dt, adic, comissao, local, cheque, dt_cheque, segmento) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    nome = VALUES(nome), 
                    entidade = VALUES(entidade), 
                    cgc = VALUES(cgc), 
                    tipo = VALUES(tipo), 
                    doc = VALUES(doc), 
                    emissao = VALUES(emissao), 
                    vencimento = VALUES(vencimento), 
                    baixa_dt = VALUES(baixa_dt), 
                    valor = VALUES(valor), 
                    plano_contas = VALUES(plano_contas), 
                    banco = VALUES(banco), 
                    obs = VALUES(obs), 
                    inc_ope = VALUES(inc_ope), 
                    bax_ope = VALUES(bax_ope), 
                    comp_dt = VALUES(comp_dt), 
                    adic = VALUES(adic), 
                    comissao = VALUES(comissao), 
                    local = VALUES(local), 
                    cheque = VALUES(cheque), 
                    dt_cheque = VALUES(dt_cheque), 
                    segmento = VALUES(segmento)"
                    );

                    $plano_contas = '0'.$conta['plano_contas'];

                    $stmtInsert->execute([
                        $system_unit_id,
                        $conta['id'],
                        $conta['nome'],
                        $conta['entidade'],
                        $conta['cgc'] ?? '',
                        $conta['tipo'],
                        $conta['doc'],
                        $conta['emissao'],
                        $conta['vencimento'],
                        $conta['baixa_dt'],
                        $conta['valor'],
                        $plano_contas,
                        $conta['banco'],
                        $conta['obs'],
                        $conta['inc_ope'],
                        $conta['bax_ope'],
                        $conta['comp_dt'],
                        $conta['adic'],
                        $conta['comissao'],
                        $conta['local'],
                        $conta['cheque'],
                        $conta['dt_cheque'],
                        $conta['segmento']
                    ]);
                }
            }

            return ["success" => true, "message" => "Contas importadas ou atualizadas com sucesso"];
        } catch (Exception $e) {
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
                $stmtRateio = $pdo->prepare("SELECT idconta AS codigo, plano_contas, valor, emissao FROM financeiro_rateio WHERE system_unit_id = :system_unit_id AND idconta = :conta");
                $stmtRateio->execute([
                    ':system_unit_id' => $system_unit_id,
                    ':conta' => $conta['codigo']
                ]);

                $rateios = $stmtRateio->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rateios)) {
                    // Substituir a conta pelo rateio
                    foreach ($rateios as $rateio) {
                        $categorias[] = [
                            'plano_contas' => $rateio['plano_contas'],
                            'valor' => $rateio['valor'],
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

            // 3. Somar valores por categoria e por mês
            $somaCategorias = [];

            foreach ($categorias as $categoria) {
                $plano = $categoria['plano_contas'];
                $mes = date('Y-m', strtotime($categoria['emissao']));

                if (!isset($somaCategorias[$plano])) {
                    $somaCategorias[$plano] = array_fill_keys(range(1, 12), 0);
                }

                $mesIndex = (int)date('m', strtotime($categoria['emissao']));
                $somaCategorias[$plano][$mesIndex] += $categoria['valor'];
            }

            // 4. Adicionar descrições das categorias
            $stmtPlano = $pdo->prepare("SELECT codigo, descricao FROM financeiro_plano WHERE system_unit_id = :system_unit_id");
            $stmtPlano->execute([':system_unit_id' => $system_unit_id]);

            $planos = $stmtPlano->fetchAll(PDO::FETCH_ASSOC);

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

            foreach ($planos as $plano) {
                $codigo = $plano['codigo'];
                $descricao = $plano['descricao'];
                $monthlyValues = isset($somaCategorias[$codigo]) ? array_values($somaCategorias[$codigo]) : array_fill(1, 12, 0);

                $resultadoFinal['categories'][] = [
                    'name' => $descricao,
                    'code' => $codigo,
                    'monthly_values' => $monthlyValues
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


}
