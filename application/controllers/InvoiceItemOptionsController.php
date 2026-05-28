<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../configs/json_response.php';
require_once __DIR__ . '/../models/Execute.php';
require_once __DIR__ . '/../models/master-pricelist/View.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    send_json_error('Method not allowed', 405);
}

$config = require __DIR__ . '/../configs/database.php';
$view = new MasterPricelistView($config);
$result = $view->getInvoiceItemOptions();

if (!($result['status'] ?? false)) {
    send_json_error('Invoice item options are unavailable.', 500);
}

$rows = array_map(static function (array $row): array {
    $brand = trim((string) ($row['brand'] ?? ''));
    $type = trim((string) ($row['type'] ?? ''));
    $year = trim((string) ($row['year'] ?? ''));
    $labelParts = array_filter([$brand, $type, $year], static fn(string $value): bool => $value !== '');
    $baseLabel = implode(' ', $labelParts);
    $regular = [
        'id' => ((int) ($row['id'] ?? 0)) . ':hepa',
        'label' => 'Brill Hepa Filter - ' . $baseLabel,
        'brand' => $brand,
        'type' => $type,
        'year' => $year,
        'variant' => 'hepa',
        'reseller_price' => (float) ($row['reseller_price'] ?? 0),
        'retail_price' => (float) ($row['retail_price'] ?? 0),
    ];

    $items = [$regular];
    $carbonReseller = (float) ($row['reseller_price_carbon'] ?? 0);
    $carbonRetail = (float) ($row['retail_price_carbon'] ?? 0);

    if ($carbonReseller > 0 || $carbonRetail > 0) {
        $items[] = [
            'id' => ((int) ($row['id'] ?? 0)) . ':carbon',
            'label' => 'Brill Hepa + Carbon Filter - ' . $baseLabel,
            'brand' => $brand,
            'type' => $type,
            'year' => $year,
            'variant' => 'carbon',
            'reseller_price' => $carbonReseller,
            'retail_price' => $carbonRetail,
        ];
    }

    return $items;
}, $result['data'] ?? []);

$rows = $rows !== []
    ? array_values(array_merge(...$rows))
    : [];

send_json_response(['results' => $rows]);
