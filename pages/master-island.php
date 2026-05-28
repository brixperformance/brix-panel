<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';

$pageData = bootstrap_page('/application/controllers/master-island/ReadController.php');
extract($pageData, EXTR_SKIP);

$search = isset($search) ? (string) $search : '';
$page = isset($page) ? max(1, (int) $page) : 1;
$limit = isset($limit) ? max(1, (int) $limit) : 15;
$offset = isset($offset) ? max(0, (int) $offset) : (($page - 1) * $limit);
$totalRows = isset($totalRows) ? max(0, (int) $totalRows) : 0;
$totalPages = isset($totalPages) ? max(1, (int) $totalPages) : 1;
$results = isset($results) && is_iterable($results) ? $results : [];
$paginationLinks = isset($paginationLinks) && is_array($paginationLinks) ? $paginationLinks : [];

$tableColumns = [
	['label' => 'Code'],
	['label' => 'Island Name'],
	['label' => 'Status'],
	['label' => 'Action', 'class' => 'w-1 text-end'],
];

$tableRows = [];
foreach ($results as $row) {
	$isActive = ($row['msi_active_status'] ?? 'N') === 'Y';
	$tableRows[] = [
		'cells' => [
			['html' => '<code>' . htmlspecialchars((string) $row['msi_code'], ENT_QUOTES) . '</code>'],
			[
				'html' => '<div class="fw-medium">' . htmlspecialchars((string) $row['msi_name'], ENT_QUOTES) . '</div><div class="text-secondary">Island master entry</div>',
			],
			[
				'html' => $isActive
					? '<span class="badge bg-success-lt text-success">Active</span>'
					: '<span class="badge bg-danger-lt text-danger">Inactive</span>',
			],
			[
				'html' => '<button type="button" class="btn btn-sm btn-icon btn-outline-secondary edit-island-btn" data-code="' . htmlspecialchars((string) $row['msi_code'], ENT_QUOTES) . '" data-name="' . htmlspecialchars((string) $row['msi_name'], ENT_QUOTES) . '" data-status="' . ($isActive ? 'Y' : 'N') . '" title="Edit" aria-label="Edit"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7h-.01"></path><path d="M3 21l3.75 -1l11.5 -11.5a2.121 2.121 0 0 0 -3 -3l-11.5 11.5l-1 3.75"></path><path d="M13 6l3 3"></path></svg></button>',
				'class' => 'text-end w-1',
			],
		],
	];
}

ob_start();
?>
<form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center">
	<div class="input-group input-group-flat" style="min-width: 320px;">
		<span class="input-group-text">
			<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/></svg>
		</span>
		<input type="search" class="form-control" name="search" placeholder="Search island code or name..." value="<?= htmlspecialchars($search ?? '', ENT_QUOTES) ?>">
	</div>
	<button type="submit" class="btn btn-primary">Search</button>
	<?php if (($search ?? '') !== ''): ?><a href="/master-island" class="btn btn-outline-secondary">Reset</a><?php endif; ?>
</form>
<?php
$tableFiltersHtml = (string) ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-primary" id="add-new-btn">
	<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
	Add New Island
</button>
<?php
$tableToolbarHtml = (string) ob_get_clean();

ob_start();
?>
<div class="row g-2 justify-content-center justify-content-sm-between align-items-center">
	<div class="col-auto d-flex align-items-center">
		<p class="m-0 text-secondary">Showing <strong><?= $totalRows > 0 ? ($offset + 1) : 0 ?></strong> to <strong><?= min($offset + $limit, $totalRows) ?></strong> of <strong><?= $totalRows ?></strong> entries</p>
	</div>
	<?php if (!empty($paginationLinks)): ?><div class="col-auto"><ul class="pagination m-0"><?php foreach ($paginationLinks as $link): $isDisabled = !empty($link['disabled']) || $link['page'] === null; $pageHref = $link['page'] !== null ? '?page=' . $link['page'] . '&search=' . urlencode((string) $search) : '#'; ?><li class="page-item <?= !empty($link['active']) ? 'active' : '' ?> <?= $isDisabled ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($pageHref, ENT_QUOTES) ?>"><?= htmlspecialchars((string) $link['label'], ENT_QUOTES) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
</div>
<?php
$tableFooterHtml = (string) ob_get_clean();

$tableId = 'master-island-table';
$tableTitle = 'Master Island';
$tableDescription = 'Manage island codes used as the base for province and dealer regional mapping.';
$tableEmptyMessage = 'No islands found.';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
	<meta http-equiv="X-UA-Compatible" content="ie=edge"/>
	<title>Master Island - BRIX</title>
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
			<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">BRIX Data Hub</div><h1 class="page-title">Master Island</h1></div></div></div></div>
			<main class="page-body"><div class="container-xl"><div class="row row-cards"><div class="col-12"><?php include __DIR__ . '/../templates/data-hub-table.php'; ?></div></div></div></main>
		</div>
	</div>

	<div id="edit-island-modal" class="modal"><div class="modal-content"><h3>Edit Island</h3><form id="edit-island-form"><input type="hidden" name="code" id="edit-island-code"><div class="form-row"><label>Island Code</label><input type="text" id="edit-island-code-display" maxlength="2" disabled></div><div class="form-row"><label for="edit-island-name">Island Name</label><input type="text" name="name" id="edit-island-name" maxlength="100" required></div><div class="form-row"><label for="edit-island-status">Status</label><label class="switch"><input type="checkbox" id="edit-island-status" name="status"><span class="slider"></span></label><small style="margin-left:8px;opacity:.8">Active when ON</small></div><div class="form-actions"><button type="submit" class="btn-save">Save Changes</button><button type="button" id="cancel-edit-island-btn" class="btn-cancel">Cancel</button></div></form></div></div>
	<div id="create-island-modal" class="modal"><div class="modal-content"><h3>Add New Island</h3><form id="create-island-form"><div class="form-row"><label for="create-island-code">Island Code <small>(2 letters, e.g. JV)</small></label><input type="text" name="code" id="create-island-code" maxlength="2" placeholder="e.g. JV" required style="text-transform:uppercase;"></div><div class="form-row"><label for="create-island-name">Island Name</label><input type="text" name="name" id="create-island-name" maxlength="100" placeholder="e.g. Java" required></div><div class="form-row"><label for="create-island-status">Status</label><label class="switch"><input type="checkbox" id="create-island-status" name="status" checked><span class="slider"></span></label><small style="margin-left:8px;opacity:.8">Active when ON</small></div><div class="form-actions"><button type="submit" class="btn-save">Create</button><button type="button" id="cancel-create-island-btn" class="btn-cancel">Cancel</button></div></form></div></div>

	<script src="/assets/dist/js/tabler.js"></script>
	<script src="/assets/js/dashboard.js"></script>
	<script src="/assets/js/master-island/modals.js" defer></script>
	<script src="/assets/js/idle-timeout.js" defer></script>
</body>
</html>
