<?php

declare(strict_types=1);

function send_json_response(array $payload, int $statusCode = 200, int $flags = 0): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, $flags);
    exit;
}

function send_json_error(string $message, int $statusCode = 400, array $extra = [], int $flags = 0): void
{
    send_json_response(array_merge($extra, ['error' => $message]), $statusCode, $flags);
}
