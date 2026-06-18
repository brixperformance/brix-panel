<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';
require_once dirname(__DIR__) . '/application/configs/shop_pdo.php';

bootstrap_page(null, false);

if (empty($_SESSION['logged_in'])) {
    header('Location: /login', true, 302);
    exit;
}

function shop_orders_esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function shop_orders_fetch_grouped(PDO $pdo, string $sql, array $ids, string $keyField): array
{
    if (empty($ids)) {
        return [];
    }

    $placeholders = [];
    $params       = [];

    foreach (array_values($ids) as $index => $id) {
        $key              = ':id_' . $index;
        $placeholders[]   = $key;
        $params[$key]     = $id;
    }

    $stmt = $pdo->prepare(str_replace('__IDS__', implode(', ', $placeholders), $sql));
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $grouped = [];

    foreach ($rows as $row) {
        $grouped[(string) ($row[$keyField] ?? '')][] = $row;
    }

    return $grouped;
}

$statusOptions = ['PENDING', 'PAID', 'EXPIRED', 'FAILED', 'UNPAID', 'CANCELED', 'REFUNDED'];
$statusFilter  = trim((string) ($_GET['status'] ?? ''));
$query         = trim((string) ($_GET['q'] ?? ''));
$perPage       = 25;
$pageNum       = max(1, (int) ($_GET['p'] ?? 1));

$dbError      = null;
$orders       = [];
$totalOrders  = 0;
$paidOrders   = 0;
$openOrders   = 0;
$grossRevenue = 0;
$totalPages   = 1;
$itemsByOrder     = [];
$paymentsByOrder  = [];

$statusBadgeClass = static function (string $status): string {
    return match (strtoupper($status)) {
        'PAID'           => 'bg-success-lt text-success',
        'PENDING', 'UNPAID' => 'bg-warning-lt text-warning',
        'EXPIRED', 'FAILED', 'CANCELED' => 'bg-danger-lt text-danger',
        'REFUNDED'       => 'bg-secondary-lt text-secondary',
        default          => 'bg-secondary-lt text-secondary',
    };
};

try {
    $pdo = get_shop_pdo();

    $where  = [];
    $params = [];

    if ($statusFilter !== '') {
        $where[]          = 'ord_status = :status';
        $params[':status'] = $statusFilter;
    }

    if ($query !== '') {
        $where[]            = '(ord_code LIKE :q_code OR ord_customer_name LIKE :q_name OR ord_customer_email LIKE :q_email)';
        $params[':q_code']  = '%' . $query . '%';
        $params[':q_name']  = '%' . $query . '%';
        $params[':q_email'] = '%' . $query . '%';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tr_orders $whereSql");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalOrders = (int) $countStmt->fetchColumn();
    $totalPages  = $totalOrders > 0 ? (int) ceil($totalOrders / $perPage) : 1;
    if ($pageNum > $totalPages) {
        $pageNum = $totalPages;
    }
    $offset = ($pageNum - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT
            ord_code AS order_id,
            COALESCE(ord_payable_amount, 0) AS payable_amount,
            COALESCE(ord_subtotal_amount, 0) AS subtotal_amount,
            COALESCE(ord_product_discount_total_amount, 0) AS product_discount_total_amount,
            COALESCE(ord_referral_discount_total_amount, 0) AS referral_discount_total_amount,
            COALESCE(ord_shipping_discount_total_amount, 0) AS shipping_discount_total_amount,
            COALESCE(ord_shipping_amount, 0) AS shipping_amount,
            ord_status AS status,
            ord_created_at AS created_at,
            ord_updated_at AS updated_at,
            COALESCE(ord_customer_name, '') AS customer_name,
            COALESCE(ord_customer_email, '') AS customer_email,
            COALESCE(ord_customer_phone, '') AS customer_phone,
            COALESCE(ord_shipping_address, '') AS shipping_address,
            COALESCE(ord_shipping_city, '') AS shipping_city,
            COALESCE(ord_shipping_postal, '') AS shipping_postal
        FROM tr_orders
        $whereSql
        ORDER BY ord_created_at DESC, ord_id DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $orderIds = array_values(array_filter(
        array_map(static fn (array $o): string => (string) ($o['order_id'] ?? ''), $orders),
        static fn (string $v): bool => $v !== ''
    ));

    $itemsByOrder = shop_orders_fetch_grouped(
        $pdo,
        "SELECT
            ori_ord_code AS order_id,
            ori_name AS name,
            COALESCE(ori_price, 0) AS price,
            COALESCE(ori_original_price, 0) AS original_price,
            COALESCE(ori_discount_amount, 0) AS discount_amount,
            COALESCE(ori_discount_label, '') AS discount_label,
            COALESCE(ori_final_price, 0) AS final_price,
            COALESCE(ori_quantity, 1) AS quantity,
            COALESCE(ori_line_total, 0) AS line_total
         FROM tr_order_items
         WHERE ori_ord_code IN (__IDS__)
         ORDER BY ori_id ASC",
        $orderIds,
        'order_id'
    );

    $paymentsByOrder = shop_orders_fetch_grouped(
        $pdo,
        "SELECT
            pay_ord_code AS order_id,
            COALESCE(pay_snap_token, '') AS snap_token,
            COALESCE(pay_transaction_status, '') AS transaction_status,
            COALESCE(pay_payment_type, '') AS payment_type,
            pay_created_at AS created_at
         FROM tr_payments
         WHERE pay_ord_code IN (__IDS__)
         ORDER BY pay_created_at DESC, pay_id DESC",
        $orderIds,
        'order_id'
    );

    $paidStmt = $pdo->prepare("SELECT COUNT(*) FROM tr_orders $whereSql" . ($whereSql ? ' AND' : ' WHERE') . " ord_status = 'PAID'");
    foreach ($params as $key => $value) {
        $paidStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $paidStmt->execute();
    $paidOrders = (int) $paidStmt->fetchColumn();

    $openStmt = $pdo->prepare("SELECT COUNT(*) FROM tr_orders $whereSql" . ($whereSql ? ' AND' : ' WHERE') . " ord_status IN ('PENDING', 'UNPAID')");
    foreach ($params as $key => $value) {
        $openStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $openStmt->execute();
    $openOrders = (int) $openStmt->fetchColumn();

    $revStmt = $pdo->prepare("SELECT COALESCE(SUM(ord_payable_amount), 0) FROM tr_orders $whereSql");
    foreach ($params as $key => $value) {
        $revStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $revStmt->execute();
    $grossRevenue = (int) $revStmt->fetchColumn();
} catch (PDOException $e) {
    $dbError = 'Database tidak tersedia. Pastikan tabel shop sudah dibuat.';
}

function shop_orders_pagination(int $current, int $total, array $extra = []): string
{
    if ($total <= 1) {
        return '';
    }

    $queryBase = $extra ? ('&' . http_build_query($extra)) : '';
    $html      = '<ul class="pagination m-0">';
    $prev      = $current - 1;
    $next      = $current + 1;

    $html .= '<li class="page-item' . ($current <= 1 ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="?p=' . $prev . $queryBase . '">&laquo;</a></li>';

    $start = max(1, $current - 2);
    $end   = min($total, $current + 2);

    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item' . ($i === $current ? ' active' : '') . '">';
        $html .= '<a class="page-link" href="?p=' . $i . $queryBase . '">' . $i . '</a></li>';
    }

    $html .= '<li class="page-item' . ($current >= $total ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="?p=' . $next . $queryBase . '">&raquo;</a></li>';
    $html .= '</ul>';

    return $html;
}

$paginationExtra = [];
if ($query !== '') {
    $paginationExtra['q'] = $query;
}
if ($statusFilter !== '') {
    $paginationExtra['status'] = $statusFilter;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>Shop Orders - BRIX</title>
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
    <link href="/assets/css/invoice-generator.css" rel="stylesheet"/>
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
                            <h1 class="page-title">Orders</h1>
                        </div>
                    </div>
                </div>
            </div>

            <main class="page-body">
                <div class="container-xl">

                    <?php if ($dbError !== null): ?>
                        <div class="alert alert-warning mb-4" role="alert">
                            <?= shop_orders_esc($dbError) ?>
                            &mdash; Lihat <code>.dev/shop-tables.sql</code>.
                        </div>
                    <?php endif; ?>

                    <div class="row row-deck row-cards mb-4">
                        <div class="col-sm-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Total Orders</div>
                                    <div class="h1 mb-0"><?= number_format($totalOrders, 0, ',', '.') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Paid Orders</div>
                                    <div class="h1 mb-0 text-success"><?= number_format($paidOrders, 0, ',', '.') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="subheader">Grand Total</div>
                                    <div class="h1 mb-0">IDR<?= number_format($grossRevenue, 0, ',', '.') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <form class="row g-2 align-items-end" method="get" action="/shop/orders">
                                <div class="col-sm">
                                    <label class="form-label">Search</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                        </span>
                                        <input type="text" class="form-control" name="q" placeholder="Order ID, customer, email..." value="<?= shop_orders_esc($query) ?>">
                                    </div>
                                </div>
                                <div class="col-sm-auto">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <?php foreach ($statusOptions as $opt): ?>
                                            <option value="<?= shop_orders_esc($opt) ?>" <?= $statusFilter === $opt ? 'selected' : '' ?>>
                                                <?= shop_orders_esc($opt) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-primary" type="submit">Filter</button>
                                    <?php if ($query !== '' || $statusFilter !== ''): ?>
                                        <a class="btn btn-outline-secondary ms-1" href="/shop/orders">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card" id="orders-list">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title m-0">Orders List</h3>
                            <?php if ($openOrders > 0): ?>
                                <span class="badge bg-warning-lt text-warning"><?= number_format($openOrders, 0, ',', '.') ?> open checkout</span>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th class="w-1">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="6" class="text-secondary text-center py-3">No orders found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-medium"><?= shop_orders_esc($order['order_id']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-medium"><?= shop_orders_esc($order['customer_name'] ?: '-') ?></div>
                                                    <div class="text-secondary small"><?= shop_orders_esc($order['customer_email'] ?: '-') ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-medium">IDR<?= number_format((int) $order['payable_amount'], 0, ',', '.') ?>,-</div>
                                                    <div class="text-secondary small">Sub IDR<?= number_format((int) $order['subtotal_amount'], 0, ',', '.') ?>,-</div>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $statusBadgeClass((string) $order['status']) ?>">
                                                        <?= shop_orders_esc($order['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div><?= shop_orders_esc($order['created_at']) ?></div>
                                                    <div class="text-secondary small">Upd: <?= shop_orders_esc($order['updated_at']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <button type="button"
                                                            class="btn btn-sm btn-primary"
                                                            data-shop-order-preview
                                                            data-order-id="<?= shop_orders_esc($order['order_id']) ?>">
                                                            Lihat Invoice
                                                        </button>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modal-order-<?= shop_orders_esc($order['order_id']) ?>">
                                                            Detail
                                                        </button>
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
                                    Showing <?= $totalOrders > 0 ? (($pageNum - 1) * $perPage + 1) : 0 ?>
                                    &ndash; <?= min($pageNum * $perPage, $totalOrders) ?>
                                    of <?= $totalOrders ?>
                                </p>
                                <?= shop_orders_pagination($pageNum, $totalPages, $paginationExtra) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <div class="modal modal-blur fade" id="modal-shop-order-preview" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header d-block">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="modal-title mb-0">Shop Invoice Preview</h5>
                        <button type="button" class="btn btn-sm btn-danger" data-bs-dismiss="modal">Close</button>
                    </div>
                    <div class="text-muted mt-1" id="modal-shop-preview-subtitle" style="font-size:.85rem;"></div>
                </div>
                <div class="modal-body">
                    <iframe id="shop-order-preview-frame" src="about:blank" title="Shop Invoice Preview"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="btn-shop-order-download">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                            <path d="M7 11l5 5l5 -5" />
                            <path d="M12 4l0 12" />
                        </svg>
                        Print / Save PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php foreach ($orders as $order): ?>
        <?php
        $orderId    = (string) ($order['order_id'] ?? '');
        $payment    = $paymentsByOrder[$orderId][0] ?? null;
        $orderItems = $itemsByOrder[$orderId] ?? [];
        $totalDiscount = (int) ($order['product_discount_total_amount'] ?? 0)
            + (int) ($order['referral_discount_total_amount'] ?? 0)
            + (int) ($order['shipping_discount_total_amount'] ?? 0);
        ?>
        <div class="modal modal-blur fade" id="modal-order-<?= shop_orders_esc($orderId) ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Order Detail — <?= shop_orders_esc($orderId) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                        <div class="row g-3 mb-3">
                            <div class="col-sm-4">
                                <div class="subheader mb-1">Customer</div>
                                <div class="fw-medium"><?= shop_orders_esc($order['customer_name'] ?: '-') ?></div>
                                <div class="text-secondary small"><?= shop_orders_esc($order['customer_email'] ?: '-') ?></div>
                                <div class="text-secondary small"><?= shop_orders_esc($order['customer_phone'] ?: '-') ?></div>
                            </div>
                            <div class="col-sm-4">
                                <div class="subheader mb-1">Shipping</div>
                                <div class="text-secondary small"><?= shop_orders_esc($order['shipping_address'] ?: '-') ?></div>
                                <div class="text-secondary small"><?= shop_orders_esc($order['shipping_city'] ?: '-') ?> <?= shop_orders_esc($order['shipping_postal'] ?: '') ?></div>
                            </div>
                            <div class="col-sm-4">
                                <div class="subheader mb-1">Payment</div>
                                <div class="text-secondary small">Status: <?= shop_orders_esc($payment['transaction_status'] ?? '-') ?></div>
                                <div class="text-secondary small">Type: <?= shop_orders_esc($payment['payment_type'] ?? '-') ?></div>
                                <div class="text-secondary small">At: <?= shop_orders_esc($payment['created_at'] ?? '-') ?></div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <div class="card card-soft-dark border-0">
                                    <div class="card-body py-2 px-3 text-center">
                                        <div class="text-secondary small">Subtotal</div>
                                        <div class="fw-medium">IDR<?= number_format((int) ($order['subtotal_amount'] ?? 0), 0, ',', '.') ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card card-soft-dark border-0">
                                    <div class="card-body py-2 px-3 text-center">
                                        <div class="text-secondary small">Discount</div>
                                        <div class="fw-medium text-danger">-IDR<?= number_format($totalDiscount, 0, ',', '.') ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card card-soft-dark border-0">
                                    <div class="card-body py-2 px-3 text-center">
                                        <div class="text-secondary small">Grand Total</div>
                                        <div class="fw-medium text-primary">IDR<?= number_format((int) ($order['payable_amount'] ?? 0), 0, ',', '.') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Harga</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orderItems)): ?>
                                        <tr><td colspan="4" class="text-secondary">No items found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($orderItems as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-medium"><?= shop_orders_esc($item['name'] ?: '-') ?></div>
                                                    <?php if (!empty($item['discount_label'])): ?>
                                                        <div class="text-secondary small"><?= shop_orders_esc($item['discount_label']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ((int) $item['original_price'] > (int) $item['final_price'] && (int) $item['original_price'] > 0): ?>
                                                        <del class="text-secondary small">IDR<?= number_format((int) $item['original_price'], 0, ',', '.') ?></del><br>
                                                    <?php endif; ?>
                                                    <span class="fw-medium">IDR<?= number_format((int) $item['final_price'], 0, ',', '.') ?></span>
                                                    <?php if ((int) $item['discount_amount'] > 0): ?>
                                                        <div class="text-secondary small">-IDR<?= number_format((int) $item['discount_amount'], 0, ',', '.') ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?= shop_orders_esc((string) $item['quantity']) ?></td>
                                                <td class="text-end">IDR<?= number_format((int) $item['line_total'], 0, ',', '.') ?></td>
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
    <?php endforeach; ?>

    <script src="/assets/dist/js/tabler.js"></script>
    <script>
        (function () {
            const previewModalEl = document.getElementById('modal-shop-order-preview');
            const previewFrame = document.getElementById('shop-order-preview-frame');
            const previewButtons = document.querySelectorAll('[data-shop-order-preview]');
            const modalSubtitle = document.getElementById('modal-shop-preview-subtitle');
            const downloadButton = document.getElementById('btn-shop-order-download');
            const ModalCtor = (typeof tabler !== 'undefined' && tabler.Modal)
                ? tabler.Modal
                : ((typeof bootstrap !== 'undefined' && bootstrap.Modal) ? bootstrap.Modal : null);

            if (!previewModalEl || !previewFrame || !previewButtons.length || !ModalCtor) {
                return;
            }

            const previewModal = new ModalCtor(previewModalEl);
            let currentOrderId = '';

            function openPreviewModal(orderId) {
                currentOrderId = orderId;
                if (modalSubtitle) {
                    modalSubtitle.textContent = orderId ? '#' + orderId : '';
                }
                previewFrame.src = '/shop/orders/preview?order_id=' + encodeURIComponent(orderId) + '&nobar=1';
                previewModal.show();
            }

            previewButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    openPreviewModal(button.getAttribute('data-order-id') || '');
                });
            });

            previewFrame.addEventListener('load', function () {
                try {
                    const doc = previewFrame.contentDocument || previewFrame.contentWindow.document;
                    if (doc && doc.documentElement) {
                        previewFrame.style.height = doc.documentElement.scrollHeight + 'px';
                    }
                } catch (error) {
                }
            });

            previewModalEl.addEventListener('shown.bs.modal', function () {
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;z-index:10400;';
                }
            });

            previewModalEl.addEventListener('hidden.bs.modal', function () {
                previewFrame.src = 'about:blank';
                previewFrame.style.height = '0';
                currentOrderId = '';
                if (modalSubtitle) {
                    modalSubtitle.textContent = '';
                }
            });

            downloadButton?.addEventListener('click', function () {
                if (!currentOrderId || !previewFrame.contentWindow) {
                    return;
                }
                previewFrame.contentWindow.postMessage({ type: 'downloadInvoicePdf' }, '*');
            });
        })();
    </script>
    <script src="/assets/js/idle-timeout.js" defer></script>
</body>
</html>
