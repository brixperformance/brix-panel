<?php
require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';

$pageData = bootstrap_page('/application/controllers/log-invoice/ReadController.php');
extract($pageData, EXTR_SKIP);

$search = isset($search) ? (string) $search : '';
$page = isset($page) ? max(1, (int) $page) : 1;
$limit = isset($limit) ? max(1, (int) $limit) : 15;
$offset = isset($offset) ? max(0, (int) $offset) : (($page - 1) * $limit);
$totalRows = isset($totalRows) ? max(0, (int) $totalRows) : 0;
$totalPages = isset($totalPages) ? max(1, (int) $totalPages) : 1;
$results = isset($results) && is_iterable($results) ? $results : [];
$paginationLinks = isset($paginationLinks) && is_array($paginationLinks) ? $paginationLinks : [];
$type = isset($type) ? (string) $type : '';
$dealerCode = isset($dealerCode) ? (string) $dealerCode : '';
$scope = isset($scope) ? (string) $scope : '';
$sort = isset($sort) ? (string) $sort : 'newest';

// Execute is already loaded by ReadController. Query dealers directly to avoid
// class-name conflict between log-invoice/View.php and master-dealer/View.php.
$_dealerCfg          = require dirname(__DIR__) . '/application/configs/database.php';
$_dealerExec         = new Execute($_dealerCfg);
$_dealerResult       = $_dealerExec->executeSelect(
    "SELECT msd_code AS dealer_code, msd_name AS dealer_name,
            msd_contact AS dealer_contact, msd_address AS dealer_address
     FROM ms_dealer WHERE msd_status = 'Y' ORDER BY msd_name ASC",
    [],
    'all'
);
$dealerOptions = $_dealerResult['data'] ?? [];
unset($_dealerCfg, $_dealerExec, $_dealerResult);

$tableColumns = [
    ['label' => 'Invoice Number'],
    ['label' => 'Type'],
    ['label' => 'Bill To'],
    ['label' => 'Ship To'],
    ['label' => 'Total (IDR)'],
    ['label' => 'Create Date'],
    ['label' => 'Last Update'],
    ['label' => 'Action', 'class' => 'w-1 text-end'],
];

$tableRows = [];
foreach ($results as $row) {
    $billToFirst = explode("\n", (string) $row['linv_bill_to'])[0];
    $typeLabel = $row['linv_type'] === 'dealer' ? 'Dealer' : 'Customer';
    $typeBadgeClass = $row['linv_type'] === 'dealer'
        ? 'badge bg-blue-lt text-blue'
        : 'badge bg-red-lt text-red';

    $tableRows[] = [
        'cells' => [
            [
                'html' => '<div class="fw-semibold">' . htmlspecialchars((string) $row['linv_number'], ENT_QUOTES) . '</div>',
            ],
            [
                'html' => '<span class="' . htmlspecialchars($typeBadgeClass, ENT_QUOTES) . '">' . htmlspecialchars($typeLabel, ENT_QUOTES) . '</span>',
            ],
            [
                'html' => '<div class="text-secondary text-wrap">' . htmlspecialchars($billToFirst, ENT_QUOTES) . '</div>',
                'class' => 'text-wrap',
            ],
            [
                'html' => '<div class="text-secondary text-wrap">' . htmlspecialchars((string) $row['linv_ship_to'], ENT_QUOTES) . '</div>',
                'class' => 'text-wrap',
            ],
            [
                'html' => '<span class="fw-medium">' . htmlspecialchars((string) $row['linv_total_fmt'], ENT_QUOTES) . '</span>',
            ],
            [
                'html' => htmlspecialchars((string) $row['linv_date_fmt'], ENT_QUOTES),
                'class' => 'text-secondary',
            ],
            [
                'html' => htmlspecialchars((string) ($row['linv_last_update_fmt'] ?? $row['linv_date_fmt']), ENT_QUOTES),
                'class' => 'text-secondary',
            ],
            [
                'html' => '
                    <div class="invoice-log-actions justify-content-end">
                        <button type="button" class="invoice-log-action-btn invoice-log-action-btn--preview log-btn--preview" data-log-id="' . (int) $row['linv_id'] . '" data-log-number="' . htmlspecialchars((string) $row['linv_number'], ENT_QUOTES) . '" title="Preview Invoice" aria-label="Preview Invoice">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 5c-5 0 -9.27 3.11 -11 7c1.73 3.89 6 7 11 7s9.27 -3.11 11 -7c-1.73 -3.89 -6 -7 -11 -7z" />
                                <path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                            </svg>
                        </button>
                        <button type="button" class="invoice-log-action-btn invoice-log-action-btn--edit log-btn--edit" data-log-id="' . (int) $row['linv_id'] . '" data-log-number="' . htmlspecialchars((string) $row['linv_number'], ENT_QUOTES) . '" data-bill-to="' . htmlspecialchars($billToFirst, ENT_QUOTES) . '" data-ship-to="' . htmlspecialchars((string) $row['linv_ship_to'], ENT_QUOTES) . '" title="Edit Invoice" aria-label="Edit Invoice">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M7 7h-.01" />
                                <path d="M3 21l3.75 -1l11.5 -11.5a2.121 2.121 0 0 0 -3 -3l-11.5 11.5l-1 3.75" />
                                <path d="M13 6l3 3" />
                            </svg>
                        </button>

                        <button type="button" class="invoice-log-action-btn invoice-log-action-btn--delete log-btn--delete" data-log-id="' . (int) $row['linv_id'] . '" data-log-number="' . htmlspecialchars((string) $row['linv_number'], ENT_QUOTES) . '" title="Delete Invoice" aria-label="Delete Invoice">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 7l16 0" />
                                <path d="M10 11l0 6" />
                                <path d="M14 11l0 6" />
                                <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                                <path d="M9 7l1 -3h4l1 3" />
                            </svg>
                        </button>
                    </div>',
                'class' => 'text-end',
            ],
        ],
    ];
}

ob_start();
?>
<form method="GET" action="" class="invoice-log-filter-form">
    <?php if ($type !== ''): ?>
        <input type="hidden" name="type" value="<?= htmlspecialchars($type, ENT_QUOTES) ?>">
    <?php endif; ?>
    <?php if ($dealerCode !== ''): ?>
        <input type="hidden" name="dealer_code" value="<?= htmlspecialchars($dealerCode, ENT_QUOTES) ?>">
    <?php endif; ?>
    <?php if ($scope !== ''): ?>
        <input type="hidden" name="scope" value="<?= htmlspecialchars($scope, ENT_QUOTES) ?>">
    <?php endif; ?>
    <div class="row g-3 align-items-end">
        <div class="col-lg-7">
            <label class="form-label" for="invoice-log-search">Search</label>
            <div class="input-group input-group-flat">
                <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                        <path d="M21 21l-6 -6" />
                    </svg>
                </span>
                <input id="invoice-log-search" type="search" name="search" class="form-control" placeholder="Search invoice number, bill to, ship to..." value="<?= htmlspecialchars($search ?? '', ENT_QUOTES) ?>">
            </div>
        </div>
        <div class="col-lg-3">
            <label class="form-label" for="invoice-log-sort">Sort By</label>
            <div class="shipping-combobox invoice-log-sort-combobox" data-combobox>
                <button type="button" class="shipping-combobox-trigger form-select text-start" data-combobox-trigger>
                    <span data-combobox-label><?=
                        match ($sort) {
                            'oldest' => 'Oldest First',
                            'highest' => 'Highest Total',
                            'lowest' => 'Lowest Total',
                            default => 'Newest First',
                        }
                    ?></span>
                    <span class="shipping-combobox-caret"></span>
                </button>
                <div class="shipping-combobox-menu" data-combobox-menu hidden>
                    <input type="text" class="shipping-combobox-search" placeholder="Search sort option" autocomplete="off" data-combobox-search>
                    <div class="shipping-combobox-options" data-combobox-options></div>
                </div>
                <select id="invoice-log-sort" name="sort" hidden>
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="highest" <?= $sort === 'highest' ? 'selected' : '' ?>>Highest Total</option>
                    <option value="lowest" <?= $sort === 'lowest' ? 'selected' : '' ?>>Lowest Total</option>
                </select>
            </div>
        </div>
        <div class="col-lg-2 d-grid">
            <button type="submit" class="btn btn-primary">Apply</button>
        </div>
    </div>
</form>
<?php
$tableFiltersHtml = (string) ob_get_clean();

ob_start();
?>
<div class="row g-2 justify-content-center justify-content-sm-between align-items-center">
    <div class="col-auto d-flex align-items-center">
        <p class="m-0 text-secondary">Showing <strong><?= $totalRows === 0 ? 0 : ($offset + 1) ?></strong> to <strong><?= min($offset + $limit, $totalRows) ?></strong> of <strong><?= $totalRows ?></strong> entries</p>
    </div>
    <?php if (!empty($paginationLinks)): ?>
        <div class="col-auto">
            <ul class="pagination m-0">
                <?php foreach ($paginationLinks as $link): ?>
                    <?php if ($link['label'] === '...'): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php else: ?>
                        <li class="page-item <?= $link['active'] ? 'active' : '' ?> <?= ($link['disabled'] ?? false) ? 'disabled' : '' ?>">
                            <a
                                class="page-link"
                                href="<?= htmlspecialchars(
                                    $link['page'] !== null
                                        ? '?' . http_build_query(array_filter([
                                            'page' => $link['page'],
                                            'search' => $search,
                                            'sort' => $sort,
                                            'type' => $type,
                                            'dealer_code' => $dealerCode,
                                            'scope' => $scope,
                                        ], static fn($value) => $value !== ''))
                                        : '#',
                                    ENT_QUOTES
                                ) ?>"
                            >
                                <?= htmlspecialchars((string) $link['label'], ENT_QUOTES) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
<?php
$tableFooterHtml = (string) ob_get_clean();

$tableId = 'invoice-log-table';
$tableTitle = 'Invoice Log';
$tableDescription = 'Review saved invoices, inspect results, and continue to preview, edit, or delete entries from one shared table layout.';
$tableEmptyMessage = 'No invoice log found.';
$tableClassName = 'invoice-log-table';
$tableNowrap = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Invoice Log - Brill</title>

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
    <link rel="stylesheet" href="/assets/css/invoice-generator.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-container { z-index: 9999 !important; }

        .invoice-log-sort-combobox {
            min-width: 100%;
        }

        .invoice-log-sort-combobox .shipping-combobox-trigger {
            width: 100%;
            min-height: var(--tblr-input-height);
            border: var(--tblr-border-width) solid var(--tblr-border-color);
            border-radius: var(--tblr-border-radius);
            background: var(--tblr-bg-surface);
            color: var(--tblr-body-color);
            padding: .5625rem 2.25rem .5625rem .75rem;
            font-size: .875rem;
            line-height: 1.4285714286;
            box-shadow: none;
        }

        .invoice-log-sort-combobox .shipping-combobox-trigger:focus {
            border-color: rgba(var(--tblr-primary-rgb), .5);
            outline: 0;
            box-shadow: 0 0 0 .25rem rgba(var(--tblr-primary-rgb), .25);
        }

        .invoice-log-sort-combobox .shipping-combobox-menu {
            z-index: 160;
        }

        .invoice-log-table .text-wrap {
            white-space: normal;
        }

        .invoice-log-actions {
            display: inline-flex;
            gap: .375rem;
            align-items: center;
        }

        .invoice-log-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border: 1px solid var(--tblr-border-color);
            border-radius: .625rem;
            background: var(--tblr-bg-surface);
            color: var(--tblr-secondary);
            transition: color .18s ease, border-color .18s ease, background-color .18s ease, transform .18s ease;
        }

        .invoice-log-action-btn:hover {
            transform: translateY(-1px);
        }

        .invoice-log-action-btn--preview:hover {
            color: var(--tblr-primary);
            border-color: rgba(var(--tblr-primary-rgb), .35);
            background: rgba(var(--tblr-primary-rgb), .06);
        }

        .invoice-log-action-btn--edit:hover {
            color: #b45309;
            border-color: rgba(217, 119, 6, .3);
            background: rgba(217, 119, 6, .08);
        }

        .invoice-log-action-btn--download:hover {
            color: #fff;
            border-color: #284b63;
            background: #284b63;
        }

        .invoice-log-action-btn--delete:hover {
            color: var(--tblr-red);
            border-color: rgba(var(--tblr-red-rgb), .35);
            background: rgba(var(--tblr-red-rgb), .08);
        }

        #modal-invoice-preview {
            position: fixed !important;
            inset: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow-y: auto !important;
            z-index: 10500 !important;
        }

        #modal-invoice-preview.fade:not(.show) {
            display: none !important;
        }

        #modal-invoice-preview .modal-dialog {
            position: relative !important;
            margin: auto !important;
            transform: none !important;
            width: calc(85vh * 0.707);
            max-width: 92vw;
            max-height: 85vh;
        }

        #modal-invoice-preview .modal-content {
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        #modal-invoice-preview .modal-header {
            padding: 1.25rem 1.5rem;
        }

        #modal-invoice-preview .modal-footer {
            padding: 1rem 1.5rem;
        }

        #modal-invoice-preview .modal-body {
            padding: 1rem;
            overflow-y: auto;
            flex: 1 1 auto;
            min-height: 0;
            background: #f1f5f9;
        }

        #modal-invoice-preview .modal-body iframe {
            width: 100%;
            height: 0;
            border: none;
            display: block;
            background: #fff;
            border-radius: .5rem;
            box-shadow: 0 2px 16px rgba(0,0,0,.10);
        }

        .edit-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(14, 28, 42, 0.65);
            z-index: 1200;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 2rem 1rem 3rem;
        }
        .edit-modal-overlay[hidden] { display: none !important; }

        .edit-modal-shell {
            background: #fff;
            border-radius: 1.25rem;
            width: 100%;
            max-width: 960px;
            box-shadow: 0 24px 64px rgba(14, 28, 42, 0.22);
            display: flex;
            flex-direction: column;
            overflow: visible;
        }

        .edit-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1.4rem 1.5rem 1rem;
            border-bottom: 1px solid #dde5ec;
        }
        .edit-modal-header h2 {
            color: #16324f;
            font-size: 1.2rem;
            margin-bottom: 0.15rem;
        }
        .edit-modal-subtitle {
            color: #607485;
            font-size: 0.92rem;
            display: block;
            margin-top: 0.15rem;
        }
        .edit-modal-close {
            border: none;
            background: #edf1f5;
            border-radius: 0.6rem;
            width: 34px;
            height: 34px;
            font-size: 1.25rem;
            line-height: 1;
            cursor: pointer;
            color: #334c61;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .edit-modal-close:hover { background: #dce5ec; }

        .edit-modal-meta {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin: 1rem 1.5rem 0;
            padding: 1.15rem;
            background: #ffffff;
            border: 1px solid #d8e0e8;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(14, 34, 56, 0.06);
        }
        .edit-modal-meta .invoice-field label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #7a90a0;
        }
        .edit-modal-meta input {
            width: 100%;
            border: 1px solid #c8d2dc;
            border-radius: 0.75rem;
            padding: 0.75rem 0.9rem;
            font-size: 0.95rem;
            color: #1f2f3d;
            background: #fff;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }
        .edit-modal-meta input[readonly] {
            background: #f3f6f9;
            color: #607485;
            cursor: default;
        }
        .edit-modal-meta input[type="date"]:focus {
            border-color: #284b63;
            box-shadow: 0 0 0 3px rgba(40, 75, 99, 0.12);
        }

        .edit-modal-address-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1rem 1.5rem 0;
            padding: 1.15rem;
            background: #ffffff;
            border: 1px solid #d8e0e8;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(14, 34, 56, 0.06);
        }
        .edit-modal-address-section [hidden] { display: none !important; }
        .edit-addr-field {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .edit-addr-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #7a90a0;
        }
        .edit-addr-field input[type="text"],
        .edit-addr-field textarea {
            width: 100%;
            border: 1px solid #c8d2dc;
            border-radius: 0.75rem;
            padding: 0.75rem 0.9rem;
            font-size: 0.95rem;
            color: #1f2f3d;
            background: #fff;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
            box-sizing: border-box;
        }
        .edit-addr-field textarea {
            resize: vertical;
            min-height: 80px;
        }
        .edit-addr-field input[type="text"]:focus,
        .edit-addr-field textarea:focus {
            border-color: #284b63;
            box-shadow: 0 0 0 3px rgba(40, 75, 99, 0.12);
        }
        .edit-addr-field .shipping-combobox { position: relative; }
        .edit-addr-field .shipping-combobox.is-open { z-index: 60; }
        .edit-addr-field .shipping-combobox-trigger {
            width: 100%;
            border: 1px solid #c8d2dc;
            border-radius: 0.75rem;
            background: #fff;
            color: #1f2f3d;
            padding: 0.75rem 2.5rem 0.75rem 0.9rem;
            min-height: 46px;
            text-align: left;
            cursor: pointer;
            position: relative;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        .edit-modal-body {
            padding: 1.25rem 1.5rem;
            display: grid;
            gap: 1.25rem;
            overflow: visible;
        }
        .edit-modal-body [hidden] { display: none !important; }

        .edit-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1rem 1.5rem 1.4rem;
            border-top: 1px solid #dde5ec;
        }
        .action-button--cancel {
            background: #edf1f5;
            color: #334c61;
        }
        .action-button--cancel:hover { background: #dce5ec; }

        .invoice-row-action-cell {
            width: 44px;
            text-align: center;
            padding: 0 4px;
        }

        @media (max-width: 640px) {
            .edit-modal-overlay { padding: 0; align-items: flex-end; }
            .edit-modal-shell { border-radius: 1.25rem 1.25rem 0 0; max-height: 92vh; overflow-y: auto; }
            .edit-modal-meta { grid-template-columns: 1fr; margin: 0.75rem 0.85rem 0; }
            .edit-modal-address-section { grid-template-columns: 1fr; margin: 0.75rem 0.85rem 0; }
            .edit-modal-body { padding: 1rem 0.85rem; }
            .edit-modal-footer { padding: 0.85rem; }
        }
    </style>
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
                            <div class="page-pretitle">Brill Invoicing</div>
                            <h1 class="page-title">Invoice Log</h1>
                        </div>
                    </div>
                </div>
            </div>

            <main class="page-body" id="invoice_log_table">
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

    <!-- Invoice Preview Modal -->
    <div class="modal modal-blur fade" id="modal-invoice-preview" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header d-block">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="modal-title mb-0">Invoice Preview</h5>
                        <button type="button" class="btn btn-sm btn-danger" data-bs-dismiss="modal">Close</button>
                    </div>
                    <div class="text-muted mt-1" id="modal-preview-subtitle" style="font-size:.85rem;"></div>
                </div>
                <div class="modal-body">
                    <iframe id="invoice-preview-frame" src="about:blank" title="Invoice Preview"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="btn-modal-download">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                            <path d="M7 11l5 5l5 -5" />
                            <path d="M12 4l0 12" />
                        </svg>
                        Download PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Off-screen iframe untuk download (butuh dimensi nyata agar html2canvas bisa render) -->
    <iframe id="invoice-download-frame" src="about:blank" tabindex="-1" aria-hidden="true"
            style="position:fixed;left:-9999px;top:-9999px;width:1240px;height:1754px;visibility:hidden;border:none;pointer-events:none;"></iframe>

    <!-- Edit Invoice Modal -->
    <div id="edit-invoice-modal" class="edit-modal-overlay" hidden>
        <div class="edit-modal-shell">
            <div class="edit-modal-header">
                <div>
                    <h2>Edit Invoice</h2>
                    <span id="edit-modal-invoice-number" class="edit-modal-subtitle"></span>
                </div>
                <button type="button" id="edit-modal-close" class="edit-modal-close" aria-label="Close">&#x2715;</button>
            </div>

            <!-- Invoice Meta -->
            <div class="edit-modal-meta">
                <div class="invoice-field">
                    <label>Invoice Number</label>
                    <input type="text" id="edit-meta-number" readonly tabindex="-1">
                </div>
                <div class="invoice-field">
                    <label>Invoice Date</label>
                    <input type="text" id="edit-meta-date" readonly tabindex="-1">
                </div>
                <div class="invoice-field">
                    <label for="edit-due-date">Invoice Due Date</label>
                    <input type="date" id="edit-due-date" autocomplete="off">
                </div>
            </div>

            <div class="edit-modal-address-section">
                <!-- Bill To -->
                <div class="edit-addr-field">
                    <label class="edit-addr-label">Bill To</label>
                    <!-- Dealer mode -->
                    <div id="edit-bill-to-dealer-wrap">
                        <div class="shipping-combobox" data-combobox>
                            <button type="button" class="shipping-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Select Dealer</span>
                                <span class="shipping-combobox-caret"></span>
                            </button>
                            <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                <input type="text" class="shipping-combobox-search" data-combobox-search autocomplete="off" placeholder="Search dealer name">
                                <div class="shipping-combobox-options" data-combobox-options></div>
                            </div>
                            <select id="edit-bill-to-select" hidden>
                                <option value="">Select Dealer</option>
                                <?php foreach ($dealerOptions as $dealer): ?>
                                    <?php
                                    $editBillToLines = array_filter([
                                        trim((string)($dealer['dealer_name']    ?? '')),
                                        trim((string)($dealer['dealer_contact'] ?? '')),
                                        trim((string)($dealer['dealer_address'] ?? '')),
                                    ], static fn($v) => $v !== '');
                                    ?>
                                    <option
                                        value="<?= htmlspecialchars((string)($dealer['dealer_code'] ?? ''), ENT_QUOTES) ?>"
                                        data-bill-to="<?= htmlspecialchars(implode("\n", $editBillToLines), ENT_QUOTES) ?>"
                                    >
                                        <?= htmlspecialchars((string)($dealer['dealer_name'] ?? ''), ENT_QUOTES) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Customer mode -->
                    <div id="edit-bill-to-customer-wrap" hidden>
                        <input type="text" id="edit-bill-to-customer" placeholder="Enter customer name or bill-to text">
                    </div>
                    <input type="hidden" id="edit-bill-to-value">
                </div>

                <!-- Ship To -->
                <div class="edit-addr-field">
                    <label class="edit-addr-label">Ship To</label>
                    <textarea id="edit-ship-to" placeholder="Enter recipient address" rows="3"></textarea>
                </div>
            </div>

            <div class="edit-modal-body">
                <div class="invoice-items-panel" style="margin:0;">
                    <div class="invoice-items-head">
                        <div>
                            <h2>Invoice Items</h2>
                            <p>Edit atau tambah item sesuai kebutuhan.</p>
                        </div>
                        <button type="button" class="action-button action-button--primary" id="edit-add-item-row">Add Row</button>
                    </div>
                    <div class="invoice-items-table-wrap">
                        <table class="invoice-items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Rate</th>
                                    <th>Amount</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="edit-item-rows"></tbody>
                        </table>
                    </div>
                </div>

                <div class="invoice-live-summary" style="margin:0;">
                    <div class="invoice-live-summary__grid">
                        <div class="invoice-field invoice-field--compact">
                            <label>Discount</label>
                            <div class="discount-type-tabs">
                                <button type="button" class="discount-tab edit-discount-tab is-active" data-type="flat">Flat</button>
                                <button type="button" class="discount-tab edit-discount-tab" data-type="percent">Persen</button>
                            </div>
                            <div id="edit-discount-flat-wrap">
                                <input type="text" id="edit-discount-flat" class="currency-input" inputmode="numeric" placeholder="IDR 0.00" autocomplete="off">
                            </div>
                            <div id="edit-discount-percent-wrap" hidden>
                                <div class="discount-percent-inputs">
                                    <input type="number" id="edit-discount-percent" class="discount-pct-input" min="0" max="100" step="0.01" placeholder="%" autocomplete="off">
                                    <input type="text" id="edit-discount-max" class="currency-input" inputmode="numeric" placeholder="Maks IDR (opsional)" autocomplete="off">
                                </div>
                                <span class="discount-percent-preview" id="edit-discount-percent-preview"></span>
                            </div>
                        </div>
                        <div class="invoice-field invoice-field--compact">
                            <label for="edit-shipping-cost">Shipping Cost</label>
                            <input type="text" id="edit-shipping-cost" class="currency-input" inputmode="numeric" placeholder="IDR 0.00" autocomplete="off">
                        </div>
                        <div class="invoice-field invoice-field--compact">
                            <label for="edit-additional-fee">Additional Fee <span style="font-weight:400;color:#7a90a0;font-size:0.85em;">(opsional)</span></label>
                            <input type="text" id="edit-additional-fee" class="currency-input" inputmode="numeric" placeholder="IDR 0.00" autocomplete="off">
                        </div>
                        <div class="summary-line">
                            <span>Subtotal</span>
                            <strong id="edit-subtotal-preview">IDR 0.00</strong>
                        </div>
                        <div class="summary-group-break" aria-hidden="true"></div>
                        <div class="summary-line summary-line--grand">
                            <span>Grand Total</span>
                            <strong id="edit-total-preview">IDR 0.00</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="edit-modal-footer">
                <button type="button" id="edit-modal-cancel" class="action-button action-button--cancel">Cancel</button>
                <button type="button" id="edit-modal-save" class="action-button action-button--submit">Save Changes</button>
            </div>
        </div>
    </div>

    <template id="edit-item-row-template">
        <tr class="invoice-item-row">
            <td>
                <div class="shipping-combobox item-combobox" data-combobox>
                    <button type="button" class="shipping-combobox-trigger" data-combobox-trigger>
                        <span data-combobox-label>Select Item</span>
                        <span class="shipping-combobox-caret"></span>
                    </button>
                    <div class="shipping-combobox-menu" data-combobox-menu hidden>
                        <input type="text" class="shipping-combobox-search" data-combobox-search autocomplete="off" placeholder="Search Brand + Type + Year">
                        <div class="shipping-combobox-options" data-combobox-options></div>
                    </div>
                    <select class="edit-item-select" hidden>
                        <option value="">Select Item</option>
                    </select>
                </div>
            </td>
            <td>
                <input type="number" class="edit-qty" min="0" step="1" autocomplete="off">
            </td>
            <td>
                <input type="text" class="edit-rate currency-input" inputmode="numeric" autocomplete="off">
            </td>
            <td>
                <input type="text" class="amount-output" value="IDR 0.00" readonly>
            </td>
            <td class="invoice-row-action-cell">
                <button type="button" class="log-btn log-btn--delete remove-row-button" aria-label="Remove row" title="Remove row">
                    <svg width="18px" height="18px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 6h18v2H3V6zm2 3h14l-1.5 12h-11L5 9zm5 2v8h2v-8h-2zm4 0v8h2v-8h-2zM9 4h6v2H9V4z" fill="#fff"/>
                    </svg>
                </button>
            </td>
        </tr>
    </template>

    <script src="/assets/dist/js/tabler.js"></script>
    <script src="/assets/js/dashboard.js"></script>

    <script>
    (function () {
        var sortSelect = document.getElementById('invoice-log-sort');
        if (!sortSelect) return;

        var sortComboboxStates = [];

        function renderSortOptions(state) {
            var keyword = (state.search.value || '').toLowerCase();
            var options = Array.from(state.field.options).filter(function (option) { return option.value !== ''; });
            var filtered = keyword
                ? options.filter(function (option) { return option.text.toLowerCase().includes(keyword); })
                : options;

            state.options.innerHTML = '';

            if (!filtered.length) {
                var empty = document.createElement('div');
                empty.className = 'shipping-combobox-empty';
                empty.textContent = 'No results';
                state.options.appendChild(empty);
                return;
            }

            filtered.forEach(function (option) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'shipping-combobox-option' + (state.field.value === option.value ? ' is-active' : '');
                button.textContent = option.text;
                button.addEventListener('click', function () {
                    state.field.value = option.value;
                    syncSortCombobox(state);
                    closeSortCombobox(state);
                    state.field.form && state.field.form.submit();
                });
                state.options.appendChild(button);
            });
        }

        function syncSortCombobox(state) {
            var selected = state.field.options[state.field.selectedIndex];
            state.label.textContent = selected ? selected.text : state.placeholder;
        }

        function openSortCombobox(state) {
            state.menu.hidden = false;
            state.combobox.classList.add('is-open');
            state.search.value = '';
            renderSortOptions(state);
            state.search.focus();
        }

        function closeSortCombobox(state) {
            state.menu.hidden = true;
            state.combobox.classList.remove('is-open');
            state.search.value = '';
        }

        function registerSortCombobox(field, placeholder) {
            var combobox = field.closest('[data-combobox]');
            if (!combobox) return;

            var state = {
                field: field,
                placeholder: placeholder,
                combobox: combobox,
                trigger: combobox.querySelector('[data-combobox-trigger]'),
                menu: combobox.querySelector('[data-combobox-menu]'),
                search: combobox.querySelector('[data-combobox-search]'),
                options: combobox.querySelector('[data-combobox-options]'),
                label: combobox.querySelector('[data-combobox-label]')
            };

            if (!state.trigger || !state.menu || !state.search || !state.options || !state.label) return;

            state.trigger.addEventListener('click', function () {
                var isOpen = !state.menu.hidden;
                sortComboboxStates.forEach(closeSortCombobox);
                if (!isOpen) openSortCombobox(state);
            });

            state.search.addEventListener('input', function () { renderSortOptions(state); });
            sortComboboxStates.push(state);
            syncSortCombobox(state);
        }

        registerSortCombobox(sortSelect, 'Newest First');

        document.addEventListener('click', function (event) {
            sortComboboxStates.forEach(function (state) {
                if (!state.combobox.contains(event.target)) {
                    closeSortCombobox(state);
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                sortComboboxStates.forEach(closeSortCombobox);
            }
        });
    })();
    </script>

    <script>
    (function () {
        const previewModalEl  = document.getElementById('modal-invoice-preview');
        const previewFrame    = document.getElementById('invoice-preview-frame');
        const downloadFrame   = document.getElementById('invoice-download-frame');
        const btnModalDl      = document.getElementById('btn-modal-download');
        const modalSubtitle   = document.getElementById('modal-preview-subtitle');

        const previewModal = new tabler.Modal(previewModalEl);

        let currentLogId     = null;
        let currentLogNumber = null;

        function openPreviewModal(logId, logNumber) {
            currentLogId     = logId;
            currentLogNumber = logNumber || '';
            if (modalSubtitle) {
                modalSubtitle.textContent = currentLogNumber ? '#' + currentLogNumber : '';
            }
            previewFrame.src = '/invoice-log/preview?log_id=' + logId + '&nobar=1';
            previewModal.show();
        }

        previewFrame.addEventListener('load', function () {
            try {
                var doc = previewFrame.contentDocument || previewFrame.contentWindow.document;
                if (doc && doc.documentElement) {
                    previewFrame.style.height = doc.documentElement.scrollHeight + 'px';
                }
            } catch (e) {}
        });

        previewModalEl.addEventListener('shown.bs.modal', function () {
            var backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;z-index:10400;';
            }
        });

        previewModalEl.addEventListener('hidden.bs.modal', function () {
            previewFrame.src = 'about:blank';
            previewFrame.style.height = '0';
            currentLogId     = null;
            currentLogNumber = null;
            if (modalSubtitle) modalSubtitle.textContent = '';
        });

        document.querySelectorAll('.log-btn--preview').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openPreviewModal(btn.dataset.logId, btn.dataset.logNumber);
            });
        });

        document.querySelectorAll('.log-btn--download').forEach(function (btn) {
            btn.addEventListener('click', function () {
                downloadFrame.src = '/invoice-log/preview?log_id=' + btn.dataset.logId + '&autodownload=1';
            });
        });

        btnModalDl?.addEventListener('click', function () {
            if (currentLogId) {
                downloadFrame.src = '/invoice-log/preview?log_id=' + currentLogId + '&autodownload=1';
            }
        });
    })();

    document.querySelectorAll('.log-btn--delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var logId     = btn.dataset.logId;
            var logNumber = btn.dataset.logNumber;

            Swal.fire({
                icon: 'warning',
                title: 'Hapus Invoice?',
                html: 'Invoice <strong>' + logNumber + '</strong> akan dihapus permanen dan tidak bisa dikembalikan.',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
            }).then(function (result) {
                if (!result.isConfirmed) return;

                var fd = new FormData();
                fd.append('id', logId);

                fetch('/invoice-log/delete', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                })
                .then(function (res) { return res.text(); })
                .then(function (text) {
                    if (text === 'OK') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus!',
                            text: 'Invoice ' + logNumber + ' berhasil dihapus.',
                            timer: 1500,
                            showConfirmButton: false,
                        }).then(function () { location.reload(); });
                    } else {
                        Swal.fire('Gagal', text || 'Terjadi kesalahan.', 'error');
                    }
                })
                .catch(function (err) {
                    Swal.fire('Error', String(err), 'error');
                });
            });
        });
    });
    </script>

    <script src="/assets/js/idle-timeout.js" defer></script>

    <script>
    (function () {
        // ── Utilities ──────────────────────────────────────────────────────
        function parseCurrency(v) {
            var raw = String(v || '').trim().replace(/IDR\s*/i, '').replace(/\s+/g, '');
            if (!raw) return 0;
            if (raw.includes(',') && raw.includes('.')) return Number(raw.replace(/,/g, '')) || 0;
            if (raw.includes(',')) return Number(raw.replace(/,/g, '')) || 0;
            if (raw.includes('.')) {
                var parts = raw.split('.');
                if (parts.length > 2) return Number(parts.join('')) || 0;
                return parts[1].length <= 2 ? (Number(raw) || 0) : (Number(parts.join('')) || 0);
            }
            return Number(raw.replace(/[^\d]/g, '')) || 0;
        }

        function formatCurrency(v) {
            return 'IDR ' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatCurrencyInput(input) {
            if (!input) return;
            var n = parseCurrency(input.value);
            input.value = n > 0 ? formatCurrency(n) : '';
        }

        // ── Combobox ────────────────────────────────────────────────────────
        var editComboboxStates = [];

        function updateFloatingPos(state) {
            if (!state || state.menu.hidden) return;
            var r = state.trigger.getBoundingClientRect();
            state.menu.style.cssText = 'position:fixed;top:' + Math.round(r.bottom + 6) + 'px;left:' + Math.round(r.left) + 'px;width:' + Math.round(r.width) + 'px;z-index:10000;';
        }

        function renderOptions(state) {
            var kw = (state.search.value || '').toLowerCase();
            var opts = Array.from(state.field.options).filter(function (o) { return o.value !== ''; });
            var filtered = kw ? opts.filter(function (o) { return o.text.toLowerCase().includes(kw); }) : opts;
            state.optionsEl.innerHTML = '';
            if (!filtered.length) {
                var el = document.createElement('div');
                el.className = 'shipping-combobox-empty';
                el.textContent = 'No results';
                state.optionsEl.appendChild(el);
                return;
            }
            filtered.forEach(function (opt) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'shipping-combobox-option' + (state.field.value === opt.value ? ' is-active' : '');
                btn.textContent = opt.text;
                btn.addEventListener('click', function () {
                    state.field.value = opt.value;
                    syncLabel(state);
                    closeCombobox(state);
                    state.field.dispatchEvent(new Event('change', { bubbles: true }));
                });
                state.optionsEl.appendChild(btn);
            });
        }

        function openCombobox(state) {
            if (state.field.disabled) return;
            if (state.menu.parentNode !== document.body) {
                state._origParent = state.menu.parentNode;
                state._origNext   = state.menu.nextSibling;
                document.body.appendChild(state.menu);
            }
            state.menu.hidden = false;
            state.combobox.classList.add('is-open');
            state.search.value = '';
            updateFloatingPos(state);
            renderOptions(state);
            state.search.focus();
        }

        function closeCombobox(state) {
            state.menu.hidden = true;
            state.combobox.classList.remove('is-open');
            state.search.value = '';
            state.menu.style.cssText = '';
            if (state._origParent && state.menu.parentNode === document.body) {
                state._origParent.insertBefore(state.menu, state._origNext || null);
            }
        }

        function syncLabel(state) {
            if (!state || !state.field) return;
            var opt = Array.from(state.field.options).find(function (o) { return o.value === state.field.value; });
            state.label.textContent = opt ? opt.text : state.placeholder;
        }

        function registerCombobox(field, placeholder, onChangeCallback) {
            var combobox = field && field.closest('[data-combobox]');
            if (!field || !combobox) return;
            if (editComboboxStates.some(function (s) { return s.field === field; })) return;

            var trigger   = combobox.querySelector('[data-combobox-trigger]');
            var menu      = combobox.querySelector('[data-combobox-menu]');
            var search    = combobox.querySelector('[data-combobox-search]');
            var optionsEl = combobox.querySelector('[data-combobox-options]');
            var label     = combobox.querySelector('[data-combobox-label]');
            if (!trigger || !menu || !search || !optionsEl || !label) return;

            var state = { field: field, combobox: combobox, trigger: trigger, menu: menu, search: search, optionsEl: optionsEl, label: label, placeholder: placeholder, _origParent: null, _origNext: null };
            editComboboxStates.push(state);

            trigger.addEventListener('click', function () {
                var isOpen = !menu.hidden;
                editComboboxStates.forEach(closeCombobox);
                if (!isOpen) openCombobox(state);
            });
            search.addEventListener('input', function () { renderOptions(state); });
            if (onChangeCallback) {
                field.addEventListener('change', onChangeCallback);
            }
            syncLabel(state);
        }

        // ── Bill To dealer select ─────────────────────────────────────────
        var editBillToSelect         = document.getElementById('edit-bill-to-select');
        var editBillToCustomerInput  = document.getElementById('edit-bill-to-customer');
        var editBillToValue          = document.getElementById('edit-bill-to-value');
        var editBillToDealerWrap     = document.getElementById('edit-bill-to-dealer-wrap');
        var editBillToCustomerWrap   = document.getElementById('edit-bill-to-customer-wrap');
        var editShipToTextarea       = document.getElementById('edit-ship-to');

        function syncBillToHidden() {
            if (!editBillToValue) return;
            var isDealerMode = editBillToDealerWrap && !editBillToDealerWrap.hidden;
            if (isDealerMode) {
                var selectedOpt = editBillToSelect
                    ? Array.from(editBillToSelect.options).find(function (o) { return o.value === editBillToSelect.value; })
                    : null;
                editBillToValue.value = (selectedOpt && selectedOpt.dataset.billTo) ? selectedOpt.dataset.billTo : '';
            } else {
                editBillToValue.value = editBillToCustomerInput ? editBillToCustomerInput.value.trim() : '';
            }
        }

        // Register dealer bill-to combobox once (persistent across open/close)
        registerCombobox(editBillToSelect, 'Select Dealer', syncBillToHidden);
        if (editBillToCustomerInput) {
            editBillToCustomerInput.addEventListener('input', syncBillToHidden);
        }

        // ── Item options ──────────────────────────────────────────────────
        var invoiceItemOptions = [];

        function setItemOptions(field) {
            if (!field) return;
            var currentVal = field.value;
            field.innerHTML = '';
            var first = document.createElement('option');
            first.value = '';
            first.textContent = 'Select Item';
            field.appendChild(first);
            invoiceItemOptions.forEach(function (row) {
                var opt = document.createElement('option');
                opt.value = String(row.label || '');
                opt.textContent = String(row.label || '');
                field.appendChild(opt);
            });
            if (currentVal) field.value = currentVal;
        }

        function ensureItemOption(field, value) {
            if (!field || !value) return;
            var exists = Array.from(field.options).some(function (o) { return o.value === value; });
            if (!exists) {
                var opt = document.createElement('option');
                opt.value = value;
                opt.textContent = value;
                field.appendChild(opt);
            }
            field.value = value;
        }

        // ── Summary ──────────────────────────────────────────────────────
        var editRowsContainer        = document.getElementById('edit-item-rows');
        var editDiscountFlatInput    = document.getElementById('edit-discount-flat');
        var editDiscountPercentInput = document.getElementById('edit-discount-percent');
        var editDiscountMaxInput     = document.getElementById('edit-discount-max');
        var editDiscountFlatWrap     = document.getElementById('edit-discount-flat-wrap');
        var editDiscountPercentWrap  = document.getElementById('edit-discount-percent-wrap');
        var editDiscountPercentPreview = document.getElementById('edit-discount-percent-preview');
        var editDiscountTypeValue    = 'flat';
        var editShippingInput        = document.getElementById('edit-shipping-cost');
        var editAdditionalFeeInput   = document.getElementById('edit-additional-fee');
        var editMetaNumberInput      = document.getElementById('edit-meta-number');
        var editMetaDateInput        = document.getElementById('edit-meta-date');
        var editDueDateInput         = document.getElementById('edit-due-date');
        var editSubtotalPreview      = document.getElementById('edit-subtotal-preview');
        var editTotalPreview         = document.getElementById('edit-total-preview');

        function getEditDiscountAmount(subtotal) {
            if (editDiscountTypeValue === 'percent') {
                var pct    = parseFloat(editDiscountPercentInput ? editDiscountPercentInput.value : '0') || 0;
                var max    = parseCurrency(editDiscountMaxInput ? editDiscountMaxInput.value : '');
                var amount = subtotal * (pct / 100);
                if (max > 0) amount = Math.min(amount, max);
                return amount;
            }
            return parseCurrency(editDiscountFlatInput ? editDiscountFlatInput.value : '');
        }

        function updateEditSummary() {
            var subtotal = 0;
            if (editRowsContainer) {
                editRowsContainer.querySelectorAll('.invoice-item-row').forEach(function (row) {
                    var qty  = Number(row.querySelector('.edit-qty')  ? row.querySelector('.edit-qty').value  : 0);
                    var rate = parseCurrency(row.querySelector('.edit-rate') ? row.querySelector('.edit-rate').value : '');
                    var amt  = qty * rate;
                    subtotal += amt;
                    var amtOut = row.querySelector('.amount-output');
                    if (amtOut) amtOut.value = formatCurrency(amt);
                });
            }
            var discount = getEditDiscountAmount(subtotal);
            if (editDiscountTypeValue === 'percent' && editDiscountPercentPreview) {
                editDiscountPercentPreview.textContent = discount > 0 ? '= ' + formatCurrency(discount) : '';
            }
            var shipping       = parseCurrency(editShippingInput      ? editShippingInput.value      : '');
            var additionalFee  = parseCurrency(editAdditionalFeeInput ? editAdditionalFeeInput.value : '');
            var total          = subtotal - discount + shipping + additionalFee;
            if (editSubtotalPreview) editSubtotalPreview.textContent = formatCurrency(subtotal);
            if (editTotalPreview)    editTotalPreview.textContent    = formatCurrency(total);
        }

        // ── Row management ───────────────────────────────────────────────
        var editRowTemplate = document.getElementById('edit-item-row-template');

        function bindEditRow(row) {
            var itemSelect = row.querySelector('.edit-item-select');
            var qtyInput   = row.querySelector('.edit-qty');
            var rateInput  = row.querySelector('.edit-rate');
            var removeBtn  = row.querySelector('.remove-row-button');

            if (itemSelect) {
                setItemOptions(itemSelect);
                registerCombobox(itemSelect, 'Select Item', null);
            }

            if (qtyInput) {
                qtyInput.addEventListener('input', updateEditSummary);
                qtyInput.addEventListener('blur', updateEditSummary);
            }
            if (rateInput) {
                rateInput.addEventListener('input', updateEditSummary);
                rateInput.addEventListener('blur', function () {
                    formatCurrencyInput(rateInput);
                    updateEditSummary();
                });
            }

            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    if (editRowsContainer.children.length <= 1) {
                        Swal.fire({ icon: 'info', title: 'At least one row is required.' });
                        return;
                    }
                    Swal.fire({
                        icon: 'warning',
                        title: 'Hapus item ini?',
                        text: 'Item akan dihapus dari invoice.',
                        showCancelButton: true,
                        confirmButtonColor: '#c0392b',
                        cancelButtonColor: '#6c7a89',
                        confirmButtonText: 'Hapus',
                        cancelButtonText: 'Batal',
                    }).then(function (result) {
                        if (!result.isConfirmed) return;
                        var field    = row.querySelector('.edit-item-select');
                        var stateIdx = editComboboxStates.findIndex(function (s) { return s.field === field; });
                        if (stateIdx !== -1) {
                            closeCombobox(editComboboxStates[stateIdx]);
                            editComboboxStates.splice(stateIdx, 1);
                        }
                        row.remove();
                        updateEditSummary();
                    });
                });
            }
        }

        function addEditRow(itemData) {
            if (!editRowTemplate) return null;
            var fragment = editRowTemplate.content.cloneNode(true);
            var row = fragment.querySelector('.invoice-item-row');
            if (!row) return null;
            editRowsContainer.appendChild(row);
            var addedRow = editRowsContainer.lastElementChild;
            bindEditRow(addedRow);

            if (itemData) {
                var itemSelect = addedRow.querySelector('.edit-item-select');
                var qtyInput   = addedRow.querySelector('.edit-qty');
                var rateInput  = addedRow.querySelector('.edit-rate');

                if (itemSelect) {
                    ensureItemOption(itemSelect, itemData.item);
                    var cbState = editComboboxStates.find(function (s) { return s.field === itemSelect; });
                    if (cbState) syncLabel(cbState);
                }
                if (qtyInput  && itemData.quantity != null) qtyInput.value  = itemData.quantity;
                if (rateInput && itemData.rate     != null) rateInput.value = formatCurrency(itemData.rate);
            }
            return addedRow;
        }

        // ── Modal open / close ───────────────────────────────────────────
        var modal         = document.getElementById('edit-invoice-modal');
        var editCloseBtn  = document.getElementById('edit-modal-close');
        var editCancelBtn = document.getElementById('edit-modal-cancel');
        var editSaveBtn   = document.getElementById('edit-modal-save');
        var editAddRowBtn = document.getElementById('edit-add-item-row');
        var currentEditId = null;

        function closeEditModal() {
            // Close all item-row comboboxes; keep the dealer bill-to combobox registered
            var toRemove = editComboboxStates.filter(function (s) { return s.field !== editBillToSelect; });
            toRemove.forEach(closeCombobox);
            editComboboxStates = editComboboxStates.filter(function (s) { return s.field === editBillToSelect; });

            if (modal) modal.hidden = true;
            document.body.style.overflow = '';
            if (editRowsContainer)        editRowsContainer.innerHTML = '';
            if (editDiscountFlatInput)    editDiscountFlatInput.value    = '';
            if (editDiscountPercentInput) editDiscountPercentInput.value = '';
            if (editDiscountMaxInput)     editDiscountMaxInput.value     = '';
            if (editDiscountPercentPreview) editDiscountPercentPreview.textContent = '';
            editDiscountTypeValue = 'flat';
            document.querySelectorAll('.edit-discount-tab').forEach(function (t) {
                t.classList.toggle('is-active', t.dataset.type === 'flat');
            });
            if (editDiscountFlatWrap)    editDiscountFlatWrap.hidden    = false;
            if (editDiscountPercentWrap) editDiscountPercentWrap.hidden = true;
            if (editShippingInput)  editShippingInput.value = '';
            if (editBillToSelect)   editBillToSelect.value  = '';
            if (editBillToCustomerInput) editBillToCustomerInput.value = '';
            if (editBillToValue)    editBillToValue.value = '';
            if (editShipToTextarea) editShipToTextarea.value = '';

            var billToState = editComboboxStates.find(function (s) { return s.field === editBillToSelect; });
            if (billToState) syncLabel(billToState);

            currentEditId = null;
        }

        function showBillToMode(type, dealerCode) {
            var isDealer = type === 'dealer';
            if (editBillToDealerWrap)   editBillToDealerWrap.hidden   = !isDealer;
            if (editBillToCustomerWrap) editBillToCustomerWrap.hidden  = isDealer;

            if (isDealer && dealerCode && editBillToSelect) {
                editBillToSelect.value = dealerCode;
                var billToState = editComboboxStates.find(function (s) { return s.field === editBillToSelect; });
                if (billToState) syncLabel(billToState);
            }
            syncBillToHidden();
        }

        function openEditModal(logId) {
            currentEditId = logId;

            var btn    = document.querySelector('.log-btn--edit[data-log-id="' + logId + '"]');
            var number = btn ? btn.dataset.logNumber : '';

            var numEl = document.getElementById('edit-modal-invoice-number');
            if (numEl) numEl.textContent = '#' + number;
            if (editMetaNumberInput) editMetaNumberInput.value = '#' + number;
            if (editMetaDateInput)   editMetaDateInput.value   = '';
            if (editDueDateInput)    editDueDateInput.value    = '';

            if (editRowsContainer)        editRowsContainer.innerHTML = '';
            if (editDiscountFlatInput)    editDiscountFlatInput.value    = '';
            if (editDiscountPercentInput) editDiscountPercentInput.value = '';
            if (editDiscountMaxInput)     editDiscountMaxInput.value     = '';
            if (editDiscountPercentPreview) editDiscountPercentPreview.textContent = '';
            if (editShippingInput)  editShippingInput.value = '';
            if (editShipToTextarea) editShipToTextarea.value = '';

            if (modal) modal.hidden = false;
            document.body.style.overflow = 'hidden';

            fetch('/api/invoice-detail?id=' + logId)
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (!json.status) throw new Error(json.message || 'Failed to load invoice.');

                    var data = json.data;

                    // Bill To & Ship To
                    showBillToMode(data.type, data.dealer_code);
                    if (data.type === 'customer' && editBillToCustomerInput) {
                        editBillToCustomerInput.value = data.bill_to || '';
                        syncBillToHidden();
                    }
                    if (editShipToTextarea) editShipToTextarea.value = data.ship_to || '';

                    // Discount type / value / max
                    var dType  = data.discount_type  || 'flat';
                    var dValue = data.discount_value || 0;
                    var dMax   = data.discount_max   || 0;

                    editDiscountTypeValue = dType;
                    document.querySelectorAll('.edit-discount-tab').forEach(function (t) {
                        t.classList.toggle('is-active', t.dataset.type === dType);
                    });
                    if (editDiscountFlatWrap)    editDiscountFlatWrap.hidden    = dType !== 'flat';
                    if (editDiscountPercentWrap) editDiscountPercentWrap.hidden = dType !== 'percent';

                    if (dType === 'percent') {
                        if (editDiscountPercentInput) editDiscountPercentInput.value = dValue > 0 ? dValue : '';
                        if (editDiscountMaxInput && dMax > 0) editDiscountMaxInput.value = formatCurrency(dMax);
                    } else {
                        if (editDiscountFlatInput && dValue > 0) editDiscountFlatInput.value = formatCurrency(dValue);
                    }

                    // Shipping
                    if (editShippingInput && data.shipping > 0) {
                        editShippingInput.value = formatCurrency(data.shipping);
                    }
                    // Additional Fee
                    if (editAdditionalFeeInput) {
                        editAdditionalFeeInput.value = data.additional_fee > 0 ? formatCurrency(data.additional_fee) : '';
                    }
                    // Meta: Invoice Date + Due Date
                    if (editMetaDateInput) editMetaDateInput.value = data.create_date || '';
                    if (editDueDateInput)  editDueDateInput.value  = data.due_date    || '';

                    // Items
                    var items = Array.isArray(data.items) ? data.items : [];
                    items.forEach(function (item) { addEditRow(item); });
                    if (items.length === 0) addEditRow(null);

                    updateEditSummary();
                })
                .catch(function (err) {
                    closeEditModal();
                    Swal.fire('Error', String(err), 'error');
                });
        }

        // ── Event listeners ──────────────────────────────────────────────
        document.querySelectorAll('.log-btn--edit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openEditModal(parseInt(btn.dataset.logId, 10));
            });
        });

        if (editCloseBtn)  editCloseBtn.addEventListener('click',  closeEditModal);
        if (editCancelBtn) editCancelBtn.addEventListener('click', closeEditModal);

        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeEditModal();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.hidden) closeEditModal();
        });

        document.addEventListener('click', function (e) {
            editComboboxStates.forEach(function (state) {
                if (!state.combobox.contains(e.target) && !state.menu.contains(e.target)) {
                    closeCombobox(state);
                }
            });
        });

        window.addEventListener('scroll', function () {
            editComboboxStates.forEach(function (s) { if (!s.menu.hidden) updateFloatingPos(s); });
        }, true);

        window.addEventListener('resize', function () {
            editComboboxStates.forEach(function (s) { if (!s.menu.hidden) updateFloatingPos(s); });
        });

        if (editAddRowBtn) {
            editAddRowBtn.addEventListener('click', function () {
                addEditRow(null);
                updateEditSummary();
            });
        }

        document.querySelectorAll('.edit-discount-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var type = tab.dataset.type;
                editDiscountTypeValue = type;
                document.querySelectorAll('.edit-discount-tab').forEach(function (t) { t.classList.remove('is-active'); });
                tab.classList.add('is-active');
                if (editDiscountFlatWrap)    editDiscountFlatWrap.hidden    = type !== 'flat';
                if (editDiscountPercentWrap) editDiscountPercentWrap.hidden = type !== 'percent';
                if (editDiscountPercentPreview) editDiscountPercentPreview.textContent = '';
                updateEditSummary();
            });
        });

        if (editDiscountFlatInput) {
            editDiscountFlatInput.addEventListener('input', updateEditSummary);
            editDiscountFlatInput.addEventListener('blur', function () {
                formatCurrencyInput(editDiscountFlatInput);
                updateEditSummary();
            });
        }
        if (editDiscountPercentInput) {
            editDiscountPercentInput.addEventListener('input', updateEditSummary);
        }
        if (editDiscountMaxInput) {
            editDiscountMaxInput.addEventListener('input', updateEditSummary);
            editDiscountMaxInput.addEventListener('blur', function () {
                formatCurrencyInput(editDiscountMaxInput);
                updateEditSummary();
            });
        }

        if (editShippingInput) {
            editShippingInput.addEventListener('input', updateEditSummary);
            editShippingInput.addEventListener('blur', function () {
                formatCurrencyInput(editShippingInput);
                updateEditSummary();
            });
        }
        if (editAdditionalFeeInput) {
            editAdditionalFeeInput.addEventListener('input', updateEditSummary);
            editAdditionalFeeInput.addEventListener('blur', function () {
                formatCurrencyInput(editAdditionalFeeInput);
                updateEditSummary();
            });
        }

        // ── Save ─────────────────────────────────────────────────────────
        if (editSaveBtn) {
            editSaveBtn.addEventListener('click', function () {
                if (!currentEditId) return;

                // Validate bill_to
                syncBillToHidden();
                var billToVal = editBillToValue ? editBillToValue.value.trim() : '';
                if (!billToVal) {
                    Swal.fire({ icon: 'warning', title: 'Bill To harus diisi.' });
                    return;
                }

                // Validate ship_to
                var shipToVal = editShipToTextarea ? editShipToTextarea.value.trim() : '';
                if (!shipToVal) {
                    Swal.fire({ icon: 'warning', title: 'Ship To harus diisi.' });
                    return;
                }

                // Collect items
                var rows = [];
                editRowsContainer.querySelectorAll('.invoice-item-row').forEach(function (row) {
                    var itemEl = row.querySelector('.edit-item-select');
                    var qtyEl  = row.querySelector('.edit-qty');
                    var rateEl = row.querySelector('.edit-rate');
                    var item   = itemEl ? itemEl.value.trim() : '';
                    var qty    = Number(qtyEl  ? qtyEl.value  : 0);
                    var rate   = parseCurrency(rateEl ? rateEl.value : '');
                    if (item && qty > 0 && rate >= 0) {
                        rows.push({ item: item, quantity: qty, rate: rate, amount: qty * rate });
                    }
                });

                if (rows.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Isi minimal satu item yang valid.' });
                    return;
                }

                var discountType  = editDiscountTypeValue;
                var discountValue = discountType === 'percent'
                    ? (parseFloat(editDiscountPercentInput ? editDiscountPercentInput.value : '0') || 0)
                    : parseCurrency(editDiscountFlatInput ? editDiscountFlatInput.value : '');
                var discountMax  = discountType === 'percent'
                    ? parseCurrency(editDiscountMaxInput ? editDiscountMaxInput.value : '')
                    : 0;
                var shippingCost   = parseCurrency(editShippingInput      ? editShippingInput.value      : '');
                var additionalFee  = parseCurrency(editAdditionalFeeInput ? editAdditionalFeeInput.value : '');

                editSaveBtn.disabled    = true;
                editSaveBtn.textContent = 'Saving...';

                var fd = new FormData();
                fd.append('id',              currentEditId);
                fd.append('bill_to',         billToVal);
                fd.append('ship_to',         shipToVal);
                fd.append('items_json',      JSON.stringify(rows));
                fd.append('discount_type',   discountType);
                fd.append('discount_value',  String(discountValue));
                fd.append('discount_max',    String(discountMax));
                fd.append('shipping_cost',   String(shippingCost));
                fd.append('additional_fee',  String(additionalFee));
                fd.append('due_date',        editDueDateInput ? editDueDateInput.value : '');

                fetch('/invoice-log/update', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        if (json.status) {
                            closeEditModal();
                            Swal.fire({
                                icon: 'success',
                                title: 'Invoice berhasil diperbarui!',
                                timer: 1500,
                                showConfirmButton: false,
                            }).then(function () { location.reload(); });
                        } else {
                            throw new Error(json.message || 'Update gagal.');
                        }
                    })
                    .catch(function (err) {
                        Swal.fire('Error', String(err), 'error');
                    })
                    .finally(function () {
                        editSaveBtn.disabled    = false;
                        editSaveBtn.textContent = 'Save Changes';
                    });
            });
        }

        // ── Init: fetch item options ──────────────────────────────────────
        fetch('/api/invoice-item-options', { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                invoiceItemOptions = Array.isArray(data.results) ? data.results : [];
            })
            .catch(function () {});

    })();
    </script>
</body>
</html>
