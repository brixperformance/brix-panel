<?php

declare(strict_types=1);

function get_shop_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    require_once __DIR__ . '/env_loader.php';

    $host = trim((string) (getenv('DB_HOST') ?: '127.0.0.1'));
    $db   = trim((string) (getenv('DB_NAME') ?: ''));
    $user = trim((string) (getenv('DB_USER') ?: ''));
    $pass = trim((string) (getenv('DB_PASS') ?: ''));
    $port = trim((string) (getenv('DB_PORT') ?: '3306'));
    $dsn  = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}
