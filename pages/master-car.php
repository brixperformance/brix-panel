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

function master_car_esc($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function master_car_redirect(string $message = '', string $error = ''): void
{
	$params = array_filter(['message' => $message, 'error' => $error], static fn ($value): bool => $value !== '');
	header('Location: /master-car' . ($params ? '?' . http_build_query($params) : ''), true, 302);
	exit;
}

function master_car_normalize_code(string $value): ?string
{
	$value = strtoupper(trim($value));
	$value = preg_replace('/[^A-Z0-9\- ]/i', '', $value) ?? '';
	$value = trim($value);
	return $value === '' ? null : substr($value, 0, 50);
}

$dbError = '';

try {
	$pdo = get_shop_pdo();
} catch (PDOException $e) {
	$pdo = null;
	$dbError = 'Database shop belum bisa diakses: ' . $e->getMessage();
}

$brands = [];
if (($pdo ?? null) instanceof PDO) {
	$brands = $pdo->query('SELECT mbr_id, mbr_code, mbr_name FROM ms_brand ORDER BY mbr_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if (($pdo ?? null) instanceof PDO && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	$token = trim((string) ($_POST['csrf_token'] ?? ''));
	if (!csrf_verify($token)) {
		master_car_redirect('', 'Invalid request. Coba lagi.');
	}

	$action = trim((string) ($_POST['action'] ?? ''));
	$id = max(0, (int) ($_POST['id'] ?? 0));
	$brandId = max(0, (int) ($_POST['brand_id'] ?? 0));
	$code = master_car_normalize_code((string) ($_POST['code'] ?? ''));
	$name = trim((string) ($_POST['name'] ?? ''));
	$isActive = isset($_POST['is_active']) ? 1 : 0;

	try {
		if ($brandId <= 0) {
			throw new RuntimeException('Brand wajib dipilih.');
		}
		if ($name === '') {
			throw new RuntimeException('Car name wajib diisi.');
		}

		if ($action === 'create') {
			$stmt = $pdo->prepare('INSERT INTO ms_car (mcr_mbr_id, mcr_code, mcr_name, mcr_is_active) VALUES (:brand_id, :code, :name, :is_active)');
			$stmt->execute([
				':brand_id' => $brandId,
				':code' => $code,
				':name' => $name,
				':is_active' => $isActive,
			]);
			master_car_redirect('Car berhasil ditambahkan.');
		}

		if ($action === 'update') {
			if ($id <= 0) {
				throw new RuntimeException('Car tidak ditemukan.');
			}
			$stmt = $pdo->prepare('UPDATE ms_car SET mcr_mbr_id = :brand_id, mcr_code = :code, mcr_name = :name, mcr_is_active = :is_active WHERE mcr_id = :id');
			$stmt->execute([
				':id' => $id,
				':brand_id' => $brandId,
				':code' => $code,
				':name' => $name,
				':is_active' => $isActive,
			]);
			master_car_redirect('Car berhasil diperbarui.');
		}

		throw new RuntimeException('Aksi tidak dikenali.');
	} catch (Throwable $e) {
		master_car_redirect('', $e->getMessage());
	}
}

$message = trim((string) ($_GET['message'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;
$results = [];
$totalRows = 0;
$paginationLinks = [];

if (($pdo ?? null) instanceof PDO) {
	try {
		$whereSql = '';
		$params = [];
		if ($search !== '') {
			$whereSql = 'WHERE c.mcr_name LIKE :search OR c.mcr_code LIKE :search OR b.mbr_name LIKE :search OR b.mbr_code LIKE :search';
			$params[':search'] = '%' . $search . '%';
		}

		$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ms_car c JOIN ms_brand b ON b.mbr_id = c.mcr_mbr_id $whereSql");
		$countStmt->execute($params);
		$totalRows = (int) $countStmt->fetchColumn();

		$stmt = $pdo->prepare("
			SELECT c.mcr_id, c.mcr_mbr_id, c.mcr_code, c.mcr_name, c.mcr_is_active, b.mbr_code, b.mbr_name
			FROM ms_car c
			JOIN ms_brand b ON b.mbr_id = c.mcr_mbr_id
			$whereSql
			ORDER BY b.mbr_name ASC, c.mcr_name ASC
			LIMIT :limit OFFSET :offset
		");
		foreach ($params as $key => $value) {
			$stmt->bindValue($key, $value, PDO::PARAM_STR);
		}
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		$paginationLinks = build_pagination_links($page, max(1, (int) ceil($totalRows / $limit)));
	} catch (PDOException $e) {
		$dbError = 'Gagal memuat data car: ' . $e->getMessage();
	}
}

$tableColumns = [
	['label' => 'Car'],
	['label' => 'Brand'],
	['label' => 'Code'],
	['label' => 'Status'],
	['label' => 'Action', 'class' => 'w-1 text-end'],
];

$tableRows = [];
foreach ($results as $row) {
	$isActive = (int) ($row['mcr_is_active'] ?? 0) === 1;
	$tableRows[] = [
		'cells' => [
			['html' => '<div class="fw-medium">' . master_car_esc($row['mcr_name'] ?? '') . '</div><div class="text-secondary">Vehicle master entry</div>'],
			['html' => '<div class="fw-medium">' . master_car_esc($row['mbr_name'] ?? '') . '</div><div class="text-secondary"><code>' . master_car_esc($row['mbr_code'] ?? '') . '</code></div>'],
			['html' => '<code>' . master_car_esc($row['mcr_code'] ?? '-') . '</code>'],
			['html' => $isActive ? '<span class="badge bg-success-lt text-success">Active</span>' : '<span class="badge bg-danger-lt text-danger">Inactive</span>'],
			['html' => '<button type="button" class="btn btn-sm btn-icon btn-outline-secondary edit-car-button" data-id="' . master_car_esc($row['mcr_id'] ?? '') . '" data-brand-id="' . master_car_esc($row['mcr_mbr_id'] ?? '') . '" data-code="' . master_car_esc($row['mcr_code'] ?? '') . '" data-name="' . master_car_esc($row['mcr_name'] ?? '') . '" data-active="' . ($isActive ? '1' : '0') . '" title="Edit" aria-label="Edit"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h-.01"></path><path d="M3 21l3.75 -1l11.5 -11.5a2.121 2.121 0 0 0 -3 -3l-11.5 11.5l-1 3.75"></path><path d="M13 6l3 3"></path></svg></button>', 'class' => 'text-end w-1'],
		],
	];
}

ob_start();
?>
<form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center">
	<div class="input-group input-group-flat" style="min-width: 320px;">
		<span class="input-group-text"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg></span>
		<input type="search" class="form-control" name="search" placeholder="Search car, brand, or code..." value="<?= master_car_esc($search) ?>">
	</div>
	<button type="submit" class="btn btn-primary">Search</button>
	<?php if ($search !== ''): ?><a href="/master-car" class="btn btn-outline-secondary">Reset</a><?php endif; ?>
</form>
<?php
$tableFiltersHtml = (string) ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-primary" id="add-new-btn">
	<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
	Add Car
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
						<a class="page-link" href="<?= master_car_esc($pageHref) ?>"><?= master_car_esc($link['label'] ?? '') ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
<?php
$tableFooterHtml = (string) ob_get_clean();

$tableId = 'master-car-table';
$tableTitle = 'Master Car';
$tableDescription = 'Kelola daftar kendaraan yang dipakai untuk mapping fitment produk.';
$tableEmptyMessage = 'No cars found.';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
	<meta http-equiv="X-UA-Compatible" content="ie=edge"/>
	<title>Master Car - BRIX</title>
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
			<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">BRIX Data Hub</div><h1 class="page-title">Master Car</h1></div></div></div></div>
			<main class="page-body"><div class="container-xl">
				<?php if ($message !== ''): ?><div class="alert alert-success"><?= master_car_esc($message) ?></div><?php endif; ?>
				<?php if ($error !== ''): ?><div class="alert alert-danger"><?= master_car_esc($error) ?></div><?php endif; ?>
				<?php if ($dbError !== ''): ?><div class="alert alert-danger"><?= master_car_esc($dbError) ?></div><?php endif; ?>
				<div class="row row-cards"><div class="col-12"><?php include __DIR__ . '/../templates/data-hub-table.php'; ?></div></div>
			</div></main>
		</div>
	</div>

	<div id="create-car-modal" class="modal">
		<div class="modal-content">
			<h3>Create Car</h3>
			<form id="create-car-form" method="post" action="/master-car">
				<input type="hidden" name="csrf_token" value="<?= master_car_esc(csrf_token()) ?>">
				<input type="hidden" name="action" value="create">
				<div class="form-row"><label for="create-car-brand">Brand</label><select name="brand_id" id="create-car-brand" required><option value="">Select brand</option><?php foreach ($brands as $brand): ?><option value="<?= (int) ($brand['mbr_id'] ?? 0) ?>"><?= master_car_esc(($brand['mbr_name'] ?? '') . ' (' . ($brand['mbr_code'] ?? '') . ')') ?></option><?php endforeach; ?></select></div>
				<div class="form-row"><label for="create-car-name">Car Name</label><input type="text" name="name" id="create-car-name" maxlength="200" required></div>
				<div class="form-row"><label for="create-car-code">Car Code</label><input type="text" name="code" id="create-car-code" maxlength="50" placeholder="Optional"></div>
				<div class="form-row"><label for="create-car-flag">Status</label><label class="switch"><input type="checkbox" id="create-car-flag" name="is_active" checked><span class="slider"></span></label></div>
				<div class="form-actions"><button type="submit" class="btn-save">Create</button><button type="button" id="cancel-create-car-btn" class="btn-cancel">Cancel</button></div>
			</form>
		</div>
	</div>

	<div id="edit-car-modal" class="modal">
		<div class="modal-content">
			<h3>Edit Car</h3>
			<form id="edit-car-form" method="post" action="/master-car">
				<input type="hidden" name="csrf_token" value="<?= master_car_esc(csrf_token()) ?>">
				<input type="hidden" name="action" value="update">
				<input type="hidden" name="id" id="edit-car-id">
				<div class="form-row"><label for="edit-car-brand">Brand</label><select name="brand_id" id="edit-car-brand" required><option value="">Select brand</option><?php foreach ($brands as $brand): ?><option value="<?= (int) ($brand['mbr_id'] ?? 0) ?>"><?= master_car_esc(($brand['mbr_name'] ?? '') . ' (' . ($brand['mbr_code'] ?? '') . ')') ?></option><?php endforeach; ?></select></div>
				<div class="form-row"><label for="edit-car-name">Car Name</label><input type="text" name="name" id="edit-car-name" maxlength="200" required></div>
				<div class="form-row"><label for="edit-car-code">Car Code</label><input type="text" name="code" id="edit-car-code" maxlength="50" placeholder="Optional"></div>
				<div class="form-row"><label for="edit-car-flag">Status</label><label class="switch"><input type="checkbox" id="edit-car-flag" name="is_active"><span class="slider"></span></label></div>
				<div class="form-actions"><button type="submit" class="btn-save">Save Changes</button><button type="button" id="cancel-edit-car-btn" class="btn-cancel">Cancel</button></div>
			</form>
		</div>
	</div>

	<script src="/assets/dist/js/tabler.js"></script>
	<script src="/assets/js/dashboard.js"></script>
	<script src="/assets/js/master-car/modals.js" defer></script>
	<script src="/assets/js/idle-timeout.js" defer></script>
</body>
</html>
