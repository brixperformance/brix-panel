<?php
require_once __DIR__ . '/../../configs/controller_response.php';
// index.php already starts the session
require_once __DIR__ . '/../../models/master-dealer/Transaction.php';
require_once __DIR__ . '/../../models/master-dealer/View.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx  = new MasterDealerTransaction($config);
$view = new MasterDealerView($config);

// Inputs
$code     = trim($_POST['dealer_code'] ?? $_POST['id'] ?? ''); // allow either name
$name     = trim($_POST['dealer_name'] ?? '');
$type     = trim($_POST['dealer_type'] ?? ''); // 'R'/'O'
$contact  = trim($_POST['dealer_contact'] ?? '');
$address  = trim($_POST['dealer_address'] ?? '');
$map      = trim($_POST['dealer_map'] ?? '');
$joinDate = trim($_POST['dealer_join_date'] ?? '');
$status   = isset($_POST['dealer_status']) ? ($_POST['dealer_status'] === 'Y' ? 'Y' : 'N')
          : (isset($_POST['status']) ? ($_POST['status'] === 'Y' ? 'Y' : 'N') : 'Y');

if ($code === '') {
    send_text_error('Missing dealer_code', 400);
}
if ($type !== 'R' && $type !== 'O') {
    send_text_error('Invalid dealer_type', 400);
}

// Optionally: ensure exists
// $exists = $view->getDealerRowById($code);
// if (empty($exists['data'])) { http_response_code(404); echo "Dealer not found"; exit; }

$res = $trx->updateDealer($code, $name, $type, $contact, $address, $map, $joinDate, $status);
if (!empty($res['status'])) {
    send_text_success();
}

send_text_error((string) ($res['message'] ?? 'Update failed'), 500);
