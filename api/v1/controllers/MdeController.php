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

            // 2) Monta URL da API
            $query = http_build_query([
                'softwarehouse_token' => self::PLUG_TOKEN,
                'cpf_cnpj'            => $cnpjUnidade,
                'date_ini'            => $data_inicial,
                'date_end'            => $data_final,
                'transaction'         => 'received', // recebido
                'mod'                 => 'NFE',      // modelo NFe
            ]);

            $url = self::PLUG_BASE_URL . 'keys?' . $query;

            // 3) Chama a API (GET)
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
            $notas = [];

            if (!is_array($invoices) || empty($invoices)) {
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

            // 4) Para cada key, verifica existência em estoque_nota
            $selNota = $pdo->prepare("
                SELECT id 
                  FROM estoque_nota 
                 WHERE system_unit_id = :system_unit_id 
                   AND chave_acesso = :chave 
                 LIMIT 1
            ");

            foreach ($invoices as $inv) {
                $chave   = $inv['key']            ?? '';
                $numero  = $inv['number']         ?? '';
                $serie   = $inv['serie']          ?? null;
                $emissao = $inv['date_emission']  ?? null; // 'YYYY-MM-DD'
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
                    'data_emissao'     => $emissao,           // YYYY-MM-DD (da API)
                    'valor_total'      => is_numeric($valor) ? (float)$valor : null,
                    'emitente_cnpj'    => $cnpjEmi,
                    'emitente_razao'   => $razao,
                    'emitente_fantasia'=> $fantasia,
                    'status'           => $importada ? 'importada' : 'nao_importada',
                    'estoque_nota_id'  => $estoqueNotaId,     // null se não existe
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
                    'last_id'   => $json['data']['last_id'] ?? null,
                ]
            ];

        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Erro inesperado: ' . $e->getMessage()];
        }
    }

    /**
     * Baixa o arquivo (PDF ou XML) da NF-e na PlugStorage e retorna APENAS o base64.
     *
     * @param int    $system_unit_id
     * @param string $chaveAcesso  44 dígitos
     * @param string $tipo         'pdf' ou 'xml' (case-insensitive)
     * @return array              base64 do arquivo solicitado
     * @throws Exception           em caso de validação ou falha na API
     */
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
                    $resultados[] = [
                        'chave'   => $chave,
                        'success' => false,
                        'step'    => 'json',
                        'message' => $errJ ? "Erro de rede: $errJ" : "HTTP $codeJ",
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

            return [
                'success' => true,
                'message' => 'Processamento concluído.',
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

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
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

            // ===== 1) Validar CNPJ do destinatário x CNPJ da unidade =====
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

            if (!$row) {
                throw new Exception("Unidade ($system_unit_id) não encontrada.");
            }

            $cnpjUnit = $row['cnpj'] ?? null;
            if (!$cnpjUnit) {
                throw new Exception("CNPJ da unidade não cadastrado.");
            }

            $norm = static function ($v) {
                return preg_replace('/\D+/', '', (string)$v);
            };

            if ($norm($cnpjDest) !== $norm($cnpjUnit)) {
                throw new Exception(
                    "CNPJ do destinatário (" . $norm($cnpjDest) .
                    ") não corresponde ao CNPJ da unidade (" . $norm($cnpjUnit) . ")."
                );
            }

            // ===== 2) Prossegue importação =====
            $fornecedorData = $notaJson['fornecedor'] ?? null;
            if (!$fornecedorData) {
                throw new Exception("Dados do fornecedor não enviados");
            }

            $fornecedor_id = self::getCreateFornecedor($system_unit_id, $fornecedorData);

            $chaveAcesso = $notaJson['chave_acesso'] ?? null;
            $numeroNF    = $notaJson['numero_nf'] ?? null;
            $serie       = $notaJson['serie'] ?? null;

            if (!$chaveAcesso || !$numeroNF) {
                throw new Exception("Chave de acesso e número da NF são obrigatórios");
            }

            // Duplicidade (mesma unidade + fornecedor + número + série)
            $stmt = $pdo->prepare("
                SELECT id FROM estoque_nota 
                WHERE system_unit_id = ? AND fornecedor_id = ? AND numero_nf = ? AND serie = ?
            ");
            $stmt->execute([$system_unit_id, $fornecedor_id, $numeroNF, $serie]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Nota já existe para este fornecedor, série e unidade");
            }

            // Inserir nota
            $stmt = $pdo->prepare("
                INSERT INTO estoque_nota 
                (system_unit_id, fornecedor_id, chave_acesso, numero_nf, serie, data_emissao, data_saida, natureza_operacao, valor_total, valor_produtos, valor_frete) 
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
                $notaJson['valor_total'] ?? 0,
                $notaJson['valor_produtos'] ?? 0,
                $notaJson['valor_frete'] ?? 0
            ]);

            $nota_id = (int)$pdo->lastInsertId();

            // Itens
            if (!empty($notaJson['itens']) && is_array($notaJson['itens'])) {
                $stmtItem = $pdo->prepare("
                    INSERT INTO estoque_nota_item
                    (system_unit_id, nota_id, numero_item, codigo_produto, descricao, ncm, cfop, unidade, quantidade, valor_unitario, valor_total, valor_frete)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($notaJson['itens'] as $item) {
                    $stmtItem->execute([
                        $system_unit_id,
                        $nota_id,
                        $item['numero_item'] ?? 0,
                        $item['codigo_produto'] ?? null,
                        $item['descricao'] ?? null,
                        $item['ncm'] ?? null,
                        $item['cfop'] ?? null,
                        $item['unidade'] ?? null,
                        $item['quantidade'] ?? 0,
                        $item['valor_unitario'] ?? 0,
                        $item['valor_total'] ?? 0,
                        $item['valor_frete'] ?? 0
                    ]);
                }
            }

            // ===== DUPLICATAS (opcional no JSON) =====
            if (!empty($notaJson['duplicatas']) && is_array($notaJson['duplicatas'])) {
                $stmtDup = $pdo->prepare("
                    INSERT INTO estoque_nota_duplicata
                      (system_unit_id, nota_id, numero_duplicata, data_vencimento, valor_parcela)
                    VALUES
                      (:unit, :nota, :num, :venc, :valor)
                    ON DUPLICATE KEY UPDATE
                      data_vencimento = VALUES(data_vencimento),
                      valor_parcela   = VALUES(valor_parcela),
                      updated_at      = CURRENT_TIMESTAMP
                ");

                $toISO = static function (?string $s) {
                    if (!$s) return null;
                    $s = trim($s);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s; // YYYY-MM-DD
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {       // DD/MM/YYYY
                        [$d,$m,$y] = explode('/', $s);
                        return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
                    }
                    return null;
                };

                foreach ($notaJson['duplicatas'] as $dup) {
                    $num   = isset($dup['numero_duplicata']) ? (string)$dup['numero_duplicata'] :
                        (isset($dup['nDup']) ? (string)$dup['nDup'] : null);
                    $venc  = isset($dup['data_vencimento']) ? $toISO($dup['data_vencimento']) :
                        (isset($dup['dVenc']) ? $toISO($dup['dVenc']) : null);
                    $valor = isset($dup['valor_parcela']) ? (float)$dup['valor_parcela'] :
                        (isset($dup['vDup']) ? (float)$dup['vDup'] : 0.0);

                    if (!$num || !$venc) { continue; }

                    $stmtDup->execute([
                        ':unit'  => $system_unit_id,
                        ':nota'  => $nota_id,
                        ':num'   => $num,
                        ':venc'  => $venc,
                        ':valor' => $valor
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
}
