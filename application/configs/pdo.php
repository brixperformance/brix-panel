<?php

declare(strict_types=1);

require_once __DIR__ . '/pdo_factory.php';

try {
    $pdo = create_configured_pdo('shop');
} catch (Throwable $e) {
    die('Database connection failed: ' . $e->getMessage());
}
