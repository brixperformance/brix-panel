<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';
require_once dirname(__DIR__) . '/application/configs/shop_pdo.php';
require_once dirname(__DIR__) . '/application/configs/csrf.php';
require_once dirname(__DIR__) . '/application/configs/pagination.php';

bootstrap_page(null, false);

if (empty($_SESSION['logged_in'])) {
	header('Location: /login', true, 302);
	exit;
}

function master_product_esc($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function master_product_redirect(string $message = '', string $error = ''): void
{
	$params = array_filter(['message' => $message, 'error' => $error], static fn ($value): bool => $value !== '');
	header('Location: /master-product' . ($params ? '?' . http_build_query($params) : ''), true, 302);
	exit;
}

function master_product_nullable_decimal(string $value): ?string
{
	$value = trim($value);
	if ($value === '') {
		return null;
	}
	if (!is_numeric($value)) {
		throw new RuntimeException('Format angka tidak valid.');
	}
	return number_format((float) $value, 2, '.', '');
}

function master_product_nullable_int(string $value): ?int
{
	$value = trim($value);
	if ($value === '') {
		return null;
	}
	if (!is_numeric($value)) {
		throw new RuntimeException('Format angka tidak valid.');
	}
	return (int) $value;
}

$dbError = '';

try {
	$pdo = get_shop_pdo();
} catch (PDOException $e) {
	$pdo = null;
	$dbError = 'Database shop belum bisa diakses: ' . $e->getMessage();
}

$types = [];
$cars = [];
if (($pdo ?? null) instanceof PDO) {
	$types = $pdo->query('SELECT mpt_id, mpt_code, mpt_name FROM ms_product_types ORDER BY mpt_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
	$cars = $pdo->query('SELECT c.mcr_id, c.mcr_name, c.mcr_is_active, b.mbr_name FROM ms_car c JOIN ms_brand b ON b.mbr_id = c.mcr_mbr_id ORDER BY b.mbr_name ASC, c.mcr_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if (($pdo ?? null) instanceof PDO && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	$token = trim((string) ($_POST['csrf_token'] ?? ''));
	if (!csrf_verify($token)) {
		master_product_redirect('', 'Invalid request. Coba lagi.');
	}

	$action = trim((string) ($_POST['action'] ?? ''));
	$id = max(0, (int) ($_POST['id'] ?? 0));
	$typeId = max(0, (int) ($_POST['type_id'] ?? 0));
	$title = trim((string) ($_POST['title'] ?? ''));
	$partNumber = trim((string) ($_POST['part_number'] ?? ''));
	$sku = trim((string) ($_POST['sku'] ?? ''));
	$price = max(0, (int) ($_POST['price'] ?? 0));
	$stockQty = max(0, (int) ($_POST['stock_qty'] ?? 0));
	$stockStatus = trim((string) ($_POST['stock_status'] ?? 'in_stock'));
	$fitmentSizing = trim((string) ($_POST['fitment_sizing'] ?? ''));
	$thickness = master_product_nullable_decimal((string) ($_POST['thickness_mm'] ?? ''));
	$width = master_product_nullable_decimal((string) ($_POST['width_mm'] ?? ''));
	$height = master_product_nullable_decimal((string) ($_POST['height_mm'] ?? ''));
	$diameter = master_product_nullable_decimal((string) ($_POST['diameter_mm'] ?? ''));
	$pcdHoles = master_product_nullable_int((string) ($_POST['pcd_holes'] ?? ''));
	$weightGrams = master_product_nullable_int((string) ($_POST['weight_grams'] ?? ''));
	$carIds = array_values(array_unique(array_filter(array_map(static fn ($value): int => max(0, (int) $value), (array) ($_POST['car_ids'] ?? [])))));

	try {
		if ($typeId <= 0) {
			throw new RuntimeException('Product type wajib dipilih.');
		}
		if ($title === '') {
			throw new RuntimeException('Product title wajib diisi.');
		}
		if (!in_array($stockStatus, ['in_stock', 'waitlist'], true)) {
			throw new RuntimeException('Stock status tidak valid.');
		}

		$pdo->beginTransaction();

		$params = [
			':type_id' => $typeId,
			':part_number' => $partNumber !== '' ? $partNumber : null,
			':sku' => $sku !== '' ? $sku : null,
			':title' => $title,
			':price' => $price,
			':stock_qty' => $stockQty,
			':stock_status' => $stockStatus,
			':fitment_sizing' => $fitmentSizing !== '' ? $fitmentSizing : null,
			':thickness_mm' => $thickness,
			':width_mm' => $width,
			':height_mm' => $height,
			':diameter_mm' => $diameter,
			':pcd_holes' => $pcdHoles,
			':weight_grams' => $weightGrams,
		];

		if ($action === 'create') {
			$stmt = $pdo->prepare('
				INSERT INTO ms_products (
					mpr_mpt_id, mpr_part_number, mpr_sku, mpr_title, mpr_price, mpr_stock_qty,
					mpr_stock_status, mpr_fitment_sizing, mpr_thickness_mm, mpr_width_mm,
					mpr_height_mm, mpr_diameter_mm, mpr_pcd_holes, mpr_weight_grams
				) VALUES (
					:type_id, :part_number, :sku, :title, :price, :stock_qty,
					:stock_status, :fitment_sizing, :thickness_mm, :width_mm,
					:height_mm, :diameter_mm, :pcd_holes, :weight_grams
				)
			');
			$stmt->execute($params);
			$id = (int) $pdo->lastInsertId();
		} elseif ($action === 'update') {
			if ($id <= 0) {
				throw new RuntimeException('Product tidak ditemukan.');
			}
			$params[':id'] = $id;
			$stmt = $pdo->prepare('
				UPDATE ms_products
				SET mpr_mpt_id = :type_id,
					mpr_part_number = :part_number,
					mpr_sku = :sku,
					mpr_title = :title,
					mpr_price = :price,
					mpr_stock_qty = :stock_qty,
					mpr_stock_status = :stock_status,
					mpr_fitment_sizing = :fitment_sizing,
					mpr_thickness_mm = :thickness_mm,
					mpr_width_mm = :width_mm,
					mpr_height_mm = :height_mm,
					mpr_diameter_mm = :diameter_mm,
					mpr_pcd_holes = :pcd_holes,
					mpr_weight_grams = :weight_grams
				WHERE mpr_id = :id
			');
			$stmt->execute($params);
			$pdo->prepare('DELETE FROM ms_product_vehicle_fitments WHERE mpv_mpr_id = :id')->execute([':id' => $id]);
		} else {
			throw new RuntimeException('Aksi tidak dikenali.');
		}

		if (!empty($carIds)) {
			$fitmentStmt = $pdo->prepare('INSERT INTO ms_product_vehicle_fitments (mpv_mpr_id, mpv_mcr_id) VALUES (:product_id, :car_id)');
			foreach ($carIds as $carId) {
				$fitmentStmt->execute([':product_id' => $id, ':car_id' => $carId]);
			}
		}

		$pdo->commit();
		master_product_redirect($action === 'create' ? 'Product berhasil ditambahkan.' : 'Product berhasil diperbarui.');
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		master_product_redirect('', $e->getMessage());
	}
}

$message = trim((string) ($_GET['message'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$results = [];
$fitmentsByProduct = [];
$paginationLinks = [];
$totalRows = 0;

if (($pdo ?? null) instanceof PDO) {
	try {
		$whereSql = '';
		$params = [];
		if ($search !== '') {
			$whereSql = 'WHERE p.mpr_title LIKE :search OR p.mpr_sku LIKE :search OR p.mpr_part_number LIKE :search';
			$params[':search'] = '%' . $search . '%';
		}

		$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ms_products p $whereSql");
		$countStmt->execute($params);
		$totalRows = (int) $countStmt->fetchColumn();

		$stmt = $pdo->prepare("
			SELECT p.mpr_id, p.mpr_mpt_id, p.mpr_part_number, p.mpr_sku, p.mpr_title, p.mpr_price,
				   p.mpr_stock_qty, p.mpr_stock_status, p.mpr_fitment_sizing, p.mpr_thickness_mm,
				   p.mpr_width_mm, p.mpr_height_mm, p.mpr_diameter_mm, p.mpr_pcd_holes, p.mpr_weight_grams,
				   pt.mpt_code, pt.mpt_name, COUNT(f.mpv_id) AS fitment_count
			FROM ms_products p
			LEFT JOIN ms_product_types pt ON pt.mpt_id = p.mpr_mpt_id
			LEFT JOIN ms_product_vehicle_fitments f ON f.mpv_mpr_id = p.mpr_id
			$whereSql
			GROUP BY p.mpr_id
			ORDER BY p.mpr_id DESC
			LIMIT :limit OFFSET :offset
		");
		foreach ($params as $key => $value) {
			$stmt->bindValue($key, $value, PDO::PARAM_STR);
		}
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

		$productIds = array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['mpr_id'] ?? 0), $results)));
		if (!empty($productIds)) {
			$placeholders = [];
			$fitmentParams = [];
			foreach ($productIds as $index => $productId) {
				$key = ':pid_' . $index;
				$placeholders[] = $key;
				$fitmentParams[$key] = $productId;
			}

			$fitmentStmt = $pdo->prepare('
				SELECT f.mpv_mpr_id, f.mpv_mcr_id, c.mcr_name, b.mbr_name
				FROM ms_product_vehicle_fitments f
				JOIN ms_car c ON c.mcr_id = f.mpv_mcr_id
				JOIN ms_brand b ON b.mbr_id = c.mcr_mbr_id
				WHERE f.mpv_mpr_id IN (' . implode(', ', $placeholders) . ')
				ORDER BY b.mbr_name ASC, c.mcr_name ASC
			');
			foreach ($fitmentParams as $key => $value) {
				$fitmentStmt->bindValue($key, $value, PDO::PARAM_INT);
			}
			$fitmentStmt->execute();

			foreach ($fitmentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $fitment) {
				$productId = (int) ($fitment['mpv_mpr_id'] ?? 0);
				if ($productId > 0) {
					$fitmentsByProduct[$productId][] = $fitment;
				}
			}
		}

		$paginationLinks = build_pagination_links($page, max(1, (int) ceil($totalRows / $limit)));
	} catch (PDOException $e) {
		$dbError = 'Gagal memuat data product: ' . $e->getMessage();
	}
}

$tableColumns = [
	['label' => 'Product'],
	['label' => 'Type'],
	['label' => 'Price'],
	['label' => 'Stock'],
	['label' => 'Fitment'],
	['label' => 'Action', 'class' => 'w-1 text-end'],
];

$tableRows = [];
foreach ($results as $row) {
	$productId = (int) ($row['mpr_id'] ?? 0);
	$fitmentRows = $fitmentsByProduct[$productId] ?? [];
	$fitmentLabels = array_map(static fn (array $fitment): string => trim((string) ($fitment['mbr_name'] ?? '') . ' ' . (string) ($fitment['mcr_name'] ?? '')), $fitmentRows);
	$fitmentIds = array_map(static fn (array $fitment): int => (int) ($fitment['mpv_mcr_id'] ?? 0), $fitmentRows);
	$isInStock = (string) ($row['mpr_stock_status'] ?? 'waitlist') === 'in_stock';

	$tableRows[] = [
		'cells' => [
			['html' => '<div class="fw-medium">' . master_product_esc($row['mpr_title'] ?? '') . '</div><div class="text-secondary">SKU: ' . master_product_esc($row['mpr_sku'] ?: '-') . ' • PN: ' . master_product_esc($row['mpr_part_number'] ?: '-') . '</div>'],
			['html' => '<div class="fw-medium">' . master_product_esc($row['mpt_name'] ?? '-') . '</div><div class="text-secondary"><code>' . master_product_esc($row['mpt_code'] ?? '') . '</code></div>'],
			['html' => '<div class="fw-medium">IDR' . number_format((int) ($row['mpr_price'] ?? 0), 0, ',', '.') . ',-</div>'],
			['html' => ($isInStock ? '<span class="badge bg-success-lt text-success">In Stock</span>' : '<span class="badge bg-warning-lt text-warning">Waitlist</span>') . '<div class="text-secondary small mt-1">Qty: ' . master_product_esc($row['mpr_stock_qty'] ?? 0) . '</div>'],
			['html' => !empty($fitmentLabels) ? '<div class="text-secondary small">' . master_product_esc(implode(', ', array_slice($fitmentLabels, 0, 3))) . (count($fitmentLabels) > 3 ? ' +' . (count($fitmentLabels) - 3) . ' more' : '') . '</div>' : '<span class="text-secondary small">No fitment</span>'],
			['html' => '<button type="button" class="btn btn-sm btn-icon btn-outline-secondary edit-product-button" data-id="' . master_product_esc($row['mpr_id'] ?? '') . '" data-type-id="' . master_product_esc($row['mpr_mpt_id'] ?? '') . '" data-title="' . master_product_esc($row['mpr_title'] ?? '') . '" data-part-number="' . master_product_esc($row['mpr_part_number'] ?? '') . '" data-sku="' . master_product_esc($row['mpr_sku'] ?? '') . '" data-price="' . master_product_esc($row['mpr_price'] ?? '') . '" data-stock-qty="' . master_product_esc($row['mpr_stock_qty'] ?? '') . '" data-stock-status="' . master_product_esc($row['mpr_stock_status'] ?? '') . '" data-fitment-sizing="' . master_product_esc($row['mpr_fitment_sizing'] ?? '') . '" data-thickness-mm="' . master_product_esc($row['mpr_thickness_mm'] ?? '') . '" data-width-mm="' . master_product_esc($row['mpr_width_mm'] ?? '') . '" data-height-mm="' . master_product_esc($row['mpr_height_mm'] ?? '') . '" data-diameter-mm="' . master_product_esc($row['mpr_diameter_mm'] ?? '') . '" data-pcd-holes="' . master_product_esc($row['mpr_pcd_holes'] ?? '') . '" data-weight-grams="' . master_product_esc($row['mpr_weight_grams'] ?? '') . '" data-fitment-ids="' . master_product_esc(implode(',', $fitmentIds)) . '" title="Edit" aria-label="Edit"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h-.01"></path><path d="M3 21l3.75 -1l11.5 -11.5a2.121 2.121 0 0 0 -3 -3l-11.5 11.5l-1 3.75"></path><path d="M13 6l3 3"></path></svg></button>', 'class' => 'text-end w-1'],
		],
	];
}

ob_start();
?>
<form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center">
	<div class="input-group input-group-flat" style="min-width: 360px;">
		<span class="input-group-text"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg></span>
		<input type="search" class="form-control" name="search" placeholder="Search title, SKU, or part number..." value="<?= master_product_esc($search) ?>">
	</div>
	<button type="submit" class="btn btn-primary">Search</button>
	<?php if ($search !== ''): ?><a href="/master-product" class="btn btn-outline-secondary">Reset</a><?php endif; ?>
</form>
<?php
$tableFiltersHtml = (string) ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-primary" id="add-new-btn">
	<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
	Add Product
</button>
<?php
$tableToolbarHtml = (string) ob_get_clean();

ob_start();
?>
<div class="row g-2 justify-content-center justify-content-sm-between align-items-center">
	<div class="col-auto d-flex align-items-center">
		<p class="m-0 text-secondary">Showing <strong><?= $totalRows > 0 ? ($offset + 1) : 0 ?></strong> to <strong><?= min($offset + $limit, $totalRows) ?></strong> of <strong><?= $totalRows ?></strong> entries</p>
	</div>
	<?php if (!empty($paginationLinks)): ?>
		<div class="col-auto">
			<ul class="pagination m-0">
				<?php foreach ($paginationLinks as $link): ?>
					<?php $pageHref = $link['page'] !== null ? '?page=' . $link['page'] . '&search=' . urlencode($search) : '#'; ?>
					<li class="page-item <?= !empty($link['active']) ? 'active' : '' ?> <?= !empty($link['disabled']) || $link['page'] === null ? 'disabled' : '' ?>">
						<a class="page-link" href="<?= master_product_esc($pageHref) ?>"><?= master_product_esc($link['label'] ?? '') ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
<?php
$tableFooterHtml = (string) ob_get_clean();

$tableId = 'master-product-table';
$tableTitle = 'Master Product';
$tableDescription = 'Kelola produk shop, stock status, spesifikasi utama, dan mapping fitment ke car.';
$tableEmptyMessage = 'No products found.';

$renderProductForm = static function (string $mode, array $types, array $cars): void {
	$prefix = $mode === 'edit' ? 'edit' : 'create';
	$title = $mode === 'edit' ? 'Edit Product' : 'Create Product';
	?>
	<div id="<?= $prefix ?>-product-modal" class="modal">
		<div class="modal-content modal-content-lg">
			<h3><?= master_product_esc($title) ?></h3>
			<form id="<?= $prefix ?>-product-form" method="post" action="/master-product">
				<input type="hidden" name="csrf_token" value="<?= master_product_esc(csrf_token()) ?>">
				<input type="hidden" name="action" value="<?= $mode === 'edit' ? 'update' : 'create' ?>">
				<?php if ($mode === 'edit'): ?><input type="hidden" name="id" id="edit-product-id"><?php endif; ?>
				<div class="row g-3">
					<div class="col-md-6">
						<div class="form-row"><label for="<?= $prefix ?>-product-type">Product Type</label><select name="type_id" id="<?= $prefix ?>-product-type" required><option value="">Select type</option><?php foreach ($types as $type): ?><option value="<?= (int) ($type['mpt_id'] ?? 0) ?>"><?= master_product_esc(($type['mpt_name'] ?? '') . ' (' . ($type['mpt_code'] ?? '') . ')') ?></option><?php endforeach; ?></select></div>
						<div class="form-row"><label for="<?= $prefix ?>-product-title">Product Title</label><input type="text" name="title" id="<?= $prefix ?>-product-title" maxlength="255" required></div>
						<div class="form-row"><label for="<?= $prefix ?>-product-part-number">Part Number</label><input type="text" name="part_number" id="<?= $prefix ?>-product-part-number" maxlength="512"></div>
						<div class="form-row"><label for="<?= $prefix ?>-product-sku">SKU</label><input type="text" name="sku" id="<?= $prefix ?>-product-sku" maxlength="64"></div>
						<div class="row g-3">
							<div class="col-md-6"><div class="form-row"><label for="<?= $prefix ?>-product-price">Price</label><input type="number" name="price" id="<?= $prefix ?>-product-price" min="0" required></div></div>
							<div class="col-md-6"><div class="form-row"><label for="<?= $prefix ?>-product-stock-qty">Stock Qty</label><input type="number" name="stock_qty" id="<?= $prefix ?>-product-stock-qty" min="0" required></div></div>
						</div>
						<div class="form-row"><label for="<?= $prefix ?>-product-stock-status">Shop Status</label><select name="stock_status" id="<?= $prefix ?>-product-stock-status" required><option value="in_stock">In Stock</option><option value="waitlist">Waitlist</option></select></div>
					</div>
					<div class="col-md-6">
						<div class="form-row"><label for="<?= $prefix ?>-product-fitment-sizing">Fitment / Sizing</label><input type="text" name="fitment_sizing" id="<?= $prefix ?>-product-fitment-sizing" maxlength="255"></div>
						<div class="row g-3">
							<div class="col-md-6"><div class="form-row"><label for="<?= $prefix ?>-product-thickness">Thickness (mm)</label><input type="number" step="0.01" name="thickness_mm" id="<?= $prefix ?>-product-thickness"></div></div>
							<div class="col-md-6"><div class="form-row"><label for="<?= $prefix ?>-product-width">Width (mm)</label><input type="number" step="0.01" name="width_mm" id="<?= $prefix ?>-product-width"></div></div>
							<div class="col-md-6"><div class="form-row"><label for="<?= $prefix ?>-product-height">Height (mm)</label><input type="number" step="0.01" name="height_mm" id="<?= $prefix ?>-product-height"></div></div>
							<div class="col-md-6"><div class="form-row"><label for="<?= $prefix ?>-product-diameter">Diameter (mm)</label><input type="number" step="0.01" name="diameter_mm" id="<?= $prefix ?>-product-diameter"></div></div>
							<div class="col-md-6"><div class="form-row"><label for="<?= $prefix ?>-product-pcd-holes">PCD Holes</label><input type="number" name="pcd_holes" id="<?= $prefix ?>-product-pcd-holes" min="0"></div></div>
							<div class="col-md-6"><div class="form-row"><label for="<?= $prefix ?>-product-weight">Weight (grams)</label><input type="number" name="weight_grams" id="<?= $prefix ?>-product-weight" min="0"></div></div>
						</div>
						<div class="form-row">
							<label for="<?= $prefix ?>-product-car-search">Fitment Cars</label>
							<input type="search" id="<?= $prefix ?>-product-car-search" class="fitment-search" placeholder="Filter car list...">
							<select name="car_ids[]" id="<?= $prefix ?>-product-cars" class="fitment-select" multiple size="8">
								<?php foreach ($cars as $car): ?>
									<option value="<?= (int) ($car['mcr_id'] ?? 0) ?>"><?= master_product_esc(($car['mbr_name'] ?? '') . ' - ' . ($car['mcr_name'] ?? '') . ((int) ($car['mcr_is_active'] ?? 0) === 1 ? '' : ' (inactive)')) ?></option>
								<?php endforeach; ?>
							</select>
							<small class="text-secondary">Gunakan Ctrl/Cmd untuk pilih lebih dari satu car.</small>
						</div>
					</div>
				</div>
				<div class="form-actions"><button type="submit" class="btn-save"><?= $mode === 'edit' ? 'Save Changes' : 'Create Product' ?></button><button type="button" id="cancel-<?= $prefix ?>-product-btn" class="btn-cancel">Cancel</button></div>
			</form>
		</div>
	</div>
	<?php
};
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
	<meta http-equiv="X-UA-Compatible" content="ie=edge"/>
	<title>Master Product - BRIX</title>
	<link rel="icon" href="/favicon.ico" type="image/x-icon"/>
	<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon"/>
	<link href="/assets/dist/css/tabler.css" rel="stylesheet"/>
	<link href="/assets/dist/css/tabler-flags.css" rel="stylesheet"/>
	<link href="/assets/dist/css/tabler-socials.css" rel="stylesheet"/>
	<link href="/assets/dist/css/tabler-payments.css" rel="stylesheet"/>
	<link href="/assets/dist/css/tabler-vendors.css" rel="stylesheet"/>
	<link href="/assets/dist/css/tabler-marketing.css" rel="stylesheet"/>
	<link href="/assets/dist/css/tabler-themes.css" rel="stylesheet"/>
	<link href="/preview/css/demo.css" rel="stylesheet"/>
	<link href="/assets/css/dashboard.css" rel="stylesheet"/>
	<link href="/assets/css/data-hub-table.css" rel="stylesheet"/>
	<link href="/assets/css/data-hub-modal.css" rel="stylesheet"/>
</head>
<body>
	<script src="/assets/dist/js/tabler-theme.js"></script>
	<div class="page">
		<?php include __DIR__ . '/../templates/sidebar.php'; ?>
		<div class="page-wrapper">
			<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">BRIX Data Hub</div><h1 class="page-title">Master Product</h1></div></div></div></div>
			<main class="page-body"><div class="container-xl">
				<?php if ($message !== ''): ?><div class="alert alert-success"><?= master_product_esc($message) ?></div><?php endif; ?>
				<?php if ($error !== ''): ?><div class="alert alert-danger"><?= master_product_esc($error) ?></div><?php endif; ?>
				<?php if ($dbError !== ''): ?><div class="alert alert-danger"><?= master_product_esc($dbError) ?></div><?php endif; ?>
				<div class="alert alert-warning">Product saat ini mengikuti schema shop terbaru. Karena belum ada kolom active/inactive khusus di tabel product, status produk memakai <strong>In Stock</strong> atau <strong>Waitlist</strong>.</div>
				<div class="row row-cards"><div class="col-12"><?php include __DIR__ . '/../templates/data-hub-table.php'; ?></div></div>
			</div></main>
		</div>
	</div>

	<?php $renderProductForm('create', $types, $cars); ?>
	<?php $renderProductForm('edit', $types, $cars); ?>

	<script src="/assets/dist/js/tabler.js"></script>
	<script src="/assets/js/dashboard.js"></script>
	<script src="/assets/js/master-product/modals.js" defer></script>
	<script src="/assets/js/idle-timeout.js" defer></script>
</body>
</html>
