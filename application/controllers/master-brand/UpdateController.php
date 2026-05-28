<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../models/master-brand/Transaction.php';
require_once __DIR__ . '/../../models/master-brand/View.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx  = new MasterBrandTransaction($config);
$view = new MasterBrandView($config);

// Sanitize input
$id   = $_POST['id']   ?? null;
$name = trim($_POST['name'] ?? '');
$file = trim($_POST['file'] ?? '');
$flag = strtoupper(trim($_POST['flag'] ?? 'N')) === 'Y' ? 'Y' : 'N';

if (!$id || !$name || !$file) {
    send_text_error('Missing required fields.', 400);
}

// (optional) fetch existing row if you still need it:
$existing = $view->getBrandRowById($id);
if (!$existing['status']) { // if your View returns this shape
    send_text_error('Failed to fetch current data: ' . $existing['message'], 500);
}

$result = $trx->updateBrand($id, $name, $file, $flag);
if (!empty($result['status'])) {
    send_text_success();
}

send_text_error((string) ($result['message'] ?? 'Update failed'), 500);
