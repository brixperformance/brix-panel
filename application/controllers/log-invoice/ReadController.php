<?php

require_once __DIR__ . '/../../configs/pagination.php';
require_once __DIR__ . '/../../models/Execute.php';
require_once __DIR__ . '/../../models/log-invoice/View.php';

$config = require_once __DIR__ . '/../../configs/database.php';
$view   = new InvoiceLogView($config);

$search = trim((string) ($_GET['search'] ?? ''));
$requestedType = strtolower(trim((string) ($_GET['type'] ?? '')));
$dealerCode = trim((string) ($_GET['dealer_code'] ?? ''));
$scope = strtolower(trim((string) ($_GET['scope'] ?? '')));
$requestedSort = strtolower(trim((string) ($_GET['sort'] ?? 'newest')));
$type = in_array($requestedType, ['dealer', 'customer'], true) ? $requestedType : '';
$sort = in_array($requestedSort, ['newest', 'oldest', 'highest', 'lowest'], true) ? $requestedSort : 'newest';

if ($scope === 'all') {
    $type = '';
    $dealerCode = '';
}

$page   = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit  = 15;
$offset = ($page - 1) * $limit;

$dataResult  = $view->getLogData($search, $offset, $limit, $type, $dealerCode, $sort);
$results     = $dataResult['data'] ?? [];

$countResult = $view->countLogData($search, $type, $dealerCode);
$totalRows   = (int) ($countResult['data'] ?? 0);
$totalPages  = max(1, (int) ceil($totalRows / $limit));

$paginationLinks = build_pagination_links($page, $totalPages);
