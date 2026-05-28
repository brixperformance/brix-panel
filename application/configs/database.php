<?php
declare(strict_types=1);

require_once __DIR__ . '/env_loader.php';

return array(
    'host'     => trim((string) (getenv('DB_HOST') ?: '')),
    'dbname'   => trim((string) (getenv('DB_NAME') ?: '')),
    'username' => trim((string) (getenv('DB_USER') ?: '')),
    'password' => trim((string) (getenv('DB_PASS') ?: '')),
    'port'     => trim((string) (getenv('DB_PORT') ?: '3306')),
    'driver'   => trim((string) (getenv('DB_DRIVER') ?: 'mysql'))
);
