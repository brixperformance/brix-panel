<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../configs/string_utils.php';
require_once __DIR__ . '/../../models/master-pricelist/Transaction.php';
require_once __DIR__ . '/../../models/master-pricelist/View.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx  = new MasterPricelistTransaction($config);
$view = new MasterPricelistView($config);

$brandId  = isset($_POST['brand']) ? (int)$_POST['brand'] : 0;
$type     = trim($_POST['type'] ?? '');
$year     = trim($_POST['year'] ?? '');
$reseller = trim($_POST['reseller'] ?? '');
$retail   = trim($_POST['retail'] ?? '');
$resellerCarbon = trim($_POST['reseller_carbon'] ?? '');
$retailCarbon   = trim($_POST['retail_carbon'] ?? '');

if ($brandId <= 0 || $type === '' || $year === '' || $reseller === '' || $retail === '' || $resellerCarbon === '' || $retailCarbon === '') {
    send_text_error('Missing required fields.', 400);
}
if (!is_numeric($reseller) || !is_numeric($retail) || !is_numeric($resellerCarbon) || !is_numeric($retailCarbon)) {
    send_text_error('Invalid price format.', 400);
}
if (app_string_length($year) > 100) {
    send_text_error('Year / range is too long.', 400);
}

if ($view->catalogExistsById($brandId, $type, $year)) {
    send_text_error('Duplicate entry. This car already exists.', 409);
}

$result = $trx->createCatalog($brandId, $type, $year, $reseller, $retail, $resellerCarbon, $retailCarbon);
if (!empty($result['status'])) {
    send_text_success();
}

send_text_error((string) ($result['message'] ?? 'Failed to create'), 500);
