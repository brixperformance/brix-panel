<?php
require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';

bootstrap_page();

require_once dirname(__DIR__) . '/application/configs/invoice_footer_defaults.php';

$noBar       = isset($_GET['nobar']);
$autoDownload = isset($_GET['autodownload']);

// --- helpers ---
function parse_currency_to_float($value): float
{
    $normalized = preg_replace('/[^\d.]/', '', (string) $value);
    return ($normalized === '' || $normalized === null) ? 0.0 : (float) $normalized;
}

function parse_quantity($value): float
{
    return ($value === null || $value === '') ? 0.0 : (float) $value;
}

function format_idr(float $value): string
{
    return 'IDR ' . number_format($value, 2, '.', ',');
}

function parse_invoice_items_payload($raw): array
{
    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return ['items' => [], 'meta' => []];
    }

    if (isset($decoded['items']) && is_array($decoded['items'])) {
        return [
            'items' => $decoded['items'],
            'meta' => isset($decoded['meta']) && is_array($decoded['meta']) ? $decoded['meta'] : [],
        ];
    }

    return ['items' => $decoded, 'meta' => []];
}

function build_invoice_items_payload(array $items, array $meta = []): string
{
    $cleanMeta = array_filter($meta, static function ($value) {
        return $value !== null && $value !== '';
    });

    if ($cleanMeta === []) {
        return json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    return json_encode([
        'items' => $items,
        'meta' => $cleanMeta,
    ], JSON_UNESCAPED_UNICODE);
}

function normalize_footer_text(?string $value, string $fallback): string
{
    $text = trim((string) $value);
    return $text !== '' ? $text : $fallback;
}

function get_total_item_quantity(array $rows): float
{
    $total = 0.0;
    foreach ($rows as $row) {
        $total += (float) ($row['quantity'] ?? 0);
    }

    return $total;
}

function get_shipping_weight_kg_label(array $rows): string
{
    $totalQuantity = get_total_item_quantity($rows);
    $weightKg = max(1, (int) ceil($totalQuantity / 3));
    return $weightKg . ' kg';
}

// --- route: GET /invoice-log/preview?log_id=X  (re-preview dari log) ---
$isLogPreview = $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['log_id']);

if (!$isLogPreview && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /invoice-generator');
    exit;
}

$logId = 0;
$logPersistError = '';
$footerDefaults = get_invoice_footer_defaults();
$footerNotesHeader    = (string) ($footerDefaults['notes_header'] ?? 'Notes');
$footerNotes          = (string) ($footerDefaults['notes'] ?? '');
$footerClosingHeader  = (string) ($footerDefaults['closing_header'] ?? '');
$footerClosingMessage = (string) ($footerDefaults['closing_message'] ?? '');

// =========================================================
//  BRANCH A — re-preview dari log
// =========================================================
if ($isLogPreview) {
    require_once dirname(__DIR__) . '/application/models/Execute.php';
    require_once dirname(__DIR__) . '/application/models/log-invoice/View.php';
    $config  = require dirname(__DIR__) . '/application/configs/database.php';
    $logView = new InvoiceLogView($config);

    $logId  = (int)($_GET['log_id'] ?? 0);
    if ($logId <= 0) {
        http_response_code(400);
        echo 'Invalid log ID.';
        exit;
    }

    $logResult = $logView->getLogById($logId);
    $logRow    = $logResult['data'] ?? null;
    if (!$logRow) {
        http_response_code(404);
        echo 'Invoice not found.';
        exit;
    }

    $billTo        = (string)$logRow['linv_bill_to'];
    $shipTo        = (string)$logRow['linv_ship_to'];
    $discountCost   = (float)($logRow['linv_discount']        ?? 0);
    $discountType   = (string)($logRow['linv_discount_type']  ?? 'flat');
    $discountValue  = (float)($logRow['linv_discount_value']  ?? 0);
    $discountMax    = (float)($logRow['linv_discount_max']    ?? 0);
    $shippingCost   = (float)$logRow['linv_shipping'];
    $additionalFee  = (float)($logRow['linv_additional_fee'] ?? 0);
    $subtotal       = (float)$logRow['linv_subtotal'];
    $total          = (float)$logRow['linv_total'];
    $itemsPayload   = parse_invoice_items_payload((string) ($logRow['linv_items_json'] ?? ''));
    $rows           = $itemsPayload['items'];
    $additionalFeeLabel = trim((string) ($logRow['linv_additional_fee_label'] ?? ''));
    if ($additionalFeeLabel === '') {
        $additionalFeeLabel = trim((string) ($itemsPayload['meta']['additional_fee_label'] ?? ''));
    }
    $footerNotesHeader = normalize_footer_text(
        (string) ($itemsPayload['meta']['footer_notes_header'] ?? ''),
        $footerNotesHeader
    );
    $footerNotes = normalize_footer_text(
        isset($logRow['linv_footer_notes']) ? (string) $logRow['linv_footer_notes'] : (
            isset($itemsPayload['meta']['footer_notes']) ? (string) $itemsPayload['meta']['footer_notes'] : ''
        ),
        $footerNotes
    );
    $footerClosingHeader = normalize_footer_text(
        (string) ($itemsPayload['meta']['footer_closing_header'] ?? ''),
        $footerClosingHeader
    );
    $footerClosingMessage = normalize_footer_text(
        isset($logRow['linv_footer_closing_message']) ? (string) $logRow['linv_footer_closing_message'] : (
            isset($itemsPayload['meta']['footer_closing_message']) ? (string) $itemsPayload['meta']['footer_closing_message'] : ''
        ),
        $footerClosingMessage
    );
    $invoiceNumber = (string)$logRow['linv_number'];
    $invoiceDate   = date('F j, Y', strtotime((string)$logRow['linv_create_date']));
    $invoiceDueDate = !empty($logRow['linv_due_date']) && $logRow['linv_due_date'] !== '0000-00-00'
        ? date('F j, Y', strtotime((string)$logRow['linv_due_date']))
        : $invoiceDate;

// =========================================================
//  BRANCH B — POST baru: generate number, simpan ke log
// =========================================================
} else {
    require_once dirname(__DIR__) . '/application/models/Execute.php';
    require_once dirname(__DIR__) . '/application/models/log-invoice/Transaction.php';
    $config = require dirname(__DIR__) . '/application/configs/database.php';
    $logTx  = new InvoiceLogTransaction($config);

    $invoiceType   = trim((string)($_POST['invoice_type']  ?? 'dealer'));
    $dealerCode    = trim((string)($_POST['dealer_code']   ?? ''));
    $billTo        = trim((string)($_POST['bill_to']       ?? ''));
    $shipTo        = trim((string)($_POST['ship_to']       ?? ''));
    $discountType  = in_array(trim((string)($_POST['discount_type'] ?? '')), ['percent', 'flat'], true)
                         ? trim((string)$_POST['discount_type']) : 'flat';
    $discountValue = (float)($_POST['discount_value'] ?? 0);
    $discountMax   = (float)($_POST['discount_max']   ?? 0);
    $shippingCost   = parse_currency_to_float($_POST['shipping_cost']   ?? 0);
    $additionalFee  = parse_currency_to_float($_POST['additional_fee'] ?? 0);
    $additionalFeeLabel = trim((string) ($_POST['additional_fee_label'] ?? ''));
    $footerNotesHeader = normalize_footer_text(
        isset($_POST['footer_notes_header']) ? (string) $_POST['footer_notes_header'] : '',
        $footerNotesHeader
    );
    $footerNotes = normalize_footer_text(
        isset($_POST['footer_notes']) ? (string) $_POST['footer_notes'] : '',
        $footerNotes
    );
    $footerClosingHeader = normalize_footer_text(
        isset($_POST['footer_closing_header']) ? (string) $_POST['footer_closing_header'] : '',
        $footerClosingHeader
    );
    $footerClosingMessage = normalize_footer_text(
        isset($_POST['footer_closing_message']) ? (string) $_POST['footer_closing_message'] : '',
        $footerClosingMessage
    );
    $footerValidationErrors = validate_invoice_footer_content($footerNotes, $footerClosingMessage);
    if ($footerValidationErrors !== []) {
        http_response_code(422);
        echo htmlspecialchars(implode(' ', $footerValidationErrors), ENT_QUOTES);
        exit;
    }
    $dueDateRaw     = trim((string)($_POST['due_date'] ?? ''));
    $invoiceDueDate = ($dueDateRaw !== '' && strtotime($dueDateRaw) !== false)
        ? date('F j, Y', strtotime($dueDateRaw))
        : date('F j, Y', strtotime('+3 days'));

    $items      = $_POST['item']     ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $rates      = $_POST['rate']     ?? [];
    $rows       = [];
    $subtotal   = 0.0;

    foreach ($items as $i => $itemName) {
        $name     = trim((string)$itemName);
        $quantity = parse_quantity($quantities[$i] ?? 0);
        $rate     = parse_currency_to_float($rates[$i] ?? 0);

        if ($name === '' || $quantity <= 0 || $rate < 0) {
            continue;
        }

        $amount    = $quantity * $rate;
        $subtotal += $amount;
        $rows[]    = ['item' => $name, 'quantity' => $quantity, 'rate' => $rate, 'amount' => $amount];
    }

    $billToLines   = preg_split('/\r\n|\r|\n/', $billTo) ?: [];
    $billToDisplay = trim((string)($billToLines[0] ?? $billTo));

    if ($billToDisplay === '' || $shipTo === '' || empty($rows)) {
        http_response_code(422);
        echo 'Invalid invoice data.';
        exit;
    }

    if ($discountType === 'percent') {
        $discountCost = $subtotal * ($discountValue / 100);
        if ($discountMax > 0) {
            $discountCost = min($discountCost, $discountMax);
        }
    } else {
        $discountCost = $discountValue;
    }

    $total = $subtotal - $discountCost + $shippingCost + $additionalFee;

    $invoiceNumber = $logTx->generateInvoiceNumber($invoiceType, $dealerCode);

    $insertResult = $logTx->insertLog([
        'number'         => $invoiceNumber,
        'type'           => $invoiceType,
        'dealer_code'    => $dealerCode ?: null,
        'bill_to'        => $billTo,
        'ship_to'        => $shipTo,
        'subtotal'       => $subtotal,
        'discount'       => $discountCost,
        'discount_type'  => $discountType,
        'discount_value' => $discountValue,
        'discount_max'   => $discountMax,
        'shipping'        => $shippingCost,
        'additional_fee'  => $additionalFee,
        'additional_fee_label' => $additionalFeeLabel,
        'footer_notes_header' => $footerNotesHeader,
        'footer_notes' => $footerNotes,
        'footer_closing_header' => $footerClosingHeader,
        'footer_closing_message' => $footerClosingMessage,
        'due_date'        => $dueDateRaw !== '' ? $dueDateRaw : date('Y-m-d', strtotime('+3 days')),
        'total'           => $total,
        'items_json'      => build_invoice_items_payload($rows),
    ]);

    if (!($insertResult['status'] ?? false)) {
        $logPersistError = (string) ($insertResult['message'] ?? 'Failed to save invoice log.');
    } else {
        $logId = $logTx->getLogIdByNumber($invoiceNumber);
        if ($logId <= 0) {
            $logPersistError = 'Invoice preview rendered, but the saved log entry could not be reloaded.';
        }
    }
    $invoiceDate = date('F j, Y');
}

$shippingWeightLabel = get_shipping_weight_kg_label($rows);

// --- discount label ---
if ($discountType === 'percent') {
    $pctStr       = rtrim(rtrim(number_format($discountValue, 2, '.', ''), '0'), '.');
    $discountLabel = 'Discount (' . $pctStr . '%';
    if ($discountMax > 0) {
        $discountLabel .= ', max ' . format_idr($discountMax);
    }
    $discountLabel .= ')';
} else {
    $discountLabel = 'Discount';
}

// --- shared render variables ---
$billToLines   = preg_split('/\r\n|\r|\n/', $billTo) ?: [];
$billToDisplay = trim((string)($billToLines[0] ?? $billTo));
$pdfFileName   = 'Invoice Brill - ' . preg_replace('/[^\w\- ]+/u', '', $billToDisplay) . '.pdf';
$footerNotesParagraphs   = split_invoice_footer_paragraphs($footerNotes);
$footerClosingParagraphs = split_invoice_footer_paragraphs($footerClosingMessage);
$footerNotesHeaderSafe   = htmlspecialchars($footerNotesHeader, ENT_QUOTES);
$footerClosingHeaderSafe = htmlspecialchars($footerClosingHeader, ENT_QUOTES);
?>
<?php
$ITEMS_PER_PAGE = 4;
$pages          = array_chunk($rows, $ITEMS_PER_PAGE);
if (empty($pages)) {
    $pages = [[]];
}
$totalPages  = count($pages);
$globalIndex = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Brill - <?= htmlspecialchars($billTo, ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="/assets/css/invoice-preview.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" defer></script>
</head>
<body>
    <?php if (!$noBar): ?>
    <div class="invoice-toolbar">
        <button type="button" class="invoice-download-button" id="download-invoice-pdf">
            Download PDF
        </button>
    </div>
    <?php endif; ?>

    <main class="invoice-page">
        <div class="invoice-scale-wrap" id="invoice-scale-wrap">
            <div class="invoice-pages" id="invoice-pages">

            <?php foreach ($pages as $pageIndex => $pageRows):
                $isFirst = ($pageIndex === 0);
                $isLast  = ($pageIndex === $totalPages - 1);
            ?>
            <section class="invoice-sheet" id="<?= $isFirst ? 'invoice-sheet' : 'invoice-sheet-' . $pageIndex ?>">

                <!-- Top bar: INVOICE (left) | logo (right) -->
                <div class="inv-topbar">
                    <div class="inv-topbar-left">
                        <h1 class="inv-title">INVOICE</h1>
                        <div class="inv-details" style="text-align:left;margin-top:2mm;">
                            <div class="inv-details-line" style="justify-content:flex-start;">
                                <span>Invoice</span>
                                <strong>#<?= htmlspecialchars($invoiceNumber, ENT_QUOTES) ?></strong>
                            </div>
                            <div class="inv-details-line" style="justify-content:flex-start;">
                                <span>Invoice Date</span>
                                <strong><?= htmlspecialchars($invoiceDate, ENT_QUOTES) ?></strong>
                            </div>
                            <div class="inv-details-line" style="justify-content:flex-start;">
                                <span>Due Date</span>
                                <strong><?= htmlspecialchars($invoiceDueDate, ENT_QUOTES) ?></strong>
                            </div>
                            <?php if ($totalPages > 1): ?>
                            <div class="inv-details-line" style="justify-content:flex-start;">
                                <span>Page</span>
                                <strong><?= $pageIndex + 1 . ' / ' . $totalPages ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="inv-brand">
                        <img src="/assets/images/logos/logo-brill-square.png" alt="Brill HEPA Filter">
                    </div>
                </div>
                <div class="inv-rule"></div>

                <?php if ($isFirst): ?>
                <!-- Address row: Invoice To (left) | Payment Via (right) — first page only -->
                <div class="inv-addr-row">
                    <div class="inv-addr">
                        <span class="inv-addr-label">Invoice To:</span>
                        <strong class="inv-addr-name"><?= htmlspecialchars($billToDisplay, ENT_QUOTES) ?></strong>
                        <?php if (!empty($shipTo)): ?>
                        <span class="inv-addr-sub-label">Ship To:</span>
                        <span class="inv-addr-value"><?= nl2br(htmlspecialchars($shipTo, ENT_QUOTES)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="inv-addr inv-addr--right">
                        <span class="inv-addr-label">Payment Via:</span>
                        <span class="inv-addr-value">Bank BCA</span>
                        <span class="inv-addr-value">Account No: 7615495000</span>
                        <span class="inv-addr-value">a/n William W atau Bryan E</span>
                    </div>
                </div>
                <?php else: ?>
                <div class="inv-continued-label">Continued from previous page</div>
                <?php endif; ?>

                <!-- Items table -->
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Rate</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageRows as $row):
                            $globalIndex++;
                            $itemParts  = explode(' - ', (string)$row['item'], 2);
                            $itemBrand  = $itemParts[0];
                            $itemDetail = preg_replace('/\s+-$/', '', trim((string)($itemParts[1] ?? '')));
                        ?>
                        <tr>
                            <td><?= $globalIndex ?></td>
                            <td>
                                <span class="inv-item-brand"><?= htmlspecialchars($itemBrand, ENT_QUOTES) ?></span>
                                <?php if ($itemDetail !== ''): ?>
                                <span class="inv-item-detail"><?= htmlspecialchars($itemDetail, ENT_QUOTES) ?></span>
                                <?php else: ?>
                                <span class="inv-item-detail"><?= htmlspecialchars($itemBrand, ENT_QUOTES) ?></span>
                                <?php endif; ?></td>
                            <td><?= htmlspecialchars(rtrim(rtrim(number_format((float)$row['quantity'], 2, '.', ','), '0'), '.'), ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars(format_idr((float)$row['rate']), ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars(format_idr((float)$row['amount']), ENT_QUOTES) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($isLast): ?>
                <!-- Summary — last page only -->
                <div class="inv-summary-wrap">
                    <div class="inv-summary-row">
                        <span>Subtotal</span>
                        <span><?= htmlspecialchars(format_idr($subtotal), ENT_QUOTES) ?></span>
                    </div>
                    <?php if ($discountCost > 0): ?>
                    <div class="inv-summary-row">
                        <span>
                            Discount
                            <?php if ($discountType === 'percent'): ?>
                            <em class="inv-summary-note">
                                *<?= htmlspecialchars($pctStr . '%' . ($discountMax > 0 ? ', max ' . format_idr($discountMax) : ''), ENT_QUOTES) ?>
                            </em>
                            <?php endif; ?>
                        </span>
                        <span><?= htmlspecialchars('- ' . format_idr($discountCost), ENT_QUOTES) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($shippingCost > 0): ?>
                    <div class="inv-summary-row">
                        <span>
                            Shipping
                            <em class="inv-summary-note"><?= htmlspecialchars($shippingWeightLabel, ENT_QUOTES) ?></em>
                        </span>
                        <span><?= htmlspecialchars(format_idr($shippingCost), ENT_QUOTES) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($additionalFee > 0): ?>
                    <div class="inv-summary-row">
                        <span>
                            Additional Fee
                            <?php if ($additionalFeeLabel !== ''): ?>
                            <em class="inv-summary-note"><?= htmlspecialchars($additionalFeeLabel, ENT_QUOTES) ?></em>
                            <?php endif; ?>
                        </span>
                        <span><?= htmlspecialchars(format_idr($additionalFee), ENT_QUOTES) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="inv-summary-row inv-summary-row--grand">
                        <span>Grand Total</span>
                        <span><?= htmlspecialchars(format_idr($total), ENT_QUOTES) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Spacer pushes footer to bottom -->
                <div class="inv-footer-spacer"></div>
                <div class="inv-rule"></div>

                <!-- Footer: notes (left) | thank you (right) -->
                <footer class="inv-footer">
                    <div class="inv-footer-notes">
                        <?php if ($footerNotesHeaderSafe !== ''): ?><strong><?= nl2br($footerNotesHeaderSafe) ?></strong><?php endif; ?>
                        <?php foreach ($footerNotesParagraphs as $paragraph): ?>
                        <p><?= nl2br(htmlspecialchars($paragraph, ENT_QUOTES)) ?></p>
                        <?php endforeach; ?>
                    </div>
                    <div class="inv-footer-thanks">
                        <?php if ($footerClosingHeaderSafe !== ''): ?><p class="inv-footer-lead"><?= nl2br($footerClosingHeaderSafe) ?></p><?php endif; ?>
                        <?php foreach ($footerClosingParagraphs as $paragraph): ?>
                        <p><?= nl2br(htmlspecialchars($paragraph, ENT_QUOTES)) ?></p>
                        <?php endforeach; ?>
                    </div>
                </footer>
                <div class="inv-rule"></div>

            </section>
            <?php endforeach; ?>

            </div><!-- /.invoice-pages -->
        </div><!-- /.invoice-scale-wrap -->
    </main>

    <script>
        (function () {
            const downloadButton = document.getElementById('download-invoice-pdf');
            const invoicePage    = document.querySelector('.invoice-page');
            const scaleWrap      = document.getElementById('invoice-scale-wrap');
            const invoicePages   = document.getElementById('invoice-pages');
            const invoiceSheet   = document.getElementById('invoice-sheet');
            const filename       = <?= json_encode($pdfFileName, JSON_UNESCAPED_SLASHES) ?>;

            function fitInvoiceForMobile() {
                if (!invoicePage || !invoicePages || !scaleWrap) return;

                scaleWrap.style.width         = '';
                scaleWrap.style.height        = '';
                invoicePages.style.transform  = '';
                invoicePage.style.minHeight   = '0';

                if (window.innerWidth > 768) return;

                const pageStyles = window.getComputedStyle(invoicePage);
                const horizontalPadding =
                    parseFloat(pageStyles.paddingLeft  || '0') +
                    parseFloat(pageStyles.paddingRight || '0');
                const availableWidth = Math.max(invoicePage.clientWidth - horizontalPadding, 0);
                const pagesWidth     = invoicePages.offsetWidth;

                if (!availableWidth || !pagesWidth) return;

                const scale = Math.min(1, availableWidth / pagesWidth);
                scaleWrap.style.width        = (pagesWidth * scale) + 'px';
                scaleWrap.style.height       = (invoicePages.offsetHeight * scale) + 'px';
                invoicePages.style.transform = 'scale(' + scale + ')';
                invoicePages.style.transformOrigin = 'top left';
                invoicePage.style.minHeight  = (invoicePages.offsetHeight * scale) + 'px';
            }

            async function downloadPdf() {
                if (typeof window.html2pdf === 'undefined') {
                    window.alert('PDF library failed to load.');
                    return;
                }
                if (downloadButton) {
                    downloadButton.disabled    = true;
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
                                margin:      0,
                                image:       { type: 'jpeg', quality: 0.98 },
                                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
                                jsPDF:       { unit: 'mm', format: [210, pageHeightMM], orientation: 'portrait' }
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
                        downloadButton.disabled    = false;
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

            <?php if (!$isLogPreview): ?>
            if (window.parent !== window) {
                window.parent.postMessage({
                    type: 'invoiceReady',
                    logId: <?= (int)$logId ?>,
                    persisted: <?= $logId > 0 ? 'true' : 'false' ?>,
                    persistError: <?= json_encode($logPersistError, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
                }, '*');
            }
            <?php endif; ?>

            fitInvoiceForMobile();
            window.addEventListener('load', fitInvoiceForMobile);
            window.addEventListener('resize', fitInvoiceForMobile);
        })();
    </script>
</body>
</html>
