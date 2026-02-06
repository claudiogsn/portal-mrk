<?php
date_default_timezone_set("America/Recife");

class UtilsController
{
    public static function getEnvCustom($key) {
        static $env = null;
        if ($env === null) {
            // Ajuste o caminho para onde o .env realmente está (ex: raiz do projeto)
            $path = __DIR__ . '/../../../.env';
            if (file_exists($path)) {
                $env = parse_ini_file($path);
            } else {
                $env = [];
                // Log de erro interno para você saber que o arquivo não foi achado
                error_log("Arquivo .env não encontrado em: " . $path);
            }
        }
        return $env[$key] ?? null;
    }
    public static function httpGet(string $url, array $headers = ['Accept: application/json']): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers
        ]);
        $body = curl_exec($ch);
        $err  = $body === false ? curl_error($ch) : null;
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //curl_close($ch);
        return [$code ?: 0, $body ?: '', $err];
    }

    public static function getClientIpChain(): array
    {
        $ips = [];

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
                $ip = trim($part);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ips[] = $ip;
                }
            }
        }

        if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            $ips[] = $_SERVER['REMOTE_ADDR'];
        }

        if (!empty($_SERVER['SERVER_ADDR']) && filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP)) {
            $ips[] = $_SERVER['SERVER_ADDR'];
        }

        return array_values(array_unique($ips));
    }

    public static function isLocalhostRequest(): bool
    {
        foreach (self::getClientIpChain() as $ip) {
            if ($ip === '127.0.0.1' || $ip === '::1' || stripos($ip, '::ffff:127.0.0.1') === 0) {
                return true;
            }
        }
        if (!empty($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
            return true;
        }
        return false;
    }

    public static function ensureDir(string $dir): bool
    {
        if (is_dir($dir)) return true;
        return @mkdir($dir, 0775, true) || is_dir($dir);
    }

    public static function saveFileOverwrite(string $fullPath, string $content): false|string
    {
        $parent = dirname($fullPath);
        if (!self::ensureDir($parent)) return false;
        return @file_put_contents($fullPath, $content) !== false ? $fullPath : false;
    }

    public static function somenteNumeros(?string $v): string
    {
        return preg_replace('/\D+/', '', (string)$v);
    }

    public static function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public static function extraiChaveDoId(?string $id): ?string
    {
        if (!$id) return null;
        return preg_match('/NFe(\d{44})$/', $id, $m) ? $m[1] : null;
    }

    public static function toISODate(?string $s): ?string
    {
        if (!$s) return null;
        $s = trim($s);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $s)) return substr($s, 0, 10);
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
            [$d, $m, $y] = explode('/', $s);
            return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
        }
        return null;
    }

    public static function uuidv4(): string
    {
        $data = random_bytes(16);
        // version 4
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // variant
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private static function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }

    /**
     * Envia a mensagem para o WhatsApp (WPP)
     */
    public static function sendWhatsapp(string $telefone, string $mensagem): array
    {
        $url = 'https://portal.mrksolucoes.com.br/jobs/run/send-mensage';

        $payload = [
            'mensagem' => $mensagem,
            'telefone' => self::somenteNumeros($telefone) // Remove formatação se houver
        ];

        return self::httpPost($url, $payload);
    }

    /**
     * Helper para requisições POST com JSON
     */
    public static function httpPost(string $url, array $data): array
    {
        $ch = curl_init($url);
        $payload = json_encode($data);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ]
        ]);

        $body = curl_exec($ch);
        $err  = $body === false ? curl_error($ch) : null;
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // curl_close($ch);
        return [$code ?: 0, $body ?: '', $err];
    }


    public static function trackApiToSqs($user, $method, $request, $response, $startTime): void
    {
        // 1. Configurações de Ambiente
        $accessKey = self::getEnvCustom('AWS_ACCESS_KEY_ID');
        $secretKey = self::getEnvCustom('AWS_SECRET_ACCESS_KEY');
        $region    = self::getEnvCustom('AWS_REGION');
        $service   = 'sqs';
        $queueUrl  = self::getEnvCustom('AWS_QUEUE_URL');

        if (!$queueUrl || !$accessKey) {
            error_log("SQS Tracking abortado: Credenciais ausentes.");
            return;
        }

        // 2. Cálculo de Tempo e Limite de Tamanho (Proteção de Carga)
        $execTime = (microtime(true) - $startTime) * 1000;

        // Limite de 60KB para o log (o SQS aceita até 256KB, mas 60KB é seguro para o SQL)
        $maxSize = 60 * 1024;
        $responseJson = json_encode($response);

        if ($responseJson === false || strlen($responseJson) > $maxSize) {
            $logResponse = [
                'info' => 'Response omitido ou muito grande para log',
                'size_bytes' => strlen($responseJson ?: ''),
                'status' => 'too_large'
            ];
        } else {
            $logResponse = $response;
        }

        // 3. Montagem do Payload
        $payload = json_encode([
            'user'    => $user ?? 'anonymous',
            'method'  => $method ?? 'unknown',
            'status'  => http_response_code(),
            'exec_ms' => round($execTime, 2),
            'request' => $request,
            'response' => $logResponse,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'date'    => date('Y-m-d H:i:s')
        ]);

        // 4. AWS Signature v4
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $params = http_build_query([
            'Action' => 'SendMessage',
            'MessageBody' => $payload,
            'Version' => '2012-11-05'
        ]);

        $parsedUrl = parse_url($queueUrl);
        $host = $parsedUrl['host'] ?? '';
        $canonicalUri = $parsedUrl['path'] ?? '/';

        $canonicalHeaders = "host:$host\nx-amz-date:$now\n";
        $signedHeaders = 'host;x-amz-date';
        $payloadHash = hash('sha256', $params);

        $canonicalRequest = "POST\n$canonicalUri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
        $credentialScope = "$date/$region/$service/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n$now\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $sign = function ($key, $msg) {
            return hash_hmac('sha256', (string)$msg, $key, true);
        };

        $kSigning = $sign($sign($sign($sign('AWS4' . $secretKey, $date), $region), $service), 'aws4_request');
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorizationHeader = "AWS4-HMAC-SHA256 Credential=$accessKey/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

        // 5. Envio "Fire and Forget" via cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $queueUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $params,
            CURLOPT_RETURNTRANSFER => true, // Mantemos true para o PHP processar internamente
            CURLOPT_TIMEOUT_MS     => 800,  // Se em 0.8s não for, aborta para não travar o worker
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                "Authorization: $authorizationHeader",
                "x-amz-date: $now",
                "Content-Type: application/x-www-form-urlencoded"
            ]
        ]);

        curl_exec($ch);
    }
}
