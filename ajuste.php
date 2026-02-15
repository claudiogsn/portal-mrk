<?php

// 1. Configurações e Inclusão do Banco
// Ajuste o caminho do require conforme a estrutura real das suas pastas
require_once __DIR__ . '/api/v1/database/db.php';

/** @var PDO $pdo */
global $pdo;

// ==============================================================================
// CONFIGURAÇÃO
// IDs das lojas para processar
$unidadesAlvo = [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35];
// ==============================================================================

if (!$pdo) {
    die("Erro: Variável global \$pdo não encontrada.\n");
}

echo "---------------------------------------------------------\n";
echo " ATUALIZANDO TRANSFER_KEY NO HISTÓRICO \n";
echo " Unidades Alvo: " . implode(', ', $unidadesAlvo) . "\n";
echo "---------------------------------------------------------\n";

try {
    $inQuery = implode(',', array_fill(0, count($unidadesAlvo), '?'));

    // 1. Busca notas que já foram marcadas como transferidas, mas não têm a KEY
    $sqlNotas = "
        SELECT 
            id, 
            system_unit_id, 
            numero_nf, 
            transferido_para_unit_id
        FROM estoque_nota 
        WHERE incluida_estoque = 1 
        AND transferido_para_unit_id IS NOT NULL -- Só as que foram transferidas
        AND transfer_key IS NULL                 -- E que ainda não tem a chave
        AND system_unit_id IN ($inQuery)
    ";

    $stmtNotas = $pdo->prepare($sqlNotas);
    $stmtNotas->execute($unidadesAlvo);
    $notas = $stmtNotas->fetchAll(PDO::FETCH_ASSOC);

    $totalNotas = count($notas);
    echo "> Notas transferidas sem chave encontradas: {$totalNotas}\n\n";

    if ($totalNotas === 0) {
        echo "Nenhuma atualização necessária.\n";
        exit;
    }

    $pdo->beginTransaction();
    $atualizados = 0;

    // PREPARE 1: Descobrir o Timestamp exato da Entrada da Nota na Movimentação
    $sqlGetTime = "
        SELECT MAX(created_at) as data_exata 
        FROM movimentacao 
        WHERE system_unit_id = :loja
          AND doc = :nf
          AND tipo_mov = 'entrada'
    ";
    $stGetTime = $pdo->prepare($sqlGetTime);

    // PREPARE 2: Achar a Transfer Key baseada nesse Timestamp
    // Procuramos um registro tipo 'ts' (Transferência Saída) com KEY, no mesmo instante
    $sqlFindKey = "
        SELECT transfer_key
        FROM movimentacao 
        WHERE system_unit_id = :loja
          AND tipo = 'ts'
          AND transfer_key IS NOT NULL
          AND created_at BETWEEN DATE_SUB(:data_mov, INTERVAL 2 SECOND) 
                             AND DATE_ADD(:data_mov, INTERVAL 2 SECOND)
        LIMIT 1
    ";
    $stFindKey = $pdo->prepare($sqlFindKey);

    // PREPARE 3: Update
    $sqlUpdate = $pdo->prepare("
        UPDATE estoque_nota 
        SET transfer_key = :key 
        WHERE id = :id
    ");

    foreach ($notas as $index => $nota) {
        $notaId = $nota['id'];
        $lojaOrigem = $nota['system_unit_id'];
        $numeroNF = $nota['numero_nf'];

        // A. Pega a data real da movimentação de entrada
        $stGetTime->execute([':loja' => $lojaOrigem, ':nf' => $numeroNF]);
        $dataMovimentacao = $stGetTime->fetchColumn();

        if (!$dataMovimentacao) {
            echo "[SKIP] Nota {$numeroNF} sem registro de entrada na movimentação.\n";
            continue;
        }

        // B. Busca a chave da transferência ocorrida nesse horário
        $stFindKey->execute([':loja' => $lojaOrigem, ':data_mov' => $dataMovimentacao]);
        $transferKey = $stFindKey->fetchColumn();

        if ($transferKey) {
            // C. Atualiza
            $sqlUpdate->execute([
                ':key' => $transferKey,
                ':id'  => $notaId
            ]);

            $atualizados++;
            echo "[OK] Nota {$numeroNF} -> Key vinculada: {$transferKey}\n";
        } else {
            // echo "[--] Nota {$numeroNF} -> Nenhuma chave de transferência encontrada no intervalo.\n";
        }

        if (($index + 1) % 50 == 0) echo "... processado " . ($index + 1) . "\n";
    }

    $pdo->commit();

    echo "\n---------------------------------------------------------\n";
    echo " SCRIPT FINALIZADO \n";
    echo " Notas processadas: {$totalNotas}\n";
    echo " Chaves recuperadas: {$atualizados}\n";
    echo "---------------------------------------------------------\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\n[ERRO FATAL]: " . $e->getMessage() . "\n";
}