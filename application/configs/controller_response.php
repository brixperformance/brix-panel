<?php

declare(strict_types=1);

function send_text_response(string $message, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function send_text_success(string $message = 'OK'): void
{
    send_text_response($message, 200);
}

function send_text_error(string $message, int $statusCode = 400): void
{
    send_text_response($message, $statusCode);
}
