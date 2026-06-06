<?php

declare(strict_types=1);

namespace Switon\HttpClient;

use Switon\Core\Attribute\Autowired;
use Switon\Core\PathAliasInterface;
use Switon\HttpClient\Exception\ConnectionException;
use Switon\HttpClient\Exception\ConnectTimeoutException;
use Switon\HttpClient\Exception\DnsException;
use Switon\HttpClient\Exception\SslException;
use Switon\HttpClient\Exception\TimeoutException;
use Switon\HttpClient\Exception\UnsupportedProxyException;

use function explode;
use function is_array;
use function is_int;
use function microtime;
use function parse_url;
use function round;
use function substr;
use function constant;
use function defined;

use const CURLE_COULDNT_RESOLVE_HOST;
use const CURLE_COULDNT_RESOLVE_PROXY;
use const CURLE_OPERATION_TIMEDOUT;
use const CURLE_SSL_CACERT;
use const CURLE_SSL_CERTPROBLEM;
use const CURLE_SSL_CONNECT_ERROR;
use const CURLINFO_CONNECT_TIME;
use const CURLINFO_HEADER_SIZE;
use const CURLINFO_PRIMARY_IP;
use const CURLINFO_TOTAL_TIME;

/**
 * Runs one HTTP request using cURL and maps transport errors to typed exceptions.
 *
 * Guidance: Configure proxy protocol handling through `$proxies`; avoid adding scheme branches in `request()`.
 *
 * Road-signs:
 * - proxy URL parsed once per request
 * - scheme lookup in `$proxies`
 * - cURL options `CURLOPT_PROXY*`
 * - unsupported or invalid proxy throws `UnsupportedProxyException`
 * - transport errors map to typed exceptions
 *
 * Use when <code>HttpClient</code> needs:
 * - reusable cURL handles for pooled requests
 * - proxy/TLS/request timeout configuration
 * - transport-level exception mapping (DNS, SSL, timeout, connection)
 *
 * @see \Switon\HttpClient\EngineInterface
 * @see \Switon\HttpClient\HttpClient
 * @see \Switon\HttpClient\Exception
 * @see \Switon\HttpClient\Curl
 * @see \Switon\HttpClient\Request
 * @see \Switon\HttpClient\Response
 * @see \Switon\Core\PathAliasInterface
 */
class Engine implements EngineInterface
{
    #[Autowired] protected PathAliasInterface $pathAlias;
    #[Autowired] protected CurlInterface $curl;

    /**
     * Proxy scheme to cURL proxy type mapping.
     *
     * @var array<string, int>
     */
    #[Autowired] protected array $proxies = [
        'http' => CURLPROXY_HTTP,
        'sock4' => CURLPROXY_SOCKS4,
        'socks4' => CURLPROXY_SOCKS4,
        'sock5' => CURLPROXY_SOCKS5,
        'socks5' => CURLPROXY_SOCKS5,
    ];

    /** Reusable cURL handle kept for connection reuse between pooled requests. */
    protected mixed $handle = null;

    public function __destruct()
    {
        if ($this->handle !== null) {
            $this->curl->close($this->handle);
            $this->handle = null;
        }
    }

    public function __clone()
    {
        if ($this->handle !== null) {
            $this->curl->close($this->handle);
            $this->handle = null;
        }
    }

    /**
     * Parse and validate proxy URL.
     *
     * @return array{scheme: string, host: string, port: int, user?: string, pass?: string}
     */
    protected function parseProxy(string $proxy, Request $request): array
    {
        $parts = parse_url($proxy);
        if (!is_array($parts)) {
            UnsupportedProxyException::raise('Invalid proxy URL: {proxy}', ['proxy' => $proxy, 'request' => $request]);
        }

        $scheme = (string)($parts['scheme'] ?? '');
        $host = (string)($parts['host'] ?? '');
        if ($scheme === '' || $host === '') {
            UnsupportedProxyException::raise('Invalid proxy URL: {proxy}', ['proxy' => $proxy, 'request' => $request]);
        }

        $proxyType = $this->proxies[$scheme] ?? null;
        if (!is_int($proxyType)) {
            UnsupportedProxyException::raise('Unsupported proxy scheme: {scheme}', ['proxy' => $proxy, 'scheme' => $scheme, 'request' => $request]);
        }

        $parts['scheme'] = $scheme;
        $parts['host'] = $host;
        $parts['port'] = (int)($parts['port'] ?? ($proxyType === CURLPROXY_HTTP ? 80 : 1080));

        return $parts;
    }

    public function request(Request $request, ?string $body): Response
    {
        $content = '';
        $header_length = 0;

        if (!$request->hasHeader('Accept-Encoding')) {
            $request->setHeader('Accept-Encoding', 'gzip, deflate');
        }

        if (($curl = $this->handle) === null) {
            $curl = $this->curl->init();
            $this->handle = $curl;
        } else {
            $this->curl->reset($curl);
        }

        try {
            $success = false;

            $this->curl->setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $this->curl->setopt($curl, CURLOPT_AUTOREFERER, true);

            $this->curl->setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            $this->curl->setopt($curl, CURLOPT_MAXREDIRS, 8);

            // GET does not require explicit method options.
            if ($request->getMethod() !== 'GET') {
                if ($request->getMethod() === 'POST') {
                    // Use CURLOPT_POST for POST requests.
                    $this->curl->setopt($curl, CURLOPT_POST, 1);
                    $this->curl->setopt($curl, CURLOPT_POSTFIELDS, $body);
                } else {
                    // Use CURLOPT_CUSTOMREQUEST for all other methods.
                    $this->curl->setopt($curl, CURLOPT_CUSTOMREQUEST, $request->getMethod());

                    // HEAD requests should not include a response body.
                    if ($request->getMethod() === 'HEAD') {
                        $this->curl->setopt($curl, CURLOPT_NOBODY, true);
                    }

                    // Set request body for methods that support payloads.
                    if ($body !== null && !in_array($request->getMethod(), ['HEAD', 'DELETE', 'OPTIONS'], true)) {
                        $this->curl->setopt($curl, CURLOPT_POSTFIELDS, $body);
                    }
                }
            }

            $timeout = $request->getOption('timeout');
            $connectTimeout = $request->getOption('connect_timeout', $timeout);
            $this->curl->setopt($curl, CURLOPT_URL, $request->getUrl());
            $this->curl->setopt($curl, CURLOPT_TIMEOUT, $timeout);
            $this->curl->setopt($curl, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
            $this->curl->setopt($curl, CURLOPT_HEADER, 1);

            if (($proxy = $request->getOption('proxy')) !== null) {
                $parts = $this->parseProxy($proxy, $request);
                $proxyType = (int)$this->proxies[$parts['scheme']];

                $this->curl->setopt($curl, CURLOPT_PROXYTYPE, $proxyType);
                $this->curl->setopt($curl, CURLOPT_PROXYPORT, $parts['port']);
                $this->curl->setopt($curl, CURLOPT_PROXY, $parts['host']);
                if (isset($parts['user'], $parts['pass'])) {
                    $this->curl->setopt($curl, CURLOPT_PROXYUSERNAME, $parts['user']);
                    $this->curl->setopt($curl, CURLOPT_PROXYPASSWORD, $parts['pass']);
                }
            }

            if (($cafile = $request->getOption('cafile')) !== null) {
                $this->curl->setopt($curl, CURLOPT_CAINFO, $this->pathAlias->resolve($cafile));
            }

            if (!$request->getOption('verify_peer')) {
                $this->curl->setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                $this->curl->setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            }

            $headers = [];
            foreach ($request->getHeaders() as $name => $value) {
                $headers[] = is_int($name) ? $value : "$name: $value";
            }
            $this->curl->setopt($curl, CURLOPT_HTTPHEADER, $headers);

            $start_time = microtime(true);

            $content = $this->curl->exec($curl);

            $errno = $this->curl->errno($curl);
            if ($errno === 23 || $errno === 61) {
                $this->curl->setopt($curl, CURLOPT_ENCODING, 'none');
                $content = $this->curl->exec($curl);
                $errno = $this->curl->errno($curl);
            }

            if ($errno) {
                // Split connect timeout from overall request timeout.
                // CURLE_OPERATION_TIMEDOUT = 28
                if ($errno === CURLE_OPERATION_TIMEDOUT || $errno === 28) {
                    $totalTime = $this->curl->getinfo($curl, CURLINFO_TOTAL_TIME);
                    $connectTime = $this->curl->getinfo($curl, CURLINFO_CONNECT_TIME);
                    if ($connectTime > 0 && $totalTime > 0 && $connectTime >= $totalTime - 0.01) {
                        ConnectTimeoutException::raise('Connect timeout ({timeout}s): {url}', ['url' => $request->getUrl(), 'timeout' => $timeout, 'request' => $request]);
                    }
                    TimeoutException::raise('Request timeout ({timeout}s): {url}', ['url' => $request->getUrl(), 'timeout' => $timeout, 'request' => $request]);
                }

                $context = [
                    'url' => $request->getUrl(),
                    'error' => $this->curl->error($curl),
                    'errno' => $errno,
                    'request' => $request,
                ];

                // DNS errors
                if (in_array($errno, [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY], true)) {
                    DnsException::raise('DNS resolution failed: {url}', $context);
                }

                // SSL errors
                $sslErrnos = [CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM, CURLE_SSL_CACERT];
                if (defined('CURLE_PEER_FAILED_VERIFICATION')) {
                    $sslErrnos[] = (int)constant('CURLE_PEER_FAILED_VERIFICATION');
                }
                if (in_array($errno, $sslErrnos, true)) {
                    SslException::raise('SSL error: {url} - {error}', $context);
                }

                // Other connection errors
                ConnectionException::raise('Connection failed: {url} - {error}', $context);
            }

            $header_length = $this->curl->getinfo($curl, CURLINFO_HEADER_SIZE);
            $request->markCompleted(
                (string)$this->curl->getinfo($curl, CURLINFO_PRIMARY_IP),
                round(microtime(true) - $start_time, 3)
            );

            $success = true;
        } finally {
            if (!$success) {
                $this->curl->close($curl);
                $this->handle = null;
            }
        }

        if (!is_string($content)) {
            $content = '';
        }

        $response_headers = explode("\r\n", substr($content, 0, $header_length - 4));
        $response_body = substr($content, $header_length);

        return new Response($request, $response_headers, $response_body);
    }
}
