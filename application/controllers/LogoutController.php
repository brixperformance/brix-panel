<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_SESSION['logged_in'])) {
    session_unset();
    session_destroy();
}

header("Location: /login");
exit;
