<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';

bootstrap_page(null, false);

$orderId = trim((string) ($_GET['order_id'] ?? ''));

if ($orderId === '' || !preg_match('/^INV-\d{8}-\d{6}-\d{4}$/', $orderId)) {
    http_response_code(400);
    echo 'Invalid order_id';
    exit;
}

header('Location: /shop/orders/preview?order_id=' . rawurlencode($orderId) . '&autodownload=1');
exit;
