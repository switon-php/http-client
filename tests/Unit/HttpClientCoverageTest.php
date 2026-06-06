<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\HttpClient\HttpClient;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;
use Switon\HttpClient\Tests\EventDispatcherStub;
use Switon\HttpClient\Tests\Fixtures\MockEngine;
use Switon\HttpClient\Tests\Fixtures\MockPoolManager;
use Switon\HttpClient\Tests\TestCase;

class HttpClientCoverageTest extends TestCase
{
    private ExposedHttpClient $httpClient;
    private MockPoolManager $httpClientPoolManager;
    private MockEngine $httpClientEngine;
    private EventDispatcherStub $httpClientDispatcher;

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

    private function createResponse(): Response
    {
        $request = new Request('GET', 'https://example.com/api', null, [], []);
        $request->markCompleted('127.0.0.1', 0.123);

        return new Response($request, [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'Content-Length: 2',
        ], '{}');
    }
}

class ExposedHttpClient extends HttpClient
{
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
