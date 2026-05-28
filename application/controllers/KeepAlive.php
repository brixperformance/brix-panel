<?php

require_once __DIR__ . '/../configs/json_response.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['last_activity'] = time();
send_json_response(['ok' => true, 'ts' => time()]);
