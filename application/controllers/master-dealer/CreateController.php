<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../configs/error_logging.php';
require_once __DIR__ . '/../../models/master-dealer/Transaction.php';
require_once __DIR__ . '/../../models/master-dealer/View.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx  = new MasterDealerTransaction($config);
$view = new MasterDealerView($config);

// Required
$island   = trim($_POST['island']   ?? '');
$province = trim($_POST['province'] ?? '');

// Optional (from Step 3)
$name     = trim($_POST['dealer_name']    ?? '');
$type     = trim($_POST['dealer_type']    ?? ''); // 'R' / 'O'
$contact  = trim($_POST['dealer_contact'] ?? '');
$address  = trim($_POST['dealer_address'] ?? '');
$map      = trim($_POST['dealer_map']     ?? '');
$joinDate = trim($_POST['dealer_join_date'] ?? '');

if ($island === '' || $province === '') {
    send_text_error('Missing required fields: island, province.', 400);
}

// Enforce name/type if you want hard requirements:
// if ($name === '' || $type === '') { http_response_code(400); echo "Missing dealer_name / dealer_type"; exit; }

try {
    $result = $trx->createDealer($island, $province, $name, $type, $contact, $address, $map, $joinDate);
    if (!empty($result['status'])) {
        send_text_success();
    }
    send_text_error((string) ($result['message'] ?? 'Failed to create'), 500);
} catch (Throwable $e) {
    app_log_exception($e, 'master_dealer_create');
    send_text_error('DB error', 500);
}
