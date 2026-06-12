<?php

declare(strict_types=1);

require_once __DIR__ . '/env_loader.php';

function article_media_env_value(array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value === false) {
            continue;
        }

        $text = trim((string) $value);
        if ($text !== '') {
            return $text;
        }
    }

    return $default;
}

function article_media_is_absolute_path(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if ($path[0] === '/' || $path[0] === '\\') {
        return true;
    }

    return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
}

function article_media_normalize_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        $path = 'uploads/photos/articles';
    }

    if (!article_media_is_absolute_path($path)) {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . trim($path, "\\/");
    }

    return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function article_media_normalize_base_url(string $baseUrl): string
{
    $baseUrl = trim($baseUrl);
    if ($baseUrl === '') {
        return '/uploads/photos/articles';
    }

    if (preg_match('~^https?://~i', $baseUrl)) {
        return rtrim($baseUrl, '/');
    }

    return '/' . trim($baseUrl, '/');
}

function get_article_media_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $config = [
        'root' => article_media_normalize_path(article_media_env_value([
            'ARTICLE_MEDIA_ROOT',
            'ARTICLE_UPLOAD_ROOT',
        ], 'uploads/photos/articles')),
        'base_url' => article_media_normalize_base_url(article_media_env_value([
            'ARTICLE_MEDIA_BASE_URL',
            'ARTICLE_UPLOAD_BASE_URL',
        ], '/uploads/photos/articles')),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    ];

    return $config;
}

function article_media_directory_name(int|string $ordinal): string
{
    $number = max(0, (int) $ordinal);
    return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
}

function article_media_public_url(int|string $ordinal, string $filename): string
{
    $file = trim($filename);
    if ($file === '') {
        return '';
    }

    $config = get_article_media_config();
    return rtrim((string) $config['base_url'], '/') . '/' . article_media_directory_name($ordinal) . '/' . rawurlencode($file);
}

