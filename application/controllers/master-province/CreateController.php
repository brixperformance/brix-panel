<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../models/master-province/Transaction.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx = new MasterProvinceTransaction($config);

$islandCode = strtoupper(trim((string)($_POST['island_code'] ?? '')));
$suffix     = strtoupper(trim((string)($_POST['suffix']      ?? '')));
$name       = trim((string)($_POST['name']   ?? ''));
$status     = strtoupper(trim((string)($_POST['status'] ?? 'Y'))) === 'Y' ? 'Y' : 'N';

if (strlen($islandCode) !== 2) {
    send_text_error('Invalid island code.', 400);
}

if (strlen($suffix) !== 2 || !ctype_alpha($suffix)) {
    send_text_error('Province suffix must be exactly 2 letters.', 400);
}

if ($name === '') {
    send_text_error('Province name is required.', 400);
}

$code   = $islandCode . $suffix;
$result = $trx->insertProvince($code, $islandCode, $name, $status);
if (!empty($result['status'])) {
    send_text_success();
}

send_text_error((string) ($result['message'] ?? 'Create failed'), 500);
