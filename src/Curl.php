<?php

declare(strict_types=1);

namespace Switon\HttpClient;

use CurlHandle;

use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_reset;
use function curl_setopt;

/**
 * Implements <code>CurlInterface</code> by delegating directly to PHP cURL functions.
 *
 * Use when production runtime should use native cURL behavior with no extra logic.
 *
 * @see \Switon\HttpClient\CurlInterface
 * @see \Switon\HttpClient\Engine
 */
class Curl implements CurlInterface
{
    public function init(): false|CurlHandle
    {
        return curl_init();
    }

    public function reset(mixed $handle): void
    {
        curl_reset($handle);
    }

    public function setopt(mixed $handle, int $option, mixed $value): bool
    {
        return curl_setopt($handle, $option, $value);
    }

    public function exec(mixed $handle): string|bool
    {
        return curl_exec($handle);
    }

    public function errno(mixed $handle): int
    {
        return curl_errno($handle);
    }

    public function error(mixed $handle): string
    {
        return curl_error($handle);
    }

    public function getinfo(mixed $handle, int $option = 0): mixed
    {
        return curl_getinfo($handle, $option);
    }

    public function close(mixed $handle): void
    {
        // PHP 8.0+ closes cURL handles automatically; explicit curl_close() is a deprecated no-op on 8.5.
    }
}
