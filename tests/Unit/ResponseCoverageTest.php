<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\Core\Exception\RuntimeException;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;
use Switon\HttpClient\Tests\TestCase;

class ResponseCoverageTest extends TestCase
{
    public function testAccessorsExposeTransportMetadata(): void
    {
        $request = new Request('GET', 'https://example.com/api', null, [], []);
        $request->markCompleted('127.0.0.1', 0.456);
        $response = new Response($request, [
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'X-Test: abc',
        ], '{}');

        $this->assertSame(0.456, $response->getProcessTime());
        $this->assertSame('127.0.0.1', $response->getRemoteIp());
        $this->assertSame([
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
            'X-Test: abc',
        ], $response->getRawHeaders());
        $this->assertSame($response->getRawHeaders(), $response->rawHeaders());
        $this->assertTrue(isset($response->url));
        $this->assertFalse(isset($response->unknown));
    }

    public function testSetThrowsForReadOnlyProperty(): void
    {
        $request = new Request('GET', 'https://example.com/api', null, [], []);
        $request->markCompleted('127.0.0.1', 0.456);
        $response = new Response($request, ['HTTP/1.1 200 OK'], '');

        $this->expectException(RuntimeException::class);

        $response->url = 'https://example.org';
    }
}
