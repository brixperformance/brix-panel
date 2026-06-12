<?php

declare(strict_types=1);

require_once __DIR__ . '/db_connections.php';

function create_configured_pdo(string $connection = 'lp'): PDO
{
    $config = get_database_config($connection);

    $host = $config['host'] ?? '';
    $db = $config['dbname'] ?? '';
    $user = $config['username'] ?? '';
    $pass = $config['password'] ?? '';
    $port = $config['port'] ?? '3306';
    $driver = $config['driver'] ?? 'mysql';
    $charset = $config['charset'] ?? 'utf8mb4';

    if ($host === '' || $db === '' || $user === '') {
        throw new RuntimeException(sprintf(
            'Database connection "%s" is not configured. Please fill the required env values.',
            $config['connection_name'] ?? $connection
        ));
    }

    $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $driver, $host, $port, $db, $charset);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    if ($driver === 'mysql') {
        $pdo->exec("SET time_zone = '+07:00'");
    }

    return $pdo;
}
