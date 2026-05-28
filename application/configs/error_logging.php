<?php

declare(strict_types=1);

function initialize_error_logging(?string $logFile = null): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    $root = dirname(__DIR__, 2);
    $logDir = $root . '/.run';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }

    $target = $logFile ?? ($logDir . '/php-error.log');

    ini_set('log_errors', '1');
    ini_set('display_errors', '0');
    ini_set('error_log', $target);
    error_reporting(E_ALL);
}

function app_log_message(string $message): void
{
    initialize_error_logging();
    error_log($message);
}

function app_log_exception(Throwable $throwable, string $context = ''): void
{
    $prefix = $context !== '' ? '[' . $context . '] ' : '';
    app_log_message($prefix . $throwable->getMessage() . ' in ' . $throwable->getFile() . ':' . $throwable->getLine());
}
