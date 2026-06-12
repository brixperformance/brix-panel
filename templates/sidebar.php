<?php

declare(strict_types=1);

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/dashboard', PHP_URL_PATH);
$currentPath = is_string($currentPath) && $currentPath !== '' ? rtrim($currentPath, '/') : '/dashboard';
$currentPath = $currentPath === '' ? '/dashboard' : $currentPath;

$masterRoutes = [
	'/master-brand',
	'/master-car',
	'/master-distributor',
	'/master-product',
];

$writerRoutes = [
	'/master-article',
	'/master-article/create',
	'/master-article/update',
];

$shopRoutes = [
	'/shop/dashboard',
	'/shop/orders',
	'/shop/pricing',
	'/shop/referrals',
];

$isMasterOpen    = in_array($currentPath, $masterRoutes, true);
$isWriterOpen    = in_array($currentPath, $writerRoutes, true);
$isShopOpen      = in_array($currentPath, $shopRoutes, true);

$menuGroups = [
	[
		'id' => 'sidebar-data-hub',
		'title' => 'BRIX Data Hub',
		'icon' => 'database',
		'open' => $isMasterOpen,
		'items' => [
			['href' => '/master-brand', 'label' => 'Master Brand'],
			['href' => '/master-car', 'label' => 'Master Car'],
			['href' => '/master-distributor', 'label' => 'Master Distributor'],
			['href' => '/master-product', 'label' => 'Master Product'],
		],
	],
	[
		'id' => 'sidebar-writer',
		'title' => 'BRIX Writer',
		'icon' => 'receipt',
		'open' => $isWriterOpen,
		'items' => [
			['href' => '/master-article', 'label' => 'Master Article'],
		],
	],
	[
		'id' => 'sidebar-shop',
		'title' => 'BRIX Shop',
		'icon' => 'shop',
		'open' => $isShopOpen,
		'items' => [
			['href' => '/shop/dashboard', 'label' => 'Shop Dashboard'],
			['href' => '/shop/orders', 'label' => 'Orders'],
			['href' => '/shop/pricing', 'label' => 'Product Pricing'],
			['href' => '/shop/referrals', 'label' => 'Referrals'],
		],
	],
];

function sidebar_icon(string $name): string
{
	return match ($name) {
		'home' => '<path d="M5 12l-2 0l9 -9l9 9l-2 0" /><path d="M5 12v7a2 2 0 0 0 2 2h3v-6h4v6h3a2 2 0 0 0 2 -2v-7" />',
		'database' => '<path d="M4 6a8 3 0 0 0 16 0a8 3 0 0 0 -16 0" /><path d="M4 6v6a8 3 0 0 0 16 0v-6" /><path d="M4 12v6a8 3 0 0 0 16 0v-6" />',
		'receipt' => '<path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2z" /><path d="M9 7l6 0" /><path d="M9 11l6 0" />',
		'shop' => '<path d="M3 9l1 -6h16l1 6" /><path d="M3 9a2 2 0 0 0 4 0a2 2 0 0 0 4 0a2 2 0 0 0 4 0a2 2 0 0 0 4 0" /><path d="M5 9v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1 -1v-10" />',
		default => '',
	};
}
?>
<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
	<div class="container-fluid">
		<button
			class="navbar-toggler"
			type="button"
			data-bs-toggle="collapse"
			data-bs-target="#sidebar-menu"
			aria-controls="sidebar-menu"
			aria-expanded="false"
			aria-label="Toggle sidebar navigation"
		>
			<span class="navbar-toggler-icon"></span>
		</button>

		<div class="navbar-brand navbar-brand-autodark">
			<a href="/dashboard" aria-label="BRIX Dashboard" class="sidebar-brand-link text-reset text-decoration-none">
				<img
					src="/assets/images/logos/logo-brix.svg"
					alt="BRIX"
					class="sidebar-brand-logo"
				>
			</a>
		</div>

		<div class="collapse navbar-collapse" id="sidebar-menu">
			<ul class="navbar-nav pt-lg-3">
				<li class="nav-item">
					<a class="nav-link <?= $currentPath === '/dashboard' ? 'active' : '' ?>" href="/dashboard" data-dashboard-nav>
						<span class="nav-link-icon d-md-none d-lg-inline-block">
							<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
								<?= sidebar_icon('home') ?>
							</svg>
						</span>
						<span class="nav-link-title">Overview</span>
					</a>
				</li>
				<?php foreach ($menuGroups as $group): ?>
					<?php $groupActive = !empty(array_filter($group['items'], static fn(array $item): bool => $item['href'] === $currentPath)); ?>
					<li class="nav-item dropdown <?= $group['open'] ? 'show' : '' ?>" data-sidebar-group>
						<a
							class="nav-link dropdown-toggle <?= $groupActive ? 'active' : '' ?>"
							href="#<?= htmlspecialchars($group['id'], ENT_QUOTES) ?>"
							data-bs-toggle="dropdown"
							data-bs-auto-close="false"
							role="button"
							aria-haspopup="true"
							aria-expanded="<?= $group['open'] ? 'true' : 'false' ?>"
						>
							<span class="nav-link-icon d-md-none d-lg-inline-block">
								<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
									<?= sidebar_icon($group['icon']) ?>
								</svg>
							</span>
							<span class="nav-link-title"><?= htmlspecialchars($group['title'], ENT_QUOTES) ?></span>
						</a>
						<div class="dropdown-menu <?= $group['open'] ? 'show' : '' ?>" id="<?= htmlspecialchars($group['id'], ENT_QUOTES) ?>" data-sidebar-group-menu>
							<div class="dropdown-menu-columns">
								<div class="dropdown-menu-column">
									<?php foreach ($group['items'] as $item): ?>
										<a
											class="dropdown-item <?= $currentPath === $item['href'] ? 'active' : '' ?>"
											href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>"
											data-dashboard-nav
										>
											<?= htmlspecialchars($item['label'], ENT_QUOTES) ?>
										</a>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
				<li class="nav-item mt-3">
					<a class="nav-link" href="/logout">
						<span class="nav-link-icon d-md-none d-lg-inline-block">
							<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
								<path d="M14 8v-2a2 2 0 0 0 -2 -2h-5a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h5a2 2 0 0 0 2 -2v-2" />
								<path d="M9 12h12l-3 -3" />
								<path d="M18 15l3 -3" />
							</svg>
						</span>
						<span class="nav-link-title">Logout</span>
					</a>
				</li>
			</ul>
		</div>
	</div>
</aside>
