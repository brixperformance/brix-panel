<?php

declare(strict_types=1);

function get_biteship_config(): array
{
    $apiKey           = trim((string) (getenv('BITESHIP_API_KEY')              ?: ''));
    $baseUrl          = rtrim(trim((string) (getenv('BITESHIP_BASE_URL')       ?: 'https://api.biteship.com')), '/');
    $originAreaId     = trim((string) (getenv('BITESHIP_ORIGIN_AREA_ID')       ?: ''));
    $originLabel      = trim((string) (getenv('BITESHIP_ORIGIN_LABEL')         ?: 'Brill Hepa Filter'));
    $couriers         = trim((string) (getenv('BITESHIP_COURIERS')             ?: 'jne,jnt'));
    $originLat        = (float) (getenv('BITESHIP_ORIGIN_LAT')                 ?: '0');
    $originLng        = (float) (getenv('BITESHIP_ORIGIN_LNG')                 ?: '0');
    $originPostal     = trim((string) (getenv('BITESHIP_ORIGIN_POSTAL_CODE')   ?: ''));
    $defaultWeight    = max(100, (int) (getenv('BITESHIP_DEFAULT_WEIGHT_GRAMS') ?: '3000'));
    $verifySslRaw     = strtolower(trim((string) (getenv('BITESHIP_VERIFY_SSL') ?: 'true')));
    $verifySsl        = !in_array($verifySslRaw, ['0', 'false', 'no', 'off'], true);
    $hasOrigin        = $originAreaId !== ''
        || ($originLat !== 0.0 && $originLng !== 0.0)
        || $originPostal !== '';

    return [
        'api_key'              => $apiKey,
        'base_url'             => $baseUrl,
        'origin_area_id'       => $originAreaId,
        'origin_label'         => $originLabel,
        'origin_lat'           => $originLat,
        'origin_lng'           => $originLng,
        'origin_postal_code'   => $originPostal,
        'couriers'             => $couriers,
        'default_weight_grams' => $defaultWeight,
        'verify_ssl'           => $verifySsl,
        'enabled'              => $apiKey !== '' && $couriers !== '' && $hasOrigin,
    ];
}
