<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configs/json_response.php';
require_once __DIR__ . '/../../models/Execute.php';
require_once __DIR__ . '/../../models/log-invoice/Transaction.php';
require_once __DIR__ . '/../../models/log-invoice/View.php';
require_once __DIR__ . '/../../configs/invoice_footer_defaults.php';

$config = require_once __DIR__ . '/../../configs/database.php';
$trx    = new InvoiceLogTransaction($config);
$view   = new InvoiceLogView($config);

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$billTo = trim((string) ($_POST['bill_to'] ?? ''));
$shipTo = trim((string) ($_POST['ship_to'] ?? ''));
$itemsJson = trim((string) ($_POST['items_json'] ?? ''));
$discountType = trim((string) ($_POST['discount_type'] ?? 'flat'));
$discountValue = (float) ($_POST['discount_value'] ?? 0);
$discountMax = (float) ($_POST['discount_max'] ?? 0);
$shippingCost = (float) ($_POST['shipping_cost'] ?? 0);
$additionalFee = (float) ($_POST['additional_fee'] ?? 0);
$additionalFeeLabel = trim((string) ($_POST['additional_fee_label'] ?? ''));
$footerNotes = trim((string) ($_POST['footer_notes'] ?? ''));
$footerClosingMessage = trim((string) ($_POST['footer_closing_message'] ?? ''));
$dueDateRaw = trim((string) ($_POST['due_date'] ?? ''));
$dueDate = ($dueDateRaw !== '' && strtotime($dueDateRaw) !== false)
    ? $dueDateRaw
    : date('Y-m-d', strtotime('+3 days'));

if ($id <= 0) {
    send_json_response(['status' => false, 'message' => 'Invalid ID.'], 400);
}

$existingResult = $view->getLogById($id);
$existingRow = $existingResult['data'] ?? null;
if (!$existingRow) {
    send_json_response(['status' => false, 'message' => 'Invoice not found.'], 404);
}

if ($billTo === '') {
    send_json_response(['status' => false, 'message' => 'Bill To harus diisi.'], 422);
}

if ($shipTo === '') {
    send_json_response(['status' => false, 'message' => 'Ship To harus diisi.'], 422);
}

$items = json_decode($itemsJson, true);
if (!is_array($items) || empty($items)) {
    send_json_response(['status' => false, 'message' => 'Items are required.'], 422);
}

$subtotal = 0.0;
$rows = [];

foreach ($items as $item) {
    $name = trim((string) ($item['item'] ?? ''));
    $quantity = (float) ($item['quantity'] ?? 0);
    $rate = (float) ($item['rate'] ?? 0);

    if ($name === '' || $quantity <= 0 || $rate < 0) {
        continue;
    }

    $amount = $quantity * $rate;
    $subtotal += $amount;
    $rows[] = ['item' => $name, 'quantity' => $quantity, 'rate' => $rate, 'amount' => $amount];
}

if (empty($rows)) {
    send_json_response(['status' => false, 'message' => 'At least one valid item is required.'], 422);
}

$existingItemsPayload = json_decode((string) ($existingRow['linv_items_json'] ?? ''), true);
$existingMeta = [];
if (isset($existingItemsPayload['meta']) && is_array($existingItemsPayload['meta'])) {
    $existingMeta = $existingItemsPayload['meta'];
}

if ($additionalFeeLabel === '') {
    $additionalFeeLabel = trim((string) ($existingRow['linv_additional_fee_label'] ?? ''));
    if ($additionalFeeLabel === '' && isset($existingMeta['additional_fee_label'])) {
        $additionalFeeLabel = trim((string) $existingMeta['additional_fee_label']);
    }
}
if ($footerNotes === '') {
    $footerNotes = trim((string) ($existingRow['linv_footer_notes'] ?? ''));
    if ($footerNotes === '' && isset($existingMeta['footer_notes'])) {
        $footerNotes = trim((string) $existingMeta['footer_notes']);
    }
}
if ($footerClosingMessage === '') {
    $footerClosingMessage = trim((string) ($existingRow['linv_footer_closing_message'] ?? ''));
    if ($footerClosingMessage === '' && isset($existingMeta['footer_closing_message'])) {
        $footerClosingMessage = trim((string) $existingMeta['footer_closing_message']);
    }
}

$footerValidationErrors = validate_invoice_footer_content($footerNotes, $footerClosingMessage);
if ($footerValidationErrors !== []) {
    send_json_response([
        'status' => false,
        'message' => implode(' ', $footerValidationErrors),
    ], 422);
}

if ($discountType === 'percent') {
    $discountCost = $subtotal * ($discountValue / 100);
    if ($discountMax > 0) {
        $discountCost = min($discountCost, $discountMax);
    }
} else {
    $discountType = 'flat';
    $discountCost = $discountValue;
}

$total = $subtotal - $discountCost + $shippingCost + $additionalFee;
$result = $trx->updateLog([
    'id' => $id,
    'bill_to' => $billTo,
    'ship_to' => $shipTo,
    'subtotal' => $subtotal,
    'discount' => $discountCost,
    'discount_type' => $discountType,
    'discount_value' => $discountValue,
    'discount_max' => $discountMax,
    'shipping' => $shippingCost,
    'additional_fee' => $additionalFee,
    'additional_fee_label' => $additionalFeeLabel,
    'footer_notes' => $footerNotes,
    'footer_closing_message' => $footerClosingMessage,
    'due_date' => $dueDate,
    'total' => $total,
    'items_json' => json_encode($rows, JSON_UNESCAPED_UNICODE),
]);

send_json_response($result);
