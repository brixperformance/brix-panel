<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$errorMessage = isset($_SESSION['error']) ? (string) $_SESSION['error'] : null;
unset($_SESSION['error']);

$demoMode = !is_file(dirname(__DIR__) . '/.env');
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
	<meta http-equiv="X-UA-Compatible" content="ie=edge"/>
	<title>Login - BRIX Admin</title>
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
<body class="d-flex flex-column bg-azure-lt">
	<script src="/assets/dist/js/tabler-theme.js"></script>

	<div class="page page-center">
		<div class="container container-tight py-4">
			<div class="text-center mb-4">
				<a href="/login" class="navbar-brand navbar-brand-autodark">
					<span class="avatar avatar-lg bg-primary-lt me-3">B</span>
					<span class="fs-1 fw-bold tracking-tight text-body">BRIX Admin</span>
				</a>
			</div>

			<div class="card card-md shadow-sm">
				<div class="card-body">
					<h2 class="h2 text-center mb-4">Sign in to your workspace</h2>

					<?php if (isset($_GET['timeout'])): ?>
						<div class="alert alert-warning" role="alert">
							Your session expired because of inactivity. Please sign in again.
						</div>
					<?php endif; ?>

					<?php if ($demoMode): ?>
						<div class="alert alert-info" role="alert">
							Database is not configured yet. Demo login: <strong>admin</strong> / <strong>admin123</strong>.
						</div>
					<?php endif; ?>

					<?php if ($errorMessage !== null): ?>
						<div class="alert alert-danger" role="alert">
							<?= htmlspecialchars($errorMessage, ENT_QUOTES) ?>
						</div>
					<?php endif; ?>

					<form action="/login" method="post" autocomplete="off" novalidate>
						<div class="mb-3">
							<label class="form-label" for="username">Username</label>
							<input id="username" class="form-control" type="text" name="username" placeholder="Enter your username" required autofocus>
						</div>
						<div class="mb-2">
							<label class="form-label" for="password">Password</label>
							<input id="password" class="form-control" type="password" name="password" placeholder="Enter your password" required>
						</div>
						<div class="form-footer">
							<button type="submit" class="btn btn-primary w-100">Sign in</button>
						</div>
					</form>
				</div>
			</div>

			<div class="text-center text-secondary mt-3">
				Admin access for BRIX Data Hub and BRIX Shop.
			</div>
		</div>
	</div>

	<script src="/assets/dist/js/tabler.js"></script>
</body>
</html>
