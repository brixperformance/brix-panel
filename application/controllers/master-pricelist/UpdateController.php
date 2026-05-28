<?php
require_once __DIR__ . '/../../configs/controller_response.php';
require_once __DIR__ . '/../../models/master-pricelist/Transaction.php';
require_once __DIR__ . '/../../models/master-pricelist/View.php';
$config = require_once __DIR__ . '/../../configs/database.php';

$trx = new MasterPricelistTransaction($config);
$view = new MasterPricelistView($config);

$id = $_POST['id'] ?? null;
$type = trim($_POST['type'] ?? '');
$year = trim($_POST['year'] ?? '');
$reseller = trim($_POST['reseller'] ?? '');
$retail = trim($_POST['retail'] ?? '');
$resellerCarbon = trim($_POST['reseller_carbon'] ?? '');
$retailCarbon = trim($_POST['retail_carbon'] ?? '');

// Normalize flags to Y/N.
$status = strtoupper(trim($_POST['status'] ?? ''));
$status = ($status === 'Y') ? 'Y' : 'N';
$status_stock = strtoupper(trim($_POST['status_stock'] ?? ''));
$status_stock = ($status_stock === 'Y') ? 'Y' : 'N';

if (!$id || !$type || !$year || $reseller === '' || $retail === '' || $resellerCarbon === '' || $retailCarbon === '') {
    send_text_error('Missing required fields.', 400);
}
if (!is_numeric($reseller) || !is_numeric($retail) || !is_numeric($resellerCarbon) || !is_numeric($retailCarbon)) {
    send_text_error('Invalid price format.', 400);
}

$existing = $view->getCatalogRowById($id);
if (!$existing['status']) {
    send_text_error('Failed to fetch current data: ' . $existing['message'], 500);
}

$current = $existing['data'];
if (!$current) {
    send_text_error('Record not found.', 404);
}

$hasChanges = (
    $current['type'] !== $type ||
    $current['year'] !== $year ||
    (float) $current['reseller'] != (float) $reseller ||
    (float) $current['retail'] != (float) $retail ||
    (float) $current['reseller_carbon'] != (float) $resellerCarbon ||
    (float) $current['retail_carbon'] != (float) $retailCarbon ||
    $current['status'] !== $status ||
    $current['status_stock'] !== $status_stock
);

if (!$hasChanges) {
    send_text_error('No changes detected.', 409);
}

$result = $trx->updateCatalog($id, $type, $year, $reseller, $retail, $resellerCarbon, $retailCarbon, $status, $status_stock, $current);
if (!empty($result['status'])) {
    send_text_success();
}

send_text_error((string) ($result['message'] ?? 'Update failed'), 500);
