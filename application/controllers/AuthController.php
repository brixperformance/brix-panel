<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../models/Execute.php';
require_once __DIR__ . '/../models/View.php';

$config = require_once __DIR__ . '/../configs/database.php';

$view = new AdminView($config);

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Username or password is required.';
    header("Location: /login");
    exit;
}

$result = $view->getAdminByUsername($username);

if ($result['status'] && $result['data']) {
    $user = $result['data'];

    if (password_verify($password, $user['msa_password'])) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['msa_id'];
        $_SESSION['username'] = $user['msa_username'];
        header("Location: /dashboard");
        exit;
    }
}

$dbConfigured = !empty($config['host']) && !empty($config['dbname']) && !empty($config['username']);
if (!$dbConfigured && $username === 'admin' && $password === 'admin123') {
    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = 0;
    $_SESSION['username'] = 'admin';
    $_SESSION['auth_mode'] = 'demo';
    header("Location: /dashboard");
    exit;
}

$_SESSION['error'] = $dbConfigured
    ? 'Login failed. Please check your credentials.'
    : 'Login failed. Demo access is available with admin / admin123 until DB is configured.';
header("Location: /login");
exit;
