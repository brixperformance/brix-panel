<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';
require_once dirname(__DIR__) . '/application/configs/csrf.php';
require_once dirname(__DIR__) . '/application/configs/db_connections.php';
require_once dirname(__DIR__) . '/application/configs/article_media.php';
require_once dirname(__DIR__) . '/application/models/master-article/MasterArticleWriter.php';

bootstrap_page(null, false);

if (empty($_SESSION['logged_in'])) {
    header('Location: /login', true, 302);
    exit;
}

$writer = new MasterArticleWriter(get_database_config('lp'));
$articleErrors = [];
$articleSuccess = '';
$articleOld = [
    'msa_code' => '',
    'msa_title' => '',
    'msa_subtitle' => '',
    'msa_slug' => '',
    'msa_category' => '',
    'msa_date' => date('Y-m-d'),
    'msa_meta_description' => '',
    'head_header_alt' => '',
    'head_header_url' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify(trim((string) ($_POST['csrf_token'] ?? '')))) {
        $articleErrors[] = 'Invalid request. Coba submit ulang.';
    } else {
        $result = $writer->createFromPost($_POST, $_FILES);
        if (!empty($result['ok'])) {
            $_SESSION['master_article_success'] = (string) ($result['message'] ?? 'Article berhasil dibuat.');
            header('Location: /master-article/update?code=' . urlencode((string) ($result['code'] ?? '')), true, 302);
            exit;
        }

        $articleErrors[] = (string) ($result['message'] ?? 'Gagal membuat article.');
        $articleOld = array_merge($articleOld, is_array($result['old'] ?? null) ? $result['old'] : []);
    }
}

$articlePageTitle = 'Create Article';
$articlePageDescription = 'Bawa flow article dari BRIX Admin ke UI panel dengan koneksi BRIX LP.';
$articleFormAction = '/master-article/create';
$articleFormMode = 'create';
$articleSubmitLabel = 'Create Article';
$articleBackHref = '/master-article';
$articleBlocksHtml = '';
$articleStorage = get_article_media_config();

require dirname(__DIR__) . '/templates/master-article-editor.php';

