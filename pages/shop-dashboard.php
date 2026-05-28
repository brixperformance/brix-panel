<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';
require_once dirname(__DIR__) . '/application/configs/shop_pdo.php';
require_once dirname(__DIR__) . '/application/models/ProductPricing.php';

bootstrap_page(null, false);

if (empty($_SESSION['logged_in'])) {
    header('Location: /login', true, 302);
    exit;
}

function shop_esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$dbError = null;
$orderSummary    = [];
$referralSummary = [];
$productSummary  = [];
$pricingSummary  = ['active_rules' => 0, 'scheduled_rules' => 0];
$recentOrders    = [];

try {
    $pdo = get_shop_pdo();

    $orderSummary = $pdo->query("
        SELECT
            COUNT(*) AS total_orders,
            SUM(CASE WHEN ord_status IN ('PENDING', 'UNPAID') THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN ord_status = 'PAID' THEN 1 ELSE 0 END) AS paid_orders
        FROM tr_orders
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $referralSummary = $pdo->query("
        SELECT
            COUNT(*) AS total_referrals,
            SUM(CASE WHEN ref_is_enabled = 1 THEN 1 ELSE 0 END) AS active_referrals
        FROM tr_referrals
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $productSummary = $pdo->query("
        SELECT COUNT(*) AS total_products
        FROM ms_products
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    if (product_pricing_table_exists($pdo)) {
        $pricingSummary = $pdo->query("
            SELECT
                SUM(CASE WHEN mprr_status = 'active' THEN 1 ELSE 0 END) AS active_rules,
                SUM(CASE WHEN mprr_status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled_rules
            FROM ms_product_pricing_rules
        ")->fetch(PDO::FETCH_ASSOC) ?: $pricingSummary;
    }

    $recentOrders = $pdo->query("
        SELECT
            ord_code AS order_id,
            ord_customer_name AS customer_name,
            ord_status AS status,
            COALESCE(ord_payable_amount, 0) AS payable_amount,
            ord_created_at AS created_at
        FROM tr_orders
        ORDER BY ord_created_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $dbError = 'Database tidak tersedia. Pastikan tabel shop sudah dibuat.';
}

$statusBadgeClass = static function (string $status): string {
    return match (strtoupper($status)) {
        'PAID'           => 'bg-success-lt text-success',
        'PENDING', 'UNPAID' => 'bg-warning-lt text-warning',
        'EXPIRED', 'FAILED', 'CANCELED' => 'bg-danger-lt text-danger',
        'REFUNDED'       => 'bg-secondary-lt text-secondary',
        default          => 'bg-secondary-lt text-secondary',
    };
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>Shop Dashboard - BRIX</title>
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
                            <h1 class="page-title">Shop Dashboard</h1>
                        </div>
                        <div class="col-auto ms-auto">
                            <div class="d-flex gap-2">
                                <a href="/shop/orders" class="btn btn-outline-primary btn-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2z" /><path d="M9 7l6 0" /><path d="M9 11l6 0" /></svg>
                                    Orders
                                </a>
                                <a href="/shop/pricing" class="btn btn-outline-secondary btn-sm">Pricing</a>
                                <a href="/shop/referrals" class="btn btn-outline-secondary btn-sm">Referrals</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <main class="page-body">
                <div class="container-xl">

                    <?php if ($dbError !== null): ?>
                        <div class="alert alert-warning mb-4" role="alert">
                            <div class="d-flex">
                                <div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4" /><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.871l-8.106 -13.534a1.914 1.914 0 0 0 -3.274 0z" /><path d="M12 16h.01" /></svg>
                                    <?= shop_esc($dbError) ?>
                                    &mdash; Lihat <code>.dev/shop-tables.sql</code> untuk membuat tabel yang dibutuhkan.
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row row-deck row-cards mb-4">
                        <div class="col-sm-6 col-lg-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="subheader">Total Products</div>
                                    </div>
                                    <div class="h1 mb-3"><?= number_format((int) ($productSummary['total_products'] ?? 0)) ?></div>
                                    <div class="text-secondary small">Produk aktif di katalog store.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Pending Orders</div>
                                    <div class="h1 mb-3 text-warning"><?= number_format((int) ($orderSummary['pending_orders'] ?? 0)) ?></div>
                                    <div class="text-secondary small">Dari total <?= number_format((int) ($orderSummary['total_orders'] ?? 0)) ?> order tercatat.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Active Referrals</div>
                                    <div class="h1 mb-3 text-success"><?= number_format((int) ($referralSummary['active_referrals'] ?? 0)) ?></div>
                                    <div class="text-secondary small">Dari <?= number_format((int) ($referralSummary['total_referrals'] ?? 0)) ?> referral aktif & nonaktif.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Live Pricing Rules</div>
                                    <div class="h1 mb-3 text-primary"><?= number_format((int) ($pricingSummary['active_rules'] ?? 0)) ?></div>
                                    <div class="text-secondary small"><?= number_format((int) ($pricingSummary['scheduled_rules'] ?? 0)) ?> rule lain masih terjadwal.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row row-cards">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Recent Orders</h3>
                                    <div class="card-options">
                                        <a href="/shop/orders" class="btn btn-sm btn-outline-secondary">View All</a>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-vcenter card-table">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Customer</th>
                                                <th>Status</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recentOrders)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-secondary text-center py-3">Belum ada order.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recentOrders as $order): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-medium"><?= shop_esc($order['order_id']) ?></div>
                                                            <div class="text-secondary small"><?= shop_esc($order['created_at']) ?></div>
                                                        </td>
                                                        <td><?= shop_esc($order['customer_name'] ?: '-') ?></td>
                                                        <td>
                                                            <span class="badge <?= $statusBadgeClass((string) $order['status']) ?>">
                                                                <?= shop_esc($order['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end">IDR<?= number_format((int) $order['payable_amount'], 0, ',', '.') ?>,-</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Quick Access</h3>
                                </div>
                                <div class="list-group list-group-flush">
                                    <a href="/shop/orders" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                                        <span class="avatar avatar-sm bg-primary-lt text-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2z" /><path d="M9 7l6 0" /><path d="M9 11l6 0" /></svg>
                                        </span>
                                        <div class="flex-fill">
                                            <div class="fw-medium">Orders</div>
                                            <div class="text-secondary small">Pantau transaksi dan invoice.</div>
                                        </div>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon text-secondary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6l-6 6" /></svg>
                                    </a>
                                    <a href="/shop/pricing" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                                        <span class="avatar avatar-sm bg-warning-lt text-warning">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7h-.01" /><path d="M4 4l16 16" /><path d="M10.5 10.5a3.5 3.5 0 0 1 4.5 -4.5" /><path d="M10 3h4" /><path d="M21 10v4" /><path d="M3 10v4" /><path d="M10 21h4" /></svg>
                                        </span>
                                        <div class="flex-fill">
                                            <div class="fw-medium">Pricing</div>
                                            <div class="text-secondary small">Atur promo dan harga coret.</div>
                                        </div>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon text-secondary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6l-6 6" /></svg>
                                    </a>
                                    <a href="/shop/referrals" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                                        <span class="avatar avatar-sm bg-success-lt text-success">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h2" /><path d="M19 16l-2 3h4l-2 3" /></svg>
                                        </span>
                                        <div class="flex-fill">
                                            <div class="fw-medium">Referrals</div>
                                            <div class="text-secondary small">Kelola kode referral dan benefit.</div>
                                        </div>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon text-secondary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6l-6 6" /></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <script src="/assets/dist/js/tabler.js"></script>
    <script src="/assets/js/idle-timeout.js" defer></script>
</body>
</html>
