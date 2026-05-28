<?php

declare(strict_types=1);

require_once __DIR__ . '/../configs/biteship.php';

function biteship_request(string $method, string $path, array $query = [], ?array $payload = null): array
{
    $config = get_biteship_config();
    if ($config['api_key'] === '') {
        throw new RuntimeException('Biteship API key is not configured.');
    }

    $url = $config['base_url'] . $path;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'Accept: application/json',
        'Authorization: ' . $config['api_key'],
    ];
    $content = '';
    if ($payload !== null) {
        $content = json_encode($payload);
        if ($content === false) {
            throw new RuntimeException('Failed to encode Biteship request payload.');
        }
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => $config['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => $config['verify_ssl'] ? 2 : 0,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($content !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException(curl_error($ch) ?: 'Unknown cURL error');
        }
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    } else {
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $content,
                'ignore_errors' => true,
                'timeout'       => 20,
            ],
            'ssl' => [
                'verify_peer'      => $config['verify_ssl'],
                'verify_peer_name' => $config['verify_ssl'],
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new RuntimeException('HTTP request failed');
        }
        $statusCode = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $statusCode = (int) $m[1];
                break;
            }
        }
    }

    $decoded = json_decode($raw, true);
    return [
        'status_code' => $statusCode,
        'body'        => is_array($decoded) ? $decoded : [],
    ];
}

function biteship_error_message(array $response, string $fallback): string
{
    $body = is_array($response['body'] ?? null) ? $response['body'] : [];
    foreach ([$body['error'] ?? null, $body['message'] ?? null, $body['errors'][0]['message'] ?? null] as $c) {
        $msg = trim((string) $c);
        if ($msg !== '') return $msg;
    }
    return $fallback;
}

function search_biteship_areas(string $query, int $limit = 8): array
{
    $response = biteship_request('GET', '/v1/maps/areas', [
        'countries' => 'ID',
        'input'     => $query,
        'type'      => 'single',
    ]);
    if ($response['status_code'] >= 400 || (($response['body']['success'] ?? true) === false)) {
        throw new RuntimeException(biteship_error_message($response, 'Biteship area search failed.'));
    }
    $areas = $response['body']['areas'] ?? [];
    return array_slice(array_values(is_array($areas) ? $areas : []), 0, max(1, $limit));
}

function calculate_biteship_rates(array $destination, array $items, string $couriers): array
{
    $config = get_biteship_config();
    $payload = ['couriers' => $couriers, 'items' => $items];
    $areaId     = trim((string) ($destination['area_id'] ?? ''));
    $postalCode = trim((string) ($destination['postal_code'] ?? ''));
    $lat        = (float) ($destination['lat'] ?? 0);
    $lng        = (float) ($destination['lng'] ?? 0);
    $usesDestinationCoordinates = $lat !== 0.0 && $lng !== 0.0;

    if ($usesDestinationCoordinates && $config['origin_lat'] !== 0.0 && $config['origin_lng'] !== 0.0) {
        $payload['origin_latitude']  = $config['origin_lat'];
        $payload['origin_longitude'] = $config['origin_lng'];
    } elseif ($config['origin_area_id'] !== '') {
        $payload['origin_area_id'] = $config['origin_area_id'];
    } elseif ($config['origin_postal_code'] !== '') {
        $payload['origin_postal_code'] = (int) $config['origin_postal_code'];
    } elseif ($config['origin_lat'] !== 0.0 && $config['origin_lng'] !== 0.0) {
        $payload['origin_latitude']  = $config['origin_lat'];
        $payload['origin_longitude'] = $config['origin_lng'];
    } else {
        throw new RuntimeException('Biteship origin location is not configured.');
    }

    if ($areaId !== '') {
        $payload['destination_area_id'] = $areaId;
    } elseif ($postalCode !== '') {
        $payload['destination_postal_code'] = (int) $postalCode;
    } elseif ($lat !== 0.0 && $lng !== 0.0) {
        $payload['destination_latitude']  = $lat;
        $payload['destination_longitude'] = $lng;
    } else {
        throw new RuntimeException('Biteship destination is incomplete.');
    }

    $response = biteship_request('POST', '/v1/rates/couriers', [], $payload);
    if ($response['status_code'] >= 400 || (($response['body']['success'] ?? true) === false)) {
        throw new RuntimeException(biteship_error_message($response, 'Biteship shipping rates request failed.'));
    }

    return $response['body']['pricing'] ?? [];
}
