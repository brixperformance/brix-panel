<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/configs/page_bootstrap.php';
require_once dirname(__DIR__) . '/application/configs/lp_pdo.php';
require_once dirname(__DIR__) . '/application/configs/csrf.php';
require_once dirname(__DIR__) . '/application/configs/pagination.php';

bootstrap_page(null, false);

if (empty($_SESSION['logged_in'])) {
    header('Location: /login', true, 302);
    exit;
}

function master_distributor_esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function master_distributor_redirect(string $message = '', string $error = ''): void
{
    $params = array_filter([
        'message' => $message,
        'error' => $error,
    ], static fn ($value): bool => $value !== '');

    header('Location: /master-distributor' . ($params ? '?' . http_build_query($params) : ''), true, 302);
    exit;
}

function master_distributor_normalize_status(bool $checked): string
{
    return $checked ? 'Y' : 'N';
}

function master_distributor_format_join_date(?string $value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '-';
    }

    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return $text;
    }

    return date('d M Y', $timestamp);
}

function master_distributor_build_code(PDO $pdo, string $provinceCode): string
{
    $prefix = substr($provinceCode, 0, 4);

    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(msd_code, 5, 2) AS UNSIGNED)) AS max_suffix
        FROM ms_distributors
        WHERE LEFT(msd_code, 4) = :prefix
    ");
    $stmt->execute([':prefix' => $prefix]);

    $maxSuffix = (int) ($stmt->fetchColumn() ?: 0);
    $next = $maxSuffix + 1;

    if ($next > 99) {
        throw new RuntimeException('Distributor code limit reached for prefix ' . $prefix . '.');
    }

    return $prefix . str_pad((string) $next, 2, '0', STR_PAD_LEFT);
}

$dbError = '';

try {
    $pdo = get_lp_pdo();
} catch (PDOException $e) {
    $pdo = null;
    $dbError = 'Database landing page belum bisa diakses: ' . $e->getMessage();
}

$islands = [];
$provinces = [];

if (($pdo ?? null) instanceof PDO) {
    try {
        $islands = $pdo->query("
            SELECT msi_code, msi_name
            FROM ms_island
            WHERE msi_active_status IN ('Y', 'T')
            ORDER BY msi_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $provinces = $pdo->query("
            SELECT msp_code, msp_msi_code, msp_name
            FROM ms_province
            WHERE msp_active_status IN ('Y', 'T')
            ORDER BY msp_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $dbError = 'Gagal memuat data referensi distributor: ' . $e->getMessage();
    }
}

if (($pdo ?? null) instanceof PDO && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = trim((string) ($_POST['csrf_token'] ?? ''));
    if (!csrf_verify($token)) {
        master_distributor_redirect('', 'Invalid request. Coba lagi.');
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $islandCode = strtoupper(trim((string) ($_POST['island_code'] ?? '')));
    $provinceCode = strtoupper(trim((string) ($_POST['province_code'] ?? '')));
    $name = trim((string) ($_POST['name'] ?? ''));
    $type = trim((string) ($_POST['type'] ?? ''));
    $contact = trim((string) ($_POST['contact'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $mapEmbed = trim((string) ($_POST['map_embed'] ?? ''));
    $joinDate = trim((string) ($_POST['join_date'] ?? ''));
    $status = master_distributor_normalize_status(isset($_POST['is_active']));

    try {
        if (!in_array($action, ['create', 'update'], true)) {
            throw new RuntimeException('Aksi tidak dikenali.');
        }

        if ($name === '') {
            throw new RuntimeException('Distributor name wajib diisi.');
        }

        if ($action === 'create') {
            if ($islandCode === '' || $provinceCode === '') {
                throw new RuntimeException('Island dan province wajib dipilih.');
            }
        }

        if ($type === '') {
            throw new RuntimeException('Distributor type wajib diisi.');
        }

        if ($joinDate !== '' && strtotime($joinDate) === false) {
            throw new RuntimeException('Join date tidak valid.');
        }

        if ($action === 'create') {
            $provinceStmt = $pdo->prepare("
                SELECT p.msp_code
                FROM ms_province p
                WHERE p.msp_code = :province_code
                  AND p.msp_msi_code = :island_code
                LIMIT 1
            ");
            $provinceStmt->execute([
                ':province_code' => $provinceCode,
                ':island_code' => $islandCode,
            ]);

            if ($provinceStmt->fetchColumn() === false) {
                throw new RuntimeException('Province tidak cocok dengan island yang dipilih.');
            }

            $newCode = master_distributor_build_code($pdo, $provinceCode);

            $stmt = $pdo->prepare("
                INSERT INTO ms_distributors
                    (msd_code, msd_msp_code, msd_name, msd_type, msd_contact, msd_address, msd_map_embed, msd_join_date, msd_status, msd_create_date, msd_update_date)
                VALUES
                    (:code, :province_code, :name, :type, :contact, :address, :map_embed, :join_date, 'Y', NOW(), NOW())
            ");
            $stmt->execute([
                ':code' => $newCode,
                ':province_code' => $provinceCode,
                ':name' => $name,
                ':type' => $type,
                ':contact' => $contact !== '' ? $contact : null,
                ':address' => $address !== '' ? $address : null,
                ':map_embed' => $mapEmbed !== '' ? $mapEmbed : null,
                ':join_date' => $joinDate !== '' ? $joinDate : null,
            ]);

            master_distributor_redirect('Distributor berhasil ditambahkan.');
        }

        if ($code === '') {
            throw new RuntimeException('Distributor code tidak ditemukan.');
        }

        $stmt = $pdo->prepare("
            UPDATE ms_distributors
            SET msd_name = :name,
                msd_type = :type,
                msd_contact = :contact,
                msd_address = :address,
                msd_map_embed = :map_embed,
                msd_join_date = :join_date,
                msd_status = :status,
                msd_update_date = NOW()
            WHERE msd_code = :code
        ");
        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':type' => $type,
            ':contact' => $contact !== '' ? $contact : null,
            ':address' => $address !== '' ? $address : null,
            ':map_embed' => $mapEmbed !== '' ? $mapEmbed : null,
            ':join_date' => $joinDate !== '' ? $joinDate : null,
            ':status' => $status,
        ]);

        master_distributor_redirect('Distributor berhasil diperbarui.');
    } catch (Throwable $e) {
        master_distributor_redirect('', $e->getMessage());
    }
}

$message = trim((string) ($_GET['message'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$results = [];
$totalRows = 0;
$paginationLinks = [];

if (($pdo ?? null) instanceof PDO) {
    try {
        $whereSql = '';
        $params = [];

        if ($search !== '') {
            $whereSql = "
                WHERE (
                    d.msd_code LIKE :search
                    OR d.msd_name LIKE :search
                    OR d.msd_type LIKE :search
                    OR d.msd_contact LIKE :search
                    OR p.msp_name LIKE :search
                    OR i.msi_name LIKE :search
                )
            ";
            $params[':search'] = '%' . $search . '%';
        }

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM ms_distributors d
            LEFT JOIN ms_province p ON p.msp_code = d.msd_msp_code
            LEFT JOIN ms_island i ON i.msi_code = p.msp_msi_code
            $whereSql
        ");
        $countStmt->execute($params);
        $totalRows = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT
                d.msd_code,
                d.msd_msp_code,
                d.msd_name,
                d.msd_type,
                d.msd_contact,
                d.msd_address,
                d.msd_map_embed,
                d.msd_join_date,
                d.msd_status,
                p.msp_name,
                p.msp_msi_code,
                i.msi_name
            FROM ms_distributors d
            LEFT JOIN ms_province p ON p.msp_code = d.msd_msp_code
            LEFT JOIN ms_island i ON i.msi_code = p.msp_msi_code
            $whereSql
            ORDER BY d.msd_name ASC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $paginationLinks = build_pagination_links($page, max(1, (int) ceil($totalRows / $limit)));
    } catch (PDOException $e) {
        $dbError = 'Gagal memuat data distributor: ' . $e->getMessage();
    }
}

$provinceMap = [];
foreach ($provinces as $province) {
    $provinceCode = (string) ($province['msp_code'] ?? '');
    if ($provinceCode === '') {
        continue;
    }
    $provinceMap[$provinceCode] = $province;
}

$tableColumns = [
    ['label' => 'Code'],
    ['label' => 'Distributor'],
    ['label' => 'Location'],
    ['label' => 'Contact'],
    ['label' => 'Join Date'],
    ['label' => 'Status'],
    ['label' => 'Action', 'class' => 'w-1 text-end'],
];

$tableRows = [];
foreach ($results as $row) {
    $isActive = (string) ($row['msd_status'] ?? 'N') === 'Y';
    $locationLabel = trim((string) ($row['msp_name'] ?? '') . ' / ' . (string) ($row['msi_name'] ?? ''));
    $islandCode = (string) ($row['msp_msi_code'] ?? '');

    $tableRows[] = [
        'cells' => [
            ['html' => '<code>' . master_distributor_esc($row['msd_code'] ?? '') . '</code>'],
            ['html' => '<div class="fw-medium">' . master_distributor_esc($row['msd_name'] ?? '') . '</div><div class="text-secondary small">' . master_distributor_esc($row['msd_type'] ?? '-') . '</div>'],
            ['html' => '<div class="fw-medium">' . master_distributor_esc($locationLabel !== '/' ? $locationLabel : '-') . '</div><div class="text-secondary small">Province code: ' . master_distributor_esc($row['msd_msp_code'] ?? '-') . '</div>'],
            ['html' => '<div>' . master_distributor_esc($row['msd_contact'] ?? '-') . '</div><div class="text-secondary small text-truncate" style="max-width: 18rem;">' . master_distributor_esc($row['msd_address'] ?? '-') . '</div>'],
            ['html' => master_distributor_esc(master_distributor_format_join_date($row['msd_join_date'] ?? null))],
            ['html' => $isActive ? '<span class="badge bg-success-lt text-success">Active</span>' : '<span class="badge bg-secondary-lt text-secondary">Inactive</span>'],
            [
                'html' => '<button type="button" class="btn btn-sm btn-icon btn-outline-secondary edit-distributor-button"'
                    . ' data-code="' . master_distributor_esc($row['msd_code'] ?? '') . '"'
                    . ' data-name="' . master_distributor_esc($row['msd_name'] ?? '') . '"'
                    . ' data-type="' . master_distributor_esc($row['msd_type'] ?? '') . '"'
                    . ' data-contact="' . master_distributor_esc($row['msd_contact'] ?? '') . '"'
                    . ' data-address="' . master_distributor_esc($row['msd_address'] ?? '') . '"'
                    . ' data-map-embed="' . master_distributor_esc($row['msd_map_embed'] ?? '') . '"'
                    . ' data-join-date="' . master_distributor_esc((string) ($row['msd_join_date'] ?? '')) . '"'
                    . ' data-status="' . master_distributor_esc($row['msd_status'] ?? 'N') . '"'
                    . ' data-province-code="' . master_distributor_esc($row['msd_msp_code'] ?? '') . '"'
                    . ' data-province-name="' . master_distributor_esc($row['msp_name'] ?? '') . '"'
                    . ' data-island-code="' . master_distributor_esc($islandCode) . '"'
                    . ' data-island-name="' . master_distributor_esc($row['msi_name'] ?? '') . '"'
                    . ' title="Edit" aria-label="Edit"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h-.01"></path><path d="M3 21l3.75 -1l11.5 -11.5a2.121 2.121 0 0 0 -3 -3l-11.5 11.5l-1 3.75"></path><path d="M13 6l3 3"></path></svg></button>',
                'class' => 'text-end w-1',
            ],
        ],
    ];
}

ob_start();
?>
<form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center">
    <div class="input-group input-group-flat" style="min-width: 360px;">
        <span class="input-group-text">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                <path d="M21 21l-6 -6" />
            </svg>
        </span>
        <input type="search" class="form-control" name="search" placeholder="Search code, distributor, type, contact, province..." value="<?= master_distributor_esc($search) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search !== ''): ?>
        <a href="/master-distributor" class="btn btn-outline-secondary">Reset</a>
    <?php endif; ?>
</form>
<?php
$tableFiltersHtml = (string) ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-primary" id="add-new-btn">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 5l0 14" />
        <path d="M5 12l14 0" />
    </svg>
    Add Distributor
</button>
<?php
$tableToolbarHtml = (string) ob_get_clean();

ob_start();
?>
<div class="row g-2 justify-content-center justify-content-sm-between align-items-center">
    <div class="col-auto d-flex align-items-center">
        <p class="m-0 text-secondary">Showing <strong><?= $totalRows > 0 ? ($offset + 1) : 0 ?></strong> to <strong><?= min($offset + $limit, $totalRows) ?></strong> of <strong><?= $totalRows ?></strong> entries</p>
    </div>
    <?php if (!empty($paginationLinks)): ?>
        <div class="col-auto">
            <ul class="pagination m-0">
                <?php foreach ($paginationLinks as $link): ?>
                    <?php $pageHref = $link['page'] !== null ? '?page=' . $link['page'] . '&search=' . urlencode($search) : '#'; ?>
                    <li class="page-item <?= !empty($link['active']) ? 'active' : '' ?> <?= !empty($link['disabled']) || $link['page'] === null ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= master_distributor_esc($pageHref) ?>"><?= master_distributor_esc($link['label'] ?? '') ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
<?php
$tableFooterHtml = (string) ob_get_clean();

$tableId = 'master-distributor-table';
$tableTitle = 'Master Distributor';
$tableDescription = 'Kelola distributor landing page dengan source data dari database BRIX LP.';
$tableEmptyMessage = 'No distributors found.';

$islandsJson = json_encode($islands, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
$provincesJson = json_encode($provinces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>Master Distributor - BRIX</title>
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
    <link href="/assets/css/data-hub-table.css" rel="stylesheet"/>
    <link href="/assets/css/data-hub-modal.css" rel="stylesheet"/>
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
                            <div class="page-pretitle">BRIX Data Hub</div>
                            <h1 class="page-title">Master Distributor</h1>
                        </div>
                    </div>
                </div>
            </div>
            <main class="page-body">
                <div class="container-xl">
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-success"><?= master_distributor_esc($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger"><?= master_distributor_esc($error) ?></div>
                    <?php endif; ?>
                    <?php if ($dbError !== ''): ?>
                        <div class="alert alert-danger"><?= master_distributor_esc($dbError) ?></div>
                    <?php endif; ?>

                    <div class="row row-cards">
                        <div class="col-12">
                            <?php include __DIR__ . '/../templates/data-hub-table.php'; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="create-distributor-modal" class="modal">
        <div class="modal-content modal-content-lg">
            <h3>Create Distributor</h3>
            <form id="create-distributor-form" method="post" action="/master-distributor">
                <input type="hidden" name="csrf_token" value="<?= master_distributor_esc(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-row">
                            <label for="create-distributor-island">Island</label>
                            <select name="island_code" id="create-distributor-island" required>
                                <option value="">Select island</option>
                                <?php foreach ($islands as $island): ?>
                                    <option value="<?= master_distributor_esc($island['msi_code'] ?? '') ?>"><?= master_distributor_esc($island['msi_name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <label for="create-distributor-province">Province</label>
                            <select name="province_code" id="create-distributor-province" required disabled>
                                <option value="">Select province</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label for="create-distributor-name">Distributor Name</label>
                            <input type="text" name="name" id="create-distributor-name" maxlength="255" required>
                        </div>
                        <div class="form-row">
                            <label for="create-distributor-type">Distributor Type</label>
                            <input type="text" name="type" id="create-distributor-type" maxlength="100" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-row">
                            <label for="create-distributor-contact">Contact</label>
                            <input type="text" name="contact" id="create-distributor-contact" maxlength="50">
                        </div>
                        <div class="form-row">
                            <label for="create-distributor-address">Address</label>
                            <textarea name="address" id="create-distributor-address" rows="4"></textarea>
                        </div>
                        <div class="form-row">
                            <label for="create-distributor-map">Google Map Embed</label>
                            <textarea name="map_embed" id="create-distributor-map" rows="4"></textarea>
                        </div>
                        <div class="form-row">
                            <label for="create-distributor-join-date">Join Date</label>
                            <input type="date" name="join_date" id="create-distributor-join-date">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">Create Distributor</button>
                    <button type="button" id="cancel-create-distributor-btn" class="btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-distributor-modal" class="modal">
        <div class="modal-content modal-content-lg">
            <h3>Edit Distributor</h3>
            <form id="edit-distributor-form" method="post" action="/master-distributor">
                <input type="hidden" name="csrf_token" value="<?= master_distributor_esc(csrf_token()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="code" id="edit-distributor-code">

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-row">
                            <label>Distributor Code</label>
                            <div class="code-preview-box" id="edit-distributor-code-preview">-</div>
                        </div>
                        <div class="form-row">
                            <label>Island</label>
                            <input type="text" id="edit-distributor-island" disabled>
                        </div>
                        <div class="form-row">
                            <label>Province</label>
                            <input type="text" id="edit-distributor-province" disabled>
                        </div>
                        <div class="form-row">
                            <label for="edit-distributor-name">Distributor Name</label>
                            <input type="text" name="name" id="edit-distributor-name" maxlength="255" required>
                        </div>
                        <div class="form-row">
                            <label for="edit-distributor-type">Distributor Type</label>
                            <input type="text" name="type" id="edit-distributor-type" maxlength="100" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-row">
                            <label for="edit-distributor-contact">Contact</label>
                            <input type="text" name="contact" id="edit-distributor-contact" maxlength="50">
                        </div>
                        <div class="form-row">
                            <label for="edit-distributor-address">Address</label>
                            <textarea name="address" id="edit-distributor-address" rows="4"></textarea>
                        </div>
                        <div class="form-row">
                            <label for="edit-distributor-map">Google Map Embed</label>
                            <textarea name="map_embed" id="edit-distributor-map" rows="4"></textarea>
                        </div>
                        <div class="form-row">
                            <label for="edit-distributor-join-date">Join Date</label>
                            <input type="date" name="join_date" id="edit-distributor-join-date">
                        </div>
                        <div class="form-row">
                            <label class="d-flex align-items-center gap-2">
                                <input type="checkbox" id="edit-distributor-active" name="is_active" value="1">
                                <span>Distributor aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">Save Changes</button>
                    <button type="button" id="cancel-edit-distributor-btn" class="btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.masterDistributorData = {
            islands: <?= $islandsJson ?>,
            provinces: <?= $provincesJson ?>,
        };
    </script>
    <script src="/assets/dist/js/tabler.js"></script>
    <script src="/assets/js/master-distributor/modals.js" defer></script>
    <script src="/assets/js/idle-timeout.js" defer></script>
</body>
</html>
