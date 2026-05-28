<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../models/master-province/Transaction.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx    = new MasterProvinceTransaction($config);
$code   = strtoupper(trim((string)($_POST['code']   ?? '')));
$name   = trim((string)($_POST['name']   ?? ''));
$status = strtoupper(trim((string)($_POST['status'] ?? 'N'))) === 'Y' ? 'Y' : 'N';

if (strlen($code) !== 4) {
    send_text_error('Invalid province code.', 400);
}
if ($name === '') {
    send_text_error('Province name is required.', 400);
}

$result = $trx->updateProvince($code, $name, $status);
if (!empty($result['status'])) {
    send_text_success();
}

send_text_error((string) ($result['message'] ?? 'Update failed'), 500);
