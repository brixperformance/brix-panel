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

function master_brand_esc($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function master_brand_redirect(string $message = '', string $error = ''): void
{
	$params = array_filter([
		'message' => $message,
		'error' => $error,
	], static fn ($value): bool => $value !== '');

	header('Location: /master-brand' . ($params ? '?' . http_build_query($params) : ''), true, 302);
	exit;
}

function master_brand_normalize_code(string $value): string
{
	$value = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($value)) ?? '');
	return substr($value, 0, 3);
}

$dbError = '';

try {
	$pdo = get_shop_pdo();
} catch (PDOException $e) {
	$pdo = null;
	$dbError = 'Database shop belum bisa diakses: ' . $e->getMessage();
}

if (($pdo ?? null) instanceof PDO && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	$token = trim((string) ($_POST['csrf_token'] ?? ''));
	if (!csrf_verify($token)) {
		master_brand_redirect('', 'Invalid request. Coba lagi.');
	}

	$action = trim((string) ($_POST['action'] ?? ''));
	$id = max(0, (int) ($_POST['id'] ?? 0));
	$code = master_brand_normalize_code((string) ($_POST['code'] ?? ''));
	$name = trim((string) ($_POST['name'] ?? ''));
	$isActive = isset($_POST['is_active']) ? 1 : 0;

	try {
		if (strlen($code) !== 3) {
			throw new RuntimeException('Brand code wajib 3 karakter.');
		}
		if ($name === '') {
			throw new RuntimeException('Brand name wajib diisi.');
		}

		if ($action === 'create') {
			$stmt = $pdo->prepare('INSERT INTO ms_brand (mbr_code, mbr_name, mbr_is_active) VALUES (:code, :name, :is_active)');
			$stmt->execute([
				':code' => $code,
				':name' => $name,
				':is_active' => $isActive,
			]);
			master_brand_redirect('Brand berhasil ditambahkan.');
		}

		if ($action === 'update') {
			if ($id <= 0) {
				throw new RuntimeException('Brand tidak ditemukan.');
			}
			$stmt = $pdo->prepare('UPDATE ms_brand SET mbr_code = :code, mbr_name = :name, mbr_is_active = :is_active WHERE mbr_id = :id');
			$stmt->execute([
				':id' => $id,
				':code' => $code,
				':name' => $name,
				':is_active' => $isActive,
			]);
			master_brand_redirect('Brand berhasil diperbarui.');
		}

		throw new RuntimeException('Aksi tidak dikenali.');
	} catch (Throwable $e) {
		master_brand_redirect('', $e->getMessage());
	}
}

$message = trim((string) ($_GET['message'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$results = [];
$totalRows = 0;
$paginationLinks = [];

if (($pdo ?? null) instanceof PDO) {
	try {
		$whereSql = '';
		$params = [];

		if ($search !== '') {
			$whereSql = 'WHERE mbr_name LIKE :search OR mbr_code LIKE :search';
			$params[':search'] = '%' . $search . '%';
		}

		$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ms_brand $whereSql");
		$countStmt->execute($params);
		$totalRows = (int) $countStmt->fetchColumn();

		$stmt = $pdo->prepare("
			SELECT mbr_id, mbr_code, mbr_name, mbr_is_active
			FROM ms_brand
			$whereSql
			ORDER BY mbr_name ASC
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
		$dbError = 'Gagal memuat data brand: ' . $e->getMessage();
	}
}

$tableColumns = [
	['label' => 'Brand'],
	['label' => 'Code'],
	['label' => 'Status'],
	['label' => 'Action', 'class' => 'w-1 text-end'],
];

$tableRows = [];
foreach ($results as $row) {
	$isActive = (int) ($row['mbr_is_active'] ?? 0) === 1;
	$tableRows[] = [
		'cells' => [
			[
				'html' => '<div class="d-flex py-1 align-items-center"><span class="avatar avatar-md me-3 bg-primary-lt text-primary fw-bold">' . master_brand_esc($row['mbr_code'] ?? '') . '</span><div class="flex-fill"><div class="fw-medium">' . master_brand_esc($row['mbr_name'] ?? '') . '</div><div class="text-secondary">Brand master entry</div></div></div>',
			],
			[
				'html' => '<code>' . master_brand_esc($row['mbr_code'] ?? '') . '</code>',
			],
			[
				'html' => $isActive
					? '<span class="badge bg-success-lt text-success">Active</span>'
					: '<span class="badge bg-danger-lt text-danger">Inactive</span>',
			],
			[
				'html' => '<button type="button" class="btn btn-sm btn-icon btn-outline-secondary edit-brand-button" data-id="' . master_brand_esc($row['mbr_id'] ?? '') . '" data-code="' . master_brand_esc($row['mbr_code'] ?? '') . '" data-name="' . master_brand_esc($row['mbr_name'] ?? '') . '" data-active="' . ($isActive ? '1' : '0') . '" title="Edit" aria-label="Edit"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h-.01"></path><path d="M3 21l3.75 -1l11.5 -11.5a2.121 2.121 0 0 0 -3 -3l-11.5 11.5l-1 3.75"></path><path d="M13 6l3 3"></path></svg></button>',
				'class' => 'text-end w-1',
			],
		],
	];
}

ob_start();
?>
<form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center">
	<div class="input-group input-group-flat" style="min-width: 280px;">
		<span class="input-group-text">
			<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
				<path d="M21 21l-6 -6" />
			</svg>
		</span>
		<input type="search" class="form-control" name="search" placeholder="Search brand or code..." value="<?= master_brand_esc($search) ?>">
	</div>
	<button type="submit" class="btn btn-primary">Search</button>
	<?php if ($search !== ''): ?>
		<a href="/master-brand" class="btn btn-outline-secondary">Reset</a>
	<?php endif; ?>
</form>
<?php
$tableFiltersHtml = (string) ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-primary" id="add-new-btn">
	<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
		<path d="M12 5l0 14" />
		<path d="M5 12l14 0" />
	</svg>
	Add Brand
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
						<a class="page-link" href="<?= master_brand_esc($pageHref) ?>"><?= master_brand_esc($link['label'] ?? '') ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
<?php
$tableFooterHtml = (string) ob_get_clean();

$tableId = 'master-brand-table';
$tableTitle = 'Master Brand';
$tableDescription = 'Kelola brand aktif yang dipakai oleh kendaraan dan produk di BRIX Shop.';
$tableEmptyMessage = 'No brands found.';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
	<meta http-equiv="X-UA-Compatible" content="ie=edge"/>
	<title>Master Brand - BRIX</title>
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
			<div class="page-header d-print-none">
				<div class="container-xl">
					<div class="row g-2 align-items-center">
						<div class="col">
							<div class="page-pretitle">BRIX Data Hub</div>
							<h1 class="page-title">Master Brand</h1>
						</div>
					</div>
				</div>
			</div>
			<main class="page-body">
				<div class="container-xl">
					<?php if ($message !== ''): ?><div class="alert alert-success"><?= master_brand_esc($message) ?></div><?php endif; ?>
					<?php if ($error !== ''): ?><div class="alert alert-danger"><?= master_brand_esc($error) ?></div><?php endif; ?>
					<?php if ($dbError !== ''): ?><div class="alert alert-danger"><?= master_brand_esc($dbError) ?></div><?php endif; ?>
					<div class="row row-cards">
						<div class="col-12">
							<?php include __DIR__ . '/../templates/data-hub-table.php'; ?>
						</div>
					</div>
				</div>
			</main>
		</div>
	</div>

	<div id="create-brand-modal" class="modal">
		<div class="modal-content">
			<h3>Create Brand</h3>
			<form id="create-brand-form" method="post" action="/master-brand">
				<input type="hidden" name="csrf_token" value="<?= master_brand_esc(csrf_token()) ?>">
				<input type="hidden" name="action" value="create">
				<div class="form-row">
					<label for="create-brand-name">Brand Name</label>
					<input type="text" name="name" id="create-brand-name" maxlength="100" required>
				</div>
				<div class="form-row">
					<label for="create-brand-code">Brand Code</label>
					<input type="text" name="code" id="create-brand-code" maxlength="3" required>
				</div>
				<div class="form-row">
					<label for="create-brand-flag">Status</label>
					<label class="switch">
						<input type="checkbox" id="create-brand-flag" name="is_active" checked>
						<span class="slider"></span>
					</label>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn-save">Create</button>
					<button type="button" id="cancel-create-brand-btn" class="btn-cancel">Cancel</button>
				</div>
			</form>
		</div>
	</div>

	<div id="edit-brand-modal" class="modal">
		<div class="modal-content">
			<h3>Edit Brand</h3>
			<form id="edit-brand-form" method="post" action="/master-brand">
				<input type="hidden" name="csrf_token" value="<?= master_brand_esc(csrf_token()) ?>">
				<input type="hidden" name="action" value="update">
				<input type="hidden" name="id" id="edit-brand-id">
				<div class="form-row">
					<label for="edit-brand-name">Brand Name</label>
					<input type="text" name="name" id="edit-brand-name" maxlength="100" required>
				</div>
				<div class="form-row">
					<label for="edit-brand-code">Brand Code</label>
					<input type="text" name="code" id="edit-brand-code" maxlength="3" required>
				</div>
				<div class="form-row">
					<label for="edit-brand-flag">Status</label>
					<label class="switch">
						<input type="checkbox" id="edit-brand-flag" name="is_active">
						<span class="slider"></span>
					</label>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn-save">Save Changes</button>
					<button type="button" id="cancel-edit-brand-btn" class="btn-cancel">Cancel</button>
				</div>
			</form>
		</div>
	</div>

	<script src="/assets/dist/js/tabler.js"></script>
	<script src="/assets/js/dashboard.js"></script>
	<script src="/assets/js/master-brand/modals.js" defer></script>
	<script src="/assets/js/idle-timeout.js" defer></script>
</body>
</html>
