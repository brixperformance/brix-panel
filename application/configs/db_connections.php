<?php

declare(strict_types=1);

require_once __DIR__ . '/env_loader.php';

function db_env_value(array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value === false) {
            continue;
        }

        $text = trim((string) $value);
        if ($text !== '') {
            return $text;
        }
    }

    return $default;
}

function get_database_config(string $connection = 'lp'): array
{
    $normalized = strtolower(trim($connection));

    $map = [
        'lp' => [
            'name' => 'brix-lp',
            'host' => ['LP_DB_HOST', 'BRIX_LP_DB_HOST', 'DB_HOST'],
            'dbname' => ['LP_DB_NAME', 'BRIX_LP_DB_NAME', 'DB_NAME'],
            'username' => ['LP_DB_USER', 'BRIX_LP_DB_USER', 'DB_USER'],
            'password' => ['LP_DB_PASS', 'BRIX_LP_DB_PASS', 'DB_PASS'],
            'port' => ['LP_DB_PORT', 'BRIX_LP_DB_PORT', 'DB_PORT'],
            'driver' => ['LP_DB_DRIVER', 'BRIX_LP_DB_DRIVER', 'DB_DRIVER'],
            'charset' => ['LP_DB_CHARSET', 'BRIX_LP_DB_CHARSET', 'DB_CHARSET'],
        ],
        'shop' => [
            'name' => 'brix-shop',
            'host' => ['SHOP_DB_HOST', 'BRIX_SHOP_DB_HOST', 'DB_HOST'],
            'dbname' => ['SHOP_DB_NAME', 'BRIX_SHOP_DB_NAME', 'DB_NAME'],
            'username' => ['SHOP_DB_USER', 'BRIX_SHOP_DB_USER', 'DB_USER'],
            'password' => ['SHOP_DB_PASS', 'BRIX_SHOP_DB_PASS', 'DB_PASS'],
            'port' => ['SHOP_DB_PORT', 'BRIX_SHOP_DB_PORT', 'DB_PORT'],
            'driver' => ['SHOP_DB_DRIVER', 'BRIX_SHOP_DB_DRIVER', 'DB_DRIVER'],
            'charset' => ['SHOP_DB_CHARSET', 'BRIX_SHOP_DB_CHARSET', 'DB_CHARSET'],
        ],
    ];

    if (!isset($map[$normalized])) {
        throw new InvalidArgumentException('Unknown database connection: ' . $connection);
    }

    $configMap = $map[$normalized];

    return [
        'connection_name' => $configMap['name'],
        'host' => db_env_value($configMap['host']),
        'dbname' => db_env_value($configMap['dbname']),
        'username' => db_env_value($configMap['username']),
        'password' => db_env_value($configMap['password']),
        'port' => db_env_value($configMap['port'], '3306'),
        'driver' => db_env_value($configMap['driver'], 'mysql'),
        'charset' => db_env_value($configMap['charset'], 'utf8mb4'),
    ];
}
