<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';

$pageData = bootstrap_page('/application/controllers/master-dealer/ReadController.php');
extract($pageData, EXTR_SKIP);

$search = isset($search) ? (string) $search : '';
$page = isset($page) ? max(1, (int) $page) : 1;
$limit = isset($limit) ? max(1, (int) $limit) : 10;
$offset = isset($offset) ? max(0, (int) $offset) : (($page - 1) * $limit);
$totalRows = isset($totalRows) ? max(0, (int) $totalRows) : 0;
$totalPages = isset($totalPages) ? max(1, (int) $totalPages) : 1;
$results = isset($results) && is_iterable($results) ? $results : [];
$islands = isset($islands) && is_iterable($islands) ? $islands : [];
$paginationLinks = isset($paginationLinks) && is_array($paginationLinks) ? $paginationLinks : [];

$tableColumns = [
	['label' => 'Code'],
	['label' => 'Name'],
	['label' => 'Type'],
	['label' => 'Contact'],
	['label' => 'Address'],
	['label' => 'Join Date'],
	['label' => 'Status'],
	['label' => 'Action', 'class' => 'w-1 text-end'],
];
$tableRows = [];
foreach ($results as $row) {
	$isActive = ($row['dealer_status'] ?? 'N') === 'Y';
	$tableRows[] = ['cells' => [
		['html' => '<code>' . htmlspecialchars((string) $row['dealer_code'], ENT_QUOTES) . '</code>'],
		['html' => '<div class="fw-medium">' . htmlspecialchars((string) $row['dealer_name'], ENT_QUOTES) . '</div>'],
		['html' => '<span class="text-secondary">' . htmlspecialchars((string) $row['dealer_type'], ENT_QUOTES) . '</span>'],
		['html' => htmlspecialchars((string) $row['dealer_contact'], ENT_QUOTES)],
		['html' => '<span class="text-secondary">' . htmlspecialchars((string) $row['dealer_address'], ENT_QUOTES) . '</span>'],
		['html' => htmlspecialchars((string) $row['dealer_join_date'], ENT_QUOTES)],
		['html' => $isActive ? '<span class="badge bg-success-lt text-success">Active</span>' : '<span class="badge bg-danger-lt text-danger">Inactive</span>'],
		['html' => '<button type="button" class="btn btn-sm btn-icon btn-outline-secondary edit-button" data-id="' . htmlspecialchars((string) $row['dealer_code'], ENT_QUOTES) . '" title="Edit" aria-label="Edit"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7h-.01"></path><path d="M3 21l3.75 -1l11.5 -11.5a2.121 2.121 0 0 0 -3 -3l-11.5 11.5l-1 3.75"></path><path d="M13 6l3 3"></path></svg></button>', 'class' => 'text-end w-1'],
	]];
}
ob_start();
?><form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center"><div class="input-group input-group-flat" style="min-width: 320px;"><span class="input-group-text"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/></svg></span><input type="search" class="form-control" name="search" placeholder="Search data..." value="<?= htmlspecialchars($search ?? '', ENT_QUOTES) ?>"></div><button type="submit" class="btn btn-primary">Search</button><?php if (($search ?? '') !== ''): ?><a href="/master-dealer" class="btn btn-outline-secondary">Reset</a><?php endif; ?></form><?php
$tableFiltersHtml = (string) ob_get_clean();
ob_start();
?><button type="button" class="btn btn-primary" id="add-new-btn"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>Add New Entry</button><?php
$tableToolbarHtml = (string) ob_get_clean();
ob_start();
?><div class="row g-2 justify-content-center justify-content-sm-between align-items-center"><div class="col-auto d-flex align-items-center"><p class="m-0 text-secondary">Showing <strong><?= $totalRows > 0 ? ($offset + 1) : 0 ?></strong> to <strong><?= min($offset + $limit, $totalRows) ?></strong> of <strong><?= $totalRows ?></strong> entries</p></div><?php if (!empty($paginationLinks)): ?><div class="col-auto"><ul class="pagination m-0"><?php foreach ($paginationLinks as $link): $isDisabled = !empty($link['disabled']) || $link['page'] === null; $pageHref = $link['page'] !== null ? '?page=' . $link['page'] . '&search=' . urlencode((string) $search) : '#'; ?><li class="page-item <?= !empty($link['active']) ? 'active' : '' ?> <?= $isDisabled ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($pageHref, ENT_QUOTES) ?>"><?= htmlspecialchars((string) $link['label'], ENT_QUOTES) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?></div><?php
$tableFooterHtml = (string) ob_get_clean();
$tableId = 'master-dealer-table';
$tableTitle = 'Master Dealer';
$tableDescription = 'Manage dealer identity, type, contact details, address mapping, and join date in one table.';
$tableEmptyMessage = 'No results found.';
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/><meta http-equiv="X-UA-Compatible" content="ie=edge"/><title>Master Dealer - BRIX</title><link rel="icon" href="/favicon.ico" type="image/x-icon"/><link rel="shortcut icon" href="/favicon.ico" type="image/x-icon"/><link href="/assets/dist/css/tabler.css" rel="stylesheet"/><link href="/assets/dist/css/tabler-flags.css" rel="stylesheet"/><link href="/assets/dist/css/tabler-socials.css" rel="stylesheet"/><link href="/assets/dist/css/tabler-payments.css" rel="stylesheet"/><link href="/assets/dist/css/tabler-vendors.css" rel="stylesheet"/><link href="/assets/dist/css/tabler-marketing.css" rel="stylesheet"/><link href="/assets/dist/css/tabler-themes.css" rel="stylesheet"/><link href="/preview/css/demo.css" rel="stylesheet"/><link href="/assets/css/dashboard.css" rel="stylesheet"/><link href="/assets/css/data-hub-table.css" rel="stylesheet"/><link href="/assets/css/data-hub-modal.css" rel="stylesheet"/><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head>
<body><script src="/assets/dist/js/tabler-theme.js"></script><div class="page"><?php include __DIR__ . '/../templates/sidebar.php'; ?><div class="page-wrapper"><div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">BRIX Data Hub</div><h1 class="page-title">Master Dealer</h1></div></div></div></div><main class="page-body"><div class="container-xl"><div class="row row-cards"><div class="col-12"><?php include __DIR__ . '/../templates/data-hub-table.php'; ?></div></div></div></main></div></div>
<div id="edit-modal" class="modal"><div class="modal-content"><h3>Edit Dealer</h3><form id="dealer-edit-form" method="post" action="/master-dealer/update" onsubmit="return false;"><input type="hidden" name="dealer_code" id="edit-code"><div class="form-row"><label for="edit-name">Dealer Name</label><input type="text" name="dealer_name" id="edit-name" required></div><div class="form-row"><label for="edit-type">Type</label><select name="dealer_type" id="edit-type" required><option value="">Select type</option><option value="R">Regular</option><option value="O">Online</option></select></div><div class="form-row"><label for="edit-contact">Contact</label><input type="text" name="dealer_contact" id="edit-contact"></div><div class="form-row"><label for="edit-address">Address</label><textarea name="dealer_address" id="edit-address" rows="3"></textarea></div><div class="form-row"><label for="edit-map">Google Map Embed</label><textarea name="dealer_map" id="edit-map" rows="3"></textarea></div><div class="form-row"><label for="edit-join">Join Date</label><input type="date" name="dealer_join_date" id="edit-join"></div><div class="form-row"><label for="edit-status">Status</label><label class="switch"><input type="checkbox" id="edit-status"><span class="slider"></span></label><small style="margin-left:8px;opacity:.8">Active when ON</small><input type="hidden" name="dealer_status" id="edit-status-hidden" value="Y"></div><div class="form-actions"><button type="submit" class="btn-save" id="edit-save">Save Changes</button><button type="button" id="edit-cancel" class="btn-cancel">Cancel</button></div></form></div></div>
<div id="modal-step1" class="modal"><div class="modal-content"><h3>Step 1 - Choose Island</h3><form id="step1-form" onsubmit="return false;"><div class="form-row"><label for="step1-island">Island</label><select id="step1-island" hidden><option value="">Select an island</option><?php foreach ($islands as $island): ?><option value="<?= htmlspecialchars((string) $island['msi_code'], ENT_QUOTES) ?>"><?= htmlspecialchars((string) $island['msi_name'], ENT_QUOTES) ?></option><?php endforeach; ?></select><div class="custom-select-wrapper"><input type="text" id="step1-island-search" placeholder="Search island..."><div id="step1-island-options" class="custom-options"></div></div></div><div class="form-actions"><button type="button" id="step1-cancel" class="btn-cancel">Cancel</button><button type="button" id="step1-next" class="btn-save" disabled>Next</button></div></form></div></div>
<div id="modal-step2" class="modal"><div class="modal-content"><h3>Step 2 - Choose Province</h3><form id="step2-form" onsubmit="return false;"><div class="form-row"><label>Island</label><input type="text" id="step2-island-display" disabled></div><div class="form-row"><label for="step2-province">Province</label><select id="step2-province" hidden><option value="">Select a province</option></select><div class="custom-select-wrapper"><input type="text" id="step2-province-search" placeholder="Search province..." disabled><div id="step2-province-options" class="custom-options"></div></div></div><div class="form-actions"><button type="button" id="step2-back" class="btn-cancel">Back</button><button type="button" id="step2-next" class="btn-save" disabled>Next</button></div></form></div></div>
<div id="modal-step3" class="modal"><div class="modal-content"><h3>Step 3 - Dealer Details</h3><form id="dealer-create-form" method="post" action="/master-dealer/create" onsubmit="return false;"><div class="form-row"><label>Island</label><input type="text" id="step3-island-display" disabled></div><div class="form-row"><label>Province</label><input type="text" id="step3-province-display" disabled></div><input type="hidden" name="island" id="final-island"><input type="hidden" name="province" id="final-province"><div class="form-row"><label for="dealer_name">Dealer Name</label><input type="text" id="dealer_name" name="dealer_name" placeholder="e.g., Asco Motorsport BSD" required></div><div class="form-row"><label for="dealer_type">Dealer Type</label><select id="dealer_type" name="dealer_type" required><option value="">Select type</option><option value="R">Regular</option><option value="O">Online</option></select></div><div class="form-row"><label for="dealer_contact">Dealer Contact</label><input type="text" id="dealer_contact" name="dealer_contact" placeholder="Phone / WhatsApp / Email"></div><div class="form-row"><label for="dealer_address">Dealer Address</label><textarea id="dealer_address" name="dealer_address" rows="3" placeholder="Full address"></textarea></div><div class="form-row"><label for="dealer_map">Google Map Embed</label><textarea id="dealer_map" name="dealer_map" rows="3" placeholder="Paste iframe embed"></textarea></div><div class="form-row"><label for="dealer_join_date">Join Date</label><input type="date" id="dealer_join_date" name="dealer_join_date"></div><div class="form-actions"><button type="button" id="step3-back" class="btn-cancel">Back</button><button type="button" id="create-submit" class="btn-save">Create</button></div></form></div></div>
<script src="/assets/dist/js/tabler.js"></script><script src="/assets/js/dashboard.js"></script><script src="/assets/js/master-dealer/modals.js" defer></script><script src="/assets/js/idle-timeout.js" defer></script></body></html>
