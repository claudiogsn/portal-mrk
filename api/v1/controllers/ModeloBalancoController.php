<?php

require_once __DIR__ . '/../database/db.php'; // Ajustando o caminho para o arquivo db.php

class ModeloBalancoController {

    private static function sendResponse($success, $message, $data = [], $httpCode = 200) {
        http_response_code($httpCode);
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
        exit;
    }

    // Cria um novo modelo de balanço junto com seus itens
    public static function createModelo($data) {
        global $pdo;

        try {
            // Inicia a transação
            $pdo->beginTransaction();

            // Verificar campos obrigatórios
            $requiredFields = ['nome', 'usuario_id', 'system_unit_id', 'itens'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    self::sendResponse(false, "O campo '$field' é obrigatório.", [], 400); // 400 Bad Request
                }
            }

            // Extrair dados
            $nome = $data['nome'];
            $usuario_id = $data['usuario_id'];
            $system_unit_id = $data['system_unit_id'];
            $ativo = isset($data['ativo']) ? $data['ativo'] : 1; // Padrão: ativo
            $tag = isset($data['tag']) ? $data['tag'] : null;
            $itens = $data['itens']; // Array contendo códigos dos produtos

            // Validar se existem itens
            if (!is_array($itens) || count($itens) == 0) {
                self::sendResponse(false, "É necessário incluir ao menos um item (produto) no modelo.", [], 400);
            }

            // Inserir o modelo de balanço
            $stmt = $pdo->prepare("INSERT INTO modelos_balanco (nome, usuario_id, system_unit_id, ativo, tag, created_at, updated_at) 
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$nome, $usuario_id, $system_unit_id, $ativo, $tag]);

            if ($stmt->rowCount() <= 0) {
                self::sendResponse(false, "Falha ao criar o modelo de balanço.", [], 500); // 500 Internal Server Error
            }

            // Obter o ID do novo modelo
            $modelo_id = $pdo->lastInsertId();

            // Inserir os itens associados ao modelo
            $stmtItem = $pdo->prepare("INSERT INTO modelos_balanco_itens (id_modelo, system_unit_id, id_produto, created_at, updated_at) 
                                   SELECT ?, ?, id, NOW(), NOW() FROM products WHERE codigo = ? AND system_unit_id = ?");
            foreach ($itens as $item) {
                if (!isset($item['codigo'])) {
                    self::sendResponse(false, "O campo 'codigo' é obrigatório para cada item.", [], 400);
                }
                $codigo_produto = $item['codigo'];
                $stmtItem->execute([$modelo_id, $system_unit_id, $codigo_produto, $system_unit_id]);

                if ($stmtItem->rowCount() <= 0) {
                    self::sendResponse(false, "Falha ao inserir item no modelo de balanço.", [], 500);
                }
            }

            // Commit da transação
            $pdo->commit();

            self::sendResponse(true, 'Modelo de balanço criado com sucesso.', ['modelo_id' => $modelo_id], 201); // 201 Created

        } catch (Exception $e) {
            // Rollback em caso de erro
            $pdo->rollBack();

            self::sendResponse(false, 'Erro ao criar modelo de balanço: ' . $e->getMessage(), [], 500);
        }
    }



    // Listar todos os modelos de balanço
    public static function listModelos($ativo = null) {
        global $pdo;

        try {
            $sql = "SELECT * FROM modelos_balanco";
            if ($ativo !== null) {
                $sql .= " WHERE ativo = :ativo";
            }

            $stmt = $pdo->prepare($sql);
            if ($ativo !== null) {
                $stmt->bindParam(':ativo', $ativo, PDO::PARAM_INT);
            }
            $stmt->execute();
            $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            self::sendResponse(true, 'Modelos listados com sucesso.', ['modelos' => $modelos], 200); // 200 OK

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao listar modelos: ' . $e->getMessage(), [], 500);
        }
    }

    // Listar itens de um modelo de balanço
    public static function listItensByModelo($modelo_id, $system_unit_id) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT mbi.*, p.nome AS nome_produto
                FROM modelos_balanco_itens mbi
                LEFT JOIN products p ON mbi.id_produto = p.id and mbi.system_unit_id = p.system_unit_id
                WHERE mbi.id_modelo = :modelo_id
                AND mbi.system_unit_id = :system_unit_id
            ");
            $stmt->bindParam(':modelo_id', $modelo_id, PDO::PARAM_INT);
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($itens) {
                self::sendResponse(true, 'Itens listados com sucesso.', ['itens' => $itens], 200);
            } else {
                self::sendResponse(false, 'Nenhum item encontrado para este modelo.', [], 404); // 404 Not Found
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao listar itens: ' . $e->getMessage(), [], 500);
        }
    }

    // Atualizar um modelo de balanço
    public static function updateModelo($id, $data) {
        global $pdo;

        try {
            // Verificar se o modelo existe
            $stmt = $pdo->prepare("SELECT * FROM modelos_balanco WHERE id = ?");
            $stmt->execute([$id]);
            $modelo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$modelo) {
                self::sendResponse(false, 'Modelo de balanço não encontrado.', [], 404); // 404 Not Found
            }

            // Atualizar o modelo
            $sql = "UPDATE modelos_balanco SET ";
            $values = [];
            foreach ($data as $key => $value) {
                $sql .= "$key = :$key, ";
                $values[":$key"] = $value;
            }
            $sql = rtrim($sql, ", ");
            $sql .= " WHERE id = :id";
            $values[':id'] = $id;

            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute($values);

            if ($stmtUpdate->rowCount() > 0) {
                self::sendResponse(true, 'Modelo de balanço atualizado com sucesso.', [], 200);
            } else {
                self::sendResponse(false, 'Nenhuma alteração foi realizada.', [], 304); // 304 Not Modified
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao atualizar modelo: ' . $e->getMessage(), [], 500);
        }
    }

    // Deletar modelo de balanço
    public static function deleteModelo($id) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("DELETE FROM modelos_balanco WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                self::sendResponse(true, 'Modelo de balanço excluído com sucesso.', [], 200);
            } else {
                self::sendResponse(false, 'Falha ao excluir o modelo de balanço.', [], 404);
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao excluir modelo: ' . $e->getMessage(), [], 500);
        }
    }

    // Deletar item de um modelo de balanço
    public static function deleteItemFromModelo($modelo_id, $produto_id) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("DELETE FROM modelos_balanco_itens WHERE id_modelo = ? AND id_produto = ?");
            $stmt->execute([$modelo_id, $produto_id]);

            if ($stmt->rowCount() > 0) {
                self::sendResponse(true, 'Item removido do modelo de balanço com sucesso.', [], 200);
            } else {
                self::sendResponse(false, 'Falha ao remover o item do modelo de balanço.', [], 404);
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao remover item: ' . $e->getMessage(), [], 500);
        }
    }

    public static function validateTagExists($tag) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM modelos_balanco WHERE tag = :tag");
            $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
            $stmt->execute();

            $count = $stmt->fetchColumn();

            if ($count > 0) {
                // Retorna true indicando que a tag já existe
                self::sendResponse(false, "A tag já existe.", [], 200);
            } else {
                // Retorna false indicando que a tag não existe
                self::sendResponse(true, "A tag não existe.", [], 200);
            }

        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao validar a tag: ' . $e->getMessage(), [], 500);
        }
    }


    public static function getModelByTag($tag) {
        global $pdo;
    
        try {
            // Extrair system_unit_id e tag do valor recebido
            if (strpos($tag, '-') === false) {
                self::sendResponse(false, "Formato de tag inválido.", [], 400);
            }
    
            list($system_unit_id, $actual_tag) = explode('-', $tag, 2);

           // print_r($system_unit_id);
            //print_r($actual_tag);
            //exit;
    
            // Buscar o modelo pelo system_unit_id e tag
            $stmt = $pdo->prepare("SELECT * FROM modelos_balanco WHERE system_unit_id = :system_unit_id AND tag = :tag");
            $stmt->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
            $stmt->execute();
    
            $modelo = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$modelo) {
                self::sendResponse(false, 'Modelo de balanço não encontrado.', [], 404);
            }
    
            // Buscar itens associados ao modelo
            $stmtItens = $pdo->prepare("
                SELECT mbi.*, p.nome AS nome_produto,p.und as und_produto, p.codigo as codigo_produto
                FROM modelos_balanco_itens mbi
                LEFT JOIN products p ON mbi.id_produto = p.id AND mbi.system_unit_id = p.system_unit_id
                WHERE mbi.id_modelo = :modelo_id AND mbi.system_unit_id = :system_unit_id
            ");
            $stmtItens->bindParam(':modelo_id', $modelo['id'], PDO::PARAM_INT);
            $stmtItens->bindParam(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmtItens->execute();
    
            $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
    
            // Retornar as informações do modelo e seus itens
            self::sendResponse(true, 'Modelo de balanço encontrado.', [
                'modelo' => $modelo,
                'itens' => $itens
            ], 200);
    
        } catch (Exception $e) {
            self::sendResponse(false, 'Erro ao buscar modelo por tag: ' . $e->getMessage(), [], 500);
        }
    }
    

}
?>
