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

function master_article_update_esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function master_article_render_blocks_html(array $blocks, string $articleCode, int $ordinal): string
{
    $html = '';

    foreach ($blocks as $block) {
        $type = (string) ($block['msab_type'] ?? '');
        $order = (int) ($block['msab_order'] ?? 0);
        $blockId = (string) ($block['msab_id'] ?? ('tmp' . uniqid('', true)));

        $hidden = '<input type="hidden" name="blocks[' . master_article_update_esc($blockId) . '][type]" value="' . master_article_update_esc($type) . '">'
            . '<input type="hidden" name="blocks[' . master_article_update_esc($blockId) . '][order]" value="' . master_article_update_esc((string) $order) . '">';

        if ($type === 'text') {
            $content = (string) ($block['msab_content'] ?? '');
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $content = implode("\n\n", array_map(static fn ($item): string => trim((string) $item), $decoded));
            }

            $html .= '<section class="article-block">'
                . $hidden
                . '<div class="article-block-header"><h3 class="article-block-title">Paragraph Block</h3><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button></div>'
                . '<div class="article-block-body"><div><label class="form-label">Content</label><textarea class="form-control" name="blocks[' . master_article_update_esc($blockId) . '][content]" rows="7" placeholder="Use one blank line between paragraphs.">' . master_article_update_esc($content) . '</textarea></div></div>'
                . '</section>';
            continue;
        }

        if ($type === 'cta' || $type === 'cta-links') {
            $content = json_decode((string) ($block['msab_content'] ?? '{}'), true);
            $content = is_array($content) ? $content : [];
            $links = is_array($content['links'] ?? null) ? $content['links'] : [];

            $linksHtml = '';
            foreach ($links as $index => $link) {
                $linksHtml .= '<div class="article-inline-grid">'
                    . '<div><label class="form-label">Label</label><input class="form-control" name="blocks[' . master_article_update_esc($blockId) . '][content][links][' . $index . '][label]" value="' . master_article_update_esc($link['label'] ?? '') . '" placeholder="Shop now"></div>'
                    . '<div><label class="form-label">URL</label><input class="form-control" name="blocks[' . master_article_update_esc($blockId) . '][content][links][' . $index . '][url]" value="' . master_article_update_esc($link['url'] ?? '') . '" placeholder="https://..."></div>'
                    . '</div>';
            }

            $html .= '<section class="article-block">'
                . $hidden
                . '<div class="article-block-header"><h3 class="article-block-title">CTA Block</h3><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button></div>'
                . '<div class="article-block-body">'
                . '<div><label class="form-label">CTA Head</label><input class="form-control" name="blocks[' . master_article_update_esc($blockId) . '][content][head]" value="' . master_article_update_esc($content['head'] ?? '') . '" placeholder="Short CTA title"></div>'
                . '<div><label class="form-label">CTA Body</label><textarea class="form-control" name="blocks[' . master_article_update_esc($blockId) . '][content][body]" rows="4" placeholder="CTA copy">' . master_article_update_esc($content['body'] ?? '') . '</textarea></div>'
                . '<div><label class="form-label">Links</label><div class="article-block-body article-cta-links" data-blockid="' . master_article_update_esc($blockId) . '">' . $linksHtml . '</div><button type="button" class="btn btn-outline-primary btn-sm mt-2" data-action="add-cta-link" data-blockid="' . master_article_update_esc($blockId) . '">Add Link</button></div>'
                . '</div></section>';
            continue;
        }

        if ($type === 'list') {
            $content = json_decode((string) ($block['msab_content'] ?? '{}'), true);
            $content = is_array($content) ? $content : [];
            $items = is_array($content['items'] ?? null) ? $content['items'] : [];

            $itemsHtml = '';
            foreach ($items as $index => $item) {
                $itemsHtml .= '<input class="form-control" name="blocks[' . master_article_update_esc($blockId) . '][content][items][' . $index . ']" value="' . master_article_update_esc($item) . '" placeholder="Item ' . ($index + 1) . '">';
            }

            $html .= '<section class="article-block">'
                . $hidden
                . '<div class="article-block-header"><h3 class="article-block-title">List Block</h3><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button></div>'
                . '<div class="article-block-body">'
                . '<div><label class="form-label">Head</label><textarea class="form-control" name="blocks[' . master_article_update_esc($blockId) . '][content][head]" rows="3" placeholder="Optional intro">' . master_article_update_esc($content['head'] ?? '') . '</textarea></div>'
                . '<div><label class="form-label">Items</label><div class="article-block-body article-list-items" data-blockid="' . master_article_update_esc($blockId) . '">' . $itemsHtml . '</div><button type="button" class="btn btn-outline-primary btn-sm mt-2" data-action="add-list-item" data-blockid="' . master_article_update_esc($blockId) . '">Add Item</button></div>'
                . '</div></section>';
            continue;
        }

        if ($type === 'list-grouped') {
            $content = json_decode((string) ($block['msab_content'] ?? '{}'), true);
            $content = is_array($content) ? $content : [];
            $items = is_array($content['items'] ?? null) ? $content['items'] : [];

            $itemsHtml = '';
            foreach ($items as $index => $item) {
                $itemsHtml .= '<div class="article-block-body">'
                    . '<input class="form-control" name="blocks[' . master_article_update_esc($blockId) . '][content][items][' . $index . '][title]" value="' . master_article_update_esc($item['title'] ?? '') . '" placeholder="Title">'
                    . '<textarea class="form-control" name="blocks[' . master_article_update_esc($blockId) . '][content][items][' . $index . '][body]" rows="3" placeholder="Body">' . master_article_update_esc($item['body'] ?? '') . '</textarea>'
                    . '</div>';
            }

            $html .= '<section class="article-block">'
                . $hidden
                . '<div class="article-block-header"><h3 class="article-block-title">Grouped List Block</h3><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button></div>'
                . '<div class="article-block-body">'
                . '<div><label class="form-label">Head</label><textarea class="form-control" name="blocks[' . master_article_update_esc($blockId) . '][content][head]" rows="3" placeholder="Optional intro">' . master_article_update_esc($content['head'] ?? '') . '</textarea></div>'
                . '<div><label class="form-label">Grouped Items</label><div class="article-block-body article-grouped-items" data-blockid="' . master_article_update_esc($blockId) . '">' . $itemsHtml . '</div><button type="button" class="btn btn-outline-primary btn-sm mt-2" data-action="add-group-item" data-blockid="' . master_article_update_esc($blockId) . '">Add Group</button></div>'
                . '</div></section>';
            continue;
        }

        if ($type === 'image') {
            $images = json_decode((string) ($block['msab_images'] ?? '[]'), true);
            $images = is_array($images) ? $images : [];
            $rowsHtml = '';

            foreach ($images as $index => $image) {
                $filename = (string) ($image['filename'] ?? '');
                $url = article_media_public_url($ordinal, $filename);
                $rowsHtml .= '<div class="article-image-row" data-index="' . $index . '">'
                    . '<div><label class="form-label">Upload Image</label><input class="form-control" type="file" name="files_block_' . master_article_update_esc($blockId) . '[]" accept=".jpg,.jpeg,.png,.webp,.gif,image/*"><input type="hidden" data-role="img-existing" name="block_' . master_article_update_esc($blockId) . '_img_' . $index . '_existing" value="' . master_article_update_esc($filename) . '"><img class="article-preview-image" src="' . master_article_update_esc($url) . '" data-existing-src="' . master_article_update_esc($url) . '" alt="Body image preview" style="display:block"></div>'
                    . '<div class="article-inline-grid"><div><label class="form-label">ALT Text</label><input class="form-control" data-role="img-alt" name="block_' . master_article_update_esc($blockId) . '_img_' . $index . '_alt" value="' . master_article_update_esc($image['alt'] ?? '') . '" placeholder="Describe the image"></div></div>'
                    . '<div class="article-image-actions"><button type="button" class="btn btn-outline-secondary btn-sm" data-action="move-up">Move Up</button><button type="button" class="btn btn-outline-secondary btn-sm" data-action="move-down">Move Down</button><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-row">Remove Row</button><button type="button" class="btn btn-outline-warning btn-sm" data-action="clear-file">Clear Selection</button></div>'
                    . '</div>';
            }

            if ($rowsHtml === '') {
                $rowsHtml = '<div class="article-image-row" data-index="0"><div><label class="form-label">Upload Image</label><input class="form-control" type="file" name="files_block_' . master_article_update_esc($blockId) . '[]" accept=".jpg,.jpeg,.png,.webp,.gif,image/*"><input type="hidden" data-role="img-existing" name="block_' . master_article_update_esc($blockId) . '_img_0_existing" value=""><img class="article-preview-image" alt="Body image preview"></div><div class="article-inline-grid"><div><label class="form-label">ALT Text</label><input class="form-control" data-role="img-alt" name="block_' . master_article_update_esc($blockId) . '_img_0_alt" value="" placeholder="Describe the image"></div></div><div class="article-image-actions"><button type="button" class="btn btn-outline-secondary btn-sm" data-action="move-up">Move Up</button><button type="button" class="btn btn-outline-secondary btn-sm" data-action="move-down">Move Down</button><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-row">Remove Row</button><button type="button" class="btn btn-outline-warning btn-sm" data-action="clear-file">Clear Selection</button></div></div>';
            }

            $html .= '<section class="article-block">'
                . $hidden
                . '<div class="article-block-header"><h3 class="article-block-title">Image Block</h3><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button></div>'
                . '<div class="article-block-body"><div class="article-image-rows" data-blockid="' . master_article_update_esc($blockId) . '">' . $rowsHtml . '</div><div><button type="button" class="btn btn-outline-primary btn-sm" data-action="add-image-row" data-blockid="' . master_article_update_esc($blockId) . '">Add Image</button></div></div>'
                . '</section>';
        }
    }

    return $html;
}

$writer = new MasterArticleWriter(get_database_config('lp'));
$code = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
$article = $code !== '' ? $writer->getByCode($code) : null;

if ($article === null) {
    http_response_code(404);
    echo 'Article not found.';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify(trim((string) ($_POST['csrf_token'] ?? '')))) {
        $_SESSION['master_article_error'] = 'Invalid request. Coba submit ulang.';
    } else {
        $result = $writer->updateFromPost((string) ($article['msa_code'] ?? $code), $_POST, $_FILES);
        if (!empty($result['ok'])) {
            $_SESSION['master_article_success'] = 'Article berhasil diperbarui.';
        } else {
            $_SESSION['master_article_error'] = implode(' ', (array) ($result['errors'] ?? ['Gagal update article.']));
        }
    }

    header('Location: /master-article/update?code=' . urlencode((string) ($article['msa_code'] ?? $code)), true, 302);
    exit;
}

$articleSuccess = trim((string) ($_SESSION['master_article_success'] ?? ''));
$articleError = trim((string) ($_SESSION['master_article_error'] ?? ''));
unset($_SESSION['master_article_success'], $_SESSION['master_article_error']);

$articleOld = $writer->toOldArray($article);
$articleBlocksHtml = master_article_render_blocks_html(
    $writer->getBlocksByCode((string) ($article['msa_code'] ?? '')),
    (string) ($article['msa_code'] ?? ''),
    (int) ($article['msa_ordinal'] ?? 0)
);

$articlePageTitle = 'Update Article';
$articlePageDescription = 'Edit article content, image blocks, and metadata with LP database connection.';
$articleFormAction = '/master-article/update?code=' . urlencode((string) ($article['msa_code'] ?? ''));
$articleFormMode = 'update';
$articleSubmitLabel = 'Save Changes';
$articleBackHref = '/master-article';
$articleErrors = $articleError !== '' ? [$articleError] : [];
$articleStorage = get_article_media_config();

require dirname(__DIR__) . '/templates/master-article-editor.php';

