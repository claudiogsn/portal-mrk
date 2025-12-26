<?php

ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../database/db.php';

class MdeController
{
    private const PLUG_BASE_URL  = 'https://app.plugstorage.com.br/api/v2/invoices/';
    private const PLUG_TOKEN     = 'fed6936ecdeb64cf0aa540435cf0910a1c580575';
    private const XML_DIR           = __DIR__ . '/../public/xml';
    private const PUBLIC_URL_LOCAL  = 'http://localhost/portal-mrk/api/v1/public/xml/';
    private const PUBLIC_URL_PROD   = 'https://portal.mrksolucoes.com.br/api/v1/public/xml/';

    public static function getPublicBaseUrl(): string
    {
        // Se preferir, honre uma variável de ambiente primeiro:
        // if ($env = getenv('APP_PUBLIC_URL')) return rtrim($env, '/') . '/';

        if (UtilsController::isLocalhostRequest()) {
            return rtrim(self::PUBLIC_URL_LOCAL, '/') . '/';
        }
        return rtrim(self::PUBLIC_URL_PROD, '/') . '/';
    }
    public static function listarChavesNfeComStatusImportacao(int $system_unit_id, string $data_inicial, string $data_final): array
    {
        global $pdo;

        try {

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicial) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_final)) {
                return ['success' => false, 'message' => 'Datas devem estar no formato YYYY-MM-DD'];
            }

            // 1) Busca CNPJ da unidade
            $stmt = $pdo->prepare("SELECT cnpj FROM system_unit WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['cnpj'])) {
                return ['success' => false, 'message' => 'CNPJ não encontrado para a unidade informada'];
            }

            $cnpjUnidade = UtilsController::somenteNumeros($row['cnpj']);
            if (strlen($cnpjUnidade) !== 14) {
                return ['success' => false, 'message' => 'CNPJ da unidade inválido'];
            }

            $lastId = null;
            $allInvoices = [];
            $loopCount = 0;

            do {
                $query = [
                    'softwarehouse_token' => self::PLUG_TOKEN,
                    'cpf_cnpj'            => $cnpjUnidade,
                    'date_ini'            => $data_inicial,
                    'date_end'            => $data_final,
                    'transaction'         => 'received',
                    'mod'                 => 'NFE'
                ];

                if ($lastId) {
                    $query['last_id'] = $lastId;
                }

                $url = self::PLUG_BASE_URL . 'keys?' . http_build_query($query);

                // Chamada GET
                [$httpCode, $body, $err] = UtilsController::httpGet($url);
                if ($err) {
                    return ['success' => false, 'message' => "Falha na chamada à API: $err"];
                }
                if ($httpCode < 200 || $httpCode >= 300) {
                    return ['success' => false, 'message' => "API retornou HTTP $httpCode", 'data' => ['raw' => $body]];
                }

                $json = json_decode($body, true);
                if (!is_array($json)) {
                    return ['success' => false, 'message' => 'Resposta da API inválida (JSON)'];
                }
                if (!empty($json['error'])) {
                    $msg = $json['message'] ?? 'Erro desconhecido pela API';
                    return ['success' => false, 'message' => "API retornou erro: $msg", 'data' => $json];
                }

                $invoices = $json['data']['invoices'] ?? [];
                $count    = $json['data']['count']  ?? 0;
                $total    = $json['data']['total']  ?? 0;
                $lastId   = $json['data']['last_id'] ?? null;

                if (empty($invoices)) break;

                $allInvoices = array_merge($allInvoices, $invoices);
                $loopCount++;

                // Continua somente se houver mais páginas
                $continue = ($count == 30 && $total > count($allInvoices));

            } while ($continue && $loopCount < 200);   // trava anti loop infinito

            if (empty($allInvoices)) {
                return [
                    'success' => true,
                    'message' => 'Nenhuma nota retornada no período informado.',
                    'data'    => [
                        'cnpj'      => $cnpjUnidade,
                        'periodo'   => ['inicio' => $data_inicial, 'fim' => $data_final],
                        'total_api' => 0,
                        'notas'     => [],
                    ]
                ];
            }

            // PREPARA SELECT PARA CHECAR IMPORTAÇÃO
            $selNota = $pdo->prepare("
            SELECT id
              FROM estoque_nota
             WHERE system_unit_id = :system_unit_id
               AND chave_acesso = :chave
             LIMIT 1
        ");

            $notas = [];

            foreach ($allInvoices as $inv) {
                $chave   = $inv['key']            ?? '';
                $numero  = $inv['number']         ?? '';
                $serie   = $inv['serie']          ?? null;
                $emissao = $inv['date_emission']  ?? null;
                $valor   = $inv['value']          ?? null;
                $razao   = $inv['razao_social']   ?? null;
                $fantasia= $inv['fantasia']       ?? null;
                $cnpjEmi = $inv['cnpj_emitter']   ?? null;

                $importada = false;
                $estoqueNotaId = null;

                if ($chave) {
                    $selNota->bindValue(':system_unit_id', $system_unit_id, PDO::PARAM_INT);
                    $selNota->bindValue(':chave', $chave);
                    $selNota->execute();
                    $existe = $selNota->fetch(PDO::FETCH_ASSOC);
                    if ($existe && !empty($existe['id'])) {
                        $importada = true;
                        $estoqueNotaId = (int)$existe['id'];
                    }
                }

                $notas[] = [
                    'chave_acesso'     => $chave,
                    'numero_nf'        => $numero,
                    'serie'            => $serie,
                    'data_emissao'     => $emissao,
                    'valor_total'      => is_numeric($valor) ? (float)$valor : null,
                    'emitente_cnpj'    => $cnpjEmi,
                    'emitente_razao'   => $razao,
                    'emitente_fantasia'=> $fantasia,
                    'status'           => $importada ? 'importada' : 'nao_importada',
                    'estoque_nota_id'  => $estoqueNotaId,
                ];
            }

            return [
                'success' => true,
                'message' => 'Consulta concluída.',
                'data'    => [
                    'cnpj'      => $cnpjUnidade,
                    'periodo'   => ['inicio' => $data_inicial, 'fim' => $data_final],
                    'total_api' => count($notas),
                    'notas'     => $notas,
                    'last_id'   => $lastId,
                ]
            ];

        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Erro inesperado: ' . $e->getMessage()];
        }
    }
    public static function baixarArquivo(int $system_unit_id, string $chaveAcesso, string $tipo): array
    {
        // normalizações/validações
        $tipo = strtolower(trim($tipo));
        if (!in_array($tipo, ['pdf', 'xml'], true)) {
            throw new Exception("Tipo inválido: use 'pdf' ou 'xml'.");
        }
        $chaveAcesso = preg_replace('/\D+/', '', (string)$chaveAcesso);
        if (strlen($chaveAcesso) !== 44) {
            throw new Exception("Chave de acesso inválida (esperado 44 dígitos).");
        }

        global $pdo;

        // CNPJ da unidade
        $stmt = $pdo->prepare("SELECT cnpj FROM system_unit WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $system_unit_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['cnpj'])) {
            throw new Exception("CNPJ não encontrado para a unidade informada.");
        }
        $cnpj = preg_replace('/\D+/', '', $row['cnpj']);
        if (strlen($cnpj) !== 14) {
            throw new Exception("CNPJ da unidade inválido.");
        }

        // Monta URL conforme tipo
        $params = [
            'softwarehouse_token' => self::PLUG_TOKEN,
            'cpf_cnpj'            => $cnpj,
            'invoice_key'         => $chaveAcesso,
            'resume'              => 'false',
            'downloaded'          => 'true',
        ];

        if ($tipo === 'pdf') {
            $params['mode'] = 'PDF';
            // retorno vem dentro de data.xml.data.pdf (base64)
            $expectPath = ['data','xml','data','pdf'];
        } else { // xml
            $params['mode']        = 'XML';
            $params['return_type'] = 'ENCODE'; // pede XML em base64
            // retorno vem em data.xml (base64)
            $expectPath = ['data','xml'];
        }

        $url = self::PLUG_BASE_URL . 'export?' . http_build_query($params);

        // Chamada HTTP
        [$code, $body, $err] = UtilsController::httpGet($url);
        if ($err) {
            throw new Exception("Falha na chamada à API: $err");
        }
        if ($code < 200 || $code >= 300) {
            throw new Exception("API retornou HTTP $code: $body");
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new Exception("Resposta da API inválida (JSON).");
        }
        if (!empty($json['error'])) {
            $msg = $json['message'] ?? 'Erro desconhecido pela API';
            throw new Exception("API retornou erro: $msg");
        }

        // Extrai base64 no caminho esperado
        $node = $json;
        foreach ($expectPath as $k) {
            if (!isset($node[$k])) {
                throw new Exception("Estrutura inesperada no retorno da API para {$tipo}.");
            }
            $node = $node[$k];
        }

        // $node deve ser o base64. Se vier XML cru (caso a API ignore ENCODE), encode.
        $base64 = (string)$node;
        $looksLikeBase64 = (bool)preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $base64) && strlen($base64) > 0;

        if ($tipo === 'xml' && !$looksLikeBase64) {
            // fallback: se veio XML puro, converte para base64
            $base64 = base64_encode($base64);
        }

        if (!$base64) {
            throw new Exception("Conteúdo vazio recebido da API.");
        }

        return [
            'success' => true,
            'content' => $base64
        ];
    }
    public static function importNotasPorChaves(int $system_unit_id, array $chaves): array
    {
        global $pdo;

        try {
            if (!$system_unit_id) {
                return ['success' => false, 'message' => 'system_unit_id inválido'];
            }
            if (empty($chaves)) {
                return ['success' => false, 'message' => 'Nenhuma chave informada'];
            }

            // CNPJ da unidade
            $stmt = $pdo->prepare("SELECT cnpj FROM system_unit WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['cnpj'])) {
                return ['success' => false, 'message' => 'CNPJ não encontrado para a unidade'];
            }
            $cnpjUnidade = UtilsController::somenteNumeros($row['cnpj']);
            if (strlen($cnpjUnidade) !== 14) {
                return ['success' => false, 'message' => 'CNPJ da unidade inválido'];
            }

            // Pasta XML
            if (!UtilsController::ensureDir(self::XML_DIR)) {
                return ['success' => false, 'message' => 'Não foi possível criar a pasta de XMLs'];
            }

            $resultados = [];

            foreach ($chaves as $chave) {
                $chave = trim((string)$chave);
                if ($chave === '') {
                    $resultados[] = [
                        'chave'   => $chave,
                        'success' => false,
                        'step'    => 'validacao',
                        'message' => 'Chave vazia'
                    ];
                    continue;
                }

                // ============================
                // (0) Verifica se já existe nota para essa chave + unidade
                // ============================
                $stmtDup = $pdo->prepare("
                SELECT id 
                  FROM estoque_nota 
                 WHERE system_unit_id = :unit 
                   AND chave_acesso  = :chave
                 LIMIT 1
            ");
                $stmtDup->bindValue(':unit', $system_unit_id, PDO::PARAM_INT);
                $stmtDup->bindValue(':chave', $chave, PDO::PARAM_STR);
                $stmtDup->execute();
                $jaExiste = $stmtDup->fetch(PDO::FETCH_ASSOC);

                if ($jaExiste) {
                    $resultados[] = [
                        'chave'   => $chave,
                        'success' => false,
                        'step'    => 'duplicidade',
                        'message' => 'Nota já importada para esta unidade (chave de acesso já existe na base)'
                    ];
                    continue;
                }

                // (A) Baixa JSON
                $urlJson = self::PLUG_BASE_URL . 'export?' . http_build_query([
                        'softwarehouse_token' => self::PLUG_TOKEN,
                        'cpf_cnpj'            => $cnpjUnidade,
                        'invoice_key'         => $chave,
                        'mode'                => 'JSON',
                        'resume'              => 'false',
                        'downloaded'          => 'true'
                    ]);
                [$codeJ, $bodyJ, $errJ] = UtilsController::httpGet($urlJson);
                if ($errJ || $codeJ < 200 || $codeJ >= 300) {
                    // tenta extrair mensagem amigável do body (ex: "Nota não encontrada.")
                    $plugMsg = null;

                    if (!$errJ && !empty($bodyJ)) {
                        $bodyDecoded = json_decode($bodyJ, true);
                        if (is_array($bodyDecoded) && !empty($bodyDecoded['message'])) {
                            $plugMsg = $bodyDecoded['message'];
                        }
                    }

                    // monta mensagem final
                    $msgFinal = $errJ
                        ? "Erro de rede: $errJ"
                        : ($plugMsg ?: "HTTP $codeJ");

                    $resultados[] = [
                        'chave'   => $chave,
                        'success' => false,
                        'step'    => 'json',
                        'message' => $msgFinal,
                        'raw'     => $bodyJ
                    ];
                    continue;
                }

                $json = json_decode($bodyJ, true);
                if (!is_array($json) || !empty($json['error'])) {
                    $resultados[] = [
                        'chave'   => $chave,
                        'success' => false,
                        'step'    => 'json',
                        'message' => $json['message'] ?? 'Resposta JSON inválida'
                    ];
                    continue;
                }

                // Normaliza JSON -> formato do importNotaFiscal
                try {
                    $notaJson = self::mapPlugJsonToNota($json);
                } catch (Throwable $e) {
                    $resultados[] = [
                        'chave'   => $chave,
                        'success' => false,
                        'step'    => 'map',
                        'message' => 'Falha ao normalizar JSON: ' . $e->getMessage()
                    ];
                    continue;
                }

                // ============================
                // (1) Valida CNPJ destinatário x CNPJ da unidade
                // ============================
                try {
                    $cnpjDest = null;

                    if (isset($notaJson['destinatario']['cnpj_cpf'])) {
                        $cnpjDest = UtilsController::somenteNumeros($notaJson['destinatario']['cnpj_cpf']);
                    } elseif (isset($notaJson['destinatario']['cnpj'])) {
                        $cnpjDest = UtilsController::somenteNumeros($notaJson['destinatario']['cnpj']);
                    } elseif (isset($notaJson['destinatario']['cpf'])) {
                        // Em teoria não deveria cair aqui pra NFe de entrada pra PJ, mas deixei como fallback
                        $cnpjDest = UtilsController::somenteNumeros($notaJson['destinatario']['cpf']);
                    }

                    if (!$cnpjDest || strlen($cnpjDest) !== 14) {
                        $resultados[] = [
                            'chave'   => $chave,
                            'success' => false,
                            'step'    => 'cnpj',
                            'message' => 'CNPJ do destinatário não encontrado ou inválido na nota fiscal'
                        ];
                        continue;
                    }

                    if ($cnpjDest !== $cnpjUnidade) {
                        $resultados[] = [
                            'chave'   => $chave,
                            'success' => false,
                            'step'    => 'cnpj',
                            'message' => 'CNPJ do destinatário da NFe é diferente do CNPJ da unidade'
                        ];
                        continue;
                    }
                } catch (Throwable $e) {
                    $resultados[] = [
                        'chave'   => $chave,
                        'success' => false,
                        'step'    => 'cnpj',
                        'message' => 'Erro ao validar CNPJ do destinatário: ' . $e->getMessage()
                    ];
                    continue;
                }

                // (B) Importa no banco
                $respImport = self::importNotaFiscal($system_unit_id, $notaJson);
                $notaId  = $respImport['nota_id'] ?? null;

                // (C) Baixa XML puro e salva
                $urlXml = self::PLUG_BASE_URL . 'export?' . http_build_query([
                        'softwarehouse_token' => self::PLUG_TOKEN,
                        'cpf_cnpj'            => $cnpjUnidade,
                        'invoice_key'         => $chave,
                        'mode'                => 'XML',
                        'return_type'         => 'XML',
                        'resume'              => 'false',
                        'downloaded'          => 'true'
                    ]);
                [$codeX, $bodyX, $errX] = UtilsController::httpGet($urlXml, ['Accept: */*']);
                $xmlSavedUrl = null;

                if (!$errX && $codeX >= 200 && $codeX < 300 && !empty($bodyX)) {
                    $fileName = $chave . '.xml';
                    $filePath = rtrim(self::XML_DIR, '/\\') . DIRECTORY_SEPARATOR . $fileName;
                    if (UtilsController::saveFileOverwrite($filePath, $bodyX) !== false) {
                        $xmlSavedUrl = self::getPublicBaseUrl() . $fileName;
                    }
                }

                $resultados[] = [
                    'chave'      => $chave,
                    'success'    => !empty($respImport['success']),
                    'nota_id'    => $notaId,
                    'xml_public' => $xmlSavedUrl,
                    'message'    => $respImport['message'] ?? ($respImport['success'] ?? false ? 'OK' : 'Falha na importação')
                ];
            }

            // ============================
            // SUCESSO GLOBAL (TOPO DO JSON)
            // ============================
            $sucessos = 0;
            $falhas   = 0;

            foreach ($resultados as $r) {
                if (!empty($r['success'])) {
                    $sucessos++;
                } else {
                    $falhas++;
                }
            }

            $successGlobal = $sucessos > 0;

            if ($successGlobal && $falhas === 0) {
                $mensagemGlobal = 'Processamento concluído com sucesso.';
            } elseif ($successGlobal && $falhas > 0) {
                $mensagemGlobal = 'Processamento concluído com falhas.';
            } else {
                $mensagemGlobal = 'Nenhuma nota importada.';
            }

            return [
                'success' => $successGlobal,
                'message' => $mensagemGlobal,
                'data'    => [
                    'system_unit_id' => $system_unit_id,
                    'cnpj'           => $cnpjUnidade,
                    'itens'          => $resultados
                ]
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Erro inesperado: ' . $e->getMessage()];
        }
    }
    private static function mapPlugJsonToNota(array $plugJson): array
    {
        $xml = $plugJson['data']['xml'] ?? null;
        if (!$xml || empty($xml['NFe']['infNFe'])) {
            throw new Exception('Bloco NFe.infNFe não encontrado no JSON');
        }

        $inf  = $xml['NFe']['infNFe'];
        $ide  = $inf['ide']  ?? [];
        $emit = $inf['emit'] ?? [];
        $dest = $inf['dest'] ?? [];
        $det  = $inf['det']  ?? [];
        $tot  = $inf['total']['ICMSTot'] ?? [];
        $cobr = $inf['cobr'] ?? [];

        $protChave = $xml['protNFe']['infProt']['chNFe'] ?? null;
        $chave = $protChave ?: UtilsController::extraiChaveDoId($inf['@attributes']['Id'] ?? null);
        if (!$chave) throw new Exception('Chave de acesso não encontrada no JSON');

        // Datas (YYYY-MM-DD)
        $dhEmi    = $ide['dhEmi']    ?? null;
        $dhSaiEnt = $ide['dhSaiEnt'] ?? null;

        // Totais
        $valorNF    = isset($tot['vNF'])    ? (float)$tot['vNF']    : 0.0;
        $valorProd  = isset($tot['vProd'])  ? (float)$tot['vProd']  : 0.0;
        $valorFrete = isset($tot['vFrete']) ? (float)$tot['vFrete'] : 0.0;

        // Fornecedor (emitente)
        $fornecedor = [
            'cnpj_cpf'  => $emit['CNPJ'] ?? null,      // seu getCreateFornecedor exige cnpj_cpf
            'razao'     => $emit['xNome'] ?? null,
            'nome'      => $emit['xFant'] ?? null,
            'endereco'  => ($emit['enderEmit']['xLgr'] ?? '') . ', ' . ($emit['enderEmit']['nro'] ?? ''),
            'cep'       => $emit['enderEmit']['CEP'] ?? null,
            'insc_estadual' => $emit['IE'] ?? null,
            'fone'      => $emit['enderEmit']['fone'] ?? null,
            'plano_contas' => null, // ajuste se necessário
            'codigo'    => '',      // se usar código próprio
        ];

        // Destinatário (para validação que já existe em importNotaFiscal)
        $destinatario = [
            'cnpj'     => $dest['CNPJ'] ?? null,
            'cnpj_cpf' => $dest['CNPJ'] ?? ($dest['CPF'] ?? null),
            'razao'    => $dest['xNome'] ?? null,
            'email'    => $dest['email'] ?? null,
        ];

        // Itens: det pode ser objeto ou array
        $detArray = UtilsController::isAssoc($det) ? [$det] : $det;
        $itens = [];
        foreach ($detArray as $d) {
            $nItem = isset($d['@attributes']['nItem']) ? (int)$d['@attributes']['nItem'] : null;
            $p     = $d['prod'] ?? [];

            $qtd = isset($p['qCom'])  ? (float)str_replace(',', '.', $p['qCom'])   : 0.0;
            $vUn = isset($p['vUnCom'])? (float)str_replace(',', '.', $p['vUnCom']) : 0.0;
            $vTo = isset($p['vProd']) ? (float)str_replace(',', '.', $p['vProd'])  : ($qtd * $vUn);

            $itens[] = [
                'numero_item'    => $nItem,
                'codigo_produto' => $p['cProd'] ?? null,
                'descricao'      => $p['xProd'] ?? null,
                'ncm'            => $p['NCM'] ?? null,
                'cfop'           => $p['CFOP'] ?? null,
                'unidade'        => $p['uCom'] ?? null,
                'quantidade'     => $qtd,
                'valor_unitario' => $vUn,
                'valor_total'    => $vTo,
                'valor_frete'    => 0.0
            ];
        }

        // Duplicatas
        $duplicatas = [];
        if (!empty($cobr['dup'])) {
            $dups = $cobr['dup'];
            $dups = UtilsController::isAssoc($dups) ? [$dups] : $dups;
            foreach ($dups as $d) {
                $duplicatas[] = [
                    'numero_duplicata' => $d['nDup'] ?? null,
                    'data_vencimento'  => $d['dVenc'] ?? null,
                    'valor_parcela'    => isset($d['vDup']) ? (float)$d['vDup'] : null,
                ];
            }
        }

        return [
            'chave_acesso'      => $chave,
            'numero_nf'         => (string)($ide['nNF'] ?? ''),
            'serie'             => (string)($ide['serie'] ?? ''),
            'data_emissao'      => UtilsController::toISODate($dhEmi),
            'data_saida'        => UtilsController::toISODate($dhSaiEnt),
            'natureza_operacao' => $ide['natOp'] ?? null,
            'valor_total'       => $valorNF,
            'valor_produtos'    => $valorProd,
            'valor_frete'       => $valorFrete,

            'fornecedor'        => $fornecedor,
            'destinatario'      => $destinatario,
            'itens'             => $itens,
            'duplicatas'        => $duplicatas
        ];
    }
    public static function getCreateFornecedor($system_unit_id, $fornecedorData)
    {
        global $pdo;

        $cnpjCpf = $fornecedorData['cnpj_cpf'] ?? null;
        if (!$cnpjCpf) {
            throw new Exception("CNPJ/CPF do fornecedor é obrigatório");
        }

        // Verifica se já existe
        $stmt = $pdo->prepare("SELECT id FROM financeiro_fornecedor WHERE system_unit_id = ? AND cnpj_cpf = ?");
        $stmt->execute([$system_unit_id, $cnpjCpf]);
        $fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fornecedor) {
            return $fornecedor['id'];
        }

        // Cria novo fornecedor
        $stmt = $pdo->prepare("
            INSERT INTO financeiro_fornecedor 
            (system_unit_id, codigo, razao, nome, cnpj_cpf, plano_contas, endereco, cep, insc_estadual, fone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $system_unit_id,
            $fornecedorData['codigo'] ?? '',
            $fornecedorData['razao'] ?? '',
            $fornecedorData['nome'] ?? '',
            $cnpjCpf,
            $fornecedorData['plano_contas'] ?? null,
            $fornecedorData['endereco'] ?? null,
            $fornecedorData['cep'] ?? null,
            $fornecedorData['insc_estadual'] ?? null,
            $fornecedorData['fone'] ?? null
        ]);

        return $pdo->lastInsertId();
    }
    public static function importNotaFiscal($system_unit_id, $notaJson): array
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            // =========================
            // 1) VALIDAR CNPJ
            // =========================
            $cnpjDest = $notaJson['destinatario']['cnpj_cpf']
                ?? $notaJson['dest']['cnpj_cpf']
                ?? $notaJson['destinatario']['cnpj']
                ?? $notaJson['dest']['cnpj']
                ?? null;

            if (!$cnpjDest) {
                throw new Exception("Dados do destinatário não enviados (CNPJ ausente).");
            }

            $stmt = $pdo->prepare("SELECT cnpj FROM system_unit WHERE id = ? LIMIT 1");
            $stmt->execute([$system_unit_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !$row['cnpj']) {
                throw new Exception("CNPJ da unidade não encontrado.");
            }

            $norm = fn($v) => preg_replace('/\D+/', '', (string)$v);

            if ($norm($cnpjDest) !== $norm($row['cnpj'])) {
                throw new Exception("CNPJ do destinatário não corresponde ao da unidade.");
            }

            // =========================
            // 2) FORNECEDOR / NOTA
            // =========================
            $fornecedorData = $notaJson['fornecedor'] ?? null;
            if (!$fornecedorData) {
                throw new Exception("Fornecedor não informado.");
            }

            $fornecedor_id = self::getCreateFornecedor($system_unit_id, $fornecedorData);

            $chaveAcesso = $notaJson['chave_acesso'] ?? null;
            $numeroNF    = $notaJson['numero_nf'] ?? null;
            $serie       = $notaJson['serie'] ?? null;

            if (!$chaveAcesso || !$numeroNF) {
                throw new Exception("Chave de acesso e número da NF são obrigatórios.");
            }

            // Duplicidade
            $stmt = $pdo->prepare("
            SELECT id FROM estoque_nota
             WHERE system_unit_id = ? AND fornecedor_id = ? AND numero_nf = ? AND serie = ?
        ");
            $stmt->execute([$system_unit_id, $fornecedor_id, $numeroNF, $serie]);
            if ($stmt->fetch()) {
                throw new Exception("Nota já importada para este fornecedor.");
            }

            // =========================
            // 3) INSERE NOTA
            // =========================
            $stmt = $pdo->prepare("
            INSERT INTO estoque_nota
            (system_unit_id, fornecedor_id, chave_acesso, numero_nf, serie,
             data_emissao, data_saida, natureza_operacao,
             valor_total, valor_produtos, valor_frete)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

            $stmt->execute([
                $system_unit_id,
                $fornecedor_id,
                $chaveAcesso,
                $numeroNF,
                $serie,
                $notaJson['data_emissao'] ?? null,
                $notaJson['data_saida'] ?? null,
                $notaJson['natureza_operacao'] ?? null,
                (float)($notaJson['valor_total'] ?? 0),
                (float)($notaJson['valor_produtos'] ?? 0),
                (float)($notaJson['valor_frete'] ?? 0),
            ]);

            $nota_id = (int)$pdo->lastInsertId();

            // =========================
            // 4) ITENS (CUSTO REAL)
            // =========================
            if (!empty($notaJson['itens']) && is_array($notaJson['itens'])) {

                $stmtItem = $pdo->prepare("
                INSERT INTO estoque_nota_item
                (system_unit_id, nota_id, numero_item, codigo_produto, descricao,
                 ncm, cfop, unidade, quantidade,
                 valor_unitario, valor_total, valor_frete)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

                foreach ($notaJson['itens'] as $item) {

                    $quantidade = (float)($item['quantidade'] ?? 0);
                    if ($quantidade <= 0) continue;

                    $vProd   = (float)($item['valor_produto'] ?? $item['vProd'] ?? 0);
                    $vIPI    = (float)($item['valor_ipi'] ?? $item['vIPI'] ?? 0);
                    $vOutro  = (float)($item['valor_outros'] ?? $item['vOutro'] ?? 0);
                    $vFrete  = (float)($item['valor_frete'] ?? $item['vFrete'] ?? 0);

                    // 👉 CUSTO REAL DO ITEM
                    $valorTotalItem = round($vProd + $vIPI + $vOutro + $vFrete, 2);

                    // 👉 VALOR UNITÁRIO REAL
                    $valorUnitario = round($valorTotalItem / $quantidade, 6);

                    $stmtItem->execute([
                        $system_unit_id,
                        $nota_id,
                        (int)($item['numero_item'] ?? 0),
                        $item['codigo_produto'] ?? null,
                        $item['descricao'] ?? null,
                        $item['ncm'] ?? null,
                        $item['cfop'] ?? null,
                        $item['unidade'] ?? null,
                        $quantidade,
                        $valorUnitario,
                        $valorTotalItem,
                        $vFrete
                    ]);
                }
            }

            $pdo->commit();
            return ['success' => true, 'nota_id' => $nota_id];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    public static function listarNotas($system_unit_id): array
    {
        global $pdo;

        try {
            $sql = "SELECT 
                    en.id,
                    en.chave_acesso,
                    en.numero_nf,
                    en.serie,
                    en.data_emissao,
                    en.data_entrada,
                    en.valor_total,
                    en.incluida_estoque,
                    en.incluida_financeiro,
                    en.tipo,
                    ff.razao AS fornecedor_razao,
                    ff.cnpj_cpf AS fornecedor_cnpj,
                    ff.id as fornecedor_id
                FROM estoque_nota en
                JOIN financeiro_fornecedor ff ON en.fornecedor_id = ff.id 
                    AND ff.system_unit_id = en.system_unit_id
                WHERE en.system_unit_id = :unit_id
                ORDER BY en.data_emissao DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':unit_id', $system_unit_id, PDO::PARAM_INT);
            $stmt->execute();

            $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $notas,
                'total' => count($notas)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao listar notas fiscais: ' . $e->getMessage()
            ];
        }
    }
    public static function listarNotasNaoImportadasUltimos30Dias(int $system_unit_id): array
    {
        try {
            // Período: últimos 30 dias (hoje + 29 dias atrás)
            $dtFim = new DateTime('today');
            $dtIni = (clone $dtFim)->modify('-29 days');

            $data_inicial = $dtIni->format('Y-m-d');
            $data_final   = $dtFim->format('Y-m-d');

            // Reaproveita o método existente que já consulta a Plug e o estoque_nota
            $resp = self::listarChavesNfeComStatusImportacao($system_unit_id, $data_inicial, $data_final);

            if (empty($resp['success'])) {
                return $resp;
            }

            $dataOriginal = $resp['data'] ?? [];
            $notas        = $dataOriginal['notas'] ?? [];

            // Filtra apenas as NÃO importadas
            $pendentesRaw = array_filter($notas, function ($n) {
                return strtolower($n['status'] ?? '') !== 'importada';
            });

            // Mapeia e JÁ REINDEXA para não vir 0,1,2,... como chaves
            $pendentes = array_values(array_map(function ($n) {
                return [
                    'chave_acesso'    => $n['chave_acesso'] ?? null,
                    'numero_nf'       => $n['numero_nf'] ?? null,
                    'serie'           => $n['serie'] ?? null,
                    'data_emissao'    => $n['data_emissao'] ?? null,
                    'valor_total'     => $n['valor_total'] ?? null,
                    'emitente_cnpj'   => $n['emitente_cnpj'] ?? null,
                    'emitente_razao'  => $n['emitente_razao']
                        ?? $n['emitente_fantasia']
                            ?? null,
                ];
            }, $pendentesRaw));

            $totalPendentes = count($pendentes);
            $totalApi       = $dataOriginal['total_api'] ?? count($notas);

            $mensagem = $totalPendentes > 0
                ? 'Notas não importadas encontradas nos últimos 30 dias.'
                : 'Nenhuma nota pendente de importação nos últimos 30 dias.';

            return [
                'success' => true,
                'message' => $mensagem,
                'data'    => [
                    'system_unit_id'   => $system_unit_id,
                    'cnpj'             => $dataOriginal['cnpj']      ?? null,
                    'periodo'          => $dataOriginal['periodo']   ?? [
                            'inicio' => $data_inicial,
                            'fim'    => $data_final,
                        ],
                    'total_api'        => $totalApi,
                    'total_pendentes'  => $totalPendentes,
                    'notas_pendentes'  => $pendentes, // aqui já vem como array sequencial
                ]
            ];

        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Erro inesperado: ' . $e->getMessage()
            ];
        }
    }
    public static function lancarNotaAvulsa(int $system_unit_id, array $data): array
    {
        global $pdo;

        try {
            $pdo->beginTransaction();

            // ===== validações básicas =====
            $requiredTopo = ['fornecedor_id', 'numero_nf', 'data_entrada', 'data_emissao', 'itens'];
            foreach ($requiredTopo as $campo) {
                if (!isset($data[$campo]) || $data[$campo] === '' || $data[$campo] === null) {
                    throw new Exception("Campo obrigatório ausente: {$campo}");
                }
            }

            if (!is_array($data['itens']) || count($data['itens']) === 0) {
                throw new Exception("É necessário informar ao menos um item.");
            }

            $fornecedor_id = (int)$data['fornecedor_id'];
            $numeroNF      = trim((string)$data['numero_nf']);
            $serie         = isset($data['serie']) ? trim((string)$data['serie']) : null;

            // helper data -> datetime
            $parseDateTime = static function (?string $s): ?string {
                if (!$s) return null;
                $s = trim($s);

                // YYYY-MM-DD
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                    return $s . ' 00:00:00';
                }

                // DD/MM/YYYY
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
                    [$d, $m, $y] = explode('/', $s);
                    return sprintf('%04d-%02d-%02d 00:00:00', (int)$y, (int)$m, (int)$d);
                }

                // qualquer coisa que strtotime aceite
                $t = strtotime($s);
                if ($t !== false) {
                    return date('Y-m-d 00:00:00', $t);
                }

                throw new Exception("Data inválida: {$s}");
            };

            $dataEntrada  = $parseDateTime((string)$data['data_entrada']);
            $dataEmissao  = $parseDateTime((string)$data['data_emissao']);

            // ===== calcula totais a partir dos itens (qtd × valor_unitario) =====
            $valorProdutos = 0.0;

            foreach ($data['itens'] as $idx => $item) {
                foreach (['codigo_produto','descricao','unidade','quantidade','valor_unitario'] as $campo) {
                    if (!isset($item[$campo]) || $item[$campo] === '' || $item[$campo] === null) {
                        throw new Exception("Item {$idx}: campo obrigatório ausente: {$campo}");
                    }
                }

                $qtd  = (float)$item['quantidade'];
                $unit = (float)$item['valor_unitario'];

                if ($qtd <= 0) {
                    throw new Exception("Item {$idx}: quantidade deve ser maior que zero.");
                }

                $valorItem = $qtd * $unit;
                $valorProdutos += $valorItem;
            }

            $valorTotal   = $valorProdutos;
            $valorFrete   = isset($data['valor_frete']) ? (float)$data['valor_frete'] : 0.0;

            // ===== chave_acesso (para avulsa) =====
            $chaveAcesso = isset($data['chave_acesso']) && trim($data['chave_acesso']) !== ''
                ? trim((string)$data['chave_acesso'])
                : ('AVULSA-' . $system_unit_id . '-' . $numeroNF . '-' . ($serie ?: 'S0'));

            if (strlen($chaveAcesso) > 50) {
                $chaveAcesso = substr($chaveAcesso, 0, 50);
            }

            // ===== checa duplicidade =====
            $stDup = $pdo->prepare("
            SELECT id 
            FROM estoque_nota
            WHERE system_unit_id = ? 
              AND fornecedor_id  = ? 
              AND numero_nf      = ? 
              AND serie          = ?
            LIMIT 1
        ");
            $stDup->execute([$system_unit_id, $fornecedor_id, $numeroNF, $serie]);
            if ($stDup->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Nota já existe para este fornecedor, série e unidade.");
            }

            // ===== insere cabeçalho (tipo = 'a' avulsa) =====
            $stNota = $pdo->prepare("
            INSERT INTO estoque_nota (
                system_unit_id,
                fornecedor_id,
                chave_acesso,
                numero_nf,
                serie,
                data_emissao,
                data_entrada,
                natureza_operacao,
                valor_total,
                valor_produtos,
                valor_frete,
                tipo
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'a'
            )
        ");
            $stNota->execute([
                $system_unit_id,
                $fornecedor_id,
                $chaveAcesso,
                $numeroNF,
                $serie,
                $dataEmissao,
                $dataEntrada,
                $data['natureza_operacao'] ?? 'NOTA AVULSA',
                $valorTotal,
                $valorProdutos,
                $valorFrete
            ]);

            $nota_id = (int)$pdo->lastInsertId();

            // ===== insere itens =====
            $stItem = $pdo->prepare("
            INSERT INTO estoque_nota_item (
                system_unit_id,
                nota_id,
                numero_item,
                codigo_produto,
                descricao,
                ncm,
                cfop,
                unidade,
                quantidade,
                valor_unitario,
                valor_total,
                valor_frete
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

            foreach ($data['itens'] as $idx => $item) {
                $numeroItem = isset($item['numero_item']) ? (int)$item['numero_item'] : ($idx + 1);
                $codigoProd = $item['codigo_produto'];
                $descricao  = $item['descricao'];
                $unidade    = $item['unidade'];
                $qtd        = (float)$item['quantidade'];
                $vUnit      = (float)$item['valor_unitario'];
                $vTotal     = $qtd * $vUnit;

                $stItem->execute([
                    $system_unit_id,
                    $nota_id,
                    $numeroItem,
                    $codigoProd,
                    $descricao,
                    $item['ncm']  ?? null,
                    $item['cfop'] ?? null,
                    $unidade,
                    $qtd,
                    $vUnit,
                    $vTotal,
                    0.0
                ]);
            }

            // === LANÇA ITENS NO ESTOQUE ===
            $estoqueResp = NotaFiscalEntradaController::lancarItensNotaAvulsaNoEstoque([
                'system_unit_id' => $system_unit_id,
                'usuario_id'     => isset($data['usuario_id']) ? (int)$data['usuario_id'] : 1,
                'nota_id'        => $nota_id,
                'data_entrada'   => $data['data_entrada'],
                'data_emissao'   => $data['data_emissao'],
                'itens'          => $data['itens']
            ]);

            if (!$estoqueResp['success']) {
                throw new Exception(
                    "Nota criada, mas houve erro ao lançar os itens no estoque: " .
                    $estoqueResp['message']
                );
            }

            $pdo->commit();

            return [
                'success'        => true,
                'message'        => 'Nota avulsa lançada com sucesso.',
                'nota_id'        => $nota_id,
                'chave_acesso'   => $chaveAcesso,
                'numero_nf'      => $numeroNF,
                'serie'          => $serie,
                'valor_total'    => $valorTotal,
                'valor_produtos' => $valorProdutos,
                'estoque'        => $estoqueResp
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    public static function getNotaAvulsa(int $system_unit_id, int $nota_id): array
    {
        global $pdo;

        try {
            // Cabeçalho da nota avulsa
            $stCab = $pdo->prepare("
            SELECT 
                id,
                system_unit_id,
                fornecedor_id,
                chave_acesso,
                numero_nf,
                serie,
                data_emissao,
                data_entrada,
                natureza_operacao,
                valor_total,
                valor_produtos,
                valor_frete,
                tipo
            FROM estoque_nota
            WHERE id = :id
              AND system_unit_id = :unit
              AND tipo = 'a'
            LIMIT 1
        ");
            $stCab->execute([
                ':id'   => $nota_id,
                ':unit' => $system_unit_id,
            ]);

            $cab = $stCab->fetch(PDO::FETCH_ASSOC);

            if (!$cab) {
                return [
                    'success' => false,
                    'message' => 'Nota avulsa não encontrada para esta unidade.',
                ];
            }

            // Itens da nota
            $stItens = $pdo->prepare("
            SELECT 
                id,
                numero_item,
                codigo_produto,
                descricao,
                ncm,
                cfop,
                unidade,
                quantidade,
                valor_unitario,
                valor_total,
                valor_frete
            FROM estoque_nota_item
            WHERE system_unit_id = :unit
              AND nota_id       = :nota
            ORDER BY numero_item ASC, id ASC
        ");
            $stItens->execute([
                ':unit' => $system_unit_id,
                ':nota' => $nota_id,
            ]);

            $itens = $stItens->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'nota'    => $cab,
                'itens'   => $itens,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    public static function updateNotaAvulsa(int $system_unit_id, int $nota_id, array $data): array
    {
        global $pdo;

        try {
            // =======================
            // 1) Carrega nota atual
            // =======================
            $stOld = $pdo->prepare("
            SELECT 
                id,
                numero_nf,
                chave_acesso,
                tipo
            FROM estoque_nota
            WHERE id = :id
              AND system_unit_id = :unit
            LIMIT 1
        ");
            $stOld->execute([
                ':id'   => $nota_id,
                ':unit' => $system_unit_id,
            ]);

            $notaOld = $stOld->fetch(PDO::FETCH_ASSOC);

            if (!$notaOld) {
                return [
                    'success' => false,
                    'message' => 'Nota não encontrada para esta unidade.',
                ];
            }

            if (($notaOld['tipo'] ?? null) !== 'a') {
                return [
                    'success' => false,
                    'message' => 'Apenas notas avulsas podem ser editadas por este método.',
                ];
            }

            $oldNumeroNF   = (string)$notaOld['numero_nf'];
            $oldChaveAcess = (string)$notaOld['chave_acesso'];

            // =======================
            // 2) Valida payload
            // =======================
            $requiredTopo = ['fornecedor_id', 'numero_nf', 'data_entrada', 'data_emissao', 'itens', 'usuario_id'];
            foreach ($requiredTopo as $campo) {
                if (!isset($data[$campo]) || $data[$campo] === '' || $data[$campo] === null) {
                    throw new Exception("Campo obrigatório ausente: {$campo}");
                }
            }

            if (!is_array($data['itens']) || count($data['itens']) === 0) {
                throw new Exception("É necessário informar ao menos um item.");
            }

            $fornecedor_id = (int)$data['fornecedor_id'];
            $numeroNF      = trim((string)$data['numero_nf']);
            $serie         = isset($data['serie']) ? trim((string)$data['serie']) : null;
            $usuarioId     = (int)$data['usuario_id'];

            // helper data -> datetime
            $parseDateTime = static function (?string $s): ?string {
                if (!$s) return null;
                $s = trim($s);

                // YYYY-MM-DD
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                    return $s . ' 00:00:00';
                }

                // DD/MM/YYYY
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
                    [$d, $m, $y] = explode('/', $s);
                    return sprintf('%04d-%02d-%02d 00:00:00', (int)$y, (int)$m, (int)$d);
                }

                // qualquer coisa que strtotime aceite
                $t = strtotime($s);
                if ($t !== false) {
                    return date('Y-m-d 00:00:00', $t);
                }

                throw new Exception("Data inválida: {$s}");
            };

            $dataEntrada = $parseDateTime((string)$data['data_entrada']);
            $dataEmissao = $parseDateTime((string)$data['data_emissao']);

            // =======================
            // 3) Calcula totais
            // =======================
            $valorProdutos = 0.0;

            foreach ($data['itens'] as $idx => $item) {
                foreach (['codigo_produto','descricao','unidade','quantidade','valor_total'] as $campo) {
                    if (!isset($item[$campo]) || $item[$campo] === '' || $item[$campo] === null) {
                        throw new Exception("Item {$idx}: campo obrigatório ausente: {$campo}");
                    }
                }

                $qtd  = (float)$item['quantidade'];
                $vTot = (float)$item['valor_total'];

                if ($qtd <= 0) {
                    throw new Exception("Item {$idx}: quantidade deve ser maior que zero.");
                }

                $valorProdutos += $vTot;
            }

            $valorTotal = $valorProdutos;
            $valorFrete = isset($data['valor_frete']) ? (float)$data['valor_frete'] : 0.0;

            // =======================
            // 4) Chave de acesso
            // =======================
            $chaveAcesso = isset($data['chave_acesso']) && trim($data['chave_acesso']) !== ''
                ? trim((string)$data['chave_acesso'])
                : $oldChaveAcess; // mantém a anterior se não vier nada

            if (!$chaveAcesso) {
                // se por algum motivo não tinha, gera um padrão
                $chaveAcesso = 'AVULSA-' . $system_unit_id . '-' . $numeroNF . '-' . ($serie ?: 'S0');
            }

            // limite coluna
            if (strlen($chaveAcesso) > 50) {
                $chaveAcesso = substr($chaveAcesso, 0, 50);
            }

            // =======================
            // 5) Atualiza nota + itens
            // =======================
            $pdo->beginTransaction();

            // opcional: checar duplicidade MESMO fornecedor/numero/serie em outra nota
            $stDup = $pdo->prepare("
            SELECT id
            FROM estoque_nota
            WHERE system_unit_id = ?
              AND fornecedor_id  = ?
              AND numero_nf      = ?
              AND serie          = ?
              AND id            <> ?
            LIMIT 1
        ");
            $stDup->execute([
                $system_unit_id,
                $fornecedor_id,
                $numeroNF,
                $serie,
                $nota_id
            ]);
            if ($stDup->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Já existe outra nota avulsa com este fornecedor, série e número para esta unidade.");
            }

            // atualiza cabeçalho
            $stUpdNota = $pdo->prepare("
            UPDATE estoque_nota
            SET 
                fornecedor_id     = ?,
                chave_acesso      = ?,
                numero_nf         = ?,
                serie             = ?,
                data_emissao      = ?,
                data_entrada      = ?,
                natureza_operacao = ?,
                valor_total       = ?,
                valor_produtos    = ?,
                valor_frete       = ?,
                updated_at        = CURRENT_TIMESTAMP
            WHERE id = ?
              AND system_unit_id = ?
              AND tipo = 'a'
            LIMIT 1
        ");
            $stUpdNota->execute([
                $fornecedor_id,
                $chaveAcesso,
                $numeroNF,
                $serie,
                $dataEmissao,
                $dataEntrada,
                $data['natureza_operacao'] ?? 'NOTA AVULSA',
                $valorTotal,
                $valorProdutos,
                $valorFrete,
                $nota_id,
                $system_unit_id
            ]);

            if ($stUpdNota->rowCount() === 0) {
                throw new Exception('Falha ao atualizar cabeçalho da nota avulsa.');
            }

            // remove itens antigos
            $stDelItens = $pdo->prepare("
            DELETE FROM estoque_nota_item
            WHERE system_unit_id = :unit
              AND nota_id        = :nota
        ");
            $stDelItens->execute([
                ':unit' => $system_unit_id,
                ':nota' => $nota_id,
            ]);

            // insere itens novos
            $stItem = $pdo->prepare("
            INSERT INTO estoque_nota_item (
                system_unit_id,
                nota_id,
                numero_item,
                codigo_produto,
                descricao,
                ncm,
                cfop,
                unidade,
                quantidade,
                valor_unitario,
                valor_total,
                valor_frete
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

            foreach ($data['itens'] as $idx => $item) {
                $numeroItem    = isset($item['numero_item']) ? (int)$item['numero_item'] : ($idx + 1);
                $codigoProd    = $item['codigo_produto'];
                $descricao     = $item['descricao'];
                $unidade       = $item['unidade'];
                $qtd           = (float)$item['quantidade'];
                $vTotalItem    = (float)$item['valor_total'];
                $vUnitarioItem = $qtd > 0 ? ($vTotalItem / $qtd) : 0.0;

                $stItem->execute([
                    $system_unit_id,
                    $nota_id,
                    $numeroItem,
                    $codigoProd,
                    $descricao,
                    $item['ncm']  ?? null,
                    $item['cfop'] ?? null,
                    $unidade,
                    $qtd,
                    $vUnitarioItem,
                    $vTotalItem,
                    0.0 // valor_frete por item (se quiser tratar)
                ]);
            }

            $pdo->commit();

            // =======================
            // 6) Atualiza movimentação de estoque
            // =======================

            // apaga movimentações antigas dessa nota (doc antigo e doc novo, por segurança)
            try {
                $stDelMov = $pdo->prepare("
                DELETE FROM movimentacao
                WHERE system_unit_id = :unit
                  AND tipo           = 'c'
                  AND tipo_mov       = 'entrada'
                  AND doc            IN (:docOld, :docNew)
            ");
                $stDelMov->execute([
                    ':unit'   => $system_unit_id,
                    ':docOld' => $oldNumeroNF,
                    ':docNew' => $numeroNF,
                ]);
            } catch (\Throwable $e) {
                // se der erro aqui, só loga/ignora; não quebra a edição da nota
            }

            // normaliza datas para YYYY-MM-DD
            $dataEntradaSimples = $dataEntrada ? substr($dataEntrada, 0, 10) : null;
            $dataEmissaoSimples = $dataEmissao ? substr($dataEmissao, 0, 10) : null;

            // monta payload pro método de estoque
            $payloadEstoque = [
                'nota_id'        => $nota_id,
                'system_unit_id' => $system_unit_id,
                'usuario_id'     => $usuarioId,
                'chave_acesso'   => $chaveAcesso,
                'data_entrada'   => $dataEntradaSimples,
                'data_emissao'   => $dataEmissaoSimples,
                'itens'          => []
            ];

            foreach ($data['itens'] as $idx => $item) {
                $numeroItem = isset($item['numero_item']) ? (int)$item['numero_item'] : ($idx + 1);

                $payloadEstoque['itens'][] = [
                    'numero_item'    => $numeroItem,
                    'codigo_produto' => (int)$item['codigo_produto'],
                    'unidade'        => $item['unidade'],
                    'quantidade'     => (float)$item['quantidade'],
                    'valor_total'    => (float)$item['valor_total'],
                    'valor_unitario' => $item['valor_unitario']
                ];
            }

            // lança de novo as movimentações
            $resultadoEstoque = NotaFiscalEntradaController::lancarItensNotaAvulsaNoEstoque($payloadEstoque);
            // se estiver no mesmo controller, pode usar: self::lancarItensNotaAvulsaNoEstoque($payloadEstoque);

            if (!$resultadoEstoque['success']) {
                return [
                    'success'        => false,
                    'message'        => 'Nota atualizada, porém houve erro ao atualizar a movimentação de estoque: ' . ($resultadoEstoque['message'] ?? 'Erro desconhecido.'),
                    'nota_id'        => $nota_id,
                    'chave_acesso'   => $chaveAcesso,
                    'numero_nf'      => $numeroNF,
                    'serie'          => $serie,
                    'valor_total'    => $valorTotal,
                    'valor_produtos' => $valorProdutos,
                    'estoque'        => $resultadoEstoque,
                ];
            }

            return [
                'success'        => true,
                'message'        => 'Nota avulsa atualizada e movimentação de estoque refeita com sucesso.',
                'nota_id'        => $nota_id,
                'chave_acesso'   => $chaveAcesso,
                'numero_nf'      => $numeroNF,
                'serie'          => $serie,
                'valor_total'    => $valorTotal,
                'valor_produtos' => $valorProdutos,
                'estoque'        => $resultadoEstoque,
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }


}
