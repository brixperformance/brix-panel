<?php

require_once __DIR__ . '/../../configs/pagination.php';
require_once __DIR__ . '/../../models/Execute.php';
require_once __DIR__ . '/../../models/master-province/View.php';

$config = require_once __DIR__ . '/../../configs/database.php';
$view   = new MasterProvinceView($config);

$search = trim((string) ($_GET['search'] ?? ''));
$page   = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit  = 15;
$offset = ($page - 1) * $limit;

$dataResult  = $view->getProvinceData($search, $offset, $limit);
$results     = $dataResult['data'] ?? [];

$countResult = $view->countProvinceData($search);
$totalRows   = (int) ($countResult['data'] ?? 0);
$totalPages  = max(1, (int) ceil($totalRows / $limit));

$islandResult = $view->getAllIslands();
$islands      = $islandResult['data'] ?? [];

$paginationLinks = build_pagination_links($page, $totalPages);
