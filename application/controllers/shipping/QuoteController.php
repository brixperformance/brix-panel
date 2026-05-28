<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../../configs/json_response.php';
require_once __DIR__ . '/../../configs/env_loader.php';
require_once __DIR__ . '/../../configs/biteship.php';
require_once __DIR__ . '/../../models/BiteshipClient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Method not allowed', 405);
}

$input = json_decode((string) file_get_contents('php://input'), true) ?: [];
$destination = is_array($input['destination'] ?? null) ? $input['destination'] : [];
$deliveryGroup = trim((string) ($input['delivery_group'] ?? 'area_based'));
$areaId = trim((string) ($destination['area_id'] ?? ''));
$postalCode = trim((string) ($destination['postal_code'] ?? ''));
$lat = (float) ($destination['lat'] ?? 0);
$lng = (float) ($destination['lng'] ?? 0);

if ($deliveryGroup === 'point_based') {
    if ($lat === 0.0 || $lng === 0.0) {
        send_json_error('Destination coordinates are required for instant delivery.', 422);
    }
} elseif ($areaId === '' && $postalCode === '') {
    send_json_error('Destination area is required.', 422);
}

$config = get_biteship_config();
if (!$config['enabled']) {
    send_json_error('Shipping is not configured yet.', 503);
}

$weightGrams = max(100, (int) ($input['weight_grams'] ?? $config['default_weight_grams']));

$items = [[
    'name' => 'Invoice Shipment',
    'description' => 'Invoice items',
    'value' => 100000,
    'quantity' => 1,
    'weight' => $weightGrams,
]];

try {
    $couriers = $config['couriers'];
    if ($deliveryGroup === 'point_based') {
        $instantCouriers = ['gojek', 'grab', 'lalamove'];
        $configured = array_values(array_filter(array_map('trim', explode(',', $config['couriers']))));
        $allowed = array_values(array_filter($configured, static fn(string $code): bool => in_array(strtolower($code), $instantCouriers, true)));
        $couriers = implode(',', $allowed);
        if ($couriers === '') {
            throw new RuntimeException('No instant couriers are configured yet.');
        }
    } else {
        $instantCouriers = ['gojek', 'grab', 'lalamove'];
        $configured = array_values(array_filter(array_map('trim', explode(',', $config['couriers']))));
        $allowed = array_values(array_filter($configured, static fn(string $code): bool => !in_array(strtolower($code), $instantCouriers, true)));
        $couriers = implode(',', $allowed);
        if ($couriers === '') {
            throw new RuntimeException('No regular couriers are configured yet.');
        }
    }

    $pricing = calculate_biteship_rates(
        ['area_id' => $areaId, 'postal_code' => $postalCode, 'lat' => $lat, 'lng' => $lng],
        $items,
        $couriers
    );

    $options = [];
    foreach ($pricing as $row) {
        if (!is_array($row)) {
            continue;
        }

        $fee = (int) round((float) ($row['price'] ?? 0));
        if ($fee <= 0) {
            continue;
        }

        $courierCode = strtolower(trim((string) ($row['courier_code'] ?? 'courier')));
        $courierName = trim((string) ($row['courier_name'] ?? $row['company'] ?? 'Courier'));
        $service = trim((string) ($row['courier_service_name'] ?? $row['courier_service_code'] ?? 'service'));
        $serviceCode = strtolower(trim((string) ($row['courier_service_code'] ?? 'service')));
        $eta = trim((string) ($row['duration'] ?? ''));

        $options[] = [
            'code' => $courierCode . ':' . $serviceCode,
            'courier_code' => $courierCode,
            'courier_name' => $courierName,
            'service' => $service,
            'label' => trim($courierName . ' ' . $service),
            'fee' => $fee,
            'eta' => $eta ?: '-',
        ];
    }

    usort($options, static fn(array $a, array $b): int => $a['fee'] <=> $b['fee']);

    send_json_response(['options' => $options]);
} catch (Throwable $e) {
    send_json_error($e->getMessage(), 502);
}
