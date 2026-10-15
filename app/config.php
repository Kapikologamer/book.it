<?php
declare(strict_types=1);

function config(string $key, string $fallback = ''): string
{
    static $file;
    if ($file === null) {
        $path = dirname(__DIR__) . '/.env';
        $file = is_file($path) ? parse_ini_file($path, false, INI_SCANNER_RAW) : [];
        if ($file === false) {
            throw new RuntimeException('Invalid configuration');
        }
    }
    $value = getenv($key);
    return $value !== false ? $value : (string) ($file[$key] ?? $fallback);
}

function app_key(): string
{
    $key = config('APP_KEY');
    if (strlen($key) < 32) {
        throw new RuntimeException('APP_KEY is missing');
    }
    return $key;
}

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . config('DB_HOST', '127.0.0.1') . ';port=' . config('DB_PORT', '3306')
            . ';dbname=' . config('DB_NAME', 'book_it') . ';charset=utf8mb4',
            config('DB_USER', 'root'), config('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
