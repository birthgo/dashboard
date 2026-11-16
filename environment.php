<?php
declare(strict_types=1);

final class DashboardEnvironment
{
    public static function parse(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $values = [];
        foreach (preg_split('/\r\n|\n|\r/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/D', $line, $m)) {
                throw new RuntimeException('Invalid Dashboard environment format');
            }
            if (array_key_exists($m[1], $values)) throw new RuntimeException('Duplicate Dashboard environment key');
            $raw = trim($m[2]);
            if (str_starts_with($raw, "'")) {
                if (!preg_match("/^'([^']*)'\\s*(?:#.*)?$/D", $raw, $q)) throw new RuntimeException('Invalid environment quoting');
                $value = $q[1];
            } elseif (str_starts_with($raw, '"')) {
                if (!preg_match('/^"((?:[^"\\\\]|\\\\.)*)"\s*(?:#.*)?$/D', $raw, $q)) throw new RuntimeException('Invalid environment quoting');
                $value = preg_replace_callback('/\\\\([\\\\"nr])/', static fn($x) => match ($x[1]) {
                    'n' => "\n", 'r' => "\r", default => $x[1],
                }, $q[1]);
            } else {
                $value = rtrim(preg_replace('/\s+#.*$/', '', $raw) ?? $raw);
                if (preg_match('/\s/', $value)) throw new RuntimeException('Environment values with spaces must be quoted');
            }
            if (str_contains($value, "\0")) throw new RuntimeException('Invalid environment value');
            $values[$m[1]] = $value;
        }
        foreach (['DB_HOST','DB_USER','DB_PASS','DB_NAME','DASHBOARD_PRIVATE_DIR'] as $key) {
            if (!isset($values[$key]) || trim($values[$key]) === '' || str_contains($values[$key], 'CHANGE_ME')) {
                throw new RuntimeException('Incomplete Dashboard environment');
            }
        }
        foreach (['DB_PORT'] as $key) {
            $port = filter_var($values[$key] ?? '3306', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>65535]]);
            if ($port === false) throw new RuntimeException('Invalid database port');
            $values[$key] = $port;
        }
        if (($values['APP_ENV'] ?? 'production') !== 'production') throw new RuntimeException('Dashboard migration requires production mode');
        $values['APP_TIMEZONE'] ??= 'Asia/Bangkok';
        new DateTimeZone($values['APP_TIMEZONE']);
        return $values;
    }

    public static function load(?string $file = null): array
    {
        $expected = PHP_OS_FAMILY === 'Windows'
            ? 'D:/webapps-secrets/dashboard.env' : '/etc/webapps/dashboard.env';
        $explicit = array_filter([$file, getenv('APP_ENV_FILE')], static fn($p) => $p !== false && $p !== null && $p !== '');
        $path = $explicit ? reset($explicit) : $expected;
        $normalize = static function (string $p): string {
            $p = str_replace('\\', '/', $p);
            return PHP_OS_FAMILY === 'Windows' ? strtolower($p) : $p;
        };
        if ($normalize($path) !== $normalize($expected)) throw new RuntimeException('Invalid Dashboard environment path');
        foreach ($explicit as $candidate) {
            if ($normalize($candidate) !== $normalize($expected)) throw new RuntimeException('Conflicting or nonstandard environment path');
        }
        $real = realpath($path);
        if (!$real || !is_file($real) || !is_readable($real) || $normalize($real) !== $normalize($expected)) {
            throw new RuntimeException('Dashboard external environment unavailable');
        }
        foreach ([dirname(__DIR__), $_SERVER['DOCUMENT_ROOT'] ?? ''] as $root) {
            $root = $root !== '' ? realpath($root) : false;
            if ($root && str_starts_with($normalize($real), rtrim($normalize($root), '/') . '/')) {
                throw new RuntimeException('Dashboard environment must be outside webroot');
            }
        }
        $text = @file_get_contents($real);
        if ($text === false) throw new RuntimeException('Dashboard external environment unreadable');
        return self::parse($text);
    }
}
