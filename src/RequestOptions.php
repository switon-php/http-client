<?php

declare(strict_types=1);

namespace Switon\HttpClient;

use function is_float;
use function is_int;

/**
 * Documents and normalizes per-request HTTP client options.
 *
 * Use when callers want one named entrypoint for request options while keeping the runtime payload as a plain
 * array passed to transport layers.
 *
 * Supported keys:
 * - <code>timeout</code>: overall request timeout in seconds
 * - <code>connect_timeout</code>: connection-establishment timeout in seconds
 * - <code>proxy</code>: proxy URL for this request
 * - <code>cafile</code>: CA file path for TLS verification
 * - <code>verify_peer</code>: whether TLS peer verification stays enabled
 *
 * @see \Switon\HttpClient\HttpClient
 * @see \Switon\HttpClient\HttpClientInterface
 * @see \Switon\HttpClient\Request
 */
class RequestOptions
{
    public const string TIMEOUT = 'timeout';
    public const string CONNECT_TIMEOUT = 'connect_timeout';
    public const string PROXY = 'proxy';
    public const string CAFILE = 'cafile';
    public const string VERIFY_PEER = 'verify_peer';

    /** @var array<string, mixed> */
    protected array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * @param array<string, mixed>|int|float $options
     */
    public static function of(array|int|float $options = []): static
    {
        if (is_int($options) || is_float($options)) {
            $options = [self::TIMEOUT => $options];
        }

        return new static($options);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->options;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
