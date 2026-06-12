<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configs/db_connections.php';
require_once __DIR__ . '/../../models/master-article/MasterArticleWriter.php';

$writer = new MasterArticleWriter(get_database_config('lp'));
$category = trim((string) ($_GET['category'] ?? ''));
$response = $writer->nextCodeResponse($category);

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

