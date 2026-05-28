<?php

declare(strict_types=1);

function ensure_page_session_timeout(int $timeoutSeconds = 900): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        return;
    }

    if ((time() - (int) $_SESSION['last_activity']) > $timeoutSeconds) {
        session_unset();
        session_destroy();
        header('Location: /login?timeout=1');
        exit();
    }

    $_SESSION['last_activity'] = time();
}
