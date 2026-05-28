<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../configs/json_response.php';
require_once __DIR__ . '/../../configs/env_loader.php';
require_once __DIR__ . '/../../configs/biteship.php';
require_once __DIR__ . '/../../models/BiteshipClient.php';

function regions_base_path(): string
{
    return dirname(__DIR__, 2) . '/resources/data/regions';
}

function read_regions_csv(string $filename): array
{
    static $cache = [];

    if (isset($cache[$filename])) {
        return $cache[$filename];
    }

    $path = regions_base_path() . '/' . $filename;
    if (!is_file($path)) {
        throw new RuntimeException('Region dataset is unavailable.');
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Failed to open region dataset.');
    }

    $rows = [];
    while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if ($data === [null] || $data === false) {
            continue;
        }

        $rows[] = array_map(static fn($value): string => trim((string) $value), $data);
    }
    fclose($handle);

    $cache[$filename] = $rows;

    return $rows;
}

// CSV format - provinces.csv: code,name
function get_region_provinces(): array
{
    $rows = read_regions_csv('provinces.csv');
    $results = [];

    foreach ($rows as $index => $row) {
        if ($index === 0 || count($row) < 2) {
            continue;
        }

        $results[] = [
            'id'   => $row[0],
            'name' => $row[1],
        ];
    }

    usort($results, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    return $results;
}

// CSV format - regencies.csv: id,province_code,name
function get_region_regencies(string $provinceCode): array
{
    $rows = read_regions_csv('regencies.csv');
    $results = [];

    foreach ($rows as $index => $row) {
        if ($index === 0 || count($row) < 3) {
            continue;
        }
        if ($row[1] !== $provinceCode) {
            continue;
        }

        $results[] = [
            'id'   => $row[0],
            'name' => $row[2],
        ];
    }

    usort($results, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    return $results;
}

// CSV format - districts.csv: id,regency_code,name
function get_region_districts(string $regencyCode): array
{
    $rows = read_regions_csv('districts.csv');
    $results = [];

    foreach ($rows as $index => $row) {
        if ($index === 0 || count($row) < 3) {
            continue;
        }
        if ($row[1] !== $regencyCode) {
            continue;
        }

        $results[] = [
            'id'   => $row[0],
            'name' => $row[2],
        ];
    }

    usort($results, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    return $results;
}

function find_region_regency(string $regencyCode): ?array
{
    $rows = read_regions_csv('regencies.csv');
    foreach ($rows as $index => $row) {
        if ($index === 0 || count($row) < 3) {
            continue;
        }
        if ($row[0] === $regencyCode) {
            return [
                'id' => $row[0],
                'name' => $row[2],
                'province_code' => $row[1],
            ];
        }
    }

    return null;
}

function find_region_district(string $districtCode): ?array
{
    $rows = read_regions_csv('districts.csv');
    foreach ($rows as $index => $row) {
        if ($index === 0 || count($row) < 3) {
            continue;
        }
        if ($row[0] === $districtCode) {
            return [
                'id' => $row[0],
                'name' => $row[2],
                'regency_code' => $row[1],
            ];
        }
    }

    return null;
}

function find_region_province(string $provinceCode): ?array
{
    $rows = read_regions_csv('provinces.csv');
    foreach ($rows as $index => $row) {
        if ($index === 0 || count($row) < 2) {
            continue;
        }
        if ($row[0] === $provinceCode) {
            return [
                'id' => $row[0],
                'name' => $row[1],
            ];
        }
    }

    return null;
}

function biteship_area_cache_path(string $query): string
{
    return sys_get_temp_dir() . '/brix-biteship-area-' . sha1($query) . '.json';
}

function get_biteship_areas_for_district(string $districtName, string $regencyName, string $provinceName): array
{
    $query = trim($districtName . ' ' . $regencyName . ' ' . $provinceName);
    $cachePath = biteship_area_cache_path($query);
    $cacheTtl = 86400;

    if (is_file($cachePath) && (time() - filemtime($cachePath)) < $cacheTtl) {
        $cached = json_decode((string) file_get_contents($cachePath), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $rows = search_biteship_areas($query, 25);
    $results = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $rowDistrict = trim((string) ($row['administrative_division_level_3_name'] ?? ''));
        $rowCity = trim((string) ($row['administrative_division_level_2_name'] ?? ''));
        $rowProvince = trim((string) ($row['administrative_division_level_1_name'] ?? ''));

        if ($rowDistrict === '' || strcasecmp($rowDistrict, $districtName) !== 0) {
            continue;
        }
        if ($rowCity === '' || stripos($regencyName, $rowCity) === false) {
            continue;
        }
        if ($rowProvince === '' || strcasecmp($rowProvince, $provinceName) !== 0) {
            continue;
        }

        $postalCode = trim((string) ($row['postal_code'] ?? ''));
        $results[] = [
            'id' => (string) ($row['id'] ?? ''),
            'name' => $postalCode !== '' ? ($districtName . ' - ' . $postalCode) : trim((string) ($row['name'] ?? $districtName)),
            'label' => trim((string) ($row['name'] ?? '')),
            'zip_code' => $postalCode,
        ];
    }

    $seen = [];
    $unique = [];
    foreach ($results as $result) {
        $key = $result['id'] . '|' . $result['zip_code'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $result;
    }

    usort($unique, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    @file_put_contents($cachePath, json_encode($unique));

    return $unique;
}

$config = get_biteship_config();

$level = trim((string) ($_GET['level'] ?? ''));
$parentId = trim((string) ($_GET['parent_id'] ?? ''));

try {
    $results = match ($level) {
        'province' => get_region_provinces(),
        'city' => $parentId !== '' ? get_region_regencies($parentId) : throw new InvalidArgumentException('parent_id is required for city'),
        'district' => $parentId !== '' ? get_region_districts($parentId) : throw new InvalidArgumentException('parent_id is required for district'),
        'subdistrict' => (function () use ($parentId, $config): array {
            if ($parentId === '') {
                throw new InvalidArgumentException('parent_id is required for subdistrict');
            }
            if ($config['api_key'] === '') {
                throw new RuntimeException('Biteship API key is not configured.');
            }

            $district = find_region_district($parentId);
            if (!$district) {
                throw new InvalidArgumentException('District not found.');
            }

            $regency = find_region_regency($district['regency_code']);
            if (!$regency) {
                throw new InvalidArgumentException('City not found.');
            }

            $province = find_region_province($regency['province_code']);
            if (!$province) {
                throw new InvalidArgumentException('Province not found.');
            }

            return get_biteship_areas_for_district($district['name'], $regency['name'], $province['name']);
        })(),
        default => throw new InvalidArgumentException('Invalid address level.'),
    };

    send_json_response(['results' => $results], 200, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    send_json_error($e->getMessage(), $e instanceof InvalidArgumentException ? 422 : 500);
}
