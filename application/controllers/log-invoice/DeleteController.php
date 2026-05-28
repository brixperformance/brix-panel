<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../models/log-invoice/Transaction.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx = new InvoiceLogTransaction($config);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    send_text_error('Invalid ID.', 400);
}

$result = $trx->deleteLog($id);
if (!empty($result['status'])) {
    send_text_success();
}

send_text_error((string) ($result['message'] ?? 'Delete failed'), 500);
