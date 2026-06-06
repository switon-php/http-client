<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\Core\Exception\JsonException;
use Switon\HttpClient\Exception\CharsetConversionException;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;
use Switon\HttpClient\Tests\TestCase;

/**
 * Test cases for Response class.
 *
 * Tests response construction, header parsing, cookie parsing, and body handling.
 */
class ResponseTest extends TestCase
{
    /**
     * Test basic response construction.
     */
    public function testConstruct(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'Content-Length: 2',
        ];
        $body = '{}';

        // Act
        $response = new Response($request, $headers, $body);

        // Assert
        $this->assertSame('https://example.com', $response->url);
        $this->assertSame(200, $response->http_code);
        $this->assertSame('application/json', $response->content_type);
        $this->assertSame('{}', $response->body);
        $this->assertSame('127.0.0.1', $response->remote_ip);
        $this->assertSame(0.123, $response->process_time);
    }

    /**
     * Test response construction with redirect (301/302).
     */
    public function testConstructWithRedirect(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 301 Moved Permanently',
            'Location: https://example.org',
            'HTTP/1.1 200 OK',
            'Content-Type: text/html',
        ];
        $body = '<html></html>';

        // Act
        $response = new Response($request, $headers, $body);

        // Assert
        $this->assertSame(200, $response->http_code);
        $this->assertSame('text/html', $response->content_type);
    }

    public function testConstructWithSeeOtherRedirect(): void
    {
        $response = $this->createRedirectResponse(303, 'See Other');

        $this->assertSame(200, $response->http_code);
        $this->assertSame('text/html', $response->content_type);
    }

    public function testConstructWithTemporaryRedirect(): void
    {
        $response = $this->createRedirectResponse(307, 'Temporary Redirect');

        $this->assertSame(200, $response->http_code);
        $this->assertSame('text/html', $response->content_type);
    }

    public function testConstructWithPermanentRedirect(): void
    {
        $response = $this->createRedirectResponse(308, 'Permanent Redirect');

        $this->assertSame(200, $response->http_code);
        $this->assertSame('text/html', $response->content_type);
    }

    public function testConstructWithContinueInterimResponse(): void
    {
        $response = $this->createInterimResponse(100, 'Continue');

        $this->assertSame(200, $response->http_code);
        $this->assertSame('text/html', $response->content_type);
    }

    public function testConstructWithEarlyHintsInterimResponse(): void
    {
        $response = $this->createInterimResponse(103, 'Early Hints');

        $this->assertSame(200, $response->http_code);
        $this->assertSame('text/html', $response->content_type);
    }

    /**
     * Test response construction with gzip encoding.
     */
    public function testConstructWithGzipEncoding(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'Content-Encoding: gzip',
        ];
        $body = gzencode('{"key":"value"}');

        // Act
        $response = new Response($request, $headers, $body);

        // Assert
        $this->assertSame('{"key":"value"}', $response->body);
    }

    /**
     * Test response construction with deflate encoding.
     */
    public function testConstructWithDeflateEncoding(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'Content-Encoding: deflate',
        ];
        $body = gzdeflate('{"key":"value"}');

        // Act
        $response = new Response($request, $headers, $body);

        // Assert
        $this->assertSame('{"key":"value"}', $response->body);
    }

    /**
     * Test getHeaders() parses headers correctly.
     */
    public function testGetHeaders(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'Content-Length: 2',
            'X-Custom-Header: value1',
            'X-Custom-Header: value2',
        ];
        $body = '{}';
        $response = new Response($request, $headers, $body);

        // Act
        $parsedHeaders = $response->getHeaders();

        // Assert
        $this->assertSame('application/json', $parsedHeaders['Content-Type']);
        $this->assertSame('2', $parsedHeaders['Content-Length']);
        $this->assertIsArray($parsedHeaders['X-Custom-Header']);
        $this->assertSame(['value1', 'value2'], $parsedHeaders['X-Custom-Header']);
    }

    /**
     * Test getHeaders() parses headers without space after colon.
     */
    public function testGetHeadersWithoutSpace(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type:application/json',
        ];
        $body = '{}';
        $response = new Response($request, $headers, $body);

        // Act
        $parsedHeaders = $response->getHeaders();

        // Assert
        $this->assertSame('application/json', $parsedHeaders['Content-Type']);
    }

    /**
     * Test getCookies() parses cookies correctly.
     */
    public function testGetCookies(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Set-Cookie: session=abc123; Path=/; HttpOnly',
            'Set-Cookie: theme=dark; Path=/; Max-Age=3600',
        ];
        $body = '';
        $response = new Response($request, $headers, $body);

        // Act
        $cookies = $response->getCookies();

        // Assert
        $this->assertArrayHasKey('session', $cookies);
        $this->assertArrayHasKey('theme', $cookies);
        $this->assertSame('abc123', $cookies['session']->value);
        $this->assertSame('dark', $cookies['theme']->value);
    }

    /**
     * Test getCookies() filters expired cookies.
     */
    public function testGetCookiesFiltersExpiredCookies(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Set-Cookie: expired=value; Expires=' . gmdate('D, d M Y H:i:s \G\M\T', time() - 3600),
        ];
        $body = '';
        $response = new Response($request, $headers, $body);

        // Act
        $cookies = $response->getCookies();

        // Assert
        $this->assertArrayNotHasKey('expired', $cookies);
    }

    /**
     * Test getRequest() returns the original request.
     */
    public function testGetRequest(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = ['HTTP/1.1 200 OK'];
        $body = '';
        $response = new Response($request, $headers, $body);

        // Act
        $retrievedRequest = $response->getRequest();

        // Assert
        $this->assertSame($request, $retrievedRequest);
    }

    /**
     * Test getJsonBody() returns array when body is already array.
     */
    public function testGetJsonBodyWithArrayBody(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = ['HTTP/1.1 200 OK'];
        $body = '{"key":"value"}';
        $response = new Response($request, $headers, $body);

        // Act
        $result = $response->getJsonBody();

        // Assert
        $this->assertSame(['key' => 'value'], $result);
        $this->assertSame('{"key":"value"}', $response->body);
    }

    /**
     * Test getJsonBody() parses JSON string.
     */
    public function testGetJsonBodyWithJsonString(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = ['HTTP/1.1 200 OK'];
        $body = '{"key":"value"}';
        $response = new Response($request, $headers, $body);

        // Act
        $result = $response->getJsonBody();

        // Assert
        $this->assertSame(['key' => 'value'], $result);
    }

    public function testResponseShortcutMethods(): void
    {
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->markCompleted('127.0.0.1', 0.123);
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'X-Test: abc',
        ];
        $response = new Response($request, $headers, '{"key":"value"}');

        $this->assertSame(200, $response->status());
        $this->assertSame(['key' => 'value'], $response->json());
        $this->assertSame('{"key":"value"}', $response->text());
        $this->assertSame('abc', $response->header('X-Test'));
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->markCompleted('127.0.0.1', 0.123);
        $response = new Response($request, [
            'HTTP/1.1 200 OK',
            'ETag: "abc"',
        ], '');

        $this->assertSame('"abc"', $response->header('etag'));
    }

    /**
     * Test getJsonBody() throws exception for invalid JSON.
     */
    public function testGetJsonBodyThrowsExceptionForInvalidJson(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = ['HTTP/1.1 200 OK'];
        $body = 'invalid json';
        $response = new Response($request, $headers, $body);

        // Act & Assert
        // Json::parse() throws JsonException when JSON is invalid
        $this->expectException(JsonException::class);
        $response->getJsonBody();
    }

    /**
     * Test getUtf8Body() converts charset correctly.
     */
    public function testGetUtf8BodyConvertsCharset(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: text/html; charset=ISO-8859-1',
        ];
        $body = iconv('UTF-8', 'ISO-8859-1', 'Hello World');
        $response = new Response($request, $headers, $body);

        // Act
        $result = $response->getUtf8Body();

        // Assert
        $this->assertSame('Hello World', $result);
    }

    /**
     * Test getUtf8Body() returns UTF-8 body as-is.
     */
    public function testGetUtf8BodyReturnsUtf8AsIs(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: text/html; charset=UTF-8',
        ];
        $body = 'Hello World';
        $response = new Response($request, $headers, $body);

        // Act
        $result = $response->getUtf8Body();

        // Assert
        $this->assertSame('Hello World', $result);
    }

    /**
     * Test getUtf8Body() throws exception for unsupported charset.
     */
    public function testGetUtf8BodyThrowsExceptionForUnsupportedCharset(): void
    {
        $response = $this->createInvalidCharsetResponse();

        $this->expectException(CharsetConversionException::class);
        $response->getUtf8Body();
    }

    /**
     * Test jsonSerialize() returns all properties.
     */
    public function testJsonSerialize(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = ['HTTP/1.1 200 OK'];
        $body = '{}';
        $response = new Response($request, $headers, $body);

        // Act
        $result = $response->jsonSerialize();

        // Assert
        $this->assertIsArray($result);
        $this->assertSame('https://example.com', $result['url']);
        $this->assertSame(200, $result['http_code']);
    }

    /**
     * Test __toString() returns UTF-8 body.
     */
    public function testToString(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = ['HTTP/1.1 200 OK'];
        $body = 'Hello World';
        $response = new Response($request, $headers, $body);

        // Act
        $result = (string)$response;

        // Assert
        $this->assertSame('Hello World', $result);
    }

    /**
     * Test __toString() falls back to raw body when charset conversion fails.
     */
    public function testToStringFallsBackToRawBodyOnCharsetConversionFailure(): void
    {
        $response = $this->createInvalidCharsetResponse();

        $this->assertSame('Hello World', (string)$response);
    }

    /**
     * Test getLastHeaders() with multiple redirects.
     */
    public function testGetLastHeadersWithMultipleRedirects(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 301 Moved Permanently',
            'Location: https://example.org',
            'HTTP/1.1 302 Found',
            'Location: https://example.net',
            'HTTP/1.1 200 OK',
            'Content-Type: text/html',
        ];
        $body = '<html></html>';

        // Act
        $response = new Response($request, $headers, $body);

        // Assert
        $this->assertSame(200, $response->http_code);
        $this->assertSame('text/html', $response->content_type);
    }

    /**
     * Test getLastHeaders() when no HTTP status line found.
     */
    public function testGetLastHeadersWhenNoHttpStatusLine(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'Content-Type: text/html',
        ];
        $body = '<html></html>';

        // Act
        $response = new Response($request, $headers, $body);

        // Assert
        $this->assertSame(200, $response->http_code); // Defaults to 200
    }

    protected function createRedirectResponse(int $status, string $reasonPhrase): Response
    {
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            "HTTP/1.1 {$status} {$reasonPhrase}",
            'Location: https://example.org',
            'HTTP/1.1 200 OK',
            'Content-Type: text/html',
        ];

        return new Response($request, $headers, '<html></html>');
    }

    protected function createInterimResponse(int $status, string $reasonPhrase): Response
    {
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            "HTTP/1.1 {$status} {$reasonPhrase}",
            'Link: </assets/app.css>; rel=preload',
            'HTTP/1.1 200 OK',
            'Content-Type: text/html',
        ];

        return new Response($request, $headers, '<html></html>');
    }

    protected function createInvalidCharsetResponse(): Response
    {
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: text/html; charset=BOGUS-CHARSET',
        ];

        return new Response($request, $headers, 'Hello World');
    }

    /**
     * Test getJsonBody() throws InvalidJsonException for non-array JSON.
     */
    public function testGetJsonBodyThrowsExceptionForNonArrayJson(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = ['HTTP/1.1 200 OK'];
        $body = '"string"';
        $response = new Response($request, $headers, $body);

        // Act & Assert
        $this->expectException(\Switon\HttpClient\Exception\InvalidJsonException::class);
        $response->getJsonBody();
    }

    /**
     * Test getUtf8Body() with no charset in content type.
     */
    public function testGetUtf8BodyWithNoCharset(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: text/plain',
        ];
        $body = 'Hello World';
        $response = new Response($request, $headers, $body);

        // Act
        $result = $response->getUtf8Body();

        // Assert
        $this->assertSame('Hello World', $result);
    }

    /**
     * Test getUtf8Body() with UTF8 charset.
     */
    public function testGetUtf8BodyWithUtf8Charset(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: text/html; charset=UTF8',
        ];
        $body = 'Hello World';
        $response = new Response($request, $headers, $body);

        // Act
        $result = $response->getUtf8Body();

        // Assert
        $this->assertSame('Hello World', $result);
    }

    /**
     * Test getHeaders() merges duplicate header names into arrays.
     */
    public function testGetHeadersMergesDuplicateNames(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'X-Test: a',
            'X-Test: b',
        ];
        $body = '';
        $response = new Response($request, $headers, $body);

        // Act
        $result = $response->getHeaders();

        // Assert
        $this->assertIsArray($result['X-Test']);
        $this->assertSame(['a', 'b'], $result['X-Test']);
    }

    /**
     * Test getCookies() filters out expired cookies.
     */
    public function testGetCookiesFiltersExpired(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Set-Cookie: alive=1; Expires=Wed, 01 Jan 3000 00:00:00 GMT',
            'Set-Cookie: dead=1; Expires=Wed, 01 Jan 2000 00:00:00 GMT',
        ];
        $body = '';
        $response = new Response($request, $headers, $body);

        // Act
        $cookies = $response->getCookies();

        // Assert
        $this->assertArrayHasKey('alive', $cookies);
        $this->assertArrayNotHasKey('dead', $cookies);
    }

    /**
     * Test response construction with valid gzip encoding decodes successfully.
     */
    public function testConstructWithValidGzipEncoding(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: text/plain',
            'Content-Encoding: gzip',
        ];
        $body = gzencode('hello');

        // Act
        $response = new Response($request, $headers, $body);

        // Assert
        $this->assertSame('hello', $response->body);
    }

    /**
     * Test response construction with valid deflate encoding decodes successfully.
     */
    public function testConstructWithValidDeflateEncoding(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: text/plain',
            'Content-Encoding: deflate',
        ];
        $body = gzdeflate('hello');

        // Act
        $response = new Response($request, $headers, $body);

        // Assert
        $this->assertSame('hello', $response->body);
    }

    /**
     * Test response construction with invalid gzip encoding throws exception.
     */
    public function testConstructWithInvalidGzipEncoding(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'Content-Encoding: gzip',
        ];
        $body = 'invalid gzip data';

        // Act & Assert
        $this->expectException(\Switon\HttpClient\Exception\DecompressionException::class);
        new Response($request, $headers, $body);
    }

    /**
     * Test response construction with invalid deflate encoding throws exception.
     */
    public function testConstructWithInvalidDeflateEncoding(): void
    {
        // Arrange
        $request = new Request('GET', 'https://example.com', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.123;
        $headers = [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'Content-Encoding: deflate',
        ];
        $body = 'invalid deflate data';

        // Act & Assert
        $this->expectException(\Switon\HttpClient\Exception\DecompressionException::class);
        new Response($request, $headers, $body);
    }
}
