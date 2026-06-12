<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';
require_once dirname(__DIR__) . '/application/configs/pagination.php';
require_once dirname(__DIR__) . '/application/configs/db_connections.php';
require_once dirname(__DIR__) . '/application/models/master-article/MasterArticleView.php';

bootstrap_page(null, false);

if (empty($_SESSION['logged_in'])) {
    header('Location: /login', true, 302);
    exit;
}

function master_article_esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function master_article_category_label(string $category): string
{
    return match ($category) {
        'street-series' => 'Street Series',
        'competition-series' => 'Competition Series',
        'event' => 'Event',
        default => $category !== '' ? ucwords(str_replace('-', ' ', $category)) : '-',
    };
}

function master_article_status_badge($value): string
{
    $active = in_array((string) $value, ['1', 'Y', 'T', 'true', 'TRUE'], true);

    return $active
        ? '<span class="badge bg-success-lt text-success">Active</span>'
        : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>';
}

$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$dbError = '';
$results = [];
$totalRows = 0;
$paginationLinks = [];

$view = new MasterArticleView(get_database_config('lp'));
$rowsResult = $view->getArticles($search, $offset, $limit);
$countResult = $view->countArticles($search);

if (empty($rowsResult['status'])) {
    $dbError = (string) ($rowsResult['message'] ?? 'Gagal memuat data article.');
} else {
    $results = is_array($rowsResult['data'] ?? null) ? $rowsResult['data'] : [];
}

if (empty($countResult['status'])) {
    $dbError = $dbError !== '' ? $dbError : (string) ($countResult['message'] ?? 'Gagal menghitung data article.');
} else {
    $totalRows = (int) ($countResult['data'] ?? 0);
}

$paginationLinks = build_pagination_links($page, max(1, (int) ceil($totalRows / $limit)));

$tableColumns = [
    ['label' => 'Code'],
    ['label' => 'Article'],
    ['label' => 'Category'],
    ['label' => 'Date'],
    ['label' => 'Views'],
    ['label' => 'Status'],
    ['label' => 'Action', 'class' => 'w-1 text-end'],
];

$tableRows = [];
foreach ($results as $row) {
    $tableRows[] = [
        'cells' => [
            ['html' => '<code>' . master_article_esc($row['msa_code'] ?? '') . '</code>'],
            ['html' => '<div class="fw-medium">' . master_article_esc($row['msa_title'] ?? '') . '</div><div class="text-secondary small">' . master_article_esc($row['msa_slug'] ?? '') . '</div>'],
            ['html' => master_article_esc(master_article_category_label((string) ($row['msa_category'] ?? '')))],
            ['html' => master_article_esc((string) ($row['msa_date'] ?? '-'))],
            ['html' => master_article_esc((string) ($row['msa_views'] ?? '0'))],
            ['html' => master_article_status_badge($row['msa_flag'] ?? null)],
            ['html' => '<a class="btn btn-sm btn-outline-secondary" href="/master-article/update?code=' . urlencode((string) ($row['msa_code'] ?? '')) . '">Edit</a>', 'class' => 'text-end w-1'],
        ],
    ];
}

ob_start();
?>
<form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center">
    <div class="input-group input-group-flat" style="min-width: 360px;">
        <span class="input-group-text">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                <path d="M21 21l-6 -6" />
            </svg>
        </span>
        <input type="search" class="form-control" name="search" placeholder="Search code, title, or slug..." value="<?= master_article_esc($search) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search !== ''): ?>
        <a href="/master-article" class="btn btn-outline-secondary">Reset</a>
    <?php endif; ?>
</form>
<?php
$tableFiltersHtml = (string) ob_get_clean();

ob_start();
?>
<a href="/master-article/create" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 5l0 14" />
        <path d="M5 12l14 0" />
    </svg>
    Add Article
</a>
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
                        <a class="page-link" href="<?= master_article_esc($pageHref) ?>"><?= master_article_esc($link['label'] ?? '') ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
<?php
$tableFooterHtml = (string) ob_get_clean();

$tableId = 'master-article-table';
$tableTitle = 'Master Article';
$tableDescription = 'Kelola article landing page dari database BRIX LP.';
$tableEmptyMessage = 'No articles found.';
$tableMobile = true;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>Master Article - BRIX</title>
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
                            <h1 class="page-title">Master Article</h1>
                        </div>
                    </div>
                </div>
            </div>
            <main class="page-body">
                <div class="container-xl">
                    <?php if ($dbError !== ''): ?>
                        <div class="alert alert-danger"><?= master_article_esc($dbError) ?></div>
                    <?php endif; ?>

                    <div class="row row-cards">
                        <div class="col-12">
                            <?php include __DIR__ . '/../templates/data-hub-table.php'; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="/assets/dist/js/tabler.js"></script>
    <script src="/assets/js/idle-timeout.js" defer></script>
</body>
</html>

