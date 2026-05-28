<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../models/master-island/Transaction.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx  = new MasterIslandTransaction($config);
$code = strtoupper(trim((string)($_POST['code'] ?? '')));

if (strlen($code) !== 2) {
    send_text_error('Invalid island code.', 400);
}

$result = $trx->deleteIsland($code);
if (!empty($result['status'])) {
    send_text_success();
}

send_text_error((string) ($result['message'] ?? 'Delete failed'), 500);
