<?php

declare(strict_types=1);

require_once __DIR__ . '/page_session.php';

function bootstrap_page(?string $controllerRelativePath = null, bool $enforceTimeout = true): array
{
    if ($enforceTimeout) {
        ensure_page_session_timeout();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if ($controllerRelativePath === null || $controllerRelativePath === '') {
        return [];
    }

    return load_page_controller_scope($controllerRelativePath);
}

function load_page_controller_scope(string $controllerRelativePath): array
{
    $controllerPath = dirname(__DIR__, 2) . $controllerRelativePath;

    return (static function (string $__controllerPath): array {
        require $__controllerPath;
        $scope = get_defined_vars();
        unset($scope['__controllerPath']);
        return $scope;
    })($controllerPath);
}
