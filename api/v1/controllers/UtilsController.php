<?php
class UtilsController
{
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
        curl_close($ch);
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

}
