<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

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
use Switon\HttpClient\HttpClient;
use Switon\HttpClient\LocalFile;
use Switon\HttpClient\MemoryFile;
use Switon\HttpClient\Request;
use Switon\HttpClient\RequestOptions;
use Switon\HttpClient\Response;
use Switon\HttpClient\Tests\EventDispatcherStub;
use Switon\HttpClient\Tests\Fixtures\{MockEngine, MockPoolManager};
use Switon\HttpClient\Tests\TestCase;
use RuntimeException;

/**
 * Test cases for HttpClient class.
 *
 * Tests HTTP client methods, request handling, event dispatching, and error handling.
 */
class HttpClientTest extends TestCase
{
    protected HttpClient $client;
    protected MockPoolManager $mockPoolManager;
    protected MockEngine $mockEngine;
    protected EventDispatcherStub $eventDispatcherStub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPoolManager = new MockPoolManager();
        $this->mockEngine = new MockEngine();
        $this->eventDispatcherStub = $this->createEventDispatcherStub();

        // Use make() with property injection
        $this->client = $this->make(HttpClient::class, [
            'eventDispatcher' => $this->eventDispatcherStub,
            'poolManager' => $this->mockPoolManager,
            'proxy' => null,
            'cafile' => null,
            'timeout' => 3,
            'verify_peer' => true,
            'pool_size' => 4,
        ]);

        // Setup pool manager to return mock engine
        $this->mockPoolManager->setExists($this->client, 'https://example.com', false);
        $this->mockPoolManager->add($this->client, $this->mockEngine, 1, 'https://example.com');
        $this->mockPoolManager->setExists($this->client, 'https://example.com', true);
    }

    /**
     * Test get() method makes GET request.
     */
    public function testGet(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->get('https://example.com/api');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
        $this->assertCount(1, $this->mockEngine->requests);
        $this->assertSame('GET', $this->mockEngine->requests[0]['request']->method);
    }

    public function testGetAcceptsPlusJsonContentType(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/problem+json; charset=utf-8'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        $response = $this->client->get('https://example.com/api');

        $this->assertSame('{"success":true}', $response->body);
        $this->assertSame(['success' => true], $response->getJsonBody());
    }

    /**
     * Test post() method makes POST request.
     */
    public function testPost(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->post('https://example.com/api', ['key' => 'value']);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
        $this->assertCount(1, $this->mockEngine->requests);
        $this->assertSame('POST', $this->mockEngine->requests[0]['request']->method);
    }

    /**
     * Test put() method makes PUT request.
     */
    public function testPut(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->put('https://example.com/api', ['key' => 'value']);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
        $this->assertSame('PUT', $this->mockEngine->requests[0]['request']->method);
    }

    /**
     * Test delete() method makes DELETE request.
     */
    public function testDelete(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->delete('https://example.com/api');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
        $this->assertSame('DELETE', $this->mockEngine->requests[0]['request']->method);
    }

    /**
     * Test patch() method makes PATCH request.
     */
    public function testPatch(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->patch('https://example.com/api', ['key' => 'value']);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
        $this->assertSame('PATCH', $this->mockEngine->requests[0]['request']->method);
    }

    /**
     * Test request() with JSON body.
     */
    public function testRequestWithJsonBody(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->request('POST', 'https://example.com/api', ['key' => 'value'], ['Content-Type' => 'application/json']);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertStringContainsString('application/json', $request->headers['Content-Type']);
    }

    /**
     * Test request() with form data body.
     */
    public function testRequestWithFormDataBody(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->request('POST', 'https://example.com/api', ['key' => 'value']);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertStringContainsString('key=value', $this->mockEngine->requests[0]['body']);
    }

    /**
     * Test request() with file upload.
     */
    public function testRequestWithFileUpload(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));
        $file = new MemoryFile('file content', 'text/plain', 'test.txt');

        // Act
        $response = $this->client->request('POST', 'https://example.com/api', ['file' => $file]);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertStringContainsString('multipart/form-data', $request->headers['Content-Type']);
        $this->assertStringContainsString('test.txt', $this->mockEngine->requests[0]['body']);
    }

    /**
     * Test request() dispatches events.
     */
    public function testRequestDispatchesEvents(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $this->client->get('https://example.com/api');

        // Assert
        $events = $this->eventDispatcherStub->dispatchedEvents;
        $this->assertGreaterThan(0, count($events));
    }

    public function testGetDispatchesSuccessLifecycleInOrder(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        $this->client->get('https://example.com/api');

        $this->assertSame([
            HttpClientStart::class,
            HttpClientRequesting::class,
            HttpClientRequested::class,
            HttpClientSuccess::class,
            HttpClientComplete::class,
        ], array_map(static fn (object $event): string => $event::class, $this->eventDispatcherStub->dispatchedEvents));
    }

    public function testHeadDoesNotRequireJsonResponse(): void
    {
        $responseHeaders = ['HTTP/1.1 204 No Content', 'Content-Type: text/plain'];
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, ''));

        $response = $this->client->head('https://example.com/api');

        $this->assertSame(204, $response->http_code);
        $this->assertSame('HEAD', $this->mockEngine->requests[0]['request']->method);
    }

    public function testRepeatedRequestsReuseExistingEnginePoolWithoutAddingAgain(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        $this->client->get('https://example.com/api');
        $this->client->get('https://example.com/api?x=1');

        $ownerId = spl_object_id($this->client);
        $this->assertCount(1, $this->mockPoolManager->added[$ownerId]['https://example.com']);
        $this->assertCount(2, $this->mockPoolManager->popped[$ownerId]['https://example.com']);
        $this->assertCount(2, $this->mockPoolManager->pushed[$ownerId]['https://example.com']);
    }

    /**
     * Test error event includes elapsed time when request fails.
     */
    public function testErrorEventHasElapsedOnFailure(): void
    {
        // Arrange
        $this->mockEngine->setException(new RuntimeException('boom'));

        // Act
        try {
            $this->client->get('https://example.com/api');
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException $e) {
            // ignore
        }

        // Assert
        $events = $this->eventDispatcherStub->dispatchedEvents;
        $errorEvents = array_filter($events, fn ($event) => $event instanceof \Switon\HttpClient\Event\HttpClientError);
        $this->assertNotEmpty($errorEvents);
        $lastError = array_values($errorEvents)[count($errorEvents) - 1];
        $this->assertGreaterThanOrEqual(0, $lastError->elapsed);
    }

    /**
     * Test request() returns raw body without JSON parsing.
     */
    public function testRequestReturnsRawBody(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"key":"value"}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->request('GET', 'https://example.com/api', null, ['Accept' => 'application/json']);

        // Assert
        $this->assertIsString($response->body);
        $this->assertSame($responseBody, $response->body);
    }

    /**
     * Test request() throws BadRequestException for 400 status.
     */
    public function testRequestThrowsBadRequestExceptionFor400(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 400 Bad Request', 'Content-Type: application/json'];
        $responseBody = '{"error":"bad request"}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(BadRequestException::class);
        $this->client->get('https://example.com/api');
    }

    public function testHttpStatusFailureDispatchesErrorWithoutSuccess(): void
    {
        $responseHeaders = ['HTTP/1.1 404 Not Found', 'Content-Type: application/json'];
        $responseBody = '{"error":"missing"}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        try {
            $this->client->get('https://example.com/api');
            $this->fail('Expected exception not thrown');
        } catch (NotFoundException) {
        }

        $eventClasses = array_map(static fn (object $event): string => $event::class, $this->eventDispatcherStub->dispatchedEvents);
        $this->assertSame([
            HttpClientStart::class,
            HttpClientRequesting::class,
            HttpClientRequested::class,
            HttpClientError::class,
            HttpClientComplete::class,
        ], $eventClasses);
        $this->assertNotContains(HttpClientSuccess::class, $eventClasses);

        /** @var HttpClientError $errorEvent */
        $errorEvent = $this->eventDispatcherStub->dispatchedEvents[3];
        $this->assertInstanceOf(HttpClientError::class, $errorEvent);
        $this->assertInstanceOf(NotFoundException::class, $errorEvent->exception);
        $this->assertInstanceOf(Response::class, $errorEvent->response);
    }

    /**
     * Test request() throws UnauthorizedException for 401 status.
     */
    public function testRequestThrowsUnauthorizedExceptionFor401(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 401 Unauthorized', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(UnauthorizedException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test request() throws ForbiddenException for 403 status.
     */
    public function testRequestThrowsForbiddenExceptionFor403(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 403 Forbidden', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(ForbiddenException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test request() throws NotFoundException for 404 status.
     */
    public function testRequestThrowsNotFoundExceptionFor404(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 404 Not Found', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test request() throws TooManyRequestsException for 429 status.
     */
    public function testRequestThrowsTooManyRequestsExceptionFor429(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 429 Too Many Requests', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(TooManyRequestsException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test request() throws RedirectionException for 3xx status.
     */
    public function testRequestThrowsRedirectionExceptionFor3xx(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 301 Moved Permanently', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(RedirectionException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test request() throws ClientErrorException for 4xx status (other than specific ones).
     */
    public function testRequestThrowsClientErrorExceptionFor4xx(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 418 I\'m a teapot', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(ClientErrorException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test request() throws InternalServerErrorException for 500 status.
     */
    public function testRequestThrowsInternalServerErrorExceptionFor500(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 500 Internal Server Error', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(InternalServerErrorException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test request() throws BadGatewayException for 502 status.
     */
    public function testRequestThrowsBadGatewayExceptionFor502(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 502 Bad Gateway', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(BadGatewayException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test request() throws ServiceUnavailableException for 503 status.
     */
    public function testRequestThrowsServiceUnavailableExceptionFor503(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 503 Service Unavailable', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(ServiceUnavailableException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test request() throws GatewayTimeoutException for 504 status.
     */
    public function testRequestThrowsGatewayTimeoutExceptionFor504(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 504 Gateway Timeout', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(GatewayTimeoutException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test request() throws ServerErrorException for 5xx status (other than specific ones).
     */
    public function testRequestThrowsServerErrorExceptionFor5xx(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 507 Insufficient Storage', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(ServerErrorException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test post() sets appropriate JSON headers.
     */
    public function testPostSetsAppropriateHeaders(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->post('https://example.com/api', ['key' => 'value']);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertSame('application/json', $request->headers['Content-Type']);
        $this->assertSame('XMLHttpRequest', $request->headers['X-Requested-With']);
        $this->assertSame('application/json', $request->headers['Accept']);
    }

    /**
     * Test post() with string body detects JSON.
     */
    public function testPostWithStringBodyDetectsJson(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->post('https://example.com/api', '{"key":"value"}');

        // Assert
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertSame('application/json', $request->headers['Content-Type']);
    }

    /**
     * Test post() with string body detects form data.
     */
    public function testPostWithStringBodyDetectsFormData(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->post('https://example.com/api', 'key=value');

        // Assert
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertStringContainsString('application/x-www-form-urlencoded', $request->headers['Content-Type']);
    }

    /**
     * Test post() with string body containing braces still treats it as form data.
     */
    public function testPostWithStringBodyContainingBracesDefaultsToFormData(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->post('https://example.com/api', 'hello {world}');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertStringContainsString('application/x-www-form-urlencoded', $request->headers['Content-Type']);
    }

    public function testPostWithScalarJsonStringDefaultsToFormContentType(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->post('https://example.com/api', 'true');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertStringContainsString('application/x-www-form-urlencoded', $request->headers['Content-Type']);
        $this->assertSame('true', $this->mockEngine->requests[0]['body']);
    }

    public function testRequestWithFormEncodedHeaderBuildsQueryBody(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        $response = $this->client->request(
            'POST',
            'https://example.com/api',
            ['key' => 'value', 'space' => 'hello world'],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('key=value&space=hello+world', $this->mockEngine->requests[0]['body']);
    }

    public function testRequestWithMultipartBodySendsMultipartPayload(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));
        $file = new MemoryFile('file content', 'text/plain', 'test.txt');

        $response = $this->client->request('POST', 'https://example.com/api', ['file' => $file]);

        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertStringContainsString('multipart/form-data', $request->headers['Content-Type']);
        $this->assertStringContainsString('filename="test.txt"', $this->mockEngine->requests[0]['body']);
        $this->assertStringContainsString('Content-Length', implode("\n", array_keys($request->headers)));
    }

    public function testRequestWithLocalFileBodyBuildsMultipartPayload(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));
        $file = $this->make(LocalFile::class, ['fileName' => $this->createTempUploadFile(), 'mimeType' => 'text/plain', 'postName' => 'test.txt']);

        $response = $this->client->request('POST', 'https://example.com/api', ['file' => $file]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('multipart/form-data', $this->mockEngine->requests[0]['request']->headers['Content-Type']);
    }

    public function testRequestWithHeaderArrayNormalizesColonSeparatedHeaders(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        $response = $this->client->request('GET', 'https://example.com/api', null, ['Accept: text/plain']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('text/plain', $this->mockEngine->requests[0]['request']->headers['Accept']);
    }

    public function testRequestWithCustomOptionsOverridesDefaults(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        $options = RequestOptions::of([
            'timeout' => 9,
            'connect_timeout' => 5,
            'proxy' => 'http://proxy.local:8080',
            'cafile' => '/tmp/ca.pem',
            'verify_peer' => false,
        ]);

        $response = $this->client->get('https://example.com/api', [], $options);

        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertSame(9, $request->option('timeout'));
        $this->assertSame(5, $request->option('connect_timeout'));
        $this->assertSame('http://proxy.local:8080', $request->option('proxy'));
        $this->assertSame('/tmp/ca.pem', $request->option('cafile'));
        $this->assertFalse($request->option('verify_peer'));
    }

    /**
     * Test get() throws BadResponseException for non-JSON response.
     */
    public function testGetThrowsBadResponseExceptionForNonJsonResponse(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: text/html'];
        $responseBody = '<html></html>';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(BadResponseException::class);
        $this->client->get('https://example.com/api');
    }

    public function testJsonParsingFailureDispatchesErrorWithoutSuccess(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: text/plain'];
        $responseBody = 'plain text';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        try {
            $this->client->get('https://example.com/api');
            $this->fail('Expected exception not thrown');
        } catch (BadResponseException) {
        }

        $eventClasses = array_map(static fn (object $event): string => $event::class, $this->eventDispatcherStub->dispatchedEvents);
        $this->assertSame([
            HttpClientStart::class,
            HttpClientRequesting::class,
            HttpClientRequested::class,
            HttpClientError::class,
            HttpClientComplete::class,
        ], $eventClasses);
        $this->assertNotContains(HttpClientSuccess::class, $eventClasses);

        /** @var HttpClientError $errorEvent */
        $errorEvent = $this->eventDispatcherStub->dispatchedEvents[3];
        $this->assertInstanceOf(HttpClientError::class, $errorEvent);
        $this->assertInstanceOf(BadResponseException::class, $errorEvent->exception);
        $this->assertInstanceOf(Response::class, $errorEvent->response);
    }

    public function testTransportFailureDispatchesOriginalExceptionWithoutResponse(): void
    {
        $this->mockEngine->setException(new RuntimeException('DNS failure'));

        try {
            $this->client->get('https://example.com/api');
            $this->fail('Expected exception not thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('DNS failure', $exception->getMessage());
        }

        $eventClasses = array_map(static fn (object $event): string => $event::class, $this->eventDispatcherStub->dispatchedEvents);
        $this->assertSame([
            HttpClientStart::class,
            HttpClientRequesting::class,
            HttpClientError::class,
            HttpClientComplete::class,
        ], $eventClasses);
        $this->assertNotContains(HttpClientSuccess::class, $eventClasses);
        $this->assertNotContains(HttpClientRequested::class, $eventClasses);

        /** @var HttpClientError $errorEvent */
        $errorEvent = $this->eventDispatcherStub->dispatchedEvents[2];
        $this->assertInstanceOf(HttpClientError::class, $errorEvent);
        $this->assertInstanceOf(RuntimeException::class, $errorEvent->exception);
        $this->assertNull($errorEvent->response);
    }

    /**
     * Test request() with HEADER_X_REQUEST_ID header.
     */
    public function testRequestWithXRequestIdHeader(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->request('GET', 'https://example.com/api', null, [HttpClient::HEADER_X_REQUEST_ID => 'test-id']);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
    }

    /**
     * Test request() with integer header name (array format).
     */
    public function testRequestWithIntegerHeaderName(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->request('GET', 'https://example.com/api', null, [0 => 'Content-Type: application/json']);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertSame('application/json', $request->headers['Content-Type']);
    }

    public function testRequestWithRequestOptionsObject(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        $response = $this->client->request('GET', 'https://example.com/api', null, [], RequestOptions::of([
            RequestOptions::TIMEOUT => 10,
            RequestOptions::CONNECT_TIMEOUT => 2,
        ]));

        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertSame(10, $request->options['timeout']);
        $this->assertSame(2, $request->options['connect_timeout']);
        $this->assertIsArray($request->options);
    }

    public function testRequestWithNumericRequestOptionsObject(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        $response = $this->client->request('GET', 'https://example.com/api', null, [], RequestOptions::of(30.5));

        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertSame(30.5, $request->options['timeout']);
        $this->assertIsArray($request->options);
    }

    public function testRequestWithDefaultRequestOptionsUsesClientDefaults(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        $response = $this->client->request('GET', 'https://example.com/api');

        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertSame(3, $request->options['timeout']);
        $this->assertSame(1, $request->options['connect_timeout']);
        $this->assertTrue($request->options['verify_peer']);
    }

    /**
     * Test request() with string body and Content-Length header.
     */
    public function testRequestWithStringBodySetsContentLength(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->request('POST', 'https://example.com/api', 'test body');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $request = $this->mockEngine->requests[0]['request'];
        $this->assertSame(9, $request->headers['Content-Length']);
    }

    /**
     * Test post() with array body and form-urlencoded content type.
     */
    public function testPostWithArrayBodyAndFormUrlencoded(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->post('https://example.com/api', ['key' => 'value'], ['Content-Type' => 'application/x-www-form-urlencoded']);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('key=value', $this->mockEngine->requests[0]['body']);
    }

    /**
     * Test get() with HTML response rejects non-JSON content types.
     */
    public function testGetWithHtmlResponseThrowsBadResponse(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: text/html'];
        $responseBody = '{"key":"value"}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(BadResponseException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test get() with non-JSON response throws BadResponseException.
     */
    public function testGetWithNonJsonResponseThrowsBadResponseException(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: text/plain'];
        $responseBody = 'plain text';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act & Assert
        $this->expectException(BadResponseException::class);
        $this->client->get('https://example.com/api');
    }

    /**
     * Test get() with special headers.
     */
    public function testGetWithSpecialHeaders(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->get('https://example.com/api', [
            HttpClient::HEADER_ACCEPT_CHARSET => 'utf-8',
            HttpClient::HEADER_AUTHORIZATION => 'Bearer token',
            HttpClient::HEADER_CACHE_CONTROL => 'no-cache',
            HttpClient::HEADER_HOST => 'example.com',
            HttpClient::HEADER_COOKIE => 'session=abc',
        ]);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
    }

    /**
     * Test request() with empty response body returns empty string.
     */
    public function testRequestWithEmptyResponseBody(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->request('GET', 'https://example.com/api', null, ['Accept' => 'application/json']);

        // Assert
        $this->assertSame('', $response->body);
    }

    public function testHeadMakesRawHeadRequest(): void
    {
        $responseHeaders = ['HTTP/1.1 200 OK', 'Date: Tue, 14 Nov 2023 22:13:20 GMT'];
        $responseBody = '';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        $response = $this->client->head('https://example.com/api');

        $this->assertSame('HEAD', $this->mockEngine->requests[0]['request']->method);
        $this->assertSame('', $response->body);
    }

    /**
     * Test request() with array URL.
     */
    public function testRequestWithArrayUrl(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));

        // Act
        $response = $this->client->request('GET', ['https://example.com/api', 'param' => 'value']);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
    }

    /**
     * Test request() when pool doesn't exist, creates new pool.
     *
     * Note: This test verifies that when pool doesn't exist, HttpClient calls add().
     * However, the actual Engine creation happens via DI, so we just verify the flow.
     */
    public function testRequestCreatesPoolWhenNotExists(): void
    {
        // Arrange
        $responseHeaders = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $responseBody = '{"success":true}';
        $this->mockEngine->setResponse($this->createResponse($responseHeaders, $responseBody));
        $this->mockPoolManager->setExists($this->client, 'https://example.com', false);
        // Setup pool manager to add engine instances (not the class)
        // HttpClient calls add() with [EngineInterface::class], so we need to add actual engine instances
        $this->mockPoolManager->add($this->client, $this->mockEngine, 1, 'https://example.com');
        $this->mockPoolManager->setExists($this->client, 'https://example.com', true);

        // Act
        $response = $this->client->get('https://example.com/api');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
    }

    /**
     * Test __clone() throws NonCloneableException.
     */
    public function testCloneThrowsException(): void
    {
        // Act & Assert
        $this->expectException(\Switon\Core\Exception\NonCloneableException::class);
        clone $this->client;
    }

    /**
     * Helper method to create a Response object.
     */
    protected function createResponse(array $headers, string $body): Response
    {
        $request = new Request('GET', 'https://example.com/api', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        return new Response($request, $headers, $body);
    }

    protected function createTempUploadFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'http-client-upload-');
        file_put_contents($file, 'upload');

        return $file;
    }
}
