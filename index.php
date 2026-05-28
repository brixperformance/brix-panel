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
		'/master-brand' => 'master_brand',
		'/master-pricelist' => 'master_pricelist',
		'/master-dealer' => 'master_dealer',
		'/master-dealer/read' => 'master_dealer_read',
		'/master-island' => 'master_island',
		'/master-province' => 'master_province',
		'/invoice-generator' => 'invoice_generator',
		'/invoice-log-directory' => 'invoice_log_directory',
		'/invoice-log' => 'invoice_log',
		'/invoice-log/preview' => 'invoice_log_preview',
		'/api/invoice-item-options' => 'api_invoice_item_options',
		'/api/invoice-detail' => 'api_invoice_detail',
		'/api/address-search' => 'api_address_search',
		'/api/address-options' => 'api_address_options',
		'/logout' => 'logout',
		'/shop/dashboard' => 'shop_dashboard',
		'/shop/orders' => 'shop_orders',
		'/shop/orders/preview' => 'shop_order_preview',
		'/shop/pricing' => 'shop_pricing',
		'/shop/referrals' => 'shop_referrals',
	],
	'POST' => [
		'/login' => 'login_action',
		'/logout' => 'logout',
		'/keepalive' => 'keepalive',
		'/master-brand/create' => 'master_brand_create',
		'/master-brand/update' => 'master_brand_update',
		'/master-pricelist/create' => 'master_pricelist_create',
		'/master-pricelist/update' => 'master_pricelist_update',
		'/master-pricelist/export' => 'master_pricelist_export',
		'/master-dealer/create' => 'master_dealer_create',
		'/master-dealer/update' => 'master_dealer_update',
		'/master-dealer/read' => 'master_dealer_read',
		'/master-island/create' => 'master_island_create',
		'/master-island/update' => 'master_island_update',
		'/master-island/delete' => 'master_island_delete',
		'/master-province/create' => 'master_province_create',
		'/master-province/update' => 'master_province_update',
		'/master-province/delete' => 'master_province_delete',
		'/invoice-generator/preview' => 'invoice_preview',
		'/invoice-log/delete' => 'invoice_log_delete',
		'/invoice-log/update' => 'invoice_log_update',
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
	case 'master_brand':
		require BASE_PATH . '/pages/master-brand.php';
		break;
	case 'master_brand_create':
		run_controller('/application/controllers/master-brand/CreateController.php');
		break;
	case 'master_brand_update':
		run_controller('/application/controllers/master-brand/UpdateController.php');
		break;
	case 'master_pricelist':
		require BASE_PATH . '/pages/master-pricelist.php';
		break;
	case 'master_pricelist_create':
		run_controller('/application/controllers/master-pricelist/CreateController.php');
		break;
	case 'master_pricelist_update':
		run_controller('/application/controllers/master-pricelist/UpdateController.php');
		break;
	case 'master_pricelist_export':
		run_controller('/application/controllers/master-pricelist/ExportController.php');
		break;
	case 'master_dealer':
		require BASE_PATH . '/pages/master-dealer.php';
		break;
	case 'master_dealer_read':
		run_controller('/application/controllers/master-dealer/ReadController.php');
		break;
	case 'master_dealer_create':
		run_controller('/application/controllers/master-dealer/CreateController.php');
		break;
	case 'master_dealer_update':
		run_controller('/application/controllers/master-dealer/UpdateController.php');
		break;
	case 'master_island':
		require BASE_PATH . '/pages/master-island.php';
		break;
	case 'master_island_create':
		run_controller('/application/controllers/master-island/CreateController.php');
		break;
	case 'master_island_update':
		run_controller('/application/controllers/master-island/UpdateController.php');
		break;
	case 'master_island_delete':
		run_controller('/application/controllers/master-island/DeleteController.php');
		break;
	case 'master_province':
		require BASE_PATH . '/pages/master-province.php';
		break;
	case 'master_province_create':
		run_controller('/application/controllers/master-province/CreateController.php');
		break;
	case 'master_province_update':
		run_controller('/application/controllers/master-province/UpdateController.php');
		break;
	case 'master_province_delete':
		run_controller('/application/controllers/master-province/DeleteController.php');
		break;
	case 'invoice_generator':
		require BASE_PATH . '/pages/invoice-generator.php';
		break;
	case 'invoice_preview':
		require BASE_PATH . '/pages/invoice-preview.php';
		break;
	case 'invoice_log_directory':
		require BASE_PATH . '/pages/invoice-log-directory.php';
		break;
	case 'invoice_log':
		require BASE_PATH . '/pages/invoice-log.php';
		break;
	case 'invoice_log_preview':
		require BASE_PATH . '/pages/invoice-preview.php';
		break;
	case 'invoice_log_delete':
		run_controller('/application/controllers/log-invoice/DeleteController.php');
		break;
	case 'invoice_log_update':
		run_controller('/application/controllers/log-invoice/UpdateController.php');
		break;
	case 'api_invoice_item_options':
		run_controller('/application/controllers/InvoiceItemOptionsController.php');
		break;
	case 'api_invoice_detail':
		run_controller('/application/controllers/log-invoice/DetailController.php');
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
