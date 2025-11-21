<?php

require_once __DIR__ . '/../database/db.php';

class FinanceiroFornecedorController {
    public static function createFornecedor($data) {
        global $pdo;

        $system_unit_id = $data['system_unit_id'] ?? null;
        $cnpjCpf        = $data['cnpj_cpf'] ?? null;

        if (!$system_unit_id) {
            return [
                'success' => false,
                'message' => 'system_unit_id é obrigatório'
            ];
        }

        if (!$cnpjCpf) {
            return [
                'success' => false,
                'message' => 'CNPJ/CPF do fornecedor é obrigatório'
            ];
        }

        // Verifica se já existe
        $stmt = $pdo->prepare("
        SELECT id 
        FROM financeiro_fornecedor 
        WHERE system_unit_id = ? 
          AND cnpj_cpf = ?
        LIMIT 1
    ");
        $stmt->execute([$system_unit_id, $cnpjCpf]);
        $fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fornecedor) {
            return [
                'success' => true,
                'message' => 'Fornecedor já existe',
                'fornecedor_id' => $fornecedor['id']
            ];
        }

        // Cria novo fornecedor
        $stmt = $pdo->prepare("
        INSERT INTO financeiro_fornecedor 
        (system_unit_id, codigo, razao, nome, cnpj_cpf, plano_contas, endereco, cep, insc_estadual, fone)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

        $stmt->execute([
            $system_unit_id,
            $data['codigo'] ?? '',
            $data['razao'] ?? '',
            $data['nome'] ?? '',
            $cnpjCpf,
            $data['plano_contas'] ?? null,
            $data['endereco'] ?? null,
            $data['cep'] ?? null,
            $data['insc_estadual'] ?? null,
            $data['fone'] ?? null
        ]);

        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Fornecedor criado com sucesso',
                'fornecedor_id' => $pdo->lastInsertId()
            ];
        }

        return [
            'success' => false,
            'message' => 'Falha ao criar fornecedor'
        ];
    }

    public static function updateFornecedor($data) {
        global $pdo;

        $id = $data['id'] ?? null;
        if (!$id) {
            return [
                'success' => false,
                'message' => 'ID do fornecedor é obrigatório'
            ];
        }

        // Confirma existência
        $stmtCheck = $pdo->prepare("SELECT id, system_unit_id, cnpj_cpf FROM financeiro_fornecedor WHERE id = ? LIMIT 1");
        $stmtCheck->execute([$id]);
        $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$existente) {
            return [
                'success' => false,
                'message' => 'Fornecedor não encontrado'
            ];
        }

        // Campos que NÃO podem ser alterados
        unset($data['cnpj_cpf'], $data['system_unit_id'], $data['id']);

        // Opcional: use system_unit_id apenas para garantir "ownership"
        $system_unit_id = $data['system_unit_id_where'] ?? null;
        // (se você manda system_unit_id no payload normal, pode setar antes do unset e usar aqui)

        // Allowlist de campos permitidos (evita alguém mandar campo indevido)
        $permitidos = [
            'codigo',
            'razao',
            'nome',
            'plano_contas',
            'endereco',
            'cep',
            'insc_estadual',
            'fone'
        ];

        $sets = [];
        $values = [':id' => $id];

        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $data)) {
                $sets[] = "{$campo} = :{$campo}";
                $values[":{$campo}"] = $data[$campo];
            }
        }

        if (empty($sets)) {
            return [
                'success' => false,
                'message' => 'Nenhum campo válido para atualizar'
            ];
        }

        $sql = "UPDATE financeiro_fornecedor SET " . implode(', ', $sets) . " WHERE id = :id";

        // se quiser travar por unidade também:
        // if ($system_unit_id) {
        //     $sql .= " AND system_unit_id = :system_unit_id";
        //     $values[':system_unit_id'] = $system_unit_id;
        // }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Fornecedor atualizado com sucesso'];
        }

        return ['success' => true, 'message' => 'Nenhuma alteração aplicada (dados iguais)'];
    }


    public static function getFornecedorById($id, $system_unit_id) {
        global $pdo;

        $sql = "SELECT *
            FROM financeiro_fornecedor
            WHERE system_unit_id = :system_unit_id
              AND id = :id
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->bindValue(':system_unit_id', (int)$system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public static function deleteFornecedor($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM financeiro_fornecedor WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Fornecedor excluído com sucesso');
        } else {
            return array('success' => false, 'message' => 'Falha ao excluir fornecedor');
        }
    }

    public static function listFornecedores($system_unit_id) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM financeiro_fornecedor WHERE system_unit_id = :system_unit_id");
        $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function importarFornecedoresApi($system_unit_id) {
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

            // Chama o método da API para buscar os fornecedores
            $fornecedores = FinanceiroApiMenewController::fetchFinanceiroFornecedor($estabelecimento);

            if (!$fornecedores['success']) {
                throw new Exception("Erro ao buscar fornecedores da API: " . $fornecedores['message']);
            }

            foreach ($fornecedores['fornecedores'] as $fornecedor) {
                $stmt = $pdo->prepare("INSERT INTO financeiro_fornecedor (system_unit_id, codigo, razao, nome, cnpj_cpf, plano_contas, endereco, cep, insc_estadual, fone) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                                        ON DUPLICATE KEY UPDATE 
                                            razao = VALUES(razao), 
                                            nome = VALUES(nome), 
                                            cnpj_cpf = VALUES(cnpj_cpf), 
                                            plano_contas = VALUES(plano_contas),
                                            endereco = VALUES(endereco),
                                            cep = VALUES(cep),
                                            insc_estadual = VALUES(insc_estadual),
                                            fone = VALUES(fone)");

                $stmt->execute([
                    $system_unit_id,
                    $fornecedor['codigo'],
                    $fornecedor['razao'] ?? '',
                    $fornecedor['nome'] ?? '',
                    $fornecedor['cnpj_cpf'] ?? '',
                    $fornecedor['plano_contas'] ?? '',
                    $fornecedor['endereco'] ?? '',
                    $fornecedor['cep'] ?? '',
                    $fornecedor['insc_estadual'] ?? '',
                    $fornecedor['fone'] ?? ''
                ]);
            }

            return ["success" => true, "message" => "Fornecedores importados com sucesso"];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public static function listItensFornecedor($system_unit_id, $fornecedor_id) {
        global $pdo;

        if (!$system_unit_id || !$fornecedor_id) {
            return [
                'success' => false,
                'message' => 'system_unit_id e fornecedor_id são obrigatórios'
            ];
        }

        try {
            $sql = "
            SELECT
                i.id,
                i.fornecedor_id,
                i.produto_codigo,
                p.nome AS nome_produto,
                i.codigo_nota,
                i.descricao_nota,
                i.unidade_nota,
                i.fator_conversao,
                i.unidade_item,
                i.created_at
            FROM item_fornecedor i
            LEFT JOIN products p
                   ON p.codigo = i.produto_codigo
                  AND p.system_unit_id = i.system_unit_id
            WHERE i.system_unit_id = :system_unit_id
              AND i.fornecedor_id = :fornecedor_id
            ORDER BY 
                COALESCE(p.nome, i.descricao_nota) ASC,
                i.produto_codigo ASC
        ";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindValue(':fornecedor_id', $fornecedor_id, PDO::PARAM_INT);
            $stmt->execute();

            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $itens
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao listar itens do fornecedor: ' . $e->getMessage()
            ];
        }
    }

}
