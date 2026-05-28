<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';
require_once dirname(__DIR__) . '/application/configs/shop_pdo.php';
require_once dirname(__DIR__) . '/application/configs/csrf.php';
require_once dirname(__DIR__) . '/application/models/ProductDisplay.php';
require_once dirname(__DIR__) . '/application/models/ProductPricing.php';

bootstrap_page(null, false);

if (empty($_SESSION['logged_in'])) {
    header('Location: /login', true, 302);
    exit;
}

function shop_pricing_esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function shop_pricing_redirect(string $message = '', string $error = '', array $extra = []): void
{
    $query = array_filter(array_merge($extra, ['message' => $message, 'error' => $error]), static fn ($v) => $v !== '');
    $url   = '/shop/pricing' . ($query ? '?' . http_build_query($query) : '');
    header('Location: ' . $url);
    exit;
}

function shop_pricing_dt_input(?string $value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    $ts = strtotime($text);
    return $ts !== false ? date('Y-m-d\TH:i', $ts) : '';
}

function shop_pricing_dt_db(?string $value): ?string
{
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }
    $ts = strtotime($text);
    return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
}

function shop_pricing_rule_type_label(string $type): string
{
    return match ($type) {
        'percent'        => 'Percent Off',
        'fixed'          => 'Amount Off',
        'price_override' => 'Direct Sale Price',
        default          => ucfirst(str_replace('_', ' ', $type)),
    };
}

function shop_pricing_pagination(int $current, int $total, array $extra = []): string
{
    if ($total <= 1) {
        return '';
    }
    $qBase = $extra ? ('&' . http_build_query($extra)) : '';
    $html  = '<ul class="pagination m-0">';
    $html .= '<li class="page-item' . ($current <= 1 ? ' disabled' : '') . '"><a class="page-link" href="?p=' . ($current - 1) . $qBase . '">&laquo;</a></li>';
    $start = max(1, $current - 2);
    $end   = min($total, $current + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item' . ($i === $current ? ' active' : '') . '"><a class="page-link" href="?p=' . $i . $qBase . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item' . ($current >= $total ? ' disabled' : '') . '"><a class="page-link" href="?p=' . ($current + 1) . $qBase . '">&raquo;</a></li>';
    $html .= '</ul>';
    return $html;
}

$dbError = null;

try {
    $pdo = get_shop_pdo();
} catch (PDOException $e) {
    $dbError = 'Database tidak tersedia. Pastikan tabel shop sudah dibuat.';
    $pdo     = null;
}

if ($pdo !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
    if (!csrf_verify($csrfToken)) {
        shop_pricing_redirect('', 'Invalid request. Coba lagi.');
    }

    $action      = trim((string) ($_POST['action'] ?? ''));
    $productId   = max(0, (int) ($_POST['product_id'] ?? 0));
    $ruleId      = max(0, (int) ($_POST['rule_id'] ?? 0));
    $redirectExtra = array_filter([
        'q'          => trim((string) ($_POST['q'] ?? '')),
        'sale_state' => trim((string) ($_POST['sale_state'] ?? '')),
        'p'          => max(1, (int) ($_POST['p'] ?? 1)) > 1 ? max(1, (int) ($_POST['p'] ?? 1)) : null,
    ], static fn ($v) => $v !== '' && $v !== null);

    try {
        if ($action === 'save') {
            $name         = trim((string) ($_POST['name'] ?? ''));
            $status       = trim((string) ($_POST['status'] ?? 'draft'));
            $discType     = trim((string) ($_POST['discount_type'] ?? 'percent'));
            $discValue    = (float) ($_POST['discount_value'] ?? 0);
            $compareAt    = trim((string) ($_POST['compare_at_price'] ?? ''));
            $badgeText    = trim((string) ($_POST['badge_text'] ?? ''));
            $startsAt     = shop_pricing_dt_db((string) ($_POST['starts_at'] ?? ''));
            $endsAt       = shop_pricing_dt_db((string) ($_POST['ends_at'] ?? ''));
            $priority     = max(1, (int) ($_POST['priority'] ?? 100));
            $notes        = trim((string) ($_POST['notes'] ?? ''));
            $isStackable  = isset($_POST['is_stackable']) ? 1 : 0;

            if ($productId <= 0) {
                throw new RuntimeException('Product harus dipilih.');
            }
            if ($name === '') {
                throw new RuntimeException('Rule name wajib diisi.');
            }
            if (!in_array($status, ['draft', 'scheduled', 'active', 'expired', 'disabled'], true)) {
                throw new RuntimeException('Status tidak valid.');
            }
            if (!in_array($discType, ['percent', 'fixed', 'price_override'], true)) {
                throw new RuntimeException('Discount type tidak valid.');
            }
            if ($discValue <= 0) {
                throw new RuntimeException('Discount value harus lebih dari 0.');
            }
            if ($discType === 'percent' && $discValue > 100) {
                throw new RuntimeException('Percent discount tidak boleh lebih dari 100.');
            }
            if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) {
                throw new RuntimeException('End date harus setelah start date.');
            }

            $compareAtVal = null;
            if ($compareAt !== '') {
                $compareAtVal = max(0, (int) preg_replace('/\D+/', '', $compareAt));
            }

            $pdo->beginTransaction();

            if ($ruleId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE ms_product_pricing_rules
                    SET mprr_mpr_id = :product_id, mprr_name = :name, mprr_status = :status,
                        mprr_discount_type = :disc_type, mprr_discount_value = :disc_value,
                        mprr_compare_at_price = :compare_at, mprr_badge_text = :badge,
                        mprr_is_stackable = :stackable, mprr_starts_at = :starts,
                        mprr_ends_at = :ends, mprr_priority = :priority, mprr_notes = :notes
                    WHERE mprr_id = :rule_id
                ");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ms_product_pricing_rules
                        (mprr_mpr_id, mprr_name, mprr_status, mprr_discount_type, mprr_discount_value,
                         mprr_compare_at_price, mprr_badge_text, mprr_is_stackable,
                         mprr_starts_at, mprr_ends_at, mprr_priority, mprr_notes)
                    VALUES
                        (:product_id, :name, :status, :disc_type, :disc_value,
                         :compare_at, :badge, :stackable, :starts, :ends, :priority, :notes)
                ");
            }

            $bindData = [
                ':product_id' => $productId, ':name' => $name, ':status' => $status,
                ':disc_type' => $discType, ':disc_value' => $discValue,
                ':compare_at' => $compareAtVal, ':badge' => $badgeText !== '' ? $badgeText : null,
                ':stackable' => $isStackable, ':starts' => $startsAt, ':ends' => $endsAt,
                ':priority' => $priority, ':notes' => $notes !== '' ? $notes : null,
            ];
            if ($ruleId > 0) {
                $bindData[':rule_id'] = $ruleId;
            }
            $stmt->execute($bindData);

            if ($ruleId <= 0) {
                $ruleId = (int) $pdo->lastInsertId();
            }

            if (in_array($status, ['active', 'scheduled'], true)) {
                $pdo->prepare("
                    UPDATE ms_product_pricing_rules SET mprr_status = 'disabled'
                    WHERE mprr_mpr_id = :product_id AND mprr_id <> :rule_id AND mprr_status IN ('active','scheduled')
                ")->execute([':product_id' => $productId, ':rule_id' => $ruleId]);
            }

            $pdo->commit();
            shop_pricing_redirect('Pricing rule saved.', '', $redirectExtra);
        }

        if ($action === 'delete') {
            if ($ruleId <= 0) {
                throw new RuntimeException('Rule tidak ditemukan.');
            }
            $pdo->prepare('DELETE FROM ms_product_pricing_rules WHERE mprr_id = :id')->execute([':id' => $ruleId]);
            shop_pricing_redirect('Pricing rule deleted.', '', $redirectExtra);
        }

        if ($action === 'disable') {
            if ($ruleId <= 0) {
                throw new RuntimeException('Rule tidak ditemukan.');
            }
            $pdo->prepare("UPDATE ms_product_pricing_rules SET mprr_status = 'disabled' WHERE mprr_id = :id")->execute([':id' => $ruleId]);
            shop_pricing_redirect('Pricing rule disabled.', '', $redirectExtra);
        }

        shop_pricing_redirect('', 'Unknown action.');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        shop_pricing_redirect('', $e->getMessage(), array_merge($redirectExtra, [
            'modal'      => $ruleId > 0 ? 'edit' : 'create',
            'product_id' => $productId > 0 ? $productId : '',
            'rule_id'    => $ruleId > 0 ? $ruleId : '',
        ]));
    }
}

$message      = trim((string) ($_GET['message'] ?? ''));
$error        = trim((string) ($_GET['error'] ?? ''));
$query        = trim((string) ($_GET['q'] ?? ''));
$saleState    = trim((string) ($_GET['sale_state'] ?? 'all'));
$modal        = trim((string) ($_GET['modal'] ?? ''));
$modalProdId  = max(0, (int) ($_GET['product_id'] ?? 0));
$modalRuleId  = max(0, (int) ($_GET['rule_id'] ?? 0));
$perPage      = 25;
$pageNum      = max(1, (int) ($_GET['p'] ?? 1));

$products          = [];
$totalProducts     = 0;
$totalPages        = 1;
$rulesByProduct    = [];
$primaryByProduct  = [];
$productOptions    = [];
$productMap        = [];
$summary           = ['total_products' => 0, 'products_on_sale' => 0, 'active_rules' => 0];

if ($pdo !== null && product_pricing_table_exists($pdo)) {
    try {
        $filterParams = [];
        $filterWhere  = [];

        if ($query !== '') {
            $filterWhere[] = '(p.mpr_title LIKE :q_title OR p.mpr_sku LIKE :q_sku OR p.mpr_fitment_sizing LIKE :q_vehicle)';
            $like = '%' . $query . '%';
            $filterParams[':q_title']   = $like;
            $filterParams[':q_sku']     = $like;
            $filterParams[':q_vehicle'] = $like;
        }

        if ($saleState === 'on_sale') {
            $filterWhere[] = "EXISTS (SELECT 1 FROM ms_product_pricing_rules r WHERE r.mprr_mpr_id = p.mpr_id AND r.mprr_status IN ('active','scheduled'))";
        } elseif ($saleState === 'no_sale') {
            $filterWhere[] = "NOT EXISTS (SELECT 1 FROM ms_product_pricing_rules r WHERE r.mprr_mpr_id = p.mpr_id AND r.mprr_status IN ('active','scheduled'))";
        }

        $whereSql = $filterWhere ? (' WHERE ' . implode(' AND ', $filterWhere)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ms_products p LEFT JOIN ms_product_types pt ON pt.mpt_id = p.mpr_mpt_id $whereSql");
        foreach ($filterParams as $k => $v) {
            $countStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $totalProducts = (int) $countStmt->fetchColumn();
        $totalPages    = $totalProducts > 0 ? (int) ceil($totalProducts / $perPage) : 1;
        if ($pageNum > $totalPages) {
            $pageNum = $totalPages;
        }
        $offset = ($pageNum - 1) * $perPage;

        $prodStmt = $pdo->prepare("
            SELECT p.mpr_id AS id, p.mpr_title AS title, p.mpr_price AS price,
                   p.mpr_sku AS sku, p.mpr_fitment_sizing AS vehicle_text,
                   COALESCE(pt.mpt_code, '') AS type_code, COALESCE(pt.mpt_name, '') AS type_name
            FROM ms_products p
            LEFT JOIN ms_product_types pt ON pt.mpt_id = p.mpr_mpt_id
            $whereSql
            ORDER BY p.mpr_id DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($filterParams as $k => $v) {
            $prodStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $prodStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $prodStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $prodStmt->execute();
        $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $products = product_pricing_enrich_product_rows($pdo, $products);

        $productIds = array_values(array_filter(array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $products)));

        if (!empty($productIds)) {
            $placeholders = implode(', ', array_map(static fn (int $i): string => ':pid_' . $i, array_keys($productIds)));
            $rulesStmt    = $pdo->prepare("
                SELECT * FROM ms_product_pricing_rules
                WHERE mprr_mpr_id IN ($placeholders)
                ORDER BY mprr_mpr_id ASC,
                    CASE mprr_status WHEN 'active' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'draft' THEN 2 WHEN 'disabled' THEN 3 ELSE 4 END,
                    mprr_priority ASC, mprr_id DESC
            ");
            foreach ($productIds as $i => $pid) {
                $rulesStmt->bindValue(':pid_' . $i, $pid, PDO::PARAM_INT);
            }
            $rulesStmt->execute();
            $allRules = $rulesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($allRules as $rule) {
                $pid = (int) ($rule['mprr_mpr_id'] ?? 0);
                $rulesByProduct[$pid][] = $rule;
            }
        }

        $allProdStmt = $pdo->prepare("
            SELECT p.mpr_id AS id, p.mpr_title AS title, p.mpr_price AS price,
                   p.mpr_sku AS sku, p.mpr_fitment_sizing AS vehicle_text,
                   COALESCE(pt.mpt_code, '') AS type_code, COALESCE(pt.mpt_name, '') AS type_name
            FROM ms_products p LEFT JOIN ms_product_types pt ON pt.mpt_id = p.mpr_mpt_id
            ORDER BY p.mpr_title ASC, p.mpr_id DESC
        ");
        $allProdStmt->execute();
        $allProds = $allProdStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($allProds as $p) {
            $pid = (int) ($p['id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $productMap[$pid]   = $p;
            $productOptions[]   = ['id' => $pid, 'label' => shop_pricing_esc(product_display_title($p)) . ' • SKU ' . shop_pricing_esc($p['sku'] ?: '-')];
        }

        foreach ($products as $product) {
            $pid = (int) ($product['id'] ?? 0);
            $primaryByProduct[$pid] = $product['pricing_rule'] ?? ($rulesByProduct[$pid][0] ?? null);
        }

        $summary['total_products']  = (int) $pdo->query('SELECT COUNT(*) FROM ms_products')->fetchColumn();
        $summary['products_on_sale'] = (int) $pdo->query("SELECT COUNT(DISTINCT mprr_mpr_id) FROM ms_product_pricing_rules WHERE mprr_status IN ('active','scheduled')")->fetchColumn();
        $summary['active_rules']     = (int) $pdo->query("SELECT COUNT(*) FROM ms_product_pricing_rules WHERE mprr_status = 'active'")->fetchColumn();
    } catch (PDOException $e) {
        $dbError = 'Gagal memuat data pricing: ' . $e->getMessage();
    }
} elseif ($pdo !== null) {
    $dbError = 'Tabel pricing rules belum tersedia. Jalankan .dev/shop-tables.sql terlebih dahulu.';
}

$paginationExtra = array_filter(['q' => $query, 'sale_state' => $saleState !== 'all' ? $saleState : ''], static fn ($v) => $v !== '');

function shop_pricing_rule_form(
    array $rule,
    array $productOptions,
    array $productMap,
    string $submitLabel,
    int $pageNum,
    string $query,
    string $saleState
): string {
    $productId = (int) ($rule['mprr_mpr_id'] ?? $rule['_product_id'] ?? 0);
    ob_start();
    ?>
    <form method="post" action="/shop/pricing">
        <input type="hidden" name="csrf_token" value="<?= shop_pricing_esc(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="rule_id" value="<?= shop_pricing_esc((string) ($rule['mprr_id'] ?? 0)) ?>">
        <input type="hidden" name="q" value="<?= shop_pricing_esc($query) ?>">
        <input type="hidden" name="sale_state" value="<?= shop_pricing_esc($saleState) ?>">
        <input type="hidden" name="p" value="<?= (int) $pageNum ?>">
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Product</label>
                <select class="form-select" name="product_id" required>
                    <option value="">Pilih product</option>
                    <?php foreach ($productOptions as $opt): ?>
                        <option value="<?= (int) $opt['id'] ?>" <?= (int) $opt['id'] === $productId ? 'selected' : '' ?>>
                            <?= $opt['label'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Rule Name</label>
                <input class="form-control" type="text" name="name" value="<?= shop_pricing_esc($rule['mprr_name'] ?? '') ?>" required>
            </div>
            <div class="row g-2 mb-3">
                <div class="col">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach (['draft', 'scheduled', 'active', 'disabled', 'expired'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($rule['mprr_status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label">Priority</label>
                    <input class="form-control" type="number" min="1" name="priority" value="<?= shop_pricing_esc((string) ($rule['mprr_priority'] ?? 100)) ?>">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col">
                    <label class="form-label">Discount Type</label>
                    <select class="form-select" name="discount_type">
                        <option value="percent" <?= ($rule['mprr_discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percent Off</option>
                        <option value="fixed" <?= ($rule['mprr_discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Amount Off</option>
                        <option value="price_override" <?= ($rule['mprr_discount_type'] ?? '') === 'price_override' ? 'selected' : '' ?>>Direct Sale Price</option>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label">Discount Value</label>
                    <input class="form-control" type="number" min="0" step="0.01" name="discount_value" value="<?= shop_pricing_esc((string) ($rule['mprr_discount_value'] ?? '0.00')) ?>">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col">
                    <label class="form-label">Compare At Price</label>
                    <input class="form-control" type="number" min="0" step="1" name="compare_at_price" value="<?= shop_pricing_esc((string) ($rule['mprr_compare_at_price'] ?? '')) ?>">
                </div>
                <div class="col">
                    <label class="form-label">Badge Text</label>
                    <input class="form-control" type="text" name="badge_text" value="<?= shop_pricing_esc($rule['mprr_badge_text'] ?? '') ?>" placeholder="Auto if empty">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col">
                    <label class="form-label">Starts At</label>
                    <input class="form-control" type="datetime-local" name="starts_at" value="<?= shop_pricing_esc(shop_pricing_dt_input($rule['mprr_starts_at'] ?? '')) ?>">
                </div>
                <div class="col">
                    <label class="form-label">Ends At</label>
                    <input class="form-control" type="datetime-local" name="ends_at" value="<?= shop_pricing_esc(shop_pricing_dt_input($rule['mprr_ends_at'] ?? '')) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_stackable" value="1" <?= !empty($rule['mprr_is_stackable']) ? 'checked' : '' ?>>
                    <span class="form-check-label">Allow rule stacking</span>
                </label>
            </div>
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="2"><?= shop_pricing_esc($rule['mprr_notes'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" type="submit"><?= shop_pricing_esc($submitLabel) ?></button>
            <?php if (!empty($rule['mprr_id'])): ?>
                <button class="btn btn-outline-warning" type="submit" name="action" value="disable">Disable</button>
            <?php endif; ?>
            <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button>
        </div>
    </form>
    <?php
    return (string) ob_get_clean();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>Shop Pricing - BRIX</title>
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
                            <div class="page-pretitle">BRIX Shop</div>
                            <h1 class="page-title">Product Pricing</h1>
                        </div>
                    </div>
                </div>
            </div>

            <main class="page-body">
                <div class="container-xl">

                    <?php if ($message !== ''): ?>
                        <div class="alert alert-success alert-dismissible mb-4" role="alert">
                            <?= shop_pricing_esc($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible mb-4" role="alert">
                            <?= shop_pricing_esc($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($dbError !== null): ?>
                        <div class="alert alert-warning mb-4" role="alert">
                            <?= shop_pricing_esc($dbError) ?> &mdash; Lihat <code>.dev/shop-tables.sql</code>.
                        </div>
                    <?php endif; ?>

                    <div class="row row-deck row-cards mb-4">
                        <div class="col-sm-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Total Products</div>
                                    <div class="h1 mb-0"><?= number_format($summary['total_products']) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Products With Promo</div>
                                    <div class="h1 mb-0 text-warning"><?= number_format($summary['products_on_sale']) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Active Rules</div>
                                    <div class="h1 mb-0 text-success"><?= number_format($summary['active_rules']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <form class="row g-2 align-items-end" method="get" action="/shop/pricing">
                                <div class="col-sm">
                                    <label class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></span>
                                        <input type="text" class="form-control" name="q" placeholder="Cari product, SKU..." value="<?= shop_pricing_esc($query) ?>">
                                    </div>
                                </div>
                                <div class="col-sm-auto">
                                    <label class="form-label">Promo Status</label>
                                    <select class="form-select" name="sale_state">
                                        <option value="all" <?= $saleState === 'all' ? 'selected' : '' ?>>All Products</option>
                                        <option value="on_sale" <?= $saleState === 'on_sale' ? 'selected' : '' ?>>On Sale</option>
                                        <option value="no_sale" <?= $saleState === 'no_sale' ? 'selected' : '' ?>>No Sale</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-primary" type="submit">Filter</button>
                                    <?php if ($query !== '' || $saleState !== 'all'): ?>
                                        <a class="btn btn-outline-secondary ms-1" href="/shop/pricing">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card" id="pricing-list">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title m-0">Pricing List</h3>
                            <?php if (!empty($productOptions)): ?>
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#modal-pricing-create">
                                    + Create Rule
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Base Price</th>
                                        <th>Promo Price</th>
                                        <th>Primary Rule</th>
                                        <th class="w-1">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="5" class="text-secondary text-center py-3">No products found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                            <?php
                                            $pid         = (int) ($product['id'] ?? 0);
                                            $title       = product_display_title($product);
                                            $primaryRule = $primaryByProduct[$pid] ?? null;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-medium"><?= shop_pricing_esc($title) ?></div>
                                                    <div class="text-secondary small">SKU: <?= shop_pricing_esc($product['sku'] ?: '-') ?></div>
                                                    <div class="text-secondary small"><?= shop_pricing_esc($product['vehicle_text'] ?: '') ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-medium">IDR<?= number_format((int) ($product['price'] ?? 0), 0, ',', '.') ?>,-</div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($product['has_discount'])): ?>
                                                        <?php if (!empty($product['compare_at_display_price'])): ?>
                                                            <del class="text-secondary small">IDR<?= number_format((int) $product['compare_at_display_price'], 0, ',', '.') ?>,-</del><br>
                                                        <?php endif; ?>
                                                        <span class="fw-medium text-success">IDR<?= number_format((int) ($product['display_price'] ?? $product['price']), 0, ',', '.') ?>,-</span>
                                                        <?php if (!empty($product['pricing_badge_text'])): ?>
                                                            <span class="badge bg-success-lt text-success ms-1"><?= shop_pricing_esc($product['pricing_badge_text']) ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-secondary small">No live promo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($primaryRule === null): ?>
                                                        <span class="text-secondary small">No rule configured.</span>
                                                    <?php else: ?>
                                                        <div class="fw-medium"><?= shop_pricing_esc($primaryRule['mprr_name'] ?? 'Untitled') ?></div>
                                                        <div class="text-secondary small"><?= shop_pricing_esc(shop_pricing_rule_type_label((string) ($primaryRule['mprr_discount_type'] ?? ''))) ?> &bull; <?= shop_pricing_esc((string) ($primaryRule['mprr_discount_value'] ?? '0')) ?></div>
                                                        <span class="badge bg-secondary-lt"><?= shop_pricing_esc((string) ($primaryRule['mprr_status'] ?? 'draft')) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modal-pview-<?= $pid ?>">View</button>
                                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-pedit-<?= $pid ?>">Edit</button>
                                                        <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#modal-pdel-<?= $pid ?>">Del</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <p class="m-0 text-secondary">
                                    Showing <?= $totalProducts > 0 ? (($pageNum - 1) * $perPage + 1) : 0 ?>
                                    &ndash; <?= min($pageNum * $perPage, $totalProducts) ?>
                                    of <?= $totalProducts ?>
                                </p>
                                <?= shop_pricing_pagination($pageNum, $totalPages, $paginationExtra) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <?php if (!empty($productOptions)): ?>
        <div class="modal modal-blur fade" id="modal-pricing-create" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Pricing Rule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <?php
                    $createRule = [
                        'mprr_id' => 0, 'mprr_name' => '', 'mprr_status' => 'draft',
                        'mprr_discount_type' => 'percent', 'mprr_discount_value' => '0.00',
                        'mprr_compare_at_price' => '', 'mprr_badge_text' => '',
                        'mprr_is_stackable' => 0, 'mprr_starts_at' => '',
                        'mprr_ends_at' => '', 'mprr_priority' => 100, 'mprr_notes' => '',
                        'mprr_mpr_id' => $modalProdId > 0 ? $modalProdId : (int) ($productOptions[0]['id'] ?? 0),
                    ];
                    echo shop_pricing_rule_form($createRule, $productOptions, $productMap, 'Save Rule', $pageNum, $query, $saleState);
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($products as $product): ?>
        <?php
        $pid         = (int) ($product['id'] ?? 0);
        $title       = product_display_title($product);
        $productRules = $rulesByProduct[$pid] ?? [];
        $primaryRule  = $primaryByProduct[$pid] ?? null;
        $editRule     = $primaryRule ?? [
            'mprr_id' => 0, 'mprr_name' => '', 'mprr_status' => 'draft',
            'mprr_discount_type' => 'percent', 'mprr_discount_value' => '0.00',
            'mprr_compare_at_price' => '', 'mprr_badge_text' => '',
            'mprr_is_stackable' => 0, 'mprr_starts_at' => '',
            'mprr_ends_at' => '', 'mprr_priority' => 100, 'mprr_notes' => '',
        ];
        $editRule['mprr_mpr_id'] = $pid;
        ?>

        <div class="modal modal-blur fade" id="modal-pview-<?= $pid ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Pricing Detail — <?= shop_pricing_esc($title) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2 mb-3">
                            <div class="col-sm-4">
                                <div class="subheader small">Base Price</div>
                                <div class="fw-medium">IDR<?= number_format((int) ($product['price'] ?? 0), 0, ',', '.') ?>,-</div>
                            </div>
                            <div class="col-sm-4">
                                <div class="subheader small">Selling Price</div>
                                <div class="fw-medium <?= !empty($product['has_discount']) ? 'text-success' : '' ?>">IDR<?= number_format((int) ($product['display_price'] ?? $product['price']), 0, ',', '.') ?>,-</div>
                            </div>
                            <div class="col-sm-4">
                                <div class="subheader small">Promo Badge</div>
                                <div><?= shop_pricing_esc($product['pricing_badge_text'] ?: 'No live promo') ?></div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Rule</th>
                                        <th>Status</th>
                                        <th>Discount</th>
                                        <th>Window</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($productRules)): ?>
                                        <tr><td colspan="4" class="text-secondary">No pricing rules for this product.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($productRules as $rule): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-medium"><?= shop_pricing_esc($rule['mprr_name'] ?: 'Untitled') ?></div>
                                                    <div class="text-secondary small">Priority: <?= (int) ($rule['mprr_priority'] ?? 100) ?></div>
                                                </td>
                                                <td><span class="badge bg-secondary-lt"><?= shop_pricing_esc((string) ($rule['mprr_status'] ?? 'draft')) ?></span></td>
                                                <td><?= shop_pricing_esc(shop_pricing_rule_type_label((string) ($rule['mprr_discount_type'] ?? ''))) ?> &bull; <?= shop_pricing_esc((string) ($rule['mprr_discount_value'] ?? '0')) ?></td>
                                                <td class="text-secondary small"><?= shop_pricing_esc(($rule['mprr_starts_at'] ?: '-') . ' to ' . ($rule['mprr_ends_at'] ?: '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal modal-blur fade" id="modal-pedit-<?= $pid ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= !empty($primaryRule['mprr_id']) ? 'Edit' : 'Create' ?> Pricing Rule — <?= shop_pricing_esc($title) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <?= shop_pricing_rule_form($editRule, $productOptions, $productMap, !empty($primaryRule['mprr_id']) ? 'Update Rule' : 'Save Rule', $pageNum, $query, $saleState) ?>
                </div>
            </div>
        </div>

        <div class="modal modal-blur fade" id="modal-pdel-<?= $pid ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Pricing Rule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($primaryRule === null || empty($primaryRule['mprr_id'])): ?>
                            <p class="text-secondary">Produk ini belum punya pricing rule yang bisa dihapus.</p>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <strong><?= shop_pricing_esc($primaryRule['mprr_name'] ?: 'Untitled Rule') ?></strong><br>
                                Status: <?= shop_pricing_esc((string) ($primaryRule['mprr_status'] ?? 'draft')) ?> &bull;
                                <?= shop_pricing_esc(shop_pricing_rule_type_label((string) ($primaryRule['mprr_discount_type'] ?? ''))) ?> &bull;
                                <?= shop_pricing_esc((string) ($primaryRule['mprr_discount_value'] ?? '0')) ?>
                            </div>
                            <p>Yakin ingin menghapus rule ini?</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <?php if (!empty($primaryRule['mprr_id'])): ?>
                            <form method="post" action="/shop/pricing">
                                <input type="hidden" name="csrf_token" value="<?= shop_pricing_esc(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?= $pid ?>">
                                <input type="hidden" name="rule_id" value="<?= shop_pricing_esc((string) $primaryRule['mprr_id']) ?>">
                                <input type="hidden" name="q" value="<?= shop_pricing_esc($query) ?>">
                                <input type="hidden" name="sale_state" value="<?= shop_pricing_esc($saleState) ?>">
                                <input type="hidden" name="p" value="<?= (int) $pageNum ?>">
                                <button class="btn btn-danger" type="submit">Delete Rule</button>
                            </form>
                        <?php endif; ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </div>
            </div>
        </div>

    <?php endforeach; ?>

    <script src="/assets/dist/js/tabler.js"></script>
    <script src="/assets/js/idle-timeout.js" defer></script>
    <?php if ($modal !== ''): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var selector = <?php
                if ($modal === 'create') {
                    echo json_encode('#modal-pricing-create');
                } elseif ($modal === 'edit') {
                    echo json_encode('#modal-pedit-' . $modalProdId);
                }
            ?>;
            if (selector) {
                var el = document.querySelector(selector);
                if (el) {
                    new bootstrap.Modal(el).show();
                }
            }
        });
        </script>
    <?php endif; ?>
</body>
</html>
