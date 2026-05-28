<?php

declare(strict_types=1);

function app_string_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }

    if ($value === '') {
        return 0;
    }

    return preg_match_all('/./us', $value, $matches) ?: strlen($value);
}

function app_string_lower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value);
    }

    return strtolower($value);
}
