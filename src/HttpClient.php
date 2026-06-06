<?php

declare(strict_types=1);

namespace Switon\HttpClient;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\NonCloneableException;
use Switon\Core\Json;
use Switon\HttpClient\Event\HttpClientComplete;
use Switon\HttpClient\Event\HttpClientError;
use Switon\HttpClient\Event\HttpClientRequested;
use Switon\HttpClient\Event\HttpClientRequesting;
use Switon\HttpClient\Event\HttpClientStart;
use Switon\HttpClient\Event\HttpClientSuccess;
use Switon\HttpClient\Exception\BadGatewayException;
use Switon\HttpClient\Exception\BadRequestException;
use Switon\HttpClient\Exception\BadResponseException;
use Switon\HttpClient\Exception\ClientErrorException;
use Switon\HttpClient\Exception\ForbiddenException;
use Switon\HttpClient\Exception\GatewayTimeoutException;
use Switon\HttpClient\Exception\InternalServerErrorException;
use Switon\HttpClient\Exception\NotFoundException;
use Switon\HttpClient\Exception\RedirectionException;
use Switon\HttpClient\Exception\ServerErrorException;
use Switon\HttpClient\Exception\ServiceUnavailableException;
use Switon\HttpClient\Exception\TooManyRequestsException;
use Switon\HttpClient\Exception\UnauthorizedException;
use Switon\Pooling\PoolManagerInterface;
use Throwable;

use function bin2hex;
use function explode;
use function http_build_query;
use function is_array;
use function is_int;
use function is_string;
use function microtime;
use function random_bytes;
use function round;
use function str_contains;
use function str_ends_with;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Executes outbound HTTP requests with JSON-first defaults and status-aware exceptions.
 *
 * Use when you need:
 * - JSON-first shortcut helpers (<code>get/post/put/patch/delete</code>) for API requests
 * - raw header checks through <code>head()</code> without JSON response enforcement
 * - multipart uploads through <code>FileInterface</code> values
 * - event hooks across request lifecycle stages
 * - pooled engine reuse for repeated calls to the same host
 *
 * Configure with:
 * - transport options: <code>timeout</code>, <code>connect_timeout</code>
 * - TLS options: <code>verify_peer</code>, <code>cafile</code>
 * - network options: <code>proxy</code>, <code>pool_size</code>
 *
 * Guidance: Shortcut methods enforce JSON response expectations except for <code>head()</code> and raw <code>request()</code>.
 *
 * @see \Switon\HttpClient\HttpClientInterface
 * @see \Switon\HttpClient\Request
 * @see \Switon\HttpClient\Response
 * @see \Switon\HttpClient\EngineInterface
 * @see \Switon\HttpClient\EngineInterface::request()
 * @see \Switon\HttpClient\CurlInterface
 * @see \Switon\HttpClient\Event\HttpClientStart
 * @see \Switon\HttpClient\Event\HttpClientSuccess
 * @see \Switon\HttpClient\Event\HttpClientError
 * @see \Switon\HttpClient\Event\HttpClientComplete
 * @see \Switon\HttpClient\Exception\ConnectionException
 */
class HttpClient implements HttpClientInterface
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    /** @var PoolManagerInterface<HttpClient, EngineInterface> */
    #[Autowired] protected PoolManagerInterface $poolManager;

    /** Default proxy URL (http/sock4/socks4/sock5/socks5) when request options do not override it. */
    #[Autowired] protected ?string $proxy;

    /** CA certificate file path used for TLS peer verification. */
    #[Autowired] protected ?string $cafile;

    /** Default total request timeout in seconds. */
    #[Autowired] protected int $timeout = 3;

    /** Default connection-establishment timeout in seconds. */
    #[Autowired] protected int $connect_timeout = 1;

    /** Whether TLS peer and host verification is enabled by default. */
    #[Autowired] protected bool $verify_peer = true;

    /** Engine pool size per host identifier. */
    #[Autowired] protected int $pool_size = 4;

    public function __clone()
    {
        NonCloneableException::raise('{class} cannot be cloned, use dependency injection instead', ['class' => static::class]);
    }

    /**
     * Send one HTTP request.
     *
     * @param string $method
     * @param string|array<int|string, mixed> $url
     * @param null|string|array<int|string, mixed> $body
     * @param array<int|string, mixed> $headers
     */
    public function request(
        string            $method,
        string|array      $url,
        null|string|array $body = null,
        array             $headers = [],
        ?RequestOptions   $options = null
    ): Response {
        return $this->performRequest($method, $url, $body, $headers, $options);
    }

    /**
     * Build and send one normalized request.
     *
     * @param string $method
     * @param string|array<int|string, mixed> $url
     * @param null|string|array<int|string, mixed> $body
     * @param array<int|string, mixed> $headers
     */
    protected function performRequest(
        string            $method,
        string|array      $url,
        null|string|array $body,
        array             $headers,
        ?RequestOptions   $options,
        ?callable         $responseHandler = null
    ): Response {
        $request = $this->createRequest($method, $url, $body, $headers, $options);
        $this->eventDispatcher->dispatch(new HttpClientStart($this, $method, $url, $request));

        $response = null;
        $elapsed = 0.0;
        $overall_start_time = microtime(true);

        try {
            $response = $this->sendRequest($request, $method, $url, $overall_start_time);
            $this->assertSuccessfulResponse($response);
            if ($responseHandler !== null) {
                $response = $responseHandler($response);
            }
            $elapsed = $this->getElapsed($overall_start_time);
            $this->eventDispatcher->dispatch(new HttpClientSuccess($this, $method, $url, $request, $response, $elapsed));

            return $response;
        } catch (Throwable $exception) {
            $elapsed = $this->getElapsed($overall_start_time);
            $this->eventDispatcher->dispatch(new HttpClientError($this, $method, $url, $request, $response, $exception, $elapsed));

            throw $exception;
        } finally {
            $elapsed = $elapsed > 0 ? $elapsed : $this->getElapsed($overall_start_time);
            $this->eventDispatcher->dispatch(new HttpClientComplete($this, $method, $url, $request, $response, $elapsed));
        }
    }

    /**
     * Send a request with JSON body and JSON response expectations.
     *
     * Array body is JSON-encoded. String body is auto-promoted to JSON only when it looks like a JSON object or
     * array payload; scalar JSON values must be sent with an explicit Content-Type header.
     *
     * @param string $method
     * @param string|array<int|string, mixed> $url
     * @param string|array<int|string, mixed> $body
     * @param array<int|string, mixed> $headers
     */
    protected function requestJson(
        string            $method,
        string|array      $url,
        null|string|array $body = null,
        array             $headers = [],
        ?RequestOptions   $options = null
    ): Response {
        $headers[self::HEADER_ACCEPT] ??= 'application/json';
        $headers[self::HEADER_X_REQUESTED_WITH] ??= 'XMLHttpRequest';
        $headers[self::HEADER_ACCEPT_ENCODING] ??= 'gzip, deflate';

        if ($body !== null) {
            if (is_string($body)) {
                if (!isset($headers[self::HEADER_CONTENT_TYPE])) {
                    $headers[self::HEADER_CONTENT_TYPE] = $this->bodyLooksLikeJson($body)
                        ? 'application/json'
                        : self::CONTENT_TYPE_FORM;
                }
            } elseif (isset($headers[self::HEADER_CONTENT_TYPE])
                && str_contains($headers[self::HEADER_CONTENT_TYPE], 'x-www-form-urlencoded')
            ) {
                $body = http_build_query($body);
            } elseif ($this->bodyContainsFile($body)) {
                // Pass through; request() will build multipart
            } else {
                $headers[self::HEADER_CONTENT_TYPE] ??= 'application/json';
                $body = Json::stringify($body);
            }
        }

        return $this->performRequest($method, $url, $body, $headers, $options, $this->parseJsonResponse(...));
    }

    /**
     * Heuristic for auto-detecting JSON string bodies.
     *
     * Only object and array payloads are auto-promoted; scalar JSON values such as null, true, false, numbers,
     * and quoted strings are left to explicit Content-Type selection.
     */
    protected function bodyLooksLikeJson(string $body): bool
    {
        $body = trim($body);

        return $body !== '' && ($body[0] === '{' || $body[0] === '[');
    }

    /**
     * Send a request with form-encoded body expectations.
     *
     * Array body is application/x-www-form-urlencoded; response body stays string.
     *
     * @param string $method
     * @param string|array<int|string, mixed> $url
     * @param string|array<int|string, mixed> $body
     * @param array<int|string, mixed> $headers
     */
    protected function requestForm(
        string            $method,
        string|array      $url,
        null|string|array $body = null,
        array             $headers = [],
        ?RequestOptions   $options = null
    ): Response {
        if (is_array($body)) {
            if ($this->bodyContainsFile($body)) {
                // Pass through; request() will build multipart
            } else {
                $headers[self::HEADER_CONTENT_TYPE] ??= self::CONTENT_TYPE_FORM;
                $body = http_build_query($body);
            }
        }

        return $this->performRequest($method, $url, $body, $headers, $options);
    }

    protected function parseJsonResponse(Response $response): Response
    {
        if (!$this->isJsonContentType($response->getContentType())) {
            BadResponseException::raise('Expected JSON, got {content_type}: {url}', [
                'request' => $response->getRequest(),
                'response' => $response,
                'url' => $response->getUrl(),
                'content_type' => $response->getContentType(),
            ]);
        }

        try {
            if ($response->getBody() !== '') {
                $response->getJsonBody();
            }
        } catch (Throwable $e) {
            BadResponseException::raise('Invalid JSON response: {url}', [
                'request' => $response->getRequest(),
                'response' => $response,
                'url' => $response->getUrl(),
                'content_type' => $response->getContentType(),
            ]);
        }

        return $response;
    }

    protected function mimeType(string $contentType): string
    {
        return strtolower(trim(($pos = strpos($contentType, ';')) === false
            ? $contentType
            : substr($contentType, 0, $pos)));
    }

    protected function isJsonContentType(string $contentType): bool
    {
        $mimeType = $this->mimeType($contentType);

        return $mimeType === 'application/json'
            || $mimeType === 'text/json'
            || str_ends_with($mimeType, '+json');
    }

    /**
     * Normalize headers/options and create one mutable request object.
     *
     * @param string $method
     * @param string|array<int|string, mixed> $url
     * @param string|array<int|string, mixed> $body
     * @param array<int|string, mixed> $headers
     */
    protected function createRequest(
        string            $method,
        string|array      $url,
        null|string|array $body,
        array             $headers,
        ?RequestOptions   $options
    ): Request {
        foreach ($headers as $name => $value) {
            if (is_int($name) && str_contains($value, ':')) {
                [$p1, $p2] = explode(':', $value, 2);
                $headers[trim($p1)] = trim($p2);
                unset($headers[$name]);
            }
        }

        $options = $options?->all() ?? [];

        $options['timeout'] ??= $this->timeout;
        $options['connect_timeout'] ??= $this->connect_timeout;
        $options['proxy'] ??= $this->proxy;
        $options['cafile'] ??= $this->cafile;
        $options['verify_peer'] ??= $this->verify_peer;

        return new Request($method, $url, $body, $headers, $options);
    }

    /**
     * @param string|array<int|string, mixed> $url
     */
    protected function sendRequest(Request $request, string $method, string|array $url, float $overallStartTime): Response
    {
        $engine_id = $this->resolveEngineId($request);
        if (!$this->poolManager->exists($this, $engine_id)) {
            $this->poolManager->add($this, [EngineInterface::class], $this->pool_size, $engine_id);
        }

        /** @var EngineInterface $engine */
        $engine = $this->poolManager->acquire($this, $request->getOption('timeout'), $engine_id);

        try {
            $this->eventDispatcher->dispatch(new HttpClientRequesting($this, $method, $url, $request));
            $response = $engine->request($request, $this->prepareRequestBody($request));
        } finally {
            $this->poolManager->release($this, $engine, $engine_id);
        }

        $this->eventDispatcher->dispatch(
            new HttpClientRequested($this, $method, $url, $request, $response, $this->getElapsed($overallStartTime))
        );

        return $response;
    }

    protected function prepareRequestBody(Request $request): ?string
    {
        if ($request->hasFile()) {
            $boundary = '------------------------' . bin2hex(random_bytes(8));
            $request->setHeader(self::HEADER_CONTENT_TYPE, "multipart/form-data; boundary=$boundary");
            $body = $request->buildMultipart($boundary);
        } else {
            $body = $request->getBody();
        }

        if (is_array($body)) {
            if (($contentType = $request->getHeader(self::HEADER_CONTENT_TYPE)) !== null
                && str_contains((string)$contentType, 'json')
            ) {
                $body = Json::stringify($body);
            } else {
                $body = http_build_query($body);
            }
        }

        if (is_string($body)) {
            $request->setHeader(self::HEADER_CONTENT_LENGTH, strlen($body));
        }

        return $body;
    }

    protected function resolveEngineId(Request $request): string
    {
        $url = $request->getUrl();
        $pos = strpos($url, '/', 8);

        return $pos !== false ? substr($url, 0, $pos) : $url;
    }

    protected function assertSuccessfulResponse(Response $response): void
    {
        $http_code = $response->getStatusCode();

        if ($http_code >= 200 && $http_code < 300) {
            return;
        }

        $context = [
            'request' => $response->getRequest(),
            'response' => $response,
            'url' => $response->getUrl(),
            'status_code' => $http_code,
        ];
        if ($http_code === 400) {
            $context['response_text'] = $response->getBody();
        }

        match (true) {
            $http_code >= 300 && $http_code < 400 => RedirectionException::raise('{status_code} Redirect: {url}', $context),
            $http_code === 400 => BadRequestException::raise('400 Bad Request: {url} - {response_text}', $context),
            $http_code === 401 => UnauthorizedException::raise('401 Unauthorized: {url}', $context),
            $http_code === 403 => ForbiddenException::raise('403 Forbidden: {url}', $context),
            $http_code === 404 => NotFoundException::raise('404 Not Found: {url}', $context),
            $http_code === 429 => TooManyRequestsException::raise('429 Too Many Requests: {url}', $context),
            $http_code >= 400 && $http_code < 500 => ClientErrorException::raise('{status_code} Client Error: {url}', $context),
            $http_code === 500 => InternalServerErrorException::raise('500 Internal Server Error: {url}', $context),
            $http_code === 502 => BadGatewayException::raise('502 Bad Gateway: {url}', $context),
            $http_code === 503 => ServiceUnavailableException::raise('503 Service Unavailable: {url}', $context),
            $http_code === 504 => GatewayTimeoutException::raise('504 Gateway Timeout: {url}', $context),
            $http_code >= 500 => ServerErrorException::raise('{status_code} Server Error: {url}', $context),
            default => BadResponseException::raise('{status_code} Unknown Error: {url}', $context),
        };
    }

    protected function getElapsed(float $overallStartTime): float
    {
        return round(microtime(true) - $overallStartTime, 3);
    }

    protected function bodyContainsFile(mixed $body): bool
    {
        if (!is_array($body)) {
            return false;
        }
        foreach ($body as $v) {
            if ($v instanceof FileInterface) {
                return true;
            }
        }
        return false;
    }
    /** Sends a JSON GET request. */
    /**
     * @param string|array<int|string, mixed> $url
     * @param array<string, mixed> $headers
     */
    public function get(string|array $url, array $headers = [], ?RequestOptions $options = null): Response
    {
        return $this->requestJson('GET', $url, null, $headers, $options);
    }

    /** Sends a HEAD request.
     * @param string|array<int|string, mixed> $url
     * @param array<string, mixed> $headers
     */
    public function head(string|array $url, array $headers = [], ?RequestOptions $options = null): Response
    {
        return $this->request('HEAD', $url, null, $headers, $options);
    }

    /**
     * Sends a JSON POST request.
     *
     * @param string|array<int|string, mixed> $url
     * @param string|array<int|string, mixed> $body
     * @param array<int|string, mixed> $headers
     */
    public function post(
        string|array    $url,
        string|array    $body = [],
        array           $headers = [],
        ?RequestOptions $options = null
    ): Response {
        return $this->requestJson('POST', $url, $body, $headers, $options);
    }

    /** Sends a JSON DELETE request.
     *
     * @param string|array<int|string, mixed> $url
     * @param array<string, mixed> $headers
     */
    public function delete(string|array $url, array $headers = [], ?RequestOptions $options = null): Response
    {
        return $this->requestJson('DELETE', $url, null, $headers, $options);
    }

    /** Sends a JSON PUT request.
     * @param string|array<int|string, mixed> $url
     * @param string|array<int|string, mixed> $body
     * @param array<string, mixed> $headers
     */
    public function put(
        string|array    $url,
        string|array    $body = [],
        array           $headers = [],
        ?RequestOptions $options = null
    ): Response {
        return $this->requestJson('PUT', $url, $body, $headers, $options);
    }

    /** Sends a JSON PATCH request.
     *
     * @param string|array<int|string, mixed> $url
     * @param string|array<int|string, mixed> $body
     * @param array<string, mixed> $headers
     */
    public function patch(
        string|array    $url,
        string|array    $body = [],
        array           $headers = [],
        ?RequestOptions $options = null
    ): Response {
        return $this->requestJson('PATCH', $url, $body, $headers, $options);
    }

}
