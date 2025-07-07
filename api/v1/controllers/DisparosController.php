<?php

require_once __DIR__ . '/../database/db.php';

class DisparosController
{
    // ===================== CONTATOS =====================

    public static function salvarContato($data)
    {
        global $pdo;

        $sql = "
            INSERT INTO contatos_disparos (nome, telefone, ativo)
            VALUES (:nome, :telefone, :ativo)
            ON DUPLICATE KEY UPDATE 
                nome = VALUES(nome),
                ativo = VALUES(ativo),
                updated_at = CURRENT_TIMESTAMP
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $data['nome'],
            ':telefone' => $data['telefone'],
            ':ativo' => $data['ativo'] ?? 1
        ]);

        return ['success' => true, 'message' => 'Contato salvo com sucesso'];
    }

    public static function toggleContatoAtivo($id)
    {
        global $pdo;

        $contato = self::getContatoById($id);
        if (!$contato) return ['success' => false, 'message' => 'Contato não encontrado'];

        $novoStatus = $contato['ativo'] ? 0 : 1;

        $stmt = $pdo->prepare("UPDATE contatos_disparos SET ativo = :ativo WHERE id = :id");
        $stmt->execute([':ativo' => $novoStatus, ':id' => $id]);

        return ['success' => true, 'message' => 'Status do contato atualizado', 'ativo' => $novoStatus];
    }

    public static function getContatoById($id)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM contatos_disparos WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getContato($telefone)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM contatos_disparos WHERE telefone = :telefone");
        $stmt->execute([':telefone' => $telefone]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function listContatos()
    {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM contatos_disparos ORDER BY nome ASC");
        return ['success' => true, 'contatos' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    // ===================== RELACIONAMENTOS =====================

    public static function salvarRelacionamentosPorContato($relacionamentos, $usuario_id)
    {
        global $pdo;

        if (empty($relacionamentos)) {
            return ['success' => false, 'message' => 'Nenhum relacionamento enviado'];
        }

        $id_contato = $relacionamentos[0]['id_contato'];

        // Buscar os antigos para log
        $stmt = $pdo->prepare("SELECT * FROM disparos_contatos_rel WHERE id_contato = :id_contato");
        $stmt->execute([':id_contato' => $id_contato]);
        $relAntigos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Apagar os antigos
        $stmtDel = $pdo->prepare("DELETE FROM disparos_contatos_rel WHERE id_contato = :id_contato");
        $stmtDel->execute([':id_contato' => $id_contato]);

        // Log de remoção
        foreach ($relAntigos as $rel) {
            self::logRelacionamento($rel['id'], $usuario_id, $rel, null);
        }

        // Inserir os novos
        $stmtInsert = $pdo->prepare("
        INSERT INTO disparos_contatos_rel (id_contato, id_disparo, id_grupo)
        VALUES (:id_contato, :id_disparo, :id_grupo)
    ");

        foreach ($relacionamentos as $novo) {
            $stmtInsert->execute([
                ':id_contato' => $novo['id_contato'],
                ':id_disparo' => $novo['id_disparo'],
                ':id_grupo' => $novo['id_grupo']
            ]);

            $id = $pdo->lastInsertId();

            // ✅ Só loga se o relacionamento foi de fato inserido
            if ($id && is_numeric($id)) {
                self::logRelacionamento($id, $usuario_id, null, $novo);
            }
        }

        return ['success' => true, 'message' => 'Relacionamentos atualizados com sucesso'];
    }

    public static function getRelacionamentosByContato($id_contato)
    {
        global $pdo;

        $stmt = $pdo->prepare("
        SELECT 
            r.*, 
            g.nome AS nome_grupo, 
            d.nome AS nome_disparo
        FROM disparos_contatos_rel r
        LEFT JOIN grupo_estabelecimento g ON r.id_grupo = g.id
        LEFT JOIN disparos d ON r.id_disparo = d.id
        WHERE r.id_contato = :id_contato
    ");

        $stmt->execute([':id_contato' => $id_contato]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function listRelacionamentos()
    {
        global $pdo;

        $sql = "
        SELECT rel.*, c.nome AS nome_contato, d.nome AS nome_disparo
        FROM disparos_contatos_rel rel
        JOIN contatos_disparos c ON c.id = rel.id_contato
        JOIN disparos d ON d.id = rel.id_disparo
        ORDER BY rel.created_at DESC
        ";

        $stmt = $pdo->query($sql);
        return ['success' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public static function listDisparos()
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT id, nome FROM disparos WHERE ativo = 1 ORDER BY nome ASC");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function listGrupos()
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT id, nome FROM grupo_estabelecimento WHERE ativo = 1 ORDER BY nome ASC");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    // ===================== LOG INTELIGENTE =====================

    private static function logRelacionamento($id_rel, $id_usuario, $dados_anteriores = null, $dados_novos = null)
    {
        global $pdo;

        if ($dados_anteriores && !$dados_novos) {
            $acao = 'remocao';
        } elseif (!$dados_anteriores && $dados_novos) {
            $acao = 'insercao';
        } elseif ($dados_anteriores && $dados_novos && json_encode($dados_anteriores) !== json_encode($dados_novos)) {
            $acao = 'atualizacao';
        } else {
            return; // Nenhuma diferença
        }

        $sql = "INSERT INTO logs_disparos_contatos 
                (id_disparos_contatos, acao, id_usuario, dados_anteriores, dados_novos)
                VALUES (:id_rel, :acao, :id_usuario, :antes, :depois)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_rel' => $id_rel,
            ':acao' => $acao,
            ':id_usuario' => $id_usuario,
            ':antes' => $dados_anteriores ? json_encode($dados_anteriores) : null,
            ':depois' => $dados_novos ? json_encode($dados_novos) : null
        ]);
    }
}
