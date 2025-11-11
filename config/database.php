<?php

declare(strict_types=1);

use PDO;

/**
 * Lightweight .env loader for local credentials.
 */
(function (string $envPath): void {
    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($value !== '' && $value[0] === '"' && substr($value, -1) === '"') {
            $value = trim($value, '"');
        }

        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
})(__DIR__ . '/../.env');

function envValue(string $key, $default = null)
{
    $value = $_ENV[$key] ?? getenv($key);

    return $value === false || $value === null ? $default : $value;
}

function db(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        envValue('DB_HOST', '127.0.0.1'),
        envValue('DB_PORT', '3306'),
        envValue('DB_NAME', 'smart_library')
    );

    $pdo = new PDO(
        $dsn,
        envValue('DB_USER', 'root'),
        envValue('DB_PASS', '')
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    return $pdo;
}
