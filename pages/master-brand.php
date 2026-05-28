<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';

$pageData = bootstrap_page('/application/controllers/master-brand/ReadController.php');
extract($pageData, EXTR_SKIP);

$search = isset($search) ? (string) $search : '';
$page = isset($page) ? max(1, (int) $page) : 1;
$limit = isset($limit) ? max(1, (int) $limit) : 10;
$offset = isset($offset) ? max(0, (int) $offset) : (($page - 1) * $limit);
$totalRows = isset($totalRows) ? max(0, (int) $totalRows) : 0;
$totalPages = isset($totalPages) ? max(1, (int) $totalPages) : 1;
$results = isset($results) && is_iterable($results) ? $results : [];
$paginationLinks = isset($paginationLinks) && is_array($paginationLinks) ? $paginationLinks : [];

function resolve_brand_cover_image(?string $fileName): string
{
	$slug = strtolower(trim((string) $fileName));
	if ($slug === '') {
		return '/assets/images/logos/cover_brand/blank.png';
	}

	$baseDir = dirname(__DIR__) . '/assets/images/logos/cover_brand';
	foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
		$file = $slug . '.' . $extension;
		if (is_file($baseDir . '/' . $file)) {
			return '/assets/images/logos/cover_brand/' . $file;
		}
	}

	return '/assets/images/logos/cover_brand/blank.png';
}

$tableColumns = [
	['label' => 'Brand'],
	['label' => 'File Name'],
	['label' => 'Status'],
	['label' => 'Action', 'class' => 'w-1 text-end'],
];

$tableRows = [];

foreach ($results as $row) {
	$imagePath = resolve_brand_cover_image($row['mbr_file_name'] ?? '');
	$isActive = ($row['mbr_flag'] ?? 'N') === 'Y';

	$tableRows[] = [
		'cells' => [
			[
				'html' => '
					<div class="d-flex py-1 align-items-center">
						<span class="avatar avatar-md me-3 bg-white border"><img src="' . htmlspecialchars($imagePath, ENT_QUOTES) . '" alt="' . htmlspecialchars((string) ($row['mbr_name'] ?? ''), ENT_QUOTES) . '" style="border-radius: 5px;"></span>
						<div class="flex-fill">
							<div class="fw-medium">' . htmlspecialchars((string) ($row['mbr_name'] ?? ''), ENT_QUOTES) . '</div>
							<div class="text-secondary">Brand master entry</div>
						</div>
					</div>',
			],
			[
				'html' => '<code>' . htmlspecialchars((string) ($row['mbr_file_name'] ?? ''), ENT_QUOTES) . '</code>',
				'class' => 'text-secondary',
			],
			[
				'html' => $isActive
					? '<span class="badge bg-success-lt text-success">Active</span>'
					: '<span class="badge bg-danger-lt text-danger">Inactive</span>',
			],
			[
				'html' => '<button type="button" class="btn btn-sm btn-icon btn-outline-secondary edit-brand-button" data-id="' . htmlspecialchars((string) ($row['mbr_id'] ?? ''), ENT_QUOTES) . '" data-name="' . htmlspecialchars((string) ($row['mbr_name'] ?? ''), ENT_QUOTES) . '" data-file="' . htmlspecialchars((string) ($row['mbr_file_name'] ?? ''), ENT_QUOTES) . '" data-flag="' . ($isActive ? 'Y' : 'N') . '" title="Edit" aria-label="Edit"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7h-.01"></path><path d="M3 21l3.75 -1l11.5 -11.5a2.121 2.121 0 0 0 -3 -3l-11.5 11.5l-1 3.75"></path><path d="M13 6l3 3"></path></svg></button>',
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
			<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
				<path d="M21 21l-6 -6" />
			</svg>
		</span>
		<input type="search" class="form-control" name="search" placeholder="Search brand..." value="<?= htmlspecialchars($search ?? '', ENT_QUOTES) ?>">
	</div>
	<button type="submit" class="btn btn-primary">Search</button>
	<?php if (($search ?? '') !== ''): ?>
		<a href="/master-brand" class="btn btn-outline-secondary">Reset</a>
	<?php endif; ?>
</form>
<?php
$tableFiltersHtml = (string) ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-primary" id="add-new-btn">
	<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
		<path d="M12 5l0 14" />
		<path d="M5 12l14 0" />
	</svg>
	Add New Entry
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
					<?php
					$isDisabled = !empty($link['disabled']) || $link['page'] === null;
					$pageHref = $link['page'] !== null ? '?page=' . $link['page'] . '&search=' . urlencode((string) $search) : '#';
					?>
					<li class="page-item <?= !empty($link['active']) ? 'active' : '' ?> <?= $isDisabled ? 'disabled' : '' ?>">
						<a class="page-link" href="<?= htmlspecialchars($pageHref, ENT_QUOTES) ?>"><?= htmlspecialchars((string) $link['label'], ENT_QUOTES) ?></a>
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
$tableDescription = 'Manage brand names, logo file keys, and active status from one shared Data Hub table.';
$tableEmptyMessage = 'No brands found.';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
	<meta http-equiv="X-UA-Compatible" content="ie=edge"/>
	<title>Master Brand - Brill</title>
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
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
							<div class="page-pretitle">Brill Data Hub</div>
							<h1 class="page-title">Master Brand</h1>
						</div>
					</div>
				</div>
			</div>

			<main class="page-body">
				<div class="container-xl">
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
			<form id="create-brand-form">
				<div class="form-row">
					<label for="create-brand-name">Brand Name</label>
					<input type="text" name="name" id="create-brand-name" maxlength="100" required>
				</div>
				<div class="form-row">
					<label for="create-brand-file">File Name (logo)</label>
					<input type="text" name="file" id="create-brand-file" maxlength="100" placeholder="auto from name" readonly>
				</div>
				<div class="form-row">
					<label for="create-brand-flag">Status</label>
					<label class="switch">
						<input type="checkbox" id="create-brand-flag" name="flag">
						<span class="slider"></span>
					</label>
					<small style="margin-left:8px;opacity:.8">Active when ON</small>
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
			<form id="edit-brand-form">
				<input type="hidden" name="id" id="edit-brand-id">
				<div class="form-row">
					<label for="edit-brand-name">Brand Name</label>
					<input type="text" name="name" id="edit-brand-name" maxlength="100" required>
				</div>
				<div class="form-row">
					<label for="edit-brand-file">File Name (logo)</label>
					<input type="text" name="file" id="edit-brand-file" maxlength="100" placeholder="e.g. toyota">
				</div>
				<div class="form-row">
					<label for="edit-brand-flag">Status</label>
					<label class="switch">
						<input type="checkbox" id="edit-brand-flag" name="flag">
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
