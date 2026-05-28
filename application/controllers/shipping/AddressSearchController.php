<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../../configs/json_response.php';
require_once __DIR__ . '/../../configs/env_loader.php';
require_once __DIR__ . '/../../configs/biteship.php';
require_once __DIR__ . '/../../configs/string_utils.php';
require_once __DIR__ . '/../../models/BiteshipClient.php';

$query = trim((string) ($_GET['q'] ?? ''));
if (app_string_length($query) < 3) {
    send_json_response(['results' => []]);
}

$config = get_biteship_config();
if ($config['api_key'] === '') {
    send_json_error('Shipping lookup is not configured.', 503);
}

try {
    $rows    = search_biteship_areas($query, 8);
    $results = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $district = trim((string) ($row['administrative_division_level_3_name'] ?? ''));
        $village  = trim((string) ($row['administrative_division_level_4_name'] ?? ''));
        if ($village === '') {
            $village = $district;
        }

        $results[] = [
            'area_id' => (string) ($row['id'] ?? ''),
            'display_name' => trim((string) ($row['name'] ?? '')),
            'village' => $village,
            'district' => $district,
            'city' => trim((string) ($row['administrative_division_level_2_name'] ?? '')),
            'province' => trim((string) ($row['administrative_division_level_1_name'] ?? '')),
            'postal_code' => trim((string) ($row['postal_code'] ?? '')),
        ];
    }

    send_json_response(['results' => $results]);
} catch (Throwable $e) {
    send_json_error('Address lookup is unavailable.', 502);
}
