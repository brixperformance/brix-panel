<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';
require_once dirname(__DIR__) . '/application/configs/shop_pdo.php';

bootstrap_page(null, false);

$noBar = isset($_GET['nobar']);
$autoDownload = isset($_GET['autodownload']);
$orderId = trim((string) ($_GET['order_id'] ?? ''));

if ($orderId === '' || !preg_match('/^INV-\d{8}-\d{6}-\d{4}$/', $orderId)) {
    http_response_code(400);
    echo 'Invalid order_id';
    exit;
}

function shop_order_preview_format_idr(float $value): string
{
    return 'IDR ' . number_format($value, 2, '.', ',');
}

function shop_order_preview_split_lines(array $parts): string
{
    $lines = [];

    foreach ($parts as $part) {
        $text = trim((string) $part);
        if ($text !== '') {
            $lines[] = $text;
        }
    }

    return implode("\n", $lines);
}

function shop_order_preview_is_adjustment(array $item): bool
{
    $itemCode = strtolower(trim((string) ($item['item_id'] ?? '')));
    return in_array($itemCode, ['shipping', 'referral'], true);
}

try {
    $pdo = get_shop_pdo();
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Store database is currently unavailable.';
    exit;
}

$orderStmt = $pdo->prepare('
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
        COALESCE(ord_customer_name, "") AS customer_name,
        COALESCE(ord_customer_email, "") AS customer_email,
        COALESCE(ord_customer_phone, "") AS customer_phone,
        COALESCE(ord_shipping_address, "") AS shipping_address,
        COALESCE(ord_shipping_city, "") AS shipping_city,
        COALESCE(ord_shipping_postal, "") AS shipping_postal
    FROM tr_orders
    WHERE ord_code = :order_id
    LIMIT 1
');
$orderStmt->execute([':order_id' => $orderId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    echo 'Order not found';
    exit;
}

$paymentStmt = $pdo->prepare('
    SELECT
        COALESCE(pay_transaction_status, "") AS transaction_status,
        COALESCE(pay_payment_type, "") AS payment_type
    FROM tr_payments
    WHERE pay_ord_code = :order_id
    ORDER BY pay_created_at DESC, pay_id DESC
    LIMIT 1
');
$paymentStmt->execute([':order_id' => $orderId]);
$payment = $paymentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$itemsStmt = $pdo->prepare('
    SELECT
        COALESCE(ori_item_code, "") AS item_id,
        COALESCE(ori_name, "") AS name,
        COALESCE(ori_price, 0) AS price,
        COALESCE(ori_final_price, 0) AS final_price,
        COALESCE(ori_quantity, 0) AS quantity,
        COALESCE(ori_line_total, 0) AS line_total
    FROM tr_order_items
    WHERE ori_ord_code = :order_id
    ORDER BY ori_id ASC
');
$itemsStmt->execute([':order_id' => $orderId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$rows = [];
foreach ($items as $item) {
    if (shop_order_preview_is_adjustment($item)) {
        continue;
    }

    $rate = (float) ($item['final_price'] ?: $item['price']);
    $rows[] = [
        'item' => (string) ($item['name'] ?? ''),
        'quantity' => (float) ($item['quantity'] ?? 0),
        'rate' => $rate,
        'amount' => (float) ($item['line_total'] ?? 0),
    ];
}

if ($rows === [] && $items !== []) {
    foreach ($items as $item) {
        $rate = (float) ($item['final_price'] ?: $item['price']);
        $rows[] = [
            'item' => (string) ($item['name'] ?? ''),
            'quantity' => (float) ($item['quantity'] ?? 0),
            'rate' => $rate,
            'amount' => (float) ($item['line_total'] ?? 0),
        ];
    }
}

$customerName = trim((string) ($order['customer_name'] ?? ''));
$customerEmail = trim((string) ($order['customer_email'] ?? ''));
$customerPhone = trim((string) ($order['customer_phone'] ?? ''));
$shippingAddress = trim((string) ($order['shipping_address'] ?? ''));
$shippingCity = trim((string) ($order['shipping_city'] ?? ''));
$shippingPostal = trim((string) ($order['shipping_postal'] ?? ''));

$billToLines = preg_split('/\r\n|\r|\n/', shop_order_preview_split_lines([
    $customerName,
    $customerPhone,
    $customerEmail,
    $shippingAddress,
    trim($shippingCity . ' ' . $shippingPostal),
])) ?: [];
$billToDisplay = trim((string) ($billToLines[0] ?? $orderId));

$subtotal = (float) ($order['subtotal_amount'] ?? 0);
$discountCost = (float) ($order['product_discount_total_amount'] ?? 0)
    + (float) ($order['referral_discount_total_amount'] ?? 0)
    + (float) ($order['shipping_discount_total_amount'] ?? 0);
$shippingCost = (float) ($order['shipping_amount'] ?? 0);
$total = (float) ($order['payable_amount'] ?? 0);

$invoiceNumber = $orderId;
$createdTimestamp = strtotime((string) ($order['created_at'] ?? 'now')) ?: time();
$invoiceDateDisplay = date('d/m/Y', $createdTimestamp);
$invoiceTimeDisplay = date('h:i:s A', $createdTimestamp);
$issuedByName = 'BRIX Performance';
$issuedByPhone = '+62 897-9754-254';
$issuedByEmail = 'brixperformance@gmail.com';
$fixedFooterNote = 'This invoice is automatically generated and serves as the official proof of purchase for this transaction.';
$fixedFooterSubNote = 'System generated document. No signature required.';
$pdfFileName = 'Shop Invoice - ' . preg_replace('/[^\w\- ]+/u', '', $invoiceNumber) . '.pdf';

$ITEMS_PER_PAGE = 5;
$pages = array_chunk($rows, $ITEMS_PER_PAGE);
if ($pages === []) {
    $pages = [[]];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Invoice - <?= htmlspecialchars($invoiceNumber, ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="/assets/css/invoice-preview.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" defer></script>
</head>
<body>
    <?php if (!$noBar): ?>
    <div class="invoice-toolbar">
        <button type="button" class="invoice-download-button" id="download-invoice-pdf">Download PDF</button>
    </div>
    <?php endif; ?>

    <main class="invoice-page">
        <div class="invoice-scale-wrap" id="invoice-scale-wrap">
            <div class="invoice-pages" id="invoice-pages">
                <?php foreach ($pages as $pageIndex => $pageRows): ?>
                    <?php $isFirst = $pageIndex === 0; ?>
                    <section class="invoice-sheet" id="<?= $isFirst ? 'invoice-sheet' : 'invoice-sheet-' . $pageIndex ?>">
                        <div class="inv-topbar">
                            <div class="inv-topbar-left">
                                <img src="/assets/images/logos/logo-brix-blue.jpg" alt="BRIX Performance">
                            </div>
                            <div class="inv-head-meta">
                                <h1 class="inv-title">INVOICE</h1>
                                <div class="inv-details">
                                    <div class="inv-details-line">
                                        <span>Invoice ID :</span>
                                        <strong>#<?= htmlspecialchars($invoiceNumber, ENT_QUOTES) ?></strong>
                                    </div>
                                    <div class="inv-details-line">
                                        <span>Invoice Date :</span>
                                        <div class="inv-details-stack">
                                            <strong><?= htmlspecialchars($invoiceDateDisplay, ENT_QUOTES) ?></strong>
                                            <strong><?= htmlspecialchars($invoiceTimeDisplay, ENT_QUOTES) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($isFirst): ?>
                        <div class="inv-addr-row">
                            <div class="inv-addr">
                                <span class="inv-addr-label">BILLED TO</span>
                                <strong class="inv-addr-name"><?= htmlspecialchars($billToDisplay, ENT_QUOTES) ?></strong>
                                <?php foreach (array_slice($billToLines, 1) as $line): ?>
                                    <?php if (trim((string) $line) !== ''): ?>
                                    <span class="inv-addr-value"><?= htmlspecialchars((string) $line, ENT_QUOTES) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="inv-addr">
                                <span class="inv-addr-label">ISSUED BY</span>
                                <strong class="inv-addr-name"><?= htmlspecialchars($issuedByName, ENT_QUOTES) ?></strong>
                                <span class="inv-addr-value"><?= htmlspecialchars($issuedByPhone, ENT_QUOTES) ?></span>
                                <span class="inv-addr-value"><?= htmlspecialchars($issuedByEmail, ENT_QUOTES) ?></span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="inv-continued-label">Continued from previous page</div>
                        <?php endif; ?>

                        <table class="inv-table">
                            <thead>
                                <tr>
                                    <th>PRODUCT</th>
                                    <th>PRICE</th>
                                    <th>QTY</th>
                                    <th>TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pageRows as $row): ?>
                                    <?php
                                    $itemParts = explode(' - ', (string) $row['item'], 2);
                                    $itemBrand = $itemParts[0];
                                    $itemDetail = preg_replace('/\s+-$/', '', trim((string) ($itemParts[1] ?? '')));
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="inv-item-brand"><?= htmlspecialchars($itemBrand, ENT_QUOTES) ?></span>
                                            <span class="inv-item-detail"><?= htmlspecialchars($itemDetail !== '' ? $itemDetail : $itemBrand, ENT_QUOTES) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars(shop_order_preview_format_idr((float) $row['rate']), ENT_QUOTES) ?></td>
                                        <td><?= htmlspecialchars(rtrim(rtrim(number_format((float) $row['quantity'], 2, '.', ','), '0'), '.'), ENT_QUOTES) ?></td>
                                        <td><?= htmlspecialchars(shop_order_preview_format_idr((float) $row['amount']), ENT_QUOTES) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="inv-summary-wrap">
                            <div class="inv-summary-row">
                                <span>Subtotal</span>
                                <span><?= htmlspecialchars(shop_order_preview_format_idr($subtotal), ENT_QUOTES) ?></span>
                            </div>
                            <div class="inv-summary-row">
                                <span>Shipping</span>
                                <span><?= htmlspecialchars($shippingCost > 0 ? shop_order_preview_format_idr($shippingCost) : 'IDR 0.00', ENT_QUOTES) ?></span>
                            </div>
                            <div class="inv-summary-row">
                                <span>Discount</span>
                                <span><?= htmlspecialchars($discountCost > 0 ? '-' . shop_order_preview_format_idr($discountCost) : 'IDR 0.00', ENT_QUOTES) ?></span>
                            </div>
                            <div class="inv-summary-row inv-summary-row--grand">
                                <span>TOTAL</span>
                                <span><?= htmlspecialchars(shop_order_preview_format_idr($total), ENT_QUOTES) ?></span>
                            </div>
                        </div>

                        <div class="inv-footer-spacer"></div>
                        <footer class="inv-footer">
                            <div class="inv-footer-left">
                                <p class="inv-footer-tagline">Track Proven. Daily Confidence</p>
                                <p class="inv-footer-link">www.brix-performance.com</p>
                            </div>
                            <div class="inv-footer-right">
                                <p class="inv-footer-note"><?= htmlspecialchars($fixedFooterNote, ENT_QUOTES) ?></p>
                                <p class="inv-footer-note"><?= htmlspecialchars($fixedFooterSubNote, ENT_QUOTES) ?></p>
                                <div class="inv-footer-signature">
                                    <span class="inv-footer-signature-rule"></span>
                                    <span class="inv-footer-signature-name">BRIX Performance</span>
                                </div>
                            </div>
                        </footer>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        (function () {
            const downloadButton = document.getElementById('download-invoice-pdf');
            const invoicePage = document.querySelector('.invoice-page');
            const scaleWrap = document.getElementById('invoice-scale-wrap');
            const invoicePages = document.getElementById('invoice-pages');
            const filename = <?= json_encode($pdfFileName, JSON_UNESCAPED_SLASHES) ?>;

            function fitInvoiceForMobile() {
                if (!invoicePage || !invoicePages || !scaleWrap) return;

                scaleWrap.style.width = '';
                scaleWrap.style.height = '';
                invoicePages.style.transform = '';
                invoicePage.style.minHeight = '0';

                if (window.innerWidth > 768) return;

                const pageStyles = window.getComputedStyle(invoicePage);
                const horizontalPadding =
                    parseFloat(pageStyles.paddingLeft || '0') +
                    parseFloat(pageStyles.paddingRight || '0');
                const availableWidth = Math.max(invoicePage.clientWidth - horizontalPadding, 0);
                const pagesWidth = invoicePages.offsetWidth;

                if (!availableWidth || !pagesWidth) return;

                const scale = Math.min(1, availableWidth / pagesWidth);
                scaleWrap.style.width = (pagesWidth * scale) + 'px';
                scaleWrap.style.height = (invoicePages.offsetHeight * scale) + 'px';
                invoicePages.style.transform = 'scale(' + scale + ')';
                invoicePages.style.transformOrigin = 'top left';
                invoicePage.style.minHeight = (invoicePages.offsetHeight * scale) + 'px';
            }

            async function downloadPdf() {
                if (typeof window.html2pdf === 'undefined') {
                    window.alert('PDF library failed to load.');
                    return;
                }
                if (downloadButton) {
                    downloadButton.disabled = true;
                    downloadButton.textContent = 'Generating PDF...';
                }
                try {
                    const sheets = Array.from(document.querySelectorAll('.invoice-sheet'));
                    const PX_PER_MM = 96 / 25.4;
                    let worker = window.html2pdf();

                    if (sheets.length === 1) {
                        const pageHeightMM = Math.max(297, Math.ceil(sheets[0].scrollHeight / PX_PER_MM));
                        await worker
                            .set({
                                filename,
                                margin: 0,
                                image: { type: 'jpeg', quality: 0.98 },
                                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
                                jsPDF: { unit: 'mm', format: [210, pageHeightMM], orientation: 'portrait' }
                            })
                            .from(sheets[0])
                            .save();
                    } else {
                        const html2canvasLib = window.html2canvas;
                        const jsPdfCtor = window.jspdf && window.jspdf.jsPDF;

                        if (!html2canvasLib || !jsPdfCtor) {
                            window.alert('PDF renderer is unavailable.');
                            return;
                        }

                        const pdf = new jsPdfCtor({
                            unit: 'mm',
                            format: 'a4',
                            orientation: 'portrait',
                        });

                        for (let index = 0; index < sheets.length; index += 1) {
                            const sheet = sheets[index];
                            const canvas = await html2canvasLib(sheet, {
                                scale: 2,
                                useCORS: true,
                                backgroundColor: '#ffffff',
                            });
                            const imageData = canvas.toDataURL('image/jpeg', 0.98);

                            if (index > 0) {
                                pdf.addPage('a4', 'portrait');
                            }

                            pdf.addImage(imageData, 'JPEG', 0, 0, 210, 297, undefined, 'FAST');
                        }

                        pdf.save(filename);
                    }
                } finally {
                    if (downloadButton) {
                        downloadButton.disabled = false;
                        downloadButton.textContent = 'Download PDF';
                    }
                }
            }

            downloadButton?.addEventListener('click', downloadPdf);
            window.addEventListener('message', function (event) {
                if (event.data?.type === 'downloadInvoicePdf') {
                    downloadPdf();
                }
            });

            <?php if ($autoDownload): ?>
            window.addEventListener('load', function () {
                setTimeout(downloadPdf, 800);
            });
            <?php endif; ?>

            fitInvoiceForMobile();
            window.addEventListener('load', fitInvoiceForMobile);
            window.addEventListener('resize', fitInvoiceForMobile);
        })();
    </script>
</body>
</html>
