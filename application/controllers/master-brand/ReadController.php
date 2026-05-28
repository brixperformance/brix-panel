<?php

require_once __DIR__ . '/../../configs/pagination.php';
require_once __DIR__ . '/../../models/Execute.php';
require_once __DIR__ . '/../../models/master-brand/View.php';

$config = require_once __DIR__ . '/../../configs/database.php';
$view   = new MasterBrandView($config);

$search = $_GET['search'] ?? '';
$page   = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

$dataResult  = $view->getBrandData($search, $offset, $limit);
$countResult = $view->countBrandData($search);

$results = $dataResult['data'] ?? [];
$totalRows = (int) ($countResult['data'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $limit));
$paginationLinks = build_pagination_links($page, $totalPages);
