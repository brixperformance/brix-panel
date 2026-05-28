<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../models/master-island/Transaction.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx = new MasterIslandTransaction($config);

$code   = strtoupper(trim((string)($_POST['code']   ?? '')));
$name   = trim((string)($_POST['name']   ?? ''));
$status = strtoupper(trim((string)($_POST['status'] ?? 'Y'))) === 'Y' ? 'Y' : 'N';

if (strlen($code) !== 2 || !ctype_alpha($code)) {
    send_text_error('Island code must be exactly 2 letters.', 400);
}

if ($name === '') {
    send_text_error('Island name is required.', 400);
}

$result = $trx->insertIsland($code, $name, $status);
if (!empty($result['status'])) {
    send_text_success();
}

send_text_error((string) ($result['message'] ?? 'Create failed'), 500);
