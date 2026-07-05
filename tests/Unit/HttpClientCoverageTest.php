<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\HttpClient\Exception\BadResponseException;
use Switon\HttpClient\HttpClient;
use Switon\HttpClient\MemoryFile;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;
use Switon\HttpClient\Tests\EventDispatcherStub;
use Switon\HttpClient\Tests\Fixtures\MockEngine;
use Switon\HttpClient\Tests\Fixtures\MockPoolManager;
use Switon\HttpClient\Tests\TestCase;
use TypeError;

class HttpClientCoverageTest extends TestCase
{
    protected ExposedHttpClient $httpClient;
    protected MockPoolManager $httpClientPoolManager;
    protected MockEngine $httpClientEngine;
    protected EventDispatcherStub $httpClientDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClientPoolManager = new MockPoolManager();
        $this->httpClientEngine = new MockEngine();
        $this->httpClientDispatcher = $this->createEventDispatcherStub();

        $this->httpClient = $this->make(ExposedHttpClient::class, [
            'eventDispatcher' => $this->httpClientDispatcher,
            'poolManager' => $this->httpClientPoolManager,
            'proxy' => null,
            'cafile' => null,
            'timeout' => 3,
            'connect_timeout' => 1,
            'verify_peer' => true,
            'pool_size' => 4,
        ]);

        $this->httpClientPoolManager->setExists($this->httpClient, 'https://example.com', false);
        $this->httpClientPoolManager->add($this->httpClient, $this->httpClientEngine, 1, 'https://example.com');
        $this->httpClientPoolManager->setExists($this->httpClient, 'https://example.com', true);
    }

    public function testGetRaisesWhenResponseIsNotJsonContentType(): void
    {
        $this->httpClientEngine->setResponse($this->createResponse(
            ['HTTP/1.1 200 OK', 'Content-Type: text/plain'],
            'plain'
        ));

        $this->expectException(BadResponseException::class);
        $this->httpClient->get('https://example.com/plain');
    }

    public function testGetRaisesWhenJsonBodyIsInvalid(): void
    {
        $this->httpClientEngine->setResponse($this->createResponse(
            ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
            '{not-json'
        ));

        $this->expectException(BadResponseException::class);
        $this->httpClient->get('https://example.com/bad-json');
    }

    public function testRequestFormWithFilePartDoesNotUrlEncodeBody(): void
    {
        $this->httpClientEngine->setResponse($this->createResponse());

        $this->httpClient->requestFormPublic('POST', 'https://example.com/upload', [
            'text' => 'hello',
            'file' => new MemoryFile('content', 'text/plain', 'demo.txt'),
        ]);

        $this->assertStringContainsString(
            'multipart/form-data',
            $this->httpClientEngine->requests[0]['request']->headers['Content-Type'] ?? '',
        );
        $this->assertIsString($this->httpClientEngine->requests[0]['body']);
        $this->assertStringContainsString('Content-Disposition', $this->httpClientEngine->requests[0]['body']);
    }

    public function testCreateRequestNormalizesNumericHeaderKeys(): void
    {
        $request = $this->httpClient->createRequestPublic(
            'GET',
            'https://example.com/api',
            null,
            [0 => 'X-Demo: yes'],
            null,
        );

        $this->assertSame('yes', $request->headers['X-Demo'] ?? null);
        $this->assertArrayNotHasKey(0, $request->headers);
    }

    public function testRequestAddsEnginePoolOnFirstSendWhenMissing(): void
    {
        $this->httpClientPoolManager->setExists($this->httpClient, 'https://other.test', false);
        $this->httpClientEngine->setResponse($this->createResponse());

        try {
            $this->httpClient->get('https://other.test/resource');
        } catch (TypeError) {
        }

        $ownerId = spl_object_id($this->httpClient);
        $this->assertNotEmpty($this->httpClientPoolManager->added[$ownerId]['https://other.test'] ?? []);
    }

    public function testRequestRaisesBadResponseExceptionForNonStandardStatusCode(): void
    {
        $this->httpClientEngine->setResponse($this->createResponse(
            ['HTTP/1.1 199 Unknown', 'Content-Type: application/json'],
            '{}',
        ));

        $this->expectException(BadResponseException::class);
        $this->httpClient->request('GET', 'https://example.com/weird-status');
    }

    public function testRequestFormEncodesArrayBodyWithoutJsonHeaders(): void
    {
        $this->httpClientEngine->setResponse($this->createResponse());

        $response = $this->httpClient->requestFormPublic('POST', 'https://example.com/api', [
            'key' => 'value',
            'space' => 'hello world',
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('key=value&space=hello+world', $this->httpClientEngine->requests[0]['body']);
        $this->assertStringContainsString('application/x-www-form-urlencoded', $this->httpClientEngine->requests[0]['request']->headers['Content-Type']);
        $this->assertSame(27, $this->httpClientEngine->requests[0]['request']->headers['Content-Length']);
    }

    /**
     * @param list<string> $responseHeaders
     */
    protected function createResponse(
        array $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
        string $responseBody = '{}',
    ): Response {
        $request = new Request('GET', 'https://example.com/api', null, [], []);
        $request->markCompleted('127.0.0.1', 0.123);

        return new Response($request, $responseHeaders, $responseBody);
    }
}

class ExposedHttpClient extends HttpClient
{
    public function createRequestPublic(
        string $method,
        string|array $url,
        null|string|array $body,
        array $headers,
        ?\Switon\HttpClient\RequestOptions $options,
    ): Request {
        return $this->createRequest($method, $url, $body, $headers, $options);
    }

    public function requestFormPublic(
        string                             $method,
        string|array                       $url,
        null|string|array                  $body = null,
        array                              $headers = [],
        ?\Switon\HttpClient\RequestOptions $options = null
    ): Response {
        return $this->requestForm($method, $url, $body, $headers, $options);
    }
}
