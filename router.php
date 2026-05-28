<?php

declare(strict_types=1);

$mounts = [
	'/preview' => __DIR__ . '/preview',
	'/assets' => __DIR__ . '/assets',
];

$singleFiles = [
	'/favicon.ico' => __DIR__ . '/favicon.ico',
];

$defaultRoot = __DIR__;

function normalize_path(string $path): string
{
	$path = rawurldecode($path);
	$path = preg_replace('#/+#', '/', $path) ?? '/';

	if ($path === '') {
		return '/';
	}

	return $path[0] === '/' ? $path : '/' . $path;
}

function is_safe_path(string $path, string $baseDir): bool
{
	$baseReal = realpath($baseDir);
	$pathReal = realpath($path);

	return $baseReal !== false
		&& $pathReal !== false
		&& strncmp($pathReal, $baseReal, strlen($baseReal)) === 0;
}

function try_file(string $path, string $baseDir): ?string
{
	if (!is_file($path)) {
		return null;
	}

	return is_safe_path($path, $baseDir) ? $path : null;
}

function resolve_from_dir(string $baseDir, string $requestPath): ?string
{
	$trimmed = ltrim($requestPath, '/');
	$candidates = [];

	if ($trimmed === '') {
		$candidates[] = $baseDir . '/index.html';
	} else {
		$candidates[] = $baseDir . '/' . $trimmed;
		$candidates[] = $baseDir . '/' . $trimmed . '.html';
		$candidates[] = $baseDir . '/' . $trimmed . '/index.html';
	}

	foreach ($candidates as $candidate) {
		$file = try_file($candidate, $baseDir);

		if ($file !== null) {
			return $file;
		}
	}

	return null;
}

function detect_content_type(string $file): string
{
	$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

	$type = match ($extension) {
		'html' => 'text/html; charset=UTF-8',
		'txt' => 'text/plain; charset=UTF-8',
		'css' => 'text/css; charset=UTF-8',
		'js', 'mjs' => 'application/javascript; charset=UTF-8',
		'json' => 'application/json; charset=UTF-8',
		'svg' => 'image/svg+xml',
		'xml' => 'application/xml; charset=UTF-8',
		'png' => 'image/png',
		'jpg', 'jpeg' => 'image/jpeg',
		'webp' => 'image/webp',
		'gif' => 'image/gif',
		'ico' => 'image/x-icon',
		'woff' => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf' => 'font/ttf',
		'map' => 'application/json; charset=UTF-8',
		default => null,
	};

	if ($type !== null) {
		return $type;
	}

	if (class_exists('finfo')) {
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$detected = $finfo->file($file);

		if (is_string($detected) && $detected !== '') {
			return $detected;
		}
	}

	return 'application/octet-stream';
}

function send_file(string $file): void
{
	header('Content-Type: ' . detect_content_type($file));
	header('Content-Length: ' . (string) filesize($file));
	readfile($file);
	exit;
}

function send_php(string $file, string $baseDir): void
{
	if (!is_safe_path($file, $baseDir) || !is_file($file)) {
		http_response_code(404);
		echo 'Not Found';
		exit;
	}

	require $file;
	exit;
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = normalize_path(parse_url($requestUri, PHP_URL_PATH) ?: '/');

if (isset($singleFiles[$requestPath])) {
	$file = try_file($singleFiles[$requestPath], dirname($singleFiles[$requestPath]));

	if ($file !== null) {
		send_file($file);
	}
}

foreach ($mounts as $prefix => $baseDir) {
	if ($requestPath === $prefix || str_starts_with($requestPath, $prefix . '/')) {
		$subPath = substr($requestPath, strlen($prefix));
		$file = resolve_from_dir($baseDir, $subPath === false ? '/' : $subPath);

		if ($file !== null) {
			send_file($file);
		}

		http_response_code(404);
		echo 'Not Found';
		exit;
	}
}

$defaultFile = resolve_from_dir($defaultRoot, $requestPath);

if ($defaultFile !== null) {
	send_file($defaultFile);
}

require __DIR__ . '/index.php';
exit;
