<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configs/json_response.php';
require_once __DIR__ . '/../../models/Execute.php';
require_once __DIR__ . '/../../models/log-invoice/View.php';

$config = require_once __DIR__ . '/../../configs/database.php';
$view = new InvoiceLogView($config);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    send_json_response(['status' => false, 'message' => 'Invalid ID.'], 400);
}

$result = $view->getLogById($id);
$row = $result['data'] ?? null;

if (!$row) {
    send_json_response(['status' => false, 'message' => 'Invoice not found.'], 404);
}

$itemsPayload = json_decode((string) $row['linv_items_json'], true);
if (isset($itemsPayload['items']) && is_array($itemsPayload['items'])) {
    $items = $itemsPayload['items'];
    $itemsMeta = isset($itemsPayload['meta']) && is_array($itemsPayload['meta']) ? $itemsPayload['meta'] : [];
} else {
    $items = is_array($itemsPayload) ? $itemsPayload : [];
    $itemsMeta = [];
}

send_json_response([
    'status' => true,
    'data' => [
        'id' => (int) $row['linv_id'],
        'number' => $row['linv_number'],
        'type' => $row['linv_type'],
        'create_date' => date('F j, Y', strtotime((string) $row['linv_create_date'])),
        'dealer_code' => $row['linv_dealer_code'] ?? '',
        'bill_to' => $row['linv_bill_to'],
        'ship_to' => $row['linv_ship_to'],
        'discount' => (float) ($row['linv_discount'] ?? 0),
        'discount_type' => (string) ($row['linv_discount_type'] ?? 'flat'),
        'discount_value' => (float) ($row['linv_discount_value'] ?? 0),
        'discount_max' => (float) ($row['linv_discount_max'] ?? 0),
        'shipping' => (float) $row['linv_shipping'],
        'additional_fee' => (float) ($row['linv_additional_fee'] ?? 0),
        'additional_fee_label' => (string) ($row['linv_additional_fee_label'] ?? ($itemsMeta['additional_fee_label'] ?? '')),
        'footer_notes' => (string) ($row['linv_footer_notes'] ?? ($itemsMeta['footer_notes'] ?? '')),
        'footer_closing_message' => (string) ($row['linv_footer_closing_message'] ?? ($itemsMeta['footer_closing_message'] ?? '')),
        'due_date' => (string) ($row['linv_due_date'] ?? ''),
        'subtotal' => (float) $row['linv_subtotal'],
        'total' => (float) $row['linv_total'],
        'items' => $items,
    ],
]);
