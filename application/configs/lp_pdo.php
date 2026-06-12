<?php

declare(strict_types=1);

require_once __DIR__ . '/pdo_factory.php';

function get_lp_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = create_configured_pdo('lp');

    return $pdo;
}
