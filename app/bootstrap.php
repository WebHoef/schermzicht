<?php

declare(strict_types=1);

if (defined('SZ_BOOTSTRAPPED')) {
    return;
}

define('SZ_BOOTSTRAPPED', true);

/**
 * Loads key=value entries from a .env style file into runtime env.
 */
function sz_load_env(string $envPath): void
{
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
            continue;
        }

        $parts = explode('=', $trimmedLine, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        if ($name === '' || !preg_match('/^[A-Z0-9_]+$/', $name)) {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }

        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }

        if (!array_key_exists($name, $_SERVER)) {
            $_SERVER[$name] = $value;
        }
    }
}

function sz_env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value !== false) {
        return (string) $value;
    }

    if (array_key_exists($name, $_ENV)) {
        return (string) $_ENV[$name];
    }

    if (array_key_exists($name, $_SERVER)) {
        return (string) $_SERVER[$name];
    }

    return $default;
}

sz_load_env(__DIR__ . '/../.env');

$appEnv = sz_env('APP_ENV', 'production');
if ($appEnv === 'production') {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
}

error_reporting(E_ALL);

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (($_SERVER['SERVER_PORT'] ?? null) === '443')
);

if ($isHttps && !headers_sent()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start([
        'use_strict_mode' => 1,
        'cookie_httponly' => 1,
        'cookie_secure' => $isHttps ? 1 : 0,
        'cookie_samesite' => 'Lax',
    ]);
}
