<?php

declare(strict_types=1);

namespace Switon\HttpClient;

use JsonSerializable;
use Switon\Core\Exception\RuntimeException;

use function count;
use function http_build_query;
use function is_array;
use function is_string;
use function str_contains;
use function strcasecmp;

/**
 * Holds normalized outbound request data for the HTTP client engine.
 *
 * Use when transport layers need one mutable object containing:
 * method, final URL, headers, body, and execution options.
 *
 * Guidance: Treat request instances as transport state; mutate them through request methods instead of direct
 * property writes.
 *
 * URL supports <code>string|array</code>: array form uses index <code>0</code> as
 * base URL and remaining entries as query parameters.
 *
 * @see \Switon\HttpClient\HttpClient
 * @see \Switon\HttpClient\EngineInterface Typical consumer
 * @see \Switon\HttpClient\RequestOptions
 * @see \Switon\HttpClient\Response
 * @see \Switon\HttpClient\FileInterface
 * @see \Switon\HttpClient\LocalFile
 * @see \Switon\HttpClient\MemoryFile
 */
class Request implements JsonSerializable
{
    protected string $method;
    protected string $url;
    /** @var array<int|string, mixed> */
    protected array $headers;
    /** @var null|string|array<int|string, mixed> */
    protected null|string|array $body;
    /** @var array<string, mixed> */
    protected array $options;
    protected float $process_time = 0.0;
    protected string $remote_ip = '';

    /**
     * @param string|array<int|string, mixed> $url
     * @param null|string|array<int|string, mixed> $body
     * @param array<int|string, mixed> $headers
     * @param array<string, mixed> $options
     */
    public function __construct(
        string            $method,
        string|array      $url,
        null|string|array $body,
        array             $headers,
        array             $options
    ) {
        $this->method = $method;

        if (is_array($url)) {
            if (count($url) > 1) {
                $uri = $url[0];
                unset($url[0]);
                $url = $uri . (str_contains($uri, '?') ? '&' : '?') . http_build_query($url);
            } else {
                $url = $url[0];
            }
        }
        $this->url = $url;

        $this->body = $body;
        $this->headers = [];
        foreach ($headers as $name => $value) {
            if (is_string($name)) {
                $this->setHeader($name, $value);
            } else {
                $this->headers[$name] = $value;
            }
        }
        $this->options = $options;
    }

    protected function resolveHeaderKey(string $name): ?string
    {
        foreach ($this->headers as $headerName => $value) {
            if (is_string($headerName) && strcasecmp($headerName, $name) === 0) {
                return $headerName;
            }
        }

        return null;
    }

    /**
     * Return the normalized HTTP method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Return the final request URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Read one header value case-insensitively.
     */
    public function getHeader(string $name, mixed $default = null): mixed
    {
        $key = $this->resolveHeaderKey($name);

        return $key !== null ? $this->headers[$key] : $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->getHeader($name, $default);
    }

    /**
     * Check header presence case-insensitively.
     */
    public function hasHeader(string $name): bool
    {
        return $this->resolveHeaderKey($name) !== null;
    }

    /**
     * Set one header and collapse case-insensitive duplicates.
     */
    public function setHeader(int|string $name, mixed $value): static
    {
        if (is_string($name) && ($existing = $this->resolveHeaderKey($name)) !== null && $existing !== $name) {
            unset($this->headers[$existing]);
        }

        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Remove one header case-insensitively.
     */
    public function removeHeader(string $name): static
    {
        if (($key = $this->resolveHeaderKey($name)) !== null) {
            unset($this->headers[$key]);
        }

        return $this;
    }

    /**
     * @return null|string|array<int|string, mixed>
     */
    public function getBody(): null|string|array
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Read one request option.
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->getOption($key, $default);
    }

    /**
     * Set one request option.
     */
    public function setOption(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Disable TLS peer verification for this request.
     */
    public function disablePeerVerification(): static
    {
        $this->options['verify_peer'] = false;

        return $this;
    }

    /**
     * Return transport process time recorded after completion.
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
     * Record transport completion metadata on the request.
     */
    public function markCompleted(string $remoteIp, float $processTime): static
    {
        $this->remote_ip = $remoteIp;
        $this->process_time = $processTime;

        return $this;
    }

    /**
     * Build multipart/form-data payload from the current body array.
     */
    public function buildMultipart(string $boundary): string
    {
        if (!is_array($this->body)) {
            return '';
        }

        $data = '';
        foreach ($this->body as $k => $v) {
            $part = "--$boundary\r\n";

            if ($v instanceof FileInterface) {
                $postName = $v->getPostName();
                $mimeType = $v->getMimeType();
                $part .= "Content-Disposition: form-data; name=\"$k\"; filename=\"$postName\"\r\n";
                $part .= "Content-Type: $mimeType\r\n\r\n";
                $part .= $v->getContent();
            } else {
                $part .= "Content-Disposition: form-data; name=\"$k\"\r\n\r\n";
                $part .= $v;
            }

            $data .= "$part\r\n";
        }

        return $data . "--$boundary--\r\n";
    }

    /**
     * Check whether the current body contains multipart file payloads.
     */
    public function hasFile(): bool
    {
        if (!is_array($this->body)) {
            return false;
        }
        $contentType = $this->getHeader('Content-Type');
        if (is_string($contentType) && !str_contains($contentType, 'multipart/form-data')) {
            return false;
        }

        foreach ($this->body as $v) {
            if ($v instanceof FileInterface) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'method' => $this->method,
            'url' => $this->url,
            'headers' => $this->headers,
            'body' => $this->body,
            'options' => $this->options,
            'process_time' => $this->process_time,
            'remote_ip' => $this->remote_ip,
        ];
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'method' => $this->method,
            'url' => $this->url,
            'headers' => $this->headers,
            'body' => $this->body,
            'options' => $this->options,
            'process_time' => $this->process_time,
            'remote_ip' => $this->remote_ip,
            default => RuntimeException::raise('Undefined request property: {property}', ['property' => $name]),
        };
    }

    public function __set(string $name, mixed $value): void
    {
        match ($name) {
            'process_time' => $this->markCompleted($this->remote_ip, (float)$value),
            'remote_ip' => $this->markCompleted((string)$value, $this->process_time),
            default => RuntimeException::raise('Request property "{property}" is read-only from outside the request.', ['property' => $name]),
        };
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'method', 'url', 'headers', 'body', 'options', 'process_time', 'remote_ip' => true,
            default => false,
        };
    }
}
