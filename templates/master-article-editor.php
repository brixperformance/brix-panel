<?php

declare(strict_types=1);

$articlePageTitle = isset($articlePageTitle) ? (string) $articlePageTitle : 'Master Article';
$articlePageDescription = isset($articlePageDescription) ? (string) $articlePageDescription : '';
$articleFormAction = isset($articleFormAction) ? (string) $articleFormAction : '/master-article/create';
$articleFormMode = isset($articleFormMode) ? (string) $articleFormMode : 'create';
$articleSubmitLabel = isset($articleSubmitLabel) ? (string) $articleSubmitLabel : 'Save Article';
$articleBackHref = isset($articleBackHref) ? (string) $articleBackHref : '/master-article';
$articleOld = isset($articleOld) && is_array($articleOld) ? $articleOld : [];
$articleBlocksHtml = isset($articleBlocksHtml) ? (string) $articleBlocksHtml : '';
$articleErrors = isset($articleErrors) && is_array($articleErrors) ? $articleErrors : [];
$articleSuccess = isset($articleSuccess) ? (string) $articleSuccess : '';
$articleStorage = isset($articleStorage) && is_array($articleStorage) ? $articleStorage : [];

if (!function_exists('master_article_form_esc')) {
    function master_article_form_esc($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title><?= master_article_form_esc($articlePageTitle) ?> - BRIX</title>
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
    <link href="/assets/css/article-editor.css" rel="stylesheet"/>
</head>
<body>
    <script src="/assets/dist/js/tabler-theme.js"></script>
    <div class="page">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="page-wrapper">
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            <div class="page-pretitle">BRIX Data Hub</div>
                            <h1 class="page-title"><?= master_article_form_esc($articlePageTitle) ?></h1>
                            <?php if ($articlePageDescription !== ''): ?>
                                <div class="text-secondary mt-1"><?= master_article_form_esc($articlePageDescription) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto ms-auto d-print-none">
                            <a href="<?= master_article_form_esc($articleBackHref) ?>" class="btn btn-outline-secondary">
                                Back to Articles
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <main class="page-body">
                <div class="container-xl">
                    <div class="article-editor-shell">
                        <section class="card article-helper-card">
                            <div class="card-body">
                                <div class="article-helper-grid">
                                    <div>
                                        <span class="article-helper-label">Storage Root</span>
                                        <span class="article-helper-value"><?= master_article_form_esc($articleStorage['root'] ?? '-') ?></span>
                                    </div>
                                    <div>
                                        <span class="article-helper-label">Public Base URL</span>
                                        <span class="article-helper-value"><?= master_article_form_esc($articleStorage['base_url'] ?? '-') ?></span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <?php if ($articleSuccess !== ''): ?>
                            <div class="alert alert-success"><?= master_article_form_esc($articleSuccess) ?></div>
                        <?php endif; ?>

                        <?php if ($articleErrors !== []): ?>
                            <div class="alert alert-danger">
                                <?= master_article_form_esc(implode(' ', $articleErrors)) ?>
                            </div>
                        <?php endif; ?>

                        <section class="card article-editor-card">
                            <div class="card-body">
                                <form
                                    method="post"
                                    action="<?= master_article_form_esc($articleFormAction) ?>"
                                    enctype="multipart/form-data"
                                    class="article-editor-form"
                                    data-master-article-form
                                    data-mode="<?= master_article_form_esc($articleFormMode) ?>"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= master_article_form_esc(csrf_token()) ?>">
                                    <?php if ($articleFormMode === 'update'): ?>
                                        <input type="hidden" name="code" value="<?= master_article_form_esc($articleOld['msa_code'] ?? '') ?>">
                                    <?php endif; ?>

                                    <section>
                                        <h2 class="article-section-title">Article Head</h2>
                                        <div class="article-editor-grid">
                                            <div>
                                                <label for="msa_category" class="form-label">Category</label>
                                                <select class="form-select" name="msa_category" id="msa_category" required>
                                                    <option value="">Select category</option>
                                                    <option value="street-series" <?= ($articleOld['msa_category'] ?? '') === 'street-series' ? 'selected' : '' ?>>Street Series</option>
                                                    <option value="competition-series" <?= ($articleOld['msa_category'] ?? '') === 'competition-series' ? 'selected' : '' ?>>Competition Series</option>
                                                    <option value="event" <?= ($articleOld['msa_category'] ?? '') === 'event' ? 'selected' : '' ?>>Event</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="msa_date" class="form-label">Date</label>
                                                <input class="form-control" type="date" id="msa_date" name="msa_date" value="<?= master_article_form_esc($articleOld['msa_date'] ?? '') ?>" required>
                                            </div>
                                            <div>
                                                <label for="msa_code" class="form-label">Article Code</label>
                                                <input class="form-control" id="msa_code" name="msa_code" value="<?= master_article_form_esc($articleOld['msa_code'] ?? '') ?>" readonly>
                                                <div class="article-field-help">Code is generated from the selected category.</div>
                                            </div>
                                            <div>
                                                <label for="msa_slug" class="form-label">Slug</label>
                                                <input class="form-control" id="msa_slug" name="msa_slug" value="<?= master_article_form_esc($articleOld['msa_slug'] ?? '') ?>" required>
                                                <div class="article-field-help">Use lowercase and dashes only.</div>
                                            </div>
                                            <div class="article-editor-grid-full">
                                                <label for="msa_title" class="form-label">Title</label>
                                                <input class="form-control" id="msa_title" name="msa_title" maxlength="200" value="<?= master_article_form_esc($articleOld['msa_title'] ?? '') ?>" required>
                                            </div>
                                            <div class="article-editor-grid-full">
                                                <label for="msa_subtitle" class="form-label">Subtitle</label>
                                                <input class="form-control" id="msa_subtitle" name="msa_subtitle" maxlength="255" value="<?= master_article_form_esc($articleOld['msa_subtitle'] ?? '') ?>">
                                            </div>
                                            <div class="article-editor-grid-full">
                                                <label for="msa_meta_description" class="form-label">Meta Description</label>
                                                <textarea class="form-control" id="msa_meta_description" name="msa_meta_description" rows="4" maxlength="255" required><?= master_article_form_esc($articleOld['msa_meta_description'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </section>

                                    <section>
                                        <h2 class="article-section-title">Header Image</h2>
                                        <div class="article-editor-grid">
                                            <div class="article-editor-grid-full">
                                                <label for="head_header_file" class="form-label">Upload Header Image</label>
                                                <input class="form-control" id="head_header_file" type="file" name="head_header_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" <?= $articleFormMode === 'create' ? 'required' : '' ?>>
                                                <div class="article-field-help">Upload a new file to replace the current header image.</div>
                                            </div>
                                            <div class="article-editor-grid-full">
                                                <div class="article-preview-panel" id="head_header_preview_wrap" style="<?= !empty($articleOld['head_header_url']) ? '' : 'display:none;' ?>">
                                                    <img
                                                        id="head_header_preview"
                                                        class="article-preview-image"
                                                        src="<?= master_article_form_esc($articleOld['head_header_url'] ?? '') ?>"
                                                        data-existing-src="<?= master_article_form_esc($articleOld['head_header_url'] ?? '') ?>"
                                                        alt="Header image preview"
                                                        style="<?= !empty($articleOld['head_header_url']) ? 'display:block;' : '' ?>"
                                                    >
                                                    <div class="article-form-actions mt-3 pt-0 border-0">
                                                        <div class="text-secondary small">Preview of the current or selected header image.</div>
                                                        <div class="btn-list">
                                                            <button type="button" class="btn btn-outline-warning btn-sm" id="head_header_clear">Clear Selection</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="article-editor-grid-full">
                                                <label for="head_header_alt" class="form-label">Header ALT Text</label>
                                                <input class="form-control" id="head_header_alt" name="head_header_alt" value="<?= master_article_form_esc($articleOld['head_header_alt'] ?? '') ?>" placeholder="Describe the header image">
                                            </div>
                                        </div>
                                    </section>

                                    <section>
                                        <h2 class="article-section-title">Detail Blocks</h2>
                                        <div class="article-toolbar">
                                            <button type="button" class="btn btn-outline-primary" data-add-master-article-block="text">Add Text</button>
                                            <button type="button" class="btn btn-outline-primary" data-add-master-article-block="image">Add Image</button>
                                            <button type="button" class="btn btn-outline-primary" data-add-master-article-block="cta">Add CTA</button>
                                            <button type="button" class="btn btn-outline-primary" data-add-master-article-block="list">Add List</button>
                                            <button type="button" class="btn btn-outline-primary" data-add-master-article-block="list-grouped">Add Grouped List</button>
                                        </div>
                                        <div id="detail-blocks" class="article-block-list"><?= $articleBlocksHtml ?></div>
                                    </section>

                                    <div class="article-form-actions">
                                        <div class="text-secondary small">
                                            All article content is saved to database `brix_lp`, while images are stored in the configured media folder.
                                        </div>
                                        <div class="btn-list">
                                            <a href="<?= master_article_form_esc($articleBackHref) ?>" class="btn btn-outline-secondary">Cancel</a>
                                            <button type="submit" class="btn btn-primary"><?= master_article_form_esc($articleSubmitLabel) ?></button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </section>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="/assets/dist/js/tabler.js"></script>
    <script src="/assets/js/master-article/create.js" defer></script>
    <script src="/assets/js/idle-timeout.js" defer></script>
</body>
</html>

