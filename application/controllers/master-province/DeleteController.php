<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../models/master-province/Transaction.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx  = new MasterProvinceTransaction($config);
$code = strtoupper(trim((string)($_POST['code'] ?? '')));

if (strlen($code) !== 4) {
    send_text_error('Invalid province code.', 400);
}

$result = $trx->deleteProvince($code);
if (!empty($result['status'])) {
    send_text_success();
}

send_text_error((string) ($result['message'] ?? 'Delete failed'), 500);
