<?php

// Ajuste os caminhos conforme a estrutura do seu projeto
require_once __DIR__ . '/../database/db.php';
require_once 'AlertController.php';

function auditMissingProductsLast10Days() {
    global $pdo;

    try {
        echo "🔄 Iniciando auditoria de produtos dos últimos 10 dias...\n";

        // Calcula a data de corte (10 dias atrás a partir de hoje)
        $dataCorte = date('Y-m-d', strtotime('-10 days'));

        // Query otimizada usando LEFT JOIN para encontrar quem está em 'sales' mas não em 'products'
        // Usamos MAX(descricao) e GROUP BY para pegar apenas 1 registro por produto faltante,
        // mesmo que ele tenha sido vendido 50 vezes nesses 10 dias.
        $sql = "
            SELECT 
                s.system_unit_id, 
                s.codMaterial, 
                MAX(s.descricao) as descricao_produto
            FROM sales s
            LEFT JOIN products p 
                ON s.system_unit_id = p.system_unit_id 
                AND s.codMaterial = p.codigo
            WHERE s.dtLancamento >= :data_corte
              AND p.codigo IS NULL
            GROUP BY s.system_unit_id, s.codMaterial
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':data_corte' => $dataCorte]);

        $produtosFaltantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($produtosFaltantes)) {
            echo "✅ Tudo certo! Nenhum produto não mapeado encontrado nas vendas dos últimos 10 dias.\n";
            return;
        }

        echo "⚠️ Foram encontrados " . count($produtosFaltantes) . " produtos não mapeados. Gerando alertas...\n";

        $alertasGerados = 0;

        foreach ($produtosFaltantes as $item) {
            $unitId = $item['system_unit_id'];
            $codMaterial = $item['codMaterial'];
            $descricao = $item['descricao_produto'] ?: 'Sem descrição registrada';

            // Cria o alerta individual chamando o seu controller
            $result = AlertController::create([
                'system_unit_id' => $unitId,
                'title'          => "Produto não mapeado: {$codMaterial}",
                'message'        => "Auditoria: O produto '{$descricao}' (Código PDV: {$codMaterial}) teve vendas nos últimos 10 dias, mas não está cadastrado/vinculado no sistema.",
                'type'           => 'warning',
                'category'       => 'integracao_pdv'
            ]);

            if ($result['success']) {
                $alertasGerados++;
            }
        }

        echo "🏁 Auditoria concluída! {$alertasGerados} alertas foram criados com sucesso.\n";

    } catch (Exception $e) {
        echo "❌ Erro ao executar a auditoria: " . $e->getMessage() . "\n";
    }
}

// Executa a função
auditMissingProductsLast10Days();