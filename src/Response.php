<?php

declare(strict_types=1);

namespace Switon\HttpClient;

use JsonSerializable;
use Stringable;
use Switon\Core\Exception\RuntimeException;
use Switon\Core\Json;
use Switon\HttpClient\Exception\CharsetConversionException;
use Switon\HttpClient\Exception\DecompressionException;
use Switon\HttpClient\Exception\InvalidJsonException;
use Switon\HttpClient\Response\Cookie;

use function array_slice;
use function count;
use function is_array;
use function str_starts_with;
use function strcasecmp;
use function strlen;
use function strpos;
use function strtoupper;
use function substr;
use function time;
use function trim;

/**
 * Represents one HTTP response with parsed headers, status metadata, and body helpers.
 *
 * Use when callers need:
 * - decompressed response body access (gzip/deflate)
 * - JSON parsing and validation via <code>getJsonBody()</code>
 * - structured headers/cookies extracted from raw response headers
 *
 * Guidance: Read response data through <code>json()</code>, <code>text()</code>, <code>status()</code>, and
 * <code>header()</code>; keep terminal-header handling in `Response` after `CURLOPT_FOLLOWLOCATION`.
 * Guidance: The response collapses redirect/interim header chains to the terminal hop only.
 *
 * Road-signs:
 * - multiple HTTP status lines collapse to the last header block
 * - interim 1xx and redirect 3xx chains end at the terminal response
 * - `getLastHeaders()` extracts the terminal hop only
 * - `getUtf8Body()` handles charset conversion from `Content-Type`
 * - `__toString()` falls back to the raw body on charset conversion failure
 * - `getJsonBody()` validates parsed payload shape
 *
 * @see \Switon\HttpClient\Request
 * @see \Switon\HttpClient\HttpClient
 * @see \Switon\HttpClient\Response\Cookie
 * @see \Switon\HttpClient\Exception\ResponseException Related failure base
 * @see \Switon\HttpClient\Exception\DecompressionException Related failure path
 * @see \Switon\HttpClient\Exception\CharsetConversionException Related failure path
 * @see \Switon\HttpClient\Exception\InvalidJsonException Related failure path
 */
class Response implements JsonSerializable, Stringable
{
    protected string $url;
    protected float $process_time;
    protected string $remote_ip;
    protected int $http_code;
    /** @var array<int, string> */
    protected array $headers = [];
    protected string $content_type;
    protected string $body;
    protected Request $request;
    /** @var null|array<int|string, mixed> */
    protected ?array $jsonBody = null;

    /**
     * @param array<int, string> $headers
     */
    public function __construct(Request $request, array $headers, string $body)
    {
        if ($this->hasMultipleStatusLines($headers)) {
            $headers = $this->getLastHeaders($headers);
        }

        $this->request = $request;
        $this->url = $request->getUrl();
        $this->remote_ip = $request->getRemoteIp();
        $this->process_time = $request->getProcessTime();
        $this->headers = $headers;

        $content_type = null;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $content_type = trim(substr($header, 13));
                break;
            }
        }
        $this->content_type = $content_type ?? '';

        $http_code = null;
        if ($headers && preg_match('#\d{3}#', $headers[0], $match)) {
            $http_code = (int)$match[0];
        }
        $this->http_code = $http_code ?? 200;

        // Set body first, then decode if needed
        // This ensures Response object is fully constructed before throwing exceptions
        $this->body = $body;

        if ($body !== '') {
            $content_encoding = null;
            foreach ($headers as $header) {
                if (stripos($header, 'Content-Encoding:') === 0) {
                    $content_encoding = trim(substr($header, 17));
                    break;
                }
            }

            if ($content_encoding === 'gzip') {
                $decoded = $this->decodeCompressedBody(
                    $body,
                    static fn (string $value): string|false => gzdecode($value)
                );
                if ($decoded === false) {
                    DecompressionException::raise('Failed to unzip response from "{url}".', [
                        'url' => $request->getUrl(),
                        'request' => $request,
                        'response' => $this,
                    ]);
                } else {
                    $this->body = $decoded;
                }
            } elseif ($content_encoding === 'deflate') {
                $decoded = $this->decodeCompressedBody(
                    $body,
                    static fn (string $value): string|false => gzinflate($value)
                );
                if ($decoded === false) {
                    DecompressionException::raise('Failed to deflate response from "{url}".', [
                        'url' => $request->getUrl(),
                        'request' => $request,
                        'response' => $this,
                    ]);
                } else {
                    $this->body = $decoded;
                }
            }
        }
    }

    /**
     * Detect multi-hop cURL header output.
     *
     * @param array<int, string> $headers
     */
    protected function hasMultipleStatusLines(array $headers): bool
    {
        $count = 0;
        foreach ($headers as $header) {
            if (str_starts_with($header, 'HTTP/')) {
                $count++;
                if ($count > 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Run native decompression; rely on <code>false</code> return and suppress stray warnings from extensions.
     */
    protected function decodeCompressedBody(string $body, callable $decoder): string|false
    {
        $decoded = @$decoder($body);

        return $decoded === false ? false : (string)$decoded;
    }

    /**
     * @param array<int, string> $headers
     *
     * @return array<int, string>
     */
    protected function getLastHeaders(array $headers): array
    {
        for ($i = count($headers) - 1; $i >= 0; $i--) {
            $header = $headers[$i];
            if (str_starts_with($header, 'HTTP/')) {
                return $i === 0 ? $headers : array_slice($headers, $i);
            }
        }

        return [];
    }

    /**
     * Return the final request URL associated with this response.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Return total transport time reported by the request.
     */
    public function getProcessTime(): float
    {
        return $this->process_time;
    }

    /**
     * Return the remote IP reported by the transport layer.
     */
    public function getRemoteIp(): string
    {
        return $this->remote_ip;
    }

    /**
     * Return the terminal HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->http_code;
    }

    /**
     * Shorthand for getStatusCode().
     */
    public function status(): int
    {
        return $this->getStatusCode();
    }

    /**
     * @return array<int, string>
     */
    public function getRawHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<int, string>
     */
    public function rawHeaders(): array
    {
        return $this->getRawHeaders();
    }

    /**
     * Return the terminal Content-Type header value.
     */
    public function getContentType(): string
    {
        return $this->content_type;
    }

    /**
     * Return the decoded response body.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Return the UTF-8-normalized response body.
     */
    public function text(): string
    {
        return $this->getUtf8Body();
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as $header) {
            if (($pos = strpos($header, ':')) === false) {
                continue;
            }

            $name = substr($header, 0, $pos);
            $value = trim(substr($header, $pos + 1));
            if (isset($headers[$name])) {
                if (!is_array($headers[$name])) {
                    $headers[$name] = [$headers[$name], $value];
                } else {
                    $headers[$name][] = $value;
                }
            } else {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Read one header value case-insensitively.
     */
    public function header(string $name, mixed $default = null): mixed
    {
        foreach ($this->getHeaders() as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @return array<string, Cookie>
     */
    public function getCookies(): array
    {
        $cookies = [];
        foreach ($this->headers as $header) {
            if (stripos($header, 'Set-Cookie:') !== 0) {
                continue;
            }

            $cookie = new Cookie(trim(substr($header, strlen('Set-Cookie:'))));
            if ($cookie->expires !== null && $cookie->expires < time()) {
                continue;
            }

            $cookies[$cookie->name] = $cookie;
        }

        return $cookies;
    }

    /**
     * Return the originating request object.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getJsonBody(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $data = Json::parse($this->body);
        if (!is_array($data)) {
            InvalidJsonException::raise('Response from {url} is not valid JSON: {cut_body}', [
                'url' => $this->url,
                'cut_body' => substr($this->body, 0, 128),
                'request' => $this->request,
                'response' => $this,
            ]);
        }

        $this->jsonBody = $data;

        return $this->jsonBody;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function json(): array
    {
        return $this->getJsonBody();
    }

    public function getUtf8Body(): string
    {
        $body = $this->body;

        if (preg_match('#charset=([\w\-]+)#i', $this->content_type, $match) === 1) {
            $charset = strtoupper($match[1]);
            if ($charset !== 'UTF-8' && $charset !== 'UTF8') {
                $body = $this->convertToUtf8($body, $charset);
            }
        }

        return $body;
    }

    protected function convertToUtf8(string $body, string $charset): string
    {
        $converted = @iconv($charset, 'UTF-8', $body);

        if ($converted === false) {
            CharsetConversionException::raise('Failed to convert response from "{url}" from {charset} to UTF-8.', [
                'url' => $this->url,
                'charset' => $charset,
                'request' => $this->request,
                'response' => $this,
            ]);
        }

        return (string)$converted;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'url' => $this->url,
            'process_time' => $this->process_time,
            'remote_ip' => $this->remote_ip,
            'http_code' => $this->http_code,
            'headers' => $this->headers,
            'content_type' => $this->content_type,
            'body' => $this->body,
            'request' => $this->request,
        ];
    }

    public function __toString(): string
    {
        try {
            return $this->getUtf8Body();
        } catch (CharsetConversionException) {
            return $this->body;
        }
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'url' => $this->url,
            'process_time' => $this->process_time,
            'remote_ip' => $this->remote_ip,
            'http_code' => $this->http_code,
            'headers' => $this->headers,
            'content_type' => $this->content_type,
            'body' => $this->body,
            default => RuntimeException::raise('Undefined response property: {property}', ['property' => $name]),
        };
    }

    public function __set(string $name, mixed $value): void
    {
        RuntimeException::raise('Response property "{property}" is read-only from outside the response.', ['property' => $name]);
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'url', 'process_time', 'remote_ip', 'http_code', 'headers', 'content_type', 'body' => true,
            default => false,
        };
    }
}
