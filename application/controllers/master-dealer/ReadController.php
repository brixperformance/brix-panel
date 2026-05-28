<?php

require_once __DIR__ . '/../../configs/json_response.php';
require_once __DIR__ . '/../../configs/pagination.php';
require_once __DIR__ . '/../../models/Execute.php';
require_once __DIR__ . '/../../models/master-dealer/View.php';

$config = require_once __DIR__ . '/../../configs/database.php';
$view = new MasterDealerView($config);

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if (isset($_GET['dealer_code'])) {
    $code = $_GET['dealer_code'];
    $row = $view->getDealerRowById($code);
    send_json_response($row['data'] ?? []);
}

if (isset($_GET['island_code'])) {
    $islandCode = $_GET['island_code'];
    $provinces = $view->getProvincesByIsland($islandCode);
    send_json_response($provinces);
}

$dataResult = $view->getDealerData($search, $offset, $limit);
$results = $dataResult['data'] ?? [];
$countResult = $view->countDealerData($search);
$islands = $view->getAllIslands();

$totalRows = (int) ($countResult['data'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $limit));
$paginationLinks = build_pagination_links($page, $totalPages);
