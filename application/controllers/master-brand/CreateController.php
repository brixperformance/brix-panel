<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../models/master-brand/Transaction.php';
$config = require_once __DIR__ . '/../../configs/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['logged_in'])) {
    send_text_error('Forbidden', 403);
}

// inputs
$name = trim($_POST['name'] ?? '');
$file = trim($_POST['file'] ?? '');
$flag = strtoupper(trim($_POST['flag'] ?? 'N')) === 'Y' ? 'Y' : 'N';

if ($name === '' || $file === '') {
    send_text_error('Missing required fields.', 400);
}

$trx = new MasterBrandTransaction($config);
$result = $trx->insertBrand($name, $file, $flag);

if (!empty($result['status'])) {
    send_text_success();
}

send_text_error($result['message'] ?? 'Insert failed', 500);
