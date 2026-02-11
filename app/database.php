<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Returns a shared PDO instance with secure defaults.
 */
function sz_db(): \PDO
{
    static $pdo = null;
    if ($pdo instanceof \PDO) {
        return $pdo;
    }

    $host = sz_env('DB_HOST');
    $port = sz_env('DB_PORT', '3306');
    $database = sz_env('DB_NAME');
    $username = sz_env('DB_USER');
    $password = sz_env('DB_PASS');

    if (
        $host === null ||
        $database === null ||
        $username === null ||
        $password === null
    ) {
        throw new \RuntimeException(
            'Database omgevingsvariabelen ontbreken. Stel DB_HOST, DB_PORT, DB_NAME, DB_USER en DB_PASS in.'
        );
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    try {
        $pdo = new \PDO(
            $dsn,
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        );
    } catch (\PDOException $exception) {
        throw new \RuntimeException(
            'Databaseverbinding mislukt. Controleer de serverconfiguratie.',
            0,
            $exception
        );
    }

    return $pdo;
}
