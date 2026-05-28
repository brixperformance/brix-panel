<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';

bootstrap_page();

require_once dirname(__DIR__) . '/application/models/Execute.php';
require_once dirname(__DIR__) . '/application/configs/string_utils.php';

$config = require dirname(__DIR__) . '/application/configs/database.php';
$exec = new Execute($config);
$sort = strtolower(trim((string) ($_GET['sort'] ?? 'most')));
$sort = in_array($sort, ['most', 'least'], true) ? $sort : 'most';
$searchQuery = trim((string) ($_GET['search'] ?? ''));
$searchKeyword = app_string_lower($searchQuery);

$dealerResult = $exec->executeSelect(
	"SELECT msd_code AS dealer_code, msd_name AS dealer_name
	 FROM ms_dealer
	 WHERE msd_status = 'Y'
	 ORDER BY msd_name ASC",
	[],
	'all'
);
$dealerRows = $dealerResult['data'] ?? [];

$invoiceCountsResult = $exec->executeSelect(
	"SELECT
		linv_type,
		COALESCE(linv_dealer_code, '') AS dealer_code,
		COUNT(*) AS total_invoices
	 FROM log_invoice
	 GROUP BY linv_type, COALESCE(linv_dealer_code, '')",
	[],
	'all'
);
$invoiceCountRows = $invoiceCountsResult['data'] ?? [];

$dealerInvoiceCounts = [];
$customerInvoiceCount = 0;
$allInvoiceCount = 0;

foreach ($invoiceCountRows as $countRow) {
	$rowType = strtolower(trim((string) ($countRow['linv_type'] ?? '')));
	$rowDealerCode = trim((string) ($countRow['dealer_code'] ?? ''));
	$rowCount = (int) ($countRow['total_invoices'] ?? 0);

	$allInvoiceCount += $rowCount;

	if ($rowType === 'customer') {
		$customerInvoiceCount += $rowCount;
		continue;
	}

	if ($rowDealerCode !== '') {
		$dealerInvoiceCounts[$rowDealerCode] = $rowCount;
	}
}

$specialCards = [
	[
		'label' => 'All Records',
		'href' => '/invoice-log?scope=all',
		'tone' => 'all',
		'count' => $allInvoiceCount,
		'description' => 'Browse every stored invoice across dealer and customer flows.',
	],
	[
		'label' => 'Customer',
		'href' => '/invoice-log?type=customer',
		'tone' => 'customer',
		'count' => $customerInvoiceCount,
		'description' => 'Review direct customer invoices with one focused entry point.',
	],
];

$dealerCards = [];
foreach ($dealerRows as $dealer) {
	$dealerCode = trim((string) ($dealer['dealer_code'] ?? ''));
	$dealerName = trim((string) ($dealer['dealer_name'] ?? ''));
	if ($dealerCode === '' || $dealerName === '') {
		continue;
	}

	$dealerCards[] = [
		'label' => $dealerName,
		'href' => '/invoice-log?dealer_code=' . rawurlencode($dealerCode),
		'tone' => 'dealer',
		'count' => (int) ($dealerInvoiceCounts[$dealerCode] ?? 0),
		'description' => 'Open the invoice list for this dealer and continue to preview or edit.',
	];
}

$sortCards = static function (array $cardsToSort) use ($sort): array {
	usort($cardsToSort, static function (array $left, array $right) use ($sort): int {
		$leftCount = (int) ($left['count'] ?? 0);
		$rightCount = (int) ($right['count'] ?? 0);

		if ($leftCount === $rightCount) {
			return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
		}

		return $sort === 'least'
			? ($leftCount <=> $rightCount)
			: ($rightCount <=> $leftCount);
	});

	return $cardsToSort;
};

if ($searchKeyword !== '') {
	$allCards = array_merge($specialCards, $dealerCards);
	$filteredCards = array_values(array_filter($allCards, static function (array $card) use ($searchKeyword): bool {
		$label = app_string_lower((string) ($card['label'] ?? ''));
		return str_contains($label, $searchKeyword);
	}));
	$cards = $sortCards($filteredCards);
} else {
	$cards = array_merge($specialCards, $sortCards($dealerCards));
}

function invoice_directory_tone_class(string $tone): string
{
	return match ($tone) {
		'all' => 'text-green',
		'customer' => 'text-red',
		default => 'text-blue',
	};
}

function invoice_directory_ribbon_class(string $tone): string
{
	return match ($tone) {
		'all' => 'bg-green',
		'customer' => 'bg-red',
		default => 'bg-blue',
	};
}

function invoice_directory_status_class(string $tone): string
{
	return match ($tone) {
		'all' => 'bg-green',
		'customer' => 'bg-red',
		default => 'bg-blue',
	};
}

function invoice_directory_ribbon_label(string $tone): string
{
	return match ($tone) {
		'all' => 'All',
		'customer' => 'Cust',
		default => 'Dealer',
	};
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
	<meta http-equiv="X-UA-Compatible" content="ie=edge"/>
	<title>Invoice Directory - Brill</title>
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
	<style>
		.invoice-directory-grid-row {
			--tblr-gutter-x: 0.75rem;
			--tblr-gutter-y: 0.75rem;
			margin-inline: calc(var(--tblr-gutter-x) * -.5);
		}

		.invoice-directory-grid-item {
			display: flex;
		}

		.invoice-directory-card {
			width: 100%;
			aspect-ratio: 16 / 9;
			overflow: hidden;
			position: relative;
		}

		.invoice-directory-card .card-body {
			padding: 1.25rem;
			height: 100%;
			display: flex;
			flex-direction: column;
		}

		.invoice-directory-card .card-title {
			margin-bottom: .75rem;
			line-height: 1.35;
			min-height: 2.7rem;
			padding-right: 4.75rem;
			display: -webkit-box;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
			word-break: break-word;
			overflow-wrap: anywhere;
			overflow: hidden;
		}

		.invoice-directory-card .card-meta {
			font-size: .875rem;
			font-weight: 600;
			color: var(--tblr-secondary);
			margin-top: auto;
		}

		.invoice-directory-toolbar .card-body {
			padding: 1rem 1.25rem;
		}

		@media (max-width: 767.98px) {
			.invoice-directory-card {
				aspect-ratio: auto;
				min-height: 10rem;
			}
		}
	</style>
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
							<div class="page-pretitle">Brill Invoicing</div>
							<h1 class="page-title">Invoice Directory</h1>
						</div>
					</div>
				</div>
			</div>

			<main class="page-body">
				<div class="container-xl">
					<div class="row row-cards">
						<div class="col-12">
							<div class="card invoice-directory-toolbar">
								<div class="card-body">
									<form method="GET" class="row g-3 align-items-end">
										<div class="col-lg-8">
											<label class="form-label" for="invoice-directory-search">Search</label>
											<input
												id="invoice-directory-search"
												type="search"
												name="search"
												class="form-control"
												placeholder="Search by dealer or group name"
												value="<?= htmlspecialchars($searchQuery, ENT_QUOTES) ?>"
											>
										</div>
										<div class="col-lg-2">
											<label class="form-label" for="invoice-directory-sort">Sort By</label>
											<select id="invoice-directory-sort" name="sort" class="form-select">
												<option value="most" <?= $sort === 'most' ? 'selected' : '' ?>>Most Invoices</option>
												<option value="least" <?= $sort === 'least' ? 'selected' : '' ?>>Fewest Invoices</option>
											</select>
										</div>
										<div class="col-lg-2 d-grid">
											<button type="submit" class="btn btn-primary">Apply</button>
										</div>
									</form>
								</div>
							</div>
						</div>

						<?php if (empty($cards)): ?>
							<div class="col-12">
								<div class="card">
									<div class="card-body text-center text-secondary py-5">No invoice groups available.</div>
								</div>
							</div>
						<?php else: ?>
							<div class="col-12">
								<div class="row row-cards invoice-directory-grid-row">
									<?php foreach ($cards as $card): ?>
										<div class="col-6 col-md-3 invoice-directory-grid-item">
											<a href="<?= htmlspecialchars($card['href'], ENT_QUOTES) ?>" class="card card-link card-link-pop invoice-directory-card text-reset">
												<div class="card-status-top <?= htmlspecialchars(invoice_directory_status_class((string) $card['tone']), ENT_QUOTES) ?>"></div>
												<div class="ribbon <?= htmlspecialchars(invoice_directory_ribbon_class((string) $card['tone']), ENT_QUOTES) ?>"><?= htmlspecialchars(invoice_directory_ribbon_label((string) $card['tone']), ENT_QUOTES) ?></div>
												<div class="card-body">
													<h3 class="card-title"><?= htmlspecialchars((string) $card['label'], ENT_QUOTES) ?></h3>
													<div class="card-meta"><?= (int) ($card['count'] ?? 0) ?> invoice<?= ((int) ($card['count'] ?? 0) === 1 ? '' : 's') ?></div>
												</div>
											</a>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</main>
		</div>
	</div>

	<script src="/assets/dist/js/tabler.js"></script>
	<script src="/assets/js/dashboard.js"></script>
</body>
</html>
