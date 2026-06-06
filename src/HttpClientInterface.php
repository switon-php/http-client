<?php

declare(strict_types=1);

namespace Switon\HttpClient;

/**
 * Contract for outgoing HTTP calls in app and framework services.
 *
 * Use when you need:
 * - JSON-first shortcut methods for common API requests
 * - `RequestOptions` overrides for timeout, proxy, and TLS behavior
 * - `Response` helpers such as `json()`, `text()`, `status()`, and `header()`
 * - typed exceptions for transport, HTTP status, and response parsing failures
 *
 * Guidance: Prefer shortcut methods for JSON APIs and pass request-specific transport overrides through
 * `RequestOptions`; use `request()` only when you need a raw method call without JSON response enforcement.
 * Guidance: URL arrays use index `0` as the base URL and remaining entries as query parameters.
 *
 * Road-signs:
 * - HttpClient
 * - RequestOptions
 * - Request/Response
 * - RequestException / HttpStatusException / ResponseException
 * - HttpClient\Event\*
 *
 * @see \Switon\HttpClient\HttpClient
 * @see \Switon\HttpClient\Request
 * @see \Switon\HttpClient\RequestOptions
 * @see \Switon\HttpClient\Response
 * @see \Switon\HttpClient\EngineInterface
 * @see \Switon\HttpClient\Exception
 * @see \Switon\HttpClient\Exception\RequestException
 * @see \Switon\HttpClient\Exception\HttpStatusException
 * @see \Switon\HttpClient\Exception\ResponseException
 */
interface HttpClientInterface
{
    public const string HEADER_USER_AGENT = 'User-Agent';
    public const string HEADER_CONTENT_TYPE = 'Content-Type';
    public const string HEADER_CONTENT_LENGTH = 'Content-Length';
    public const string HEADER_ACCEPT = 'Accept';
    public const string HEADER_ACCEPT_ENCODING = 'Accept-Encoding';
    public const string HEADER_ACCEPT_CHARSET = 'Accept-Charset';
    public const string HEADER_X_REQUESTED_WITH = 'X-Requested-With';
    public const string HEADER_X_REQUEST_ID = 'X-Request-Id';
    public const string HEADER_AUTHORIZATION = 'Authorization';
    public const string HEADER_COOKIE = 'Cookie';
    public const string HEADER_HOST = 'Host';
    public const string HEADER_REFERER = 'Referer';
    public const string HEADER_ORIGIN = 'Origin';
    public const string HEADER_CACHE_CONTROL = 'Cache-Control';
    public const string USER_AGENT_CHROME = 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36';
    public const string USER_AGENT_FIREFOX = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0';
    public const string CONTENT_TYPE_FORM = 'application/x-www-form-urlencoded; charset=UTF-8';

    /**
     * Sends a raw request without forcing JSON response parsing.
     *
     * @param string $method
     * @param string|array<int|string, mixed> $url
     * @param string|array<int|string, mixed> $body
     * @param array<string, mixed> $headers
     *
     * @return Response Parsed response wrapper without JSON enforcement
     */
    public function request(
        string            $method,
        string|array      $url,
        null|string|array $body = null,
        array             $headers = [],
        ?RequestOptions   $options = null
    ): Response;

    /**
     * Sends a HEAD request.
     *
     * @param string|array<int|string, mixed> $url
     * @param array<string, mixed> $headers
     */
    public function head(string|array $url, array $headers = [], ?RequestOptions $options = null): Response;

    /**
     * Sends a JSON request and parses the JSON response.
     *
     * @param string|array<int|string, mixed> $url
     * @param array<string, mixed> $headers
     */
    public function get(string|array $url, array $headers = [], ?RequestOptions $options = null): Response;

    /**
     * Sends a JSON POST request.
     *
     * @param string|array<int|string, mixed> $url
     * @param string|array<int|string, mixed> $body
     * @param array<string, mixed> $headers
     */
    public function post(string|array $url, string|array $body = [], array $headers = [], ?RequestOptions $options = null): Response;

    /**
     * Sends a JSON DELETE request.
     *
     * @param string|array<int|string, mixed> $url
     * @param array<string, mixed> $headers
     */
    public function delete(string|array $url, array $headers = [], ?RequestOptions $options = null): Response;

    /**
     * Sends a JSON PUT request.
     *
     * @param string|array<int|string, mixed> $url
     * @param string|array<int|string, mixed> $body
     * @param array<string, mixed> $headers
     */
    public function put(string|array $url, string|array $body = [], array $headers = [], ?RequestOptions $options = null): Response;

    /**
     * Sends a JSON PATCH request.
     *
     * @param string|array<int|string, mixed> $url
     * @param string|array<int|string, mixed> $body
     * @param array<string, mixed> $headers
     */
    public function patch(string|array $url, string|array $body = [], array $headers = [], ?RequestOptions $options = null): Response;
}
