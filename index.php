<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

define('BASE_PATH', __DIR__);

function normalize_public_path(string $path): string
{
	$path = rawurldecode($path);
	$path = preg_replace('#/+#', '/', $path) ?? '/';

	if ($path === '') {
		return '/';
	}

	return $path[0] === '/' ? $path : '/' . $path;
}

function is_safe_public_file(string $path, string $baseDir): bool
{
	$baseReal = realpath($baseDir);
	$pathReal = realpath($path);

	return $baseReal !== false
		&& $pathReal !== false
		&& strncmp($pathReal, $baseReal, strlen($baseReal)) === 0;
}

function try_public_file(string $path, string $baseDir): ?string
{
	if (!is_file($path)) {
		return null;
	}

	return is_safe_public_file($path, $baseDir) ? $path : null;
}

function resolve_public_file(string $baseDir, string $requestPath): ?string
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
		$file = try_public_file($candidate, $baseDir);
		if ($file !== null) {
			return $file;
		}
	}

	return null;
}

function detect_public_content_type(string $file): string
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

function send_public_file(string $file): void
{
	header('Content-Type: ' . detect_public_content_type($file));
	header('Content-Length: ' . (string) filesize($file));
	readfile($file);
	exit;
}

function try_serve_public_asset(string $requestPath): void
{
	$mounts = [
		'/assets' => BASE_PATH . '/assets',
		'/preview' => BASE_PATH . '/preview',
		'/uploads' => BASE_PATH . '/uploads',
	];
	$singleFiles = [
		'/favicon.ico' => BASE_PATH . '/favicon.ico',
	];

	if (isset($singleFiles[$requestPath])) {
		$file = try_public_file($singleFiles[$requestPath], dirname($singleFiles[$requestPath]));
		if ($file !== null) {
			send_public_file($file);
		}
	}

	foreach ($mounts as $prefix => $baseDir) {
		if ($requestPath === $prefix || str_starts_with($requestPath, $prefix . '/')) {
			$subPath = substr($requestPath, strlen($prefix));
			$file = resolve_public_file($baseDir, $subPath === false ? '/' : $subPath);

			if ($file !== null) {
				send_public_file($file);
			}

			http_response_code(404);
			echo 'Not Found';
			exit;
		}
	}
}

function run_controller(string $relativePath): void
{
	$fullPath = BASE_PATH . $relativePath;
	$previousDirectory = getcwd();

	if ($previousDirectory !== false) {
		chdir(dirname($fullPath));
	}

	require $fullPath;

	if ($previousDirectory !== false) {
		chdir($previousDirectory);
	}
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) && $uri !== '' ? $uri : '/';
$uri = normalize_public_path($uri);

try_serve_public_asset($uri);

if ($uri !== '/' && str_ends_with($uri, '/')) {
	header('Location: ' . rtrim($uri, '/'), true, 301);
	exit;
}

$routes = [
	'GET' => [
		'/' => 'home',
		'/login' => 'login_form',
		'/dashboard' => 'dashboard',
		'/master-article' => 'master_article',
		'/master-article/create' => 'master_article_create',
		'/master-article/update' => 'master_article_update',
		'/master-article/next-code' => 'master_article_next_code',
		'/master-brand' => 'master_brand',
		'/master-car' => 'master_car',
		'/master-distributor' => 'master_distributor',
		'/master-product' => 'master_product',
		'/api/address-search' => 'api_address_search',
		'/api/address-options' => 'api_address_options',
		'/logout' => 'logout',
		'/shop/dashboard' => 'shop_dashboard',
		'/shop/orders' => 'shop_orders',
		'/shop/orders/preview' => 'shop_order_preview',
		'/shop/orders/download' => 'shop_order_download',
		'/shop/pricing' => 'shop_pricing',
		'/shop/referrals' => 'shop_referrals',
	],
	'POST' => [
		'/login' => 'login_action',
		'/logout' => 'logout',
		'/keepalive' => 'keepalive',
		'/master-article/create' => 'master_article_create',
		'/master-article/update' => 'master_article_update',
		'/master-brand' => 'master_brand',
		'/master-car' => 'master_car',
		'/master-distributor' => 'master_distributor',
		'/master-product' => 'master_product',
		'/api/shipping-quote' => 'api_shipping_quote',
		'/shop/pricing' => 'shop_pricing',
		'/shop/referrals' => 'shop_referrals',
	],
];

$action = $routes[$method][$uri] ?? null;
$publicActions = ['home', 'login_form', 'login_action'];

if ($action === 'home') {
	header('Location: ' . (!empty($_SESSION['logged_in']) ? '/dashboard' : '/login'), true, 302);
	exit;
}

if ($action !== null && !in_array($action, $publicActions, true) && empty($_SESSION['logged_in'])) {
	header('Location: /login', true, 302);
	exit;
}

switch ($action) {
	case 'keepalive':
		run_controller('/application/controllers/KeepAlive.php');
		break;
	case 'logout':
		run_controller('/application/controllers/LogoutController.php');
		break;
	case 'login_form':
		require BASE_PATH . '/pages/login.php';
		break;
	case 'login_action':
		run_controller('/application/controllers/AuthController.php');
		break;
	case 'dashboard':
		require BASE_PATH . '/pages/dashboard.php';
		break;
	case 'master_article':
		require BASE_PATH . '/pages/master-article.php';
		break;
	case 'master_article_create':
		require BASE_PATH . '/pages/master-article-create.php';
		break;
	case 'master_article_update':
		require BASE_PATH . '/pages/master-article-update.php';
		break;
	case 'master_article_next_code':
		run_controller('/application/controllers/master-article/NextCodeController.php');
		break;
	case 'master_brand':
		require BASE_PATH . '/pages/master-brand.php';
		break;
	case 'master_car':
		require BASE_PATH . '/pages/master-car.php';
		break;
	case 'master_distributor':
		require BASE_PATH . '/pages/master-distributor.php';
		break;
	case 'master_product':
		require BASE_PATH . '/pages/master-product.php';
		break;
	case 'api_address_search':
		run_controller('/application/controllers/shipping/AddressSearchController.php');
		break;
	case 'api_address_options':
		run_controller('/application/controllers/shipping/AddressOptionsController.php');
		break;
	case 'api_shipping_quote':
		run_controller('/application/controllers/shipping/QuoteController.php');
		break;
	case 'shop_dashboard':
		require BASE_PATH . '/pages/shop-dashboard.php';
		break;
	case 'shop_orders':
		require BASE_PATH . '/pages/shop-orders.php';
		break;
	case 'shop_order_preview':
		require BASE_PATH . '/pages/shop-order-preview.php';
		break;
	case 'shop_order_download':
		require BASE_PATH . '/pages/shop-order-download.php';
		break;
	case 'shop_pricing':
		require BASE_PATH . '/pages/shop-pricing.php';
		break;
	case 'shop_referrals':
		require BASE_PATH . '/pages/shop-referrals.php';
		break;
	default:
		http_response_code(404);
		echo 'Not Found';
}
