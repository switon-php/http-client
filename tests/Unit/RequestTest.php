<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\HttpClient\MemoryFile;
use Switon\HttpClient\Request;
use Switon\HttpClient\Tests\TestCase;

/**
 * Test cases for Request class.
 *
 * Tests request construction, URL building, multipart building, and file detection.
 */
class RequestTest extends TestCase
{
    /**
     * Test basic request construction with string URL.
     */
    public function testConstructWithStringUrl(): void
    {
        // Arrange & Act
        $request = new Request('GET', 'https://example.com', null, [], []);

        // Assert
        $this->assertSame('GET', $request->method);
        $this->assertSame('https://example.com', $request->url);
        $this->assertNull($request->body);
        $this->assertSame([], $request->headers);
        $this->assertSame([], $request->options);
    }

    /**
     * Test request construction with array URL (single element).
     */
    public function testConstructWithArrayUrlSingleElement(): void
    {
        // Arrange & Act
        $request = new Request('GET', ['https://example.com'], null, [], []);

        // Assert
        $this->assertSame('https://example.com', $request->url);
    }

    /**
     * Test request construction with array URL (multiple elements - query string).
     */
    public function testConstructWithArrayUrlWithQueryParams(): void
    {
        // Arrange & Act
        $request = new Request('GET', ['https://example.com', 'key1' => 'value1', 'key2' => 'value2'], null, [], []);

        // Assert
        $this->assertStringContainsString('https://example.com', $request->url);
        $this->assertStringContainsString('key1=value1', $request->url);
        $this->assertStringContainsString('key2=value2', $request->url);
    }

    /**
     * Test request construction with array URL containing existing query string.
     */
    public function testConstructWithArrayUrlWithExistingQuery(): void
    {
        // Arrange & Act
        $request = new Request('GET', ['https://example.com?existing=param', 'key1' => 'value1'], null, [], []);

        // Assert
        $this->assertStringContainsString('https://example.com', $request->url);
        $this->assertStringContainsString('existing=param', $request->url);
        $this->assertStringContainsString('key1=value1', $request->url);
        $this->assertStringContainsString('&', $request->url);
    }

    /**
     * Test hasFile() returns false when body is not an array.
     */
    public function testHasFileReturnsFalseWhenBodyIsNotArray(): void
    {
        // Arrange
        $request = new Request('POST', 'https://example.com', 'string body', [], []);

        // Act & Assert
        $this->assertFalse($request->hasFile());
    }

    /**
     * Test hasFile() returns false when Content-Type header is set.
     */
    public function testHasFileReturnsFalseWhenContentTypeIsSet(): void
    {
        // Arrange
        $request = new Request('POST', 'https://example.com', ['key' => 'value'], ['Content-Type' => 'application/json'], []);

        // Act & Assert
        $this->assertFalse($request->hasFile());
    }

    /**
     * Test hasFile() returns false when body array contains no files.
     */
    public function testHasFileReturnsFalseWhenNoFilesInBody(): void
    {
        // Arrange
        $request = new Request('POST', 'https://example.com', ['key' => 'value'], [], []);

        // Act & Assert
        $this->assertFalse($request->hasFile());
    }

    /**
     * Test hasFile() returns true when body array contains a file.
     */
    public function testHasFileReturnsTrueWhenFileInBody(): void
    {
        // Arrange
        $file = new MemoryFile('content', 'text/plain', 'test.txt');
        $request = new Request('POST', 'https://example.com', ['file' => $file], [], []);

        // Act & Assert
        $this->assertTrue($request->hasFile());
    }

    /**
     * Test hasFile() returns true when multipart Content-Type is set.
     */
    public function testHasFileReturnsTrueWithMultipartContentType(): void
    {
        // Arrange
        $file = new MemoryFile('content', 'text/plain', 'test.txt');
        $request = new Request(
            'POST',
            'https://example.com',
            ['file' => $file],
            ['Content-Type' => 'multipart/form-data; boundary=abc'],
            []
        );

        // Act & Assert
        $this->assertTrue($request->hasFile());
    }

    /**
     * Test buildMultipart() with regular values.
     */
    public function testBuildMultipartWithRegularValues(): void
    {
        // Arrange
        $request = new Request('POST', 'https://example.com', ['key1' => 'value1', 'key2' => 'value2'], [], []);
        $boundary = 'test-boundary';

        // Act
        $result = $request->buildMultipart($boundary);

        // Assert
        $this->assertStringContainsString('--test-boundary', $result);
        $this->assertStringContainsString('Content-Disposition: form-data; name="key1"', $result);
        $this->assertStringContainsString('value1', $result);
        $this->assertStringContainsString('Content-Disposition: form-data; name="key2"', $result);
        $this->assertStringContainsString('value2', $result);
        $this->assertStringEndsWith("--test-boundary--\r\n", $result);
    }

    /**
     * Test buildMultipart() with file.
     */
    public function testBuildMultipartWithFile(): void
    {
        // Arrange
        $file = new MemoryFile('file content', 'text/plain', 'test.txt');
        $request = new Request('POST', 'https://example.com', ['file' => $file], [], []);
        $boundary = 'test-boundary';

        // Act
        $result = $request->buildMultipart($boundary);

        // Assert
        $this->assertStringContainsString('--test-boundary', $result);
        $this->assertStringContainsString('Content-Disposition: form-data; name="file"; filename="test.txt"', $result);
        $this->assertStringContainsString('Content-Type: text/plain', $result);
        $this->assertStringContainsString('file content', $result);
        $this->assertStringEndsWith("--test-boundary--\r\n", $result);
    }

    /**
     * Test jsonSerialize() returns all properties.
     */
    public function testJsonSerialize(): void
    {
        // Arrange
        $request = new Request('POST', 'https://example.com', ['key' => 'value'], ['Header' => 'Value'], ['timeout' => 10]);
        $request->process_time = 0.123;
        $request->remote_ip = '127.0.0.1';

        // Act
        $result = $request->jsonSerialize();

        // Assert
        $this->assertIsArray($result);
        $this->assertSame('POST', $result['method']);
        $this->assertSame('https://example.com', $result['url']);
        $this->assertSame(['key' => 'value'], $result['body']);
        $this->assertSame(['Header' => 'Value'], $result['headers']);
        $this->assertSame(['timeout' => 10], $result['options']);
        $this->assertSame(0.123, $result['process_time']);
        $this->assertSame('127.0.0.1', $result['remote_ip']);
    }

    public function testHeaderAndOptionShortcuts(): void
    {
        $request = new Request('POST', 'https://example.com', null, ['X-Test' => 'abc'], ['timeout' => 10]);

        $this->assertSame('abc', $request->header('X-Test'));
        $this->assertSame(10, $request->option('timeout'));
    }

    public function testHeaderLookupAndRemovalAreCaseInsensitive(): void
    {
        $request = new Request('POST', 'https://example.com', null, ['X-Test' => 'abc'], []);

        $this->assertSame('abc', $request->header('x-test'));
        $this->assertTrue($request->hasHeader('x-test'));

        $request->removeHeader('x-test');

        $this->assertFalse($request->hasHeader('X-Test'));
    }

    public function testHasFileRespectsLowercaseContentTypeHeader(): void
    {
        $file = new MemoryFile('content', 'text/plain', 'test.txt');
        $request = new Request(
            'POST',
            'https://example.com',
            ['file' => $file],
            ['content-type' => 'application/json'],
            []
        );

        $this->assertFalse($request->hasFile());
    }

    public function testOptionAccessAndMutationAreAvailable(): void
    {
        $request = new Request('POST', 'https://example.com', null, [], ['timeout' => 10]);

        $this->assertSame(['timeout' => 10], $request->getOptions());
        $this->assertSame(['timeout' => 10], $request->options);
        $this->assertSame(10, $request->option('timeout'));
        $this->assertSame('fallback', $request->option('missing', 'fallback'));

        $request->setOption('connect_timeout', 3)->disablePeerVerification();

        $this->assertSame(3, $request->getOption('connect_timeout'));
        $this->assertFalse($request->option('verify_peer'));
    }

    public function testMagicAccessorsExposeAndProtectRequestState(): void
    {
        $request = new Request('POST', 'https://example.com', null, [], []);
        $request->markCompleted('127.0.0.1', 0.456);

        $this->assertSame('POST', $request->method);
        $this->assertSame('https://example.com', $request->url);
        $this->assertSame(0.456, $request->process_time);
        $this->assertSame('127.0.0.1', $request->remote_ip);
        $this->assertTrue(isset($request->method));
        $this->assertTrue(isset($request->remote_ip));
        $this->assertFalse(isset($request->unknown));

        $this->expectException(\Switon\Core\Exception\RuntimeException::class);
        $request->unknown = 'value';
    }
}
