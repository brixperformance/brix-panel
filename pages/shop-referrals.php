<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';
require_once dirname(__DIR__) . '/application/configs/shop_pdo.php';
require_once dirname(__DIR__) . '/application/configs/csrf.php';

bootstrap_page(null, false);

if (empty($_SESSION['logged_in'])) {
    header('Location: /login', true, 302);
    exit;
}

function shop_ref_esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function shop_ref_redirect(string $message = '', string $error = '', array $extra = []): void
{
    $query = array_filter(array_merge($extra, ['message' => $message, 'error' => $error]), static fn ($v) => $v !== '');
    $url   = '/shop/referrals' . ($query ? '?' . http_build_query($query) : '');
    header('Location: ' . $url);
    exit;
}

function shop_ref_dt_input(?string $value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    $ts = strtotime($text);
    return $ts !== false ? date('Y-m-d\TH:i', $ts) : '';
}

function shop_ref_dt_db(?string $value): ?string
{
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }
    $ts = strtotime($text);
    return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
}

function shop_ref_minute_floor(?int $timestamp = null): int
{
    return ((int) floor(($timestamp ?? time()) / 60)) * 60;
}

function shop_ref_min_start_ts(?int $timestamp = null): int
{
    return shop_ref_minute_floor($timestamp) + 60;
}

function shop_ref_auto_dt_input(int $minutesFromNow): string
{
    return date('Y-m-d\TH:i', shop_ref_minute_floor() + ($minutesFromNow * 60));
}

function shop_ref_status_badge(array $referral): string
{
    if ((int) ($referral['is_enabled'] ?? 0) !== 1) {
        return '<span class="badge bg-secondary-lt text-secondary">Disabled</span>';
    }

    $now = time();
    $startsAt = strtotime((string) ($referral['starts_at'] ?? ''));
    $endsAt = strtotime((string) ($referral['ends_at'] ?? ''));

    if ($startsAt !== false && $startsAt > $now) {
        return '<span class="badge bg-warning-lt text-warning">Scheduled</span>';
    }

    if ($endsAt !== false && $endsAt < $now) {
        return '<span class="badge bg-danger-lt text-danger">Expired</span>';
    }

    return '<span class="badge bg-success-lt text-success">Enabled</span>';
}

function shop_ref_benefit_summary(array $referral): string
{
    $parts = [];

    if (($referral['item_discount_type'] ?? 'none') === 'percent') {
        $pct  = (float) ($referral['item_discount_value'] ?? 0);
        $text = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') . '%';
        if (!empty($referral['item_discount_max_amount'])) {
            $text .= ' max IDR' . number_format((int) $referral['item_discount_max_amount'], 0, ',', '.');
        }
        $parts[] = 'Product ' . $text;
    }

    if (($referral['shipping_discount_type'] ?? 'none') === 'fixed') {
        $parts[] = 'Shipping IDR' . number_format((int) ($referral['shipping_discount_value'] ?? 0), 0, ',', '.');
    }

    return $parts ? implode(' + ', $parts) : 'No benefit';
}

function shop_ref_pagination(int $current, int $total, array $extra = []): string
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
        shop_ref_redirect('', 'Invalid request. Coba lagi.');
    }

    $action        = trim((string) ($_POST['action'] ?? ''));
    $redirectExtra = array_filter([
        'q'      => trim((string) ($_POST['q'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? '')),
        'p'      => max(1, (int) ($_POST['p'] ?? 1)) > 1 ? max(1, (int) ($_POST['p'] ?? 1)) : null,
    ], static fn ($v) => $v !== '' && $v !== null);

    try {
        if ($action === 'save') {
            $id             = max(0, (int) ($_POST['id'] ?? 0));
            $name           = trim((string) ($_POST['name'] ?? ''));
            $code           = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $isEnabled      = isset($_POST['is_enabled']) ? 1 : 0;
            $maxUsage       = max(0, (int) ($_POST['max_usage_total'] ?? 0));
            $usedCount      = max(0, (int) ($_POST['used_count'] ?? 0));
            $startsAt       = shop_ref_dt_db((string) ($_POST['starts_at'] ?? ''));
            $endsAt         = shop_ref_dt_db((string) ($_POST['ends_at'] ?? ''));
            $currentStartsAt = null;

            $itemEnabled    = isset($_POST['item_benefit_enabled']);
            $itemPercent    = $itemEnabled ? max(0, (float) ($_POST['item_discount_value'] ?? 0)) : 0.0;
            $itemCap        = $itemEnabled ? max(0, (int) ($_POST['item_discount_max_amount'] ?? 0)) : 0;

            $shipEnabled    = isset($_POST['shipping_benefit_enabled']);
            $shipAmount     = $shipEnabled ? max(0, (int) ($_POST['shipping_discount_value'] ?? 0)) : 0;

            if ($name === '') {
                throw new RuntimeException('Referral name is required.');
            }
            if ($code === '') {
                throw new RuntimeException('Referral code is required.');
            }
            if ($id > 0) {
                $currentStmt = $pdo->prepare('SELECT ref_starts_at FROM tr_referrals WHERE ref_id = :id');
                $currentStmt->execute([':id' => $id]);
                $currentStartsAt = shop_ref_dt_db((string) ($currentStmt->fetchColumn() ?: ''));
            }
            if (!$itemEnabled && !$shipEnabled) {
                throw new RuntimeException('Enable at least one referral benefit.');
            }
            if ($itemEnabled && $itemPercent <= 0) {
                throw new RuntimeException('Product discount percent must be greater than 0.');
            }
            if ($shipEnabled && $shipAmount <= 0) {
                throw new RuntimeException('Shipping discount amount must be greater than 0.');
            }
            if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) {
                throw new RuntimeException('End date harus setelah start date.');
            }
            if ($startsAt !== null && ($id <= 0 || $startsAt !== $currentStartsAt) && strtotime($startsAt) < shop_ref_min_start_ts()) {
                throw new RuntimeException('Start date minimal 1 menit dari waktu sekarang.');
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE tr_referrals SET ref_name = :name, ref_code = :code, ref_is_enabled = :enabled,
                     ref_max_usage_total = :max_usage, ref_used_count = :used,
                     ref_starts_at = :starts_at, ref_ends_at = :ends_at WHERE ref_id = :id'
                )->execute([':id' => $id, ':name' => $name, ':code' => $code,
                    ':enabled' => $isEnabled, ':max_usage' => $maxUsage, ':used' => $usedCount,
                    ':starts_at' => $startsAt, ':ends_at' => $endsAt]);
            } else {
                $pdo->prepare(
                    'INSERT INTO tr_referrals (ref_name, ref_code, ref_is_enabled, ref_max_usage_total, ref_used_count, ref_starts_at, ref_ends_at)
                     VALUES (:name, :code, :enabled, :max_usage, :used, :starts_at, :ends_at)'
                )->execute([':name' => $name, ':code' => $code,
                    ':enabled' => $isEnabled, ':max_usage' => $maxUsage, ':used' => $usedCount,
                    ':starts_at' => $startsAt, ':ends_at' => $endsAt]);
                $id = (int) $pdo->lastInsertId();
            }

            $pdo->prepare(
                'INSERT INTO tr_referral_benefits
                    (rfb_ref_id, rfb_item_discount_type, rfb_item_discount_value, rfb_item_discount_max_amount,
                     rfb_shipping_discount_type, rfb_shipping_discount_value)
                 VALUES (:ref_id, :item_type, :item_val, :item_cap, :ship_type, :ship_val)
                 ON DUPLICATE KEY UPDATE
                    rfb_item_discount_type = VALUES(rfb_item_discount_type),
                    rfb_item_discount_value = VALUES(rfb_item_discount_value),
                    rfb_item_discount_max_amount = VALUES(rfb_item_discount_max_amount),
                    rfb_shipping_discount_type = VALUES(rfb_shipping_discount_type),
                    rfb_shipping_discount_value = VALUES(rfb_shipping_discount_value)'
            )->execute([
                ':ref_id'   => $id,
                ':item_type' => $itemEnabled ? 'percent' : 'none',
                ':item_val'  => $itemPercent,
                ':item_cap'  => $itemEnabled && $itemCap > 0 ? $itemCap : null,
                ':ship_type' => $shipEnabled ? 'fixed' : 'none',
                ':ship_val'  => $shipAmount,
            ]);

            $pdo->commit();
            shop_ref_redirect('Referral saved.', '', $redirectExtra);
        }

        if ($action === 'delete') {
            $id = max(0, (int) ($_POST['id'] ?? 0));
            if ($id <= 0) {
                throw new RuntimeException('Referral tidak ditemukan.');
            }
            $pdo->prepare('DELETE FROM tr_referrals WHERE ref_id = :id')->execute([':id' => $id]);
            shop_ref_redirect('Referral deleted.', '', $redirectExtra);
        }

        shop_ref_redirect('', 'Unknown action.');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $id = max(0, (int) ($_POST['id'] ?? 0));
        shop_ref_redirect('', $e->getMessage(), array_merge($redirectExtra, [
            'modal'       => $id > 0 ? 'edit' : 'create',
            'referral_id' => $id > 0 ? $id : '',
        ]));
    }
}

$message      = trim((string) ($_GET['message'] ?? ''));
$error        = trim((string) ($_GET['error'] ?? ''));
$modal        = trim((string) ($_GET['modal'] ?? ''));
$modalRefId   = max(0, (int) ($_GET['referral_id'] ?? 0));
$filterQuery  = trim((string) ($_GET['q'] ?? ''));
$filterStatus = trim((string) ($_GET['status'] ?? ''));
$perPage      = 25;
$pageNum      = max(1, (int) ($_GET['p'] ?? 1));

$referrals      = [];
$totalReferrals = 0;
$activeReferrals = 0;
$usageLogTotal  = 0;
$totalPages     = 1;
$usageLogs      = [];

if ($pdo !== null) {
    try {
        $refWhere  = [];
        $refParams = [];

        if ($filterQuery !== '') {
            $refWhere[]             = '(r.ref_name LIKE :q_name OR r.ref_code LIKE :q_code)';
            $refParams[':q_name']   = '%' . $filterQuery . '%';
            $refParams[':q_code']   = '%' . $filterQuery . '%';
        }
        if ($filterStatus === 'enabled') {
            $refWhere[] = 'r.ref_is_enabled = 1 AND (r.ref_starts_at IS NULL OR r.ref_starts_at <= NOW()) AND (r.ref_ends_at IS NULL OR r.ref_ends_at >= NOW())';
        } elseif ($filterStatus === 'scheduled') {
            $refWhere[] = 'r.ref_is_enabled = 1 AND r.ref_starts_at IS NOT NULL AND r.ref_starts_at > NOW()';
        } elseif ($filterStatus === 'expired') {
            $refWhere[] = 'r.ref_is_enabled = 1 AND r.ref_ends_at IS NOT NULL AND r.ref_ends_at < NOW()';
        } elseif ($filterStatus === 'disabled') {
            $refWhere[] = 'r.ref_is_enabled = 0';
        }

        $whereSql = $refWhere ? (' WHERE ' . implode(' AND ', $refWhere)) : '';

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM tr_referrals r $whereSql");
        foreach ($refParams as $k => $v) {
            $cntStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $cntStmt->execute();
        $totalReferrals = (int) $cntStmt->fetchColumn();
        $totalPages     = $totalReferrals > 0 ? (int) ceil($totalReferrals / $perPage) : 1;
        if ($pageNum > $totalPages) {
            $pageNum = $totalPages;
        }
        $offset = ($pageNum - 1) * $perPage;

        $actWhere   = array_merge($refWhere, ['r.ref_is_enabled = 1', '(r.ref_starts_at IS NULL OR r.ref_starts_at <= NOW())', '(r.ref_ends_at IS NULL OR r.ref_ends_at >= NOW())']);
        $actSql     = 'SELECT COUNT(*) FROM tr_referrals r WHERE ' . implode(' AND ', $actWhere);
        $actStmt    = $pdo->prepare($actSql);
        foreach ($refParams as $k => $v) {
            $actStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $actStmt->execute();
        $activeReferrals = (int) $actStmt->fetchColumn();

        $usageLogTotal = (int) $pdo->query('SELECT COUNT(*) FROM tr_referral_usages')->fetchColumn();

        $listStmt = $pdo->prepare("
            SELECT r.ref_id AS id, r.ref_name AS name, r.ref_code AS code,
                   r.ref_is_enabled AS is_enabled, r.ref_max_usage_total AS max_usage_total,
                   r.ref_used_count AS used_count, r.ref_created_at AS created_at,
                   r.ref_starts_at AS starts_at, r.ref_ends_at AS ends_at,
                   COALESCE(b.rfb_item_discount_type, 'none') AS item_discount_type,
                   COALESCE(b.rfb_item_discount_value, 0) AS item_discount_value,
                   b.rfb_item_discount_max_amount AS item_discount_max_amount,
                   COALESCE(b.rfb_shipping_discount_type, 'none') AS shipping_discount_type,
                   COALESCE(b.rfb_shipping_discount_value, 0) AS shipping_discount_value,
                   COALESCE(logs.total_logs, 0) AS total_logs,
                   COALESCE(logs.used_logs, 0) AS used_logs
            FROM tr_referrals r
            LEFT JOIN tr_referral_benefits b ON b.rfb_ref_id = r.ref_id
            LEFT JOIN (
                SELECT rfu_ref_id AS referral_id, COUNT(*) AS total_logs,
                       SUM(CASE WHEN rfu_status = 'used' THEN 1 ELSE 0 END) AS used_logs
                FROM tr_referral_usages GROUP BY rfu_ref_id
            ) logs ON logs.referral_id = r.ref_id
            $whereSql
            ORDER BY r.ref_created_at DESC, r.ref_id DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($refParams as $k => $v) {
            $listStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        $referrals = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $usageLogs = $pdo->query("
            SELECT u.rfu_ref_id AS referral_id,
                   COALESCE(u.rfu_referral_code_snapshot, '') AS referral_code_snapshot,
                   COALESCE(u.rfu_ord_code, '') AS order_id,
                   COALESCE(u.rfu_customer_name, '') AS customer_name,
                   COALESCE(u.rfu_customer_email, '') AS customer_email,
                   COALESCE(u.rfu_discount_item_amount, 0) AS discount_item_amount,
                   COALESCE(u.rfu_discount_shipping_amount, 0) AS discount_shipping_amount,
                   COALESCE(u.rfu_discount_total_amount, 0) AS discount_total_amount,
                   COALESCE(u.rfu_status, '') AS status,
                   u.rfu_used_at AS used_at,
                   u.rfu_created_at AS created_at,
                   COALESCE(r.ref_name, '') AS referral_name
            FROM tr_referral_usages u
            LEFT JOIN tr_referrals r ON r.ref_id = u.rfu_ref_id
            ORDER BY u.rfu_created_at DESC, u.rfu_id DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $dbError = 'Gagal memuat data referral: ' . $e->getMessage();
    }
}

$paginationExtra = array_filter(['q' => $filterQuery, 'status' => $filterStatus], static fn ($v) => $v !== '');

$emptyReferral = [
    'id' => 0, 'name' => '', 'code' => '', 'is_enabled' => 1,
    'max_usage_total' => 0, 'used_count' => 0,
    'starts_at' => shop_ref_auto_dt_input(10), 'ends_at' => shop_ref_auto_dt_input(20),
    'item_discount_type' => 'none', 'item_discount_value' => '0.00',
    'item_discount_max_amount' => '', 'shipping_discount_type' => 'none', 'shipping_discount_value' => '0',
];

$statusUsageBadge = static function (string $status): string {
    return match (strtolower($status)) {
        'used'    => 'bg-success-lt text-success',
        'pending' => 'bg-warning-lt text-warning',
        default   => 'bg-secondary-lt text-secondary',
    };
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>Shop Referrals - BRIX</title>
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
                            <h1 class="page-title">Referral Admin</h1>
                        </div>
                    </div>
                </div>
            </div>

            <main class="page-body">
                <div class="container-xl">

                    <?php if ($message !== ''): ?>
                        <div class="alert alert-success alert-dismissible mb-4" role="alert">
                            <?= shop_ref_esc($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible mb-4" role="alert">
                            <?= shop_ref_esc($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($dbError !== null): ?>
                        <div class="alert alert-warning mb-4" role="alert">
                            <?= shop_ref_esc($dbError) ?> &mdash; Lihat <code>.dev/shop-tables.sql</code>.
                        </div>
                    <?php endif; ?>

                    <div class="row row-deck row-cards mb-4">
                        <div class="col-sm-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Total Referrals</div>
                                    <div class="h1 mb-0"><?= number_format($totalReferrals, 0, ',', '.') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Active Referrals</div>
                                    <div class="h1 mb-0 text-success"><?= number_format($activeReferrals, 0, ',', '.') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Usage Logs</div>
                                    <div class="h1 mb-0 text-primary"><?= number_format($usageLogTotal, 0, ',', '.') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <form class="row g-2 align-items-end" method="get" action="/shop/referrals">
                                <div class="col-sm">
                                    <label class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></span>
                                        <input type="text" class="form-control" name="q" placeholder="Cari nama atau kode referral..." value="<?= shop_ref_esc($filterQuery) ?>">
                                    </div>
                                </div>
                                <div class="col-sm-auto">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <option value="enabled" <?= $filterStatus === 'enabled' ? 'selected' : '' ?>>Enabled</option>
                                        <option value="scheduled" <?= $filterStatus === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                        <option value="expired" <?= $filterStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                                        <option value="disabled" <?= $filterStatus === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-primary" type="submit">Filter</button>
                                    <?php if ($filterQuery !== '' || $filterStatus !== ''): ?>
                                        <a class="btn btn-outline-secondary ms-1" href="/shop/referrals">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card" id="referral-list">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title m-0">Referral List</h3>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#modal-ref-create">
                                    + Create Referral
                                </button>
                                <?php if (!empty($usageLogs)): ?>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#modal-ref-logs">
                                        View Logs
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Referral</th>
                                        <th>Benefits</th>
                                        <th>Usage</th>
                                        <th>Status</th>
                                        <th class="w-1">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($referrals)): ?>
                                        <tr>
                                            <td colspan="6" class="text-secondary text-center py-3">No referrals found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($referrals as $ref): ?>
                                            <?php $refId = (int) $ref['id']; ?>
                                            <tr>
                                                <td><strong>#<?= $refId ?></strong></td>
                                                <td>
                                                    <div class="fw-medium"><?= shop_ref_esc($ref['name']) ?></div>
                                                    <div class="text-secondary small"><code><?= shop_ref_esc($ref['code']) ?></code></div>
                                                </td>
                                                <td class="text-secondary small"><?= shop_ref_esc(shop_ref_benefit_summary($ref)) ?></td>
                                                <td>
                                                    <div class="text-secondary small">Max: <?= (int) $ref['max_usage_total'] ?></div>
                                                    <div class="text-secondary small">Used: <?= (int) $ref['used_count'] ?></div>
                                                    <div class="text-secondary small">Logs: <?= (int) $ref['used_logs'] ?>/<?= (int) $ref['total_logs'] ?></div>
                                                    <div class="text-secondary small">Ends: <?= shop_ref_esc($ref['ends_at'] ?: '-') ?></div>
                                                </td>
                                                <td>
                                                    <?= shop_ref_status_badge($ref) ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modal-rview-<?= $refId ?>">View</button>
                                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-redit-<?= $refId ?>">Edit</button>
                                                        <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#modal-rdel-<?= $refId ?>">Del</button>
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
                                    Showing <?= $totalReferrals > 0 ? (($pageNum - 1) * $perPage + 1) : 0 ?>
                                    &ndash; <?= min($pageNum * $perPage, $totalReferrals) ?>
                                    of <?= $totalReferrals ?>
                                </p>
                                <?= shop_ref_pagination($pageNum, $totalPages, $paginationExtra) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <?php
    function shop_ref_form(array $ref, string $submitLabel, int $pageNum, string $filterQuery, string $filterStatus): string {
        $isCreate = (int) ($ref['id'] ?? 0) <= 0;
        $minStart = shop_ref_dt_input(date('Y-m-d H:i:s', shop_ref_min_start_ts()));
        $startValue = shop_ref_dt_input($ref['starts_at'] ?? '');
        $endMin = $startValue !== '' ? $startValue : ($isCreate ? $minStart : '');
        ob_start();
        ?>
        <form method="post" action="/shop/referrals" <?= $isCreate ? 'data-referral-create-form="1"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= shop_ref_esc(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($ref['id'] ?? 0) ?>">
            <input type="hidden" name="q" value="<?= shop_ref_esc($filterQuery) ?>">
            <input type="hidden" name="status" value="<?= shop_ref_esc($filterStatus) ?>">
            <input type="hidden" name="p" value="<?= (int) $pageNum ?>">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" type="text" name="name" value="<?= shop_ref_esc($ref['name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Referral Code <small class="text-secondary">(akan di-uppercase otomatis)</small></label>
                    <input class="form-control" type="text" name="code" value="<?= shop_ref_esc($ref['code'] ?? '') ?>" required style="text-transform:uppercase">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label">Max Usage Total</label>
                        <input class="form-control" type="number" min="0" name="max_usage_total" value="<?= (int) ($ref['max_usage_total'] ?? 0) ?>">
                    </div>
                    <div class="col">
                        <label class="form-label">Used Count</label>
                        <input class="form-control" type="number" min="0" name="used_count" value="<?= (int) ($ref['used_count'] ?? 0) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" <?= !empty($ref['is_enabled']) ? 'checked' : '' ?>>
                        <span class="form-check-label"><strong>Referral aktif</strong></span>
                    </label>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label">Starts At</label>
                        <input class="form-control" type="datetime-local" name="starts_at" value="<?= shop_ref_esc($startValue) ?>" <?= $isCreate ? 'min="' . shop_ref_esc($minStart) . '"' : '' ?>>
                    </div>
                    <div class="col">
                        <label class="form-label">Ends At</label>
                        <input class="form-control" type="datetime-local" name="ends_at" value="<?= shop_ref_esc(shop_ref_dt_input($ref['ends_at'] ?? '')) ?>" <?= $endMin !== '' ? 'min="' . shop_ref_esc($endMin) . '"' : '' ?>>
                    </div>
                </div>

                <div class="card card-soft-dark border-0 mb-3">
                    <div class="card-body">
                        <h5 class="card-title mb-2">Product Benefit</h5>
                        <div class="mb-2">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="item_benefit_enabled" value="1" <?= ($ref['item_discount_type'] ?? 'none') !== 'none' ? 'checked' : '' ?>>
                                <span class="form-check-label">Enable product discount</span>
                            </label>
                        </div>
                        <div class="row g-2">
                            <div class="col">
                                <label class="form-label">Discount Percent (%)</label>
                                <input class="form-control" type="number" step="0.01" min="0" name="item_discount_value" value="<?= shop_ref_esc((string) ($ref['item_discount_value'] ?? '0.00')) ?>">
                            </div>
                            <div class="col">
                                <label class="form-label">Max Discount Amount</label>
                                <input class="form-control" type="number" min="0" name="item_discount_max_amount" value="<?= shop_ref_esc((string) ($ref['item_discount_max_amount'] ?? '')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-soft-dark border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-2">Shipping Benefit</h5>
                        <div class="mb-2">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="shipping_benefit_enabled" value="1" <?= ($ref['shipping_discount_type'] ?? 'none') !== 'none' ? 'checked' : '' ?>>
                                <span class="form-check-label">Enable shipping discount</span>
                            </label>
                        </div>
                        <div>
                            <label class="form-label">Shipping Discount Amount (IDR)</label>
                            <input class="form-control" type="number" min="0" name="shipping_discount_value" value="<?= shop_ref_esc((string) ($ref['shipping_discount_value'] ?? '0')) ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" type="submit"><?= shop_ref_esc($submitLabel) ?></button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            </div>
        </form>
        <?php
        return (string) ob_get_clean();
    }
    ?>

    <div class="modal modal-blur fade" id="modal-ref-create" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Referral</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <?= shop_ref_form($emptyReferral, 'Save Referral', $pageNum, $filterQuery, $filterStatus) ?>
            </div>
        </div>
    </div>

    <?php if (!empty($usageLogs)): ?>
        <div class="modal modal-blur fade" id="modal-ref-logs" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Recent Usage Logs</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Referral</th>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Discount</th>
                                        <th>Status</th>
                                        <th>Used At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usageLogs as $usage): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-medium"><?= shop_ref_esc($usage['referral_name'] ?: '-') ?></div>
                                                <div class="text-secondary small"><code><?= shop_ref_esc($usage['referral_code_snapshot']) ?></code></div>
                                            </td>
                                            <td class="text-secondary small"><?= shop_ref_esc($usage['order_id'] ?: '-') ?></td>
                                            <td>
                                                <div class="fw-medium"><?= shop_ref_esc($usage['customer_name'] ?: '-') ?></div>
                                                <div class="text-secondary small"><?= shop_ref_esc($usage['customer_email'] ?: '-') ?></div>
                                            </td>
                                            <td class="text-secondary small">
                                                <div>Item: IDR<?= number_format((int) $usage['discount_item_amount'], 0, ',', '.') ?></div>
                                                <div>Ship: IDR<?= number_format((int) $usage['discount_shipping_amount'], 0, ',', '.') ?></div>
                                                <div class="fw-medium">Total: IDR<?= number_format((int) $usage['discount_total_amount'], 0, ',', '.') ?></div>
                                            </td>
                                            <td><span class="badge <?= $statusUsageBadge((string) $usage['status']) ?>"><?= shop_ref_esc($usage['status']) ?></span></td>
                                            <td class="text-secondary small"><?= shop_ref_esc($usage['used_at'] ?: '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
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
    <?php endif; ?>

    <?php foreach ($referrals as $ref): ?>
        <?php $refId = (int) $ref['id']; ?>

        <div class="modal modal-blur fade" id="modal-rview-<?= $refId ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Referral Detail — <code><?= shop_ref_esc($ref['code']) ?></code></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <div class="subheader small">Name</div>
                                <div class="fw-medium"><?= shop_ref_esc($ref['name']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="subheader small">Code</div>
                                <code><?= shop_ref_esc($ref['code']) ?></code>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="subheader small">Benefits</div>
                            <div><?= shop_ref_esc(shop_ref_benefit_summary($ref)) ?></div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <div class="subheader small">Starts At</div>
                                <div class="text-secondary"><?= shop_ref_esc($ref['starts_at'] ?: '-') ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="subheader small">Ends At</div>
                                <div class="text-secondary"><?= shop_ref_esc($ref['ends_at'] ?: '-') ?></div>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="card card-soft-dark border-0">
                                    <div class="card-body py-2 px-3 text-center">
                                        <div class="text-secondary small">Max</div>
                                        <div class="fw-medium"><?= number_format((int) $ref['max_usage_total'], 0, ',', '.') ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card card-soft-dark border-0">
                                    <div class="card-body py-2 px-3 text-center">
                                        <div class="text-secondary small">Used</div>
                                        <div class="fw-medium"><?= number_format((int) $ref['used_count'], 0, ',', '.') ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card card-soft-dark border-0">
                                    <div class="card-body py-2 px-3 text-center">
                                        <div class="text-secondary small">Logs</div>
                                        <div class="fw-medium"><?= (int) $ref['used_logs'] ?>/<?= (int) $ref['total_logs'] ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal modal-blur fade" id="modal-redit-<?= $refId ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Referral — <code><?= shop_ref_esc($ref['code']) ?></code></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <?= shop_ref_form($ref, 'Update Referral', $pageNum, $filterQuery, $filterStatus) ?>
                </div>
            </div>
        </div>

        <div class="modal modal-blur fade" id="modal-rdel-<?= $refId ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Referral</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <strong><?= shop_ref_esc($ref['name']) ?></strong> — <code><?= shop_ref_esc($ref['code']) ?></code><br>
                            <span class="text-secondary small"><?= shop_ref_esc(shop_ref_benefit_summary($ref)) ?></span>
                        </div>
                        <p>Yakin ingin menghapus referral ini? Histori penggunaan juga akan terhapus.</p>
                    </div>
                    <div class="modal-footer">
                        <form method="post" action="/shop/referrals" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= shop_ref_esc(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $refId ?>">
                            <input type="hidden" name="q" value="<?= shop_ref_esc($filterQuery) ?>">
                            <input type="hidden" name="status" value="<?= shop_ref_esc($filterStatus) ?>">
                            <input type="hidden" name="p" value="<?= (int) $pageNum ?>">
                            <button class="btn btn-danger" type="submit">Delete</button>
                        </form>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </div>
            </div>
        </div>

    <?php endforeach; ?>

    <script src="/assets/dist/js/tabler.js"></script>
    <script src="/assets/js/idle-timeout.js" defer></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function floorToMinute(date) {
            return new Date(date.getFullYear(), date.getMonth(), date.getDate(), date.getHours(), date.getMinutes(), 0, 0);
        }

        function addMinutes(date, minutes) {
            return new Date(date.getTime() + (minutes * 60000));
        }

        function formatDateTimeLocal(date) {
            var pad = function (value) { return String(value).padStart(2, '0'); };
            return date.getFullYear() + '-' +
                pad(date.getMonth() + 1) + '-' +
                pad(date.getDate()) + 'T' +
                pad(date.getHours()) + ':' +
                pad(date.getMinutes());
        }

        function syncEndMin(form) {
            var startsAt = form.querySelector('input[name="starts_at"]');
            var endsAt = form.querySelector('input[name="ends_at"]');
            if (!startsAt || !endsAt) {
                return;
            }
            endsAt.min = startsAt.value || startsAt.min || '';
            if (startsAt.value && endsAt.value && endsAt.value < startsAt.value) {
                endsAt.value = startsAt.value;
            }
        }

        document.querySelectorAll('form[action="/shop/referrals"]').forEach(function (form) {
            var startsAt = form.querySelector('input[name="starts_at"]');
            if (startsAt) {
                startsAt.addEventListener('input', function () { syncEndMin(form); });
                syncEndMin(form);
            }
        });

        var createModal = document.getElementById('modal-ref-create');
        if (createModal) {
            createModal.addEventListener('show.bs.modal', function () {
                var form = createModal.querySelector('form[data-referral-create-form="1"]');
                if (!form) {
                    return;
                }
                var base = floorToMinute(new Date());
                var minStart = formatDateTimeLocal(addMinutes(base, 1));
                var defaultStart = formatDateTimeLocal(addMinutes(base, 10));
                var defaultEnd = formatDateTimeLocal(addMinutes(base, 20));
                var startsAt = form.querySelector('input[name="starts_at"]');
                var endsAt = form.querySelector('input[name="ends_at"]');
                if (startsAt) {
                    startsAt.min = minStart;
                    startsAt.value = defaultStart;
                }
                if (endsAt) {
                    endsAt.min = defaultStart;
                    endsAt.value = defaultEnd;
                }
            });
        }
    });
    </script>
    <?php if ($modal !== ''): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var selector = <?php
                if ($modal === 'create') {
                    echo json_encode('#modal-ref-create');
                } elseif ($modal === 'edit' && $modalRefId > 0) {
                    echo json_encode('#modal-redit-' . $modalRefId);
                } else {
                    echo 'null';
                }
            ?>;
            if (selector) {
                var el = document.querySelector(selector);
                if (el) { new bootstrap.Modal(el).show(); }
            }
        });
        </script>
    <?php endif; ?>
</body>
</html>
