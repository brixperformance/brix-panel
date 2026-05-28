<?php
require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';

bootstrap_page();

require_once dirname(__DIR__) . '/application/models/Execute.php';
require_once dirname(__DIR__) . '/application/models/master-dealer/View.php';
require_once dirname(__DIR__) . '/application/configs/invoice_footer_defaults.php';

$config = require dirname(__DIR__) . '/application/configs/database.php';
$dealerView = new MasterDealerView($config);
$dealerOptionsResult = $dealerView->getActiveDealerOptions();
$dealerOptions = $dealerOptionsResult['data'] ?? [];
$invoiceFooterDefaults = get_invoice_footer_defaults();
$invoiceFooterLimits = get_invoice_footer_limits();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>Invoice Generator - Brill</title>
    <link rel="icon" href="/favicon.ico" type="image/x-icon"/>
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon"/>
    <link href="/assets/dist/css/tabler.css" rel="stylesheet"/>
    <link href="/assets/dist/css/tabler-vendors.css" rel="stylesheet"/>
    <link href="/assets/dist/css/tabler-themes.css" rel="stylesheet"/>
    <link href="/preview/css/demo.css" rel="stylesheet"/>
    <link href="/assets/css/dashboard.css" rel="stylesheet"/>
    <link href="/assets/css/invoice-generator.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet"/>
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <a href="#content" class="visually-hidden skip-link">Skip to main content</a>
    <script src="/assets/dist/js/tabler-theme.js"></script>

    <div class="page">
        <?php include __DIR__ . '/../templates/sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row g-3 align-items-center">
                        <div class="col">
                            <div class="page-pretitle">Invoicing</div>
                            <h1 class="page-title">Invoice Generator</h1>
                            <div class="text-secondary">Fill the recipient, items, and shipping cost.</div>
                        </div>
                    </div>
                </div>
            </div>

            <main id="content" class="page-body">
                <div class="container-xl">
                    <form id="invoice-generator-form" action="/invoice-generator/preview" method="POST" novalidate autocomplete="off">
                        <div class="row g-4">

                            <!-- Invoice Meta -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">Invoice Meta</h3>
                                            <p class="card-subtitle">Invoice number and date are auto-generated. Due date defaults to 3 days from today.</p>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Invoice Number</label>
                                                <input type="text" class="form-control" id="meta-invoice-number" value="Auto-generated" readonly tabindex="-1">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Invoice Date</label>
                                                <input type="text" class="form-control" id="meta-invoice-date" readonly tabindex="-1">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label" for="meta-due-date">Invoice Due Date</label>
                                                <div class="date-input-wrap">
                                                    <input type="text" class="form-control" id="meta-due-date" placeholder="Select due date" autocomplete="off">
                                                    <span class="date-input-icon" aria-hidden="true">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                                            <path d="M3 9h18" stroke="currentColor" stroke-width="2"/>
                                                            <path d="M8 2v4M16 2v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                        </svg>
                                                    </span>
                                                </div>
                                                <input type="hidden" id="due-date-hidden" name="due_date">
                                            </div>
                                        </div>
                                        <div class="row g-3 mt-1">
                                            <div class="col-md-6">
                                                <label class="form-label">Invoice Type</label>
                                                <div class="shipping-combobox" data-combobox>
                                                    <button type="button" id="invoice-type" class="shipping-combobox-trigger" data-combobox-trigger>
                                                        <span data-combobox-label>Dealer Invoice</span>
                                                        <span class="shipping-combobox-caret"></span>
                                                    </button>
                                                    <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                                        <input type="text" class="shipping-combobox-search" data-combobox-search autocomplete="off" placeholder="Search invoice type">
                                                        <div class="shipping-combobox-options" data-combobox-options></div>
                                                    </div>
                                                    <select id="invoice-type-select" name="invoice_type" hidden required>
                                                        <option value="dealer">Dealer Invoice</option>
                                                        <option value="customer">Customer Invoice</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Bill To</label>
                                                <div id="bill-to-dealer-field">
                                                    <div class="shipping-combobox" data-combobox>
                                                        <button type="button" id="bill-to" class="shipping-combobox-trigger" data-combobox-trigger>
                                                            <span data-combobox-label>Select Dealer</span>
                                                            <span class="shipping-combobox-caret"></span>
                                                        </button>
                                                        <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                                            <input type="text" class="shipping-combobox-search" data-combobox-search autocomplete="off" placeholder="Search dealer name">
                                                            <div class="shipping-combobox-options" data-combobox-options></div>
                                                        </div>
                                                        <select id="bill-to-select" hidden required>
                                                            <option value="">Select Dealer</option>
                                                            <?php foreach ($dealerOptions as $dealer): ?>
                                                                <?php
                                                                $billToLines = array_filter([
                                                                    trim((string) ($dealer['dealer_name'] ?? '')),
                                                                    trim((string) ($dealer['dealer_contact'] ?? '')),
                                                                    trim((string) ($dealer['dealer_address'] ?? '')),
                                                                ], static fn($value) => $value !== '');
                                                                ?>
                                                                <option
                                                                    value="<?= htmlspecialchars((string) ($dealer['dealer_code'] ?? ''), ENT_QUOTES) ?>"
                                                                    data-bill-to="<?= htmlspecialchars(implode("\n", $billToLines), ENT_QUOTES) ?>"
                                                                >
                                                                    <?= htmlspecialchars((string) ($dealer['dealer_name'] ?? ''), ENT_QUOTES) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div id="bill-to-customer-field" hidden>
                                                    <input type="text" class="form-control" id="bill-to-customer-input" placeholder="Enter customer name or bill-to text">
                                                </div>
                                                <input type="hidden" id="bill-to-value" name="bill_to" required>
                                                <input type="hidden" id="dealer-code-value" name="dealer_code">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Items -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">Invoice Items</h3>
                                            <p class="card-subtitle">Add or remove rows as needed. Amount is calculated automatically.</p>
                                        </div>
                                        <div class="card-options">
                                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="add-custom-item-row">Add Custom Row</button>
                                            <button type="button" class="btn btn-primary btn-sm" id="add-item-row">Add Row</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-vcenter card-table invoice-items-table" id="invoice-items-table">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th style="width:110px">Quantity</th>
                                                    <th style="width:160px">Rate</th>
                                                    <th style="width:160px">Amount</th>
                                                    <th style="width:48px"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="invoice-item-rows">
                                                <tr class="invoice-item-row" data-row-mode="preset">
                                                    <td>
                                                        <div class="shipping-combobox item-combobox" data-combobox>
                                                            <button type="button" class="shipping-combobox-trigger" data-combobox-trigger>
                                                                <span data-combobox-label>Select Item</span>
                                                                <span class="shipping-combobox-caret"></span>
                                                            </button>
                                                            <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                                                <input type="text" class="shipping-combobox-search" data-combobox-search autocomplete="off" placeholder="Search Brand + Type + Year">
                                                                <div class="shipping-combobox-options" data-combobox-options></div>
                                                            </div>
                                                            <select name="item[]" class="invoice-item-select" hidden required>
                                                                <option value="">Select Item</option>
                                                            </select>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control" name="quantity[]" min="0" step="1" value="1" required autocomplete="off">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control currency-input" name="rate[]" inputmode="numeric" required autocomplete="off">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control amount-output" value="IDR 0.00" readonly>
                                                    </td>
                                                    <td class="invoice-row-action-cell">
                                                        <button type="button" class="btn btn-sm btn-icon btn-danger remove-row-button" aria-label="Remove row" title="Remove row">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                                <path d="M10 11v6M14 11v6"></path>
                                                                <path d="M9 6V4h6v2"></path>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <span class="text-secondary">Subtotal</span>
                                        <strong id="subtotal-preview">IDR 0.00</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Fees -->
                            <div class="col-12">
                                <div class="card invoice-lockable-panel" id="shipping-fees-panel">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">Shipping Fees</h3>
                                            <p class="card-subtitle">Choose the destination area and shipping service for this invoice.</p>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label" for="shipping-mode-select">Shipping Method</label>
                                            <div class="shipping-combobox" data-combobox>
                                                <button type="button" id="shipping-mode" class="shipping-combobox-trigger" data-combobox-trigger>
                                                    <span data-combobox-label>Fill Manually</span>
                                                    <span class="shipping-combobox-caret"></span>
                                                </button>
                                                <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                                    <input type="text" class="shipping-combobox-search" placeholder="Search shipping method" autocomplete="off" data-combobox-search>
                                                    <div class="shipping-combobox-options" data-combobox-options></div>
                                                </div>
                                                <select id="shipping-mode-select" hidden>
                                                    <option value="manual" selected>Fill Manually</option>
                                                    <option value="automatic">Calculate Online</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div id="shipping-manual-fields">
                                            <div class="mb-3">
                                                <label class="form-label" for="shipping-manual-shipto">Ship To</label>
                                                <textarea class="form-control" id="shipping-manual-shipto" rows="4" placeholder="Enter shipping recipient and address"></textarea>
                                            </div>
                                        </div>

                                        <div id="shipping-auto-fields" hidden>
                                            <div class="mb-3">
                                                <label class="form-label" for="shipping-delivery-group-select">Delivery Type</label>
                                                <div class="shipping-combobox" data-combobox>
                                                    <button type="button" id="shipping-delivery-group" class="shipping-combobox-trigger" data-combobox-trigger>
                                                        <span data-combobox-label>Regular Delivery</span>
                                                        <span class="shipping-combobox-caret"></span>
                                                    </button>
                                                    <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                                        <input type="text" class="shipping-combobox-search" placeholder="Search delivery type" autocomplete="off" data-combobox-search>
                                                        <div class="shipping-combobox-options" data-combobox-options></div>
                                                    </div>
                                                    <select id="shipping-delivery-group-select" hidden>
                                                        <option value="area_based" selected>Regular Delivery</option>
                                                        <option value="point_based">Instant Delivery</option>
                                                    </select>
                                                </div>
                                                <div class="form-hint mt-2">Regular delivery uses area and postal code. Instant delivery uses an exact map pin.</div>
                                            </div>

                                            <div id="shipping-area-based-fields">
                                            <div class="mb-3">
                                                <label class="form-label">Destination Area</label>
                                                <div class="shipping-combobox-grid shipping-combobox-grid--address">
                                                    <div class="shipping-field">
                                                        <label for="shipping-country">Country</label>
                                                        <div class="shipping-combobox" data-combobox>
                                                            <button type="button" id="shipping-country" class="shipping-combobox-trigger" data-combobox-trigger>
                                                                <span data-combobox-label>Indonesia</span>
                                                                <span class="shipping-combobox-caret"></span>
                                                            </button>
                                                            <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                                                <input type="text" class="shipping-combobox-search" placeholder="Search Country" autocomplete="off" data-combobox-search>
                                                                <div class="shipping-combobox-options" data-combobox-options></div>
                                                            </div>
                                                            <select id="shipping-country-select" hidden>
                                                                <option value="">Select Country</option>
                                                                <option value="Indonesia" selected>Indonesia</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="shipping-field">
                                                        <label for="shipping-province">Province</label>
                                                        <div class="shipping-combobox" data-combobox>
                                                            <button type="button" id="shipping-province" class="shipping-combobox-trigger" data-combobox-trigger disabled>
                                                                <span data-combobox-label>Select Province</span>
                                                                <span class="shipping-combobox-caret"></span>
                                                            </button>
                                                            <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                                                <input type="text" class="shipping-combobox-search" placeholder="Search Province" autocomplete="off" data-combobox-search>
                                                                <div class="shipping-combobox-options" data-combobox-options></div>
                                                            </div>
                                                            <select id="shipping-province-select" disabled hidden>
                                                                <option value="">Select Province</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="shipping-field">
                                                        <label for="shipping-city">City</label>
                                                        <div class="shipping-combobox" data-combobox>
                                                            <button type="button" id="shipping-city" class="shipping-combobox-trigger" data-combobox-trigger disabled>
                                                                <span data-combobox-label>Select City</span>
                                                                <span class="shipping-combobox-caret"></span>
                                                            </button>
                                                            <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                                                <input type="text" class="shipping-combobox-search" placeholder="Search City" autocomplete="off" data-combobox-search>
                                                                <div class="shipping-combobox-options" data-combobox-options></div>
                                                            </div>
                                                            <select id="shipping-city-select" disabled hidden>
                                                                <option value="">Select City</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="shipping-field">
                                                        <label for="shipping-district">District</label>
                                                        <div class="shipping-combobox" data-combobox>
                                                            <button type="button" id="shipping-district" class="shipping-combobox-trigger" data-combobox-trigger disabled>
                                                                <span data-combobox-label>Select District</span>
                                                                <span class="shipping-combobox-caret"></span>
                                                            </button>
                                                            <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                                                <input type="text" class="shipping-combobox-search" placeholder="Search District" autocomplete="off" data-combobox-search>
                                                                <div class="shipping-combobox-options" data-combobox-options></div>
                                                            </div>
                                                            <select id="shipping-district-select" disabled hidden>
                                                                <option value="">Select District</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="shipping-field">
                                                        <label for="shipping-subdistrict">Village / Subdistrict</label>
                                                        <div class="shipping-combobox" data-combobox>
                                                            <button type="button" id="shipping-subdistrict" class="shipping-combobox-trigger" data-combobox-trigger disabled>
                                                                <span data-combobox-label>Select Coverage Area</span>
                                                                <span class="shipping-combobox-caret"></span>
                                                            </button>
                                                            <div class="shipping-combobox-menu" data-combobox-menu hidden>
                                                                <input type="text" class="shipping-combobox-search" placeholder="Search Coverage Area" autocomplete="off" data-combobox-search>
                                                                <div class="shipping-combobox-options" data-combobox-options></div>
                                                            </div>
                                                            <select id="shipping-subdistrict-select" disabled hidden>
                                                                <option value="">Select Coverage Area</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="shipping-field">
                                                        <label for="shipping-postal-display">Postal Code</label>
                                                        <input type="text" class="form-control" id="shipping-postal-display" readonly tabindex="-1" placeholder="Postal code">
                                                    </div>
                                                </div>
                                                <span class="inv-area-status" id="shipping-area-status"></span>
                                                <input type="hidden" id="shipping-area-id">
                                                <input type="hidden" id="shipping-postal-code">
                                            </div>
                                            </div>

                                            <div id="shipping-point-based-fields" hidden>
                                                <div class="shipping-point-grid">
                                                    <div class="shipping-point-map-wrap">
                                                        <div id="shipping-point-map" class="shipping-point-map" aria-label="Delivery pin map"></div>
                                                    </div>
                                                    <div class="shipping-point-fields">
                                                        <div class="shipping-field">
                                                            <label for="shipping-point-label">Pinned Location Notes</label>
                                                            <textarea class="form-control" id="shipping-point-label" rows="4" placeholder="Optional label for this instant delivery point"></textarea>
                                                        </div>
                                                        <div class="shipping-combobox-grid">
                                                            <div class="shipping-field">
                                                                <label for="shipping-point-lat">Latitude</label>
                                                                <input type="number" class="form-control" id="shipping-point-lat" step="any" placeholder="-6.268912">
                                                            </div>
                                                            <div class="shipping-field">
                                                                <label for="shipping-point-lng">Longitude</label>
                                                                <input type="number" class="form-control" id="shipping-point-lng" step="any" placeholder="106.642294">
                                                            </div>
                                                        </div>
                                                        <div class="form-hint">Klik peta untuk drop pin, lalu geser marker bila perlu.</div>
                                                    </div>
                                                </div>
                                                <span class="inv-area-status" id="shipping-point-status"></span>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-primary" id="shipping-point-check-rates-btn">Check Rates</button>
                                                </div>
                                                <input type="hidden" id="shipping-point-lat-hidden">
                                                <input type="hidden" id="shipping-point-lng-hidden">
                                            </div>

                                            <div id="invoice-shipping-methods" hidden>
                                                <div class="inv-shipping-couriers" id="shipping-couriers-box"></div>
                                                <div class="inv-shipping-options" id="shipping-options-box"></div>
                                                <div class="inv-shipping-status" id="inv-shipping-status"></div>
                                            </div>

                                        </div>

                                        <div class="invoice-inline-fields invoice-inline-fields--shipping-summary mb-3">
                                            <div>
                                                <label class="form-label" for="shipping-total-weight-display">Total Weight</label>
                                                <input type="text" class="form-control" id="shipping-total-weight-display" readonly tabindex="-1" value="1 kg">
                                                <div class="form-hint">Shipping weight is calculated automatically: every 3 items count as 1 kg.</div>
                                            </div>
                                            <div>
                                                <label class="form-label" for="shipping-cost-input">Shipping Cost</label>
                                                <input type="text" class="form-control currency-input" id="shipping-cost-input" inputmode="numeric" placeholder="IDR 0.00" autocomplete="off">
                                            </div>
                                        </div>

                                        <div id="shipping-auto-override-field" class="mb-3" hidden>
                                            <label class="form-label" for="shipping-auto-shipto-override">
                                                Ship To
                                                <span class="text-secondary ms-1" style="font-size:.85em;font-weight:400">(optional override)</span>
                                            </label>
                                            <textarea class="form-control" id="shipping-auto-shipto-override" rows="4" placeholder="Leave blank to use the selected country, province, city, district, and coverage area"></textarea>
                                        </div>
                                        <input type="hidden" id="ship-to-value" name="ship_to">
                                    </div>
                                    <div class="invoice-panel-lock" id="shipping-fees-lock" hidden>
                                        <span>Min. 1 item required to calculate</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Discount & Additional Fees -->
                            <div class="col-12">
                                <div class="card invoice-lockable-panel" id="discount-fees-panel">
                                    <div class="card-header">
                                        <h3 class="card-title">Discount &amp; Additional Fees</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Discount</label>
                                                <div class="discount-type-tabs mb-2">
                                                    <button type="button" class="discount-tab is-active" data-type="flat">Flat</button>
                                                    <button type="button" class="discount-tab" data-type="percent">Persen</button>
                                                </div>
                                                <div id="discount-flat-wrap">
                                                    <input type="text" class="form-control currency-input" id="discount-flat-input" inputmode="numeric" placeholder="IDR 0.00" autocomplete="off">
                                                </div>
                                                <div id="discount-percent-wrap" hidden>
                                                    <div class="discount-percent-inputs">
                                                        <input type="number" class="form-control discount-pct-input" id="discount-percent-input" min="0" max="100" step="0.01" placeholder="%" autocomplete="off">
                                                        <input type="text" class="form-control currency-input" id="discount-max-input" inputmode="numeric" placeholder="Maks IDR (opsional)" autocomplete="off">
                                                    </div>
                                                    <span class="discount-percent-preview" id="discount-percent-preview"></span>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label" for="additional-fee-input">
                                                    Additional Fee
                                                    <span class="text-secondary ms-1" style="font-size:.85em;font-weight:400">(opsional)</span>
                                                </label>
                                                <div class="invoice-inline-fields invoice-inline-fields--additional-fee">
                                                    <input type="text" class="form-control currency-input" id="additional-fee-input" inputmode="numeric" placeholder="IDR 0.00" autocomplete="off">
                                                    <input type="text" class="form-control" id="additional-fee-label-input" placeholder="Fee description (optional)" autocomplete="off">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer d-flex align-items-center justify-content-between">
                                        <span class="text-secondary fw-medium">Grand Total</span>
                                        <strong id="total-preview" class="fs-3">IDR 0.00</strong>
                                    </div>
                                    <input type="hidden" name="discount_type"  id="discount-type-hidden"  value="flat">
                                    <input type="hidden" name="discount_value" id="discount-value-hidden" value="0">
                                    <input type="hidden" name="discount_max"   id="discount-max-hidden"   value="0">
                                    <input type="hidden" id="shipping-cost" name="shipping_cost" value="0">
                                    <input type="hidden" id="additional-fee" name="additional_fee" value="0">
                                    <input type="hidden" id="additional-fee-label" name="additional_fee_label" value="">
                                    <div class="invoice-panel-lock" id="discount-fees-lock" hidden>
                                        <span>Min. 1 item required to calculate</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Editor -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">Footer Editor</h3>
                                            <p class="card-subtitle">Customize the footer content shown at the bottom of the invoice.</p>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="footer-editor-preview mb-4">
                                            <div class="footer-editor-preview__head">
                                                <h3>Live Preview</h3>
                                                <p>This preview is locked to the invoice footer layout and scales down without changing the footer composition.</p>
                                            </div>
                                            <div class="footer-editor-preview__frame" id="footer-preview-frame">
                                                <div class="footer-editor-preview__scale-wrap" id="footer-preview-scale-wrap">
                                                    <div class="footer-editor-preview__canvas" id="footer-preview-canvas">
                                                        <div class="footer-editor-preview__rule"></div>
                                                        <footer class="footer-editor-preview__footer">
                                                            <div class="footer-editor-preview__notes">
                                                                <strong id="footer-preview-notes-header"><?= nl2br(htmlspecialchars((string) ($invoiceFooterDefaults['notes_header'] ?? 'Notes'), ENT_QUOTES)) ?></strong>
                                                                <div id="footer-preview-notes">
                                                                    <?php foreach (split_invoice_footer_paragraphs((string) ($invoiceFooterDefaults['notes'] ?? '')) as $paragraph): ?>
                                                                    <p><?= nl2br(htmlspecialchars($paragraph, ENT_QUOTES)) ?></p>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                            <div class="footer-editor-preview__closing">
                                                                <p class="is-lead" id="footer-preview-closing-header"><?= nl2br(htmlspecialchars((string) ($invoiceFooterDefaults['closing_header'] ?? ''), ENT_QUOTES)) ?></p>
                                                                <div id="footer-preview-closing">
                                                                    <p><?= nl2br(htmlspecialchars((string) ($invoiceFooterDefaults['closing_message'] ?? ''), ENT_QUOTES)) ?></p>
                                                                </div>
                                                            </div>
                                                        </footer>
                                                        <div class="footer-editor-preview__rule"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php
                                        $maxNoteHeader     = (int) ($invoiceFooterLimits['notes_header']['max_chars'] ?? 60);
                                        $maxNotePerLine    = (int) ($invoiceFooterLimits['notes']['max_chars_per_paragraph'] ?? 150);
                                        $defaultNoteLines  = split_invoice_footer_paragraphs((string) ($invoiceFooterDefaults['notes'] ?? ''));
                                        $noteLine = [$defaultNoteLines[0] ?? '', $defaultNoteLines[1] ?? '', $defaultNoteLines[2] ?? ''];

                                        $maxClosingHeader  = (int) ($invoiceFooterLimits['closing_header']['max_chars'] ?? 100);
                                        $maxClosingPerLine = (int) ($invoiceFooterLimits['closing_message']['max_chars_per_paragraph'] ?? 150);
                                        $defaultClosingMsg = (string) ($invoiceFooterDefaults['closing_message'] ?? '');
                                        ?>
                                        <div class="row g-3">
                                            <!-- Notes column -->
                                            <div class="col-md-6">
                                                <div class="footer-editor__field-head mb-2">
                                                    <label class="form-label mb-0">Notes</label>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="footer-notes-reset-btn">Revert to Default</button>
                                                </div>
                                                <input type="hidden" id="footer-notes-header-input" name="footer_notes_header" value="<?= htmlspecialchars((string) ($invoiceFooterDefaults['notes_header'] ?? 'Notes'), ENT_QUOTES) ?>">
                                                <input type="hidden" id="footer-notes-input" name="footer_notes" value="<?= htmlspecialchars(implode("\n\n", array_filter($noteLine, fn($l) => $l !== '')), ENT_QUOTES) ?>">
                                                <div class="d-flex flex-column gap-2">
                                                    <div>
                                                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                                                            <label class="form-label form-label-sm text-secondary mb-0">Header</label>
                                                            <span class="footer-editor__counter" data-footer-counter-for="footer-notes-header"></span>
                                                        </div>
                                                        <textarea
                                                            class="form-control footer-line-input"
                                                            id="footer-notes-header"
                                                            rows="1"
                                                            maxlength="<?= $maxNoteHeader ?>"
                                                            spellcheck="false"
                                                        ><?= htmlspecialchars((string) ($invoiceFooterDefaults['notes_header'] ?? 'Notes'), ENT_QUOTES) ?></textarea>
                                                    </div>
                                                    <?php for ($i = 0; $i < 3; $i++): ?>
                                                    <div>
                                                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                                                            <label class="form-label form-label-sm text-secondary mb-0">Paragraph <?= $i + 1 ?></label>
                                                            <span class="footer-editor__counter" data-footer-counter-for="footer-notes-<?= $i + 1 ?>"></span>
                                                        </div>
                                                        <textarea
                                                            class="form-control footer-line-input"
                                                            id="footer-notes-<?= $i + 1 ?>"
                                                            rows="1"
                                                            maxlength="<?= $maxNotePerLine ?>"
                                                            spellcheck="false"
                                                        ><?= htmlspecialchars($noteLine[$i], ENT_QUOTES) ?></textarea>
                                                    </div>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <!-- Closing column -->
                                            <div class="col-md-6">
                                                <div class="footer-editor__field-head mb-2">
                                                    <label class="form-label mb-0">Closing Message</label>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="footer-closing-reset-btn">Revert to Default</button>
                                                </div>
                                                <input type="hidden" id="footer-closing-header-input" name="footer_closing_header" value="<?= htmlspecialchars((string) ($invoiceFooterDefaults['closing_header'] ?? ''), ENT_QUOTES) ?>">
                                                <input type="hidden" id="footer-closing-message-input" name="footer_closing_message" value="<?= htmlspecialchars($defaultClosingMsg, ENT_QUOTES) ?>">
                                                <div class="d-flex flex-column gap-2">
                                                    <div>
                                                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                                                            <label class="form-label form-label-sm text-secondary mb-0">Header</label>
                                                            <span class="footer-editor__counter" data-footer-counter-for="footer-closing-header"></span>
                                                        </div>
                                                        <textarea
                                                            class="form-control footer-line-input footer-line-input--header"
                                                            id="footer-closing-header"
                                                            rows="1"
                                                            maxlength="<?= $maxClosingHeader ?>"
                                                            spellcheck="false"
                                                        ><?= htmlspecialchars((string) ($invoiceFooterDefaults['closing_header'] ?? ''), ENT_QUOTES) ?></textarea>
                                                    </div>
                                                    <div>
                                                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                                                            <label class="form-label form-label-sm text-secondary mb-0">Paragraph</label>
                                                            <span class="footer-editor__counter" data-footer-counter-for="footer-closing-1"></span>
                                                        </div>
                                                        <textarea
                                                            class="form-control footer-line-input"
                                                            id="footer-closing-1"
                                                            rows="1"
                                                            maxlength="<?= $maxClosingPerLine ?>"
                                                            spellcheck="false"
                                                        ><?= htmlspecialchars($defaultClosingMsg, ENT_QUOTES) ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Generate button -->
                            <div class="col-12 mb-4">
                                <div class="d-flex justify-content-end">
                                    <button type="button" id="btn-preview-invoice" class="btn btn-primary btn-lg px-5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                            <line x1="16" y1="13" x2="8" y2="13"></line>
                                            <line x1="16" y1="17" x2="8" y2="17"></line>
                                            <polyline points="10 9 9 9 8 9"></polyline>
                                        </svg>
                                        Generate Invoice
                                    </button>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Invoice Preview Modal -->
    <div class="modal modal-blur fade" id="modal-invoice-preview" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header d-block">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="modal-title mb-0">Invoice Preview</h5>
                        <button type="button" class="btn btn-sm btn-danger" data-bs-dismiss="modal">Close</button>
                    </div>
                    <div class="text-muted mt-1" id="modal-preview-subtitle" style="font-size:.85rem;">Preview before downloading or sharing the invoice.</div>
                </div>
                <div class="modal-body">
                    <iframe id="invoice-preview-frame" name="invoice-preview-frame" src="about:blank" title="Invoice Preview"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="btn-modal-download">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                            <path d="M7 11l5 5l5 -5" />
                            <path d="M12 4l0 12" />
                        </svg>
                        Download PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Off-screen iframe for download -->
    <iframe id="invoice-download-frame" name="invoice-download-frame" src="about:blank" tabindex="-1" aria-hidden="true"
            style="position:fixed;left:-9999px;top:-9999px;width:1240px;height:1754px;visibility:hidden;border:none;pointer-events:none;"></iframe>

    <template id="invoice-item-row-template">
        <tr class="invoice-item-row" data-row-mode="preset">
            <td>
                <div class="shipping-combobox item-combobox" data-combobox>
                    <button type="button" class="shipping-combobox-trigger" data-combobox-trigger>
                        <span data-combobox-label>Select Item</span>
                        <span class="shipping-combobox-caret"></span>
                    </button>
                    <div class="shipping-combobox-menu" data-combobox-menu hidden>
                        <input type="text" class="shipping-combobox-search" data-combobox-search autocomplete="off" placeholder="Search Brand + Type + Year">
                        <div class="shipping-combobox-options" data-combobox-options></div>
                    </div>
                    <select name="item[]" class="invoice-item-select" hidden required>
                        <option value="">Select Item</option>
                    </select>
                </div>
            </td>
            <td>
                <input type="number" class="form-control" name="quantity[]" min="0" step="1" value="1" required autocomplete="off">
            </td>
            <td>
                <input type="text" class="form-control currency-input" name="rate[]" inputmode="numeric" required autocomplete="off">
            </td>
            <td>
                <input type="text" class="form-control amount-output" value="IDR 0.00" readonly>
            </td>
            <td class="invoice-row-action-cell">
                <button type="button" class="btn btn-sm btn-icon btn-danger remove-row-button" aria-label="Remove row" title="Remove row">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6l-1 14H6L5 6"></path>
                        <path d="M10 11v6M14 11v6"></path>
                        <path d="M9 6V4h6v2"></path>
                    </svg>
                </button>
            </td>
        </tr>
    </template>

    <template id="invoice-custom-item-row-template">
        <tr class="invoice-item-row" data-row-mode="custom">
            <td>
                <input type="text" class="form-control invoice-item-manual-input" name="item[]" placeholder="Enter custom item name" autocomplete="off" required>
            </td>
            <td>
                <input type="number" class="form-control" name="quantity[]" min="0" step="1" value="1" required autocomplete="off">
            </td>
            <td>
                <input type="text" class="form-control currency-input" name="rate[]" inputmode="numeric" required autocomplete="off" placeholder="IDR 0.00">
            </td>
            <td>
                <input type="text" class="form-control amount-output" value="IDR 0.00" readonly>
            </td>
            <td class="invoice-row-action-cell">
                <button type="button" class="btn btn-sm btn-icon btn-danger remove-row-button" aria-label="Remove row" title="Remove row">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6l-1 14H6L5 6"></path>
                        <path d="M10 11v6M14 11v6"></path>
                        <path d="M9 6V4h6v2"></path>
                    </svg>
                </button>
            </td>
        </tr>
    </template>

    <script src="/assets/dist/js/tabler.js"></script>
    <script src="/assets/js/invoice-generator.js" defer></script>
    <script src="/assets/js/idle-timeout.js" defer></script>
</body>
</html>
