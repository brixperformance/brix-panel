<?php

declare(strict_types=1);

function product_display_label(string $typeCode, string $typeName = ''): string
{
    $map = [
        'brake_pad'        => 'Street-Sport',
        'disc_street'      => 'Street Series',
        'disc_competition' => 'Competition Series',
    ];

    if (isset($map[$typeCode])) {
        return $map[$typeCode];
    }

    $label = trim($typeName);
    $label = preg_replace('/\s+(rotors?|brake\s*pad)s?$/i', '', $label) ?: $label;

    return $label !== '' ? $label : 'BRIX Performance';
}

function product_display_position(string $typeCode, ?string $fitmentSizing): string
{
    if ($typeCode === 'brake_pad') {
        return 'Brake Pad';
    }

    $fitment = strtolower(trim((string) $fitmentSizing));
    if (strpos($fitment, 'rear') !== false) {
        return 'Rear Rotors';
    }

    if (strpos($fitment, 'front') !== false) {
        return 'Front Rotors';
    }

    if (str_starts_with($typeCode, 'disc_')) {
        return 'Rotors';
    }

    return 'Product';
}

function product_display_vehicle(?string $vehicleText): string
{
    $vehicle = trim((string) $vehicleText);
    $vehicle = preg_replace('/\s+/', ' ', $vehicle) ?: $vehicle;

    return $vehicle !== '' ? $vehicle : 'Vehicle Compatibility';
}

function product_display_lines(array $product): array
{
    $line1 = product_display_label(
        (string) ($product['type_code'] ?? ''),
        (string) ($product['type_name'] ?? '')
    );
    $line2 = product_display_position(
        (string) ($product['type_code'] ?? ''),
        isset($product['fitment_sizing']) ? (string) $product['fitment_sizing'] : null
    );
    $line3 = product_display_vehicle(isset($product['vehicle_text']) ? (string) $product['vehicle_text'] : null);

    return array_values(array_filter([$line1, $line2, $line3], static function (string $line): bool {
        return $line !== '';
    }));
}

function product_display_title(array $product): string
{
    $title = trim((string) ($product['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    return implode(' ', product_display_lines($product));
}
