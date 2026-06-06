<?php

declare(strict_types=1);

namespace Switon\HttpClient;

/**
 * Defines a thin abstraction over PHP cURL operations.
 *
 * Use when transport code needs injectable cURL calls for testing or substitution.
 *
 * @see \Switon\HttpClient\Curl
 * @see \Switon\HttpClient\Engine
 * @see \Switon\HttpClient\EngineInterface Typical consumer
 */
interface CurlInterface
{
    public function init(): mixed;

    public function reset(mixed $handle): void;

    public function setopt(mixed $handle, int $option, mixed $value): bool;

    public function exec(mixed $handle): string|bool;

    public function errno(mixed $handle): int;

    public function error(mixed $handle): string;

    public function getinfo(mixed $handle, int $option = 0): mixed;

    public function close(mixed $handle): void;
}
