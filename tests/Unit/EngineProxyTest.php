<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Switon\Core\PathAliasInterface;
use Switon\HttpClient\Engine;
use Switon\HttpClient\Exception\UnsupportedProxyException;
use Switon\HttpClient\Request;
use Switon\HttpClient\Tests\Fixtures\FakeCurl;
use Switon\HttpClient\Tests\TestCase;

/**
 * Unit tests for Engine proxy protocol routing.
 */
class EngineProxyTest extends TestCase
{
    protected function makeEngine(FakeCurl $curl, array $proxies = []): Engine
    {
        $parameters = [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ];
        if ($proxies !== []) {
            $parameters['proxies'] = $proxies;
        }

        return $this->make(Engine::class, $parameters);
    }

    protected function makeRequest(string $proxy): Request
    {
        return new Request('GET', 'https://example.com', null, [], [
            'timeout' => 1,
            'connect_timeout' => 1,
            'verify_peer' => false,
            'proxy' => $proxy,
            'cafile' => null,
        ]);
    }

    protected function primeSuccess(FakeCurl $curl): void
    {
        $curl->execResult = "HTTP/1.1 200 OK\r\n\r\nok";
        $curl->info[CURLINFO_HEADER_SIZE] = 19;
        $curl->info[CURLINFO_PRIMARY_IP] = '127.0.0.1';
    }

    public static function proxySchemeProvider(): array
    {
        return [
            ['http', CURLPROXY_HTTP],
            ['sock4', CURLPROXY_SOCKS4],
            ['socks4', CURLPROXY_SOCKS4],
            ['sock5', CURLPROXY_SOCKS5],
            ['socks5', CURLPROXY_SOCKS5],
        ];
    }

    #[DataProvider('proxySchemeProvider')]
    public function testRequestUsesProxyTypeMap(string $scheme, int $expectedProxyType): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest($scheme . '://proxy.example.com:9000'), null);

        $this->assertSame($expectedProxyType, $curl->options[CURLOPT_PROXYTYPE] ?? null);
        $this->assertSame('proxy.example.com', $curl->options[CURLOPT_PROXY] ?? null);
        $this->assertSame(9000, $curl->options[CURLOPT_PROXYPORT] ?? null);
    }

    public function testRequestUsesConfiguredProxyTypeMap(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl, ['custom' => CURLPROXY_SOCKS5]);

        $engine->request($this->makeRequest('custom://proxy.example.com:1088'), null);

        $this->assertSame(CURLPROXY_SOCKS5, $curl->options[CURLOPT_PROXYTYPE] ?? null);
        $this->assertSame(1088, $curl->options[CURLOPT_PROXYPORT] ?? null);
    }

    public function testRequestUsesDefaultPortWhenProxyPortMissing(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('socks5://proxy.example.com'), null);

        $this->assertSame(1080, $curl->options[CURLOPT_PROXYPORT] ?? null);
    }

    public function testRequestUsesDefaultHttpPortWhenProxyPortMissing(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('http://proxy.example.com'), null);

        $this->assertSame(CURLPROXY_HTTP, $curl->options[CURLOPT_PROXYTYPE] ?? null);
        $this->assertSame(80, $curl->options[CURLOPT_PROXYPORT] ?? null);
    }

    public function testRequestSetsProxyCredentialsWhenUserAndPassProvided(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('http://alice:secret@proxy.example.com:8080'), null);

        $this->assertSame('alice', $curl->options[CURLOPT_PROXYUSERNAME] ?? null);
        $this->assertSame('secret', $curl->options[CURLOPT_PROXYPASSWORD] ?? null);
    }

    public function testRequestDoesNotSetProxyCredentialsWhenPasswordMissing(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('http://alice@proxy.example.com:8080'), null);

        $this->assertArrayNotHasKey(CURLOPT_PROXYUSERNAME, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_PROXYPASSWORD, $curl->options);
    }

    public function testRequestThrowsWhenProxySchemeUnsupported(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $this->expectException(UnsupportedProxyException::class);
        $this->expectExceptionMessage('Unsupported proxy scheme');

        $engine->request($this->makeRequest('ftp://proxy.example.com:2121'), null);
    }

    public function testRequestThrowsWhenProxyUrlInvalid(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $this->expectException(UnsupportedProxyException::class);
        $this->expectExceptionMessage('Invalid proxy URL');

        $engine->request($this->makeRequest('proxy-without-scheme-or-host'), null);
    }

    public function testRequestUsesDefaultPortWhenSocks4ProxyPortMissing(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('socks4://proxy.example.com'), null);

        $this->assertSame(CURLPROXY_SOCKS4, $curl->options[CURLOPT_PROXYTYPE] ?? null);
        $this->assertSame(1080, $curl->options[CURLOPT_PROXYPORT] ?? null);
    }

    public function testRequestSetsProxyCredentialsWhenPasswordIsEmptyString(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('http://alice:@proxy.example.com:8080'), null);

        $this->assertSame('alice', $curl->options[CURLOPT_PROXYUSERNAME] ?? null);
        $this->assertSame('', $curl->options[CURLOPT_PROXYPASSWORD] ?? null);
    }

    public function testRequestThrowsWhenConfiguredProxyTypeIsNotInteger(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl, ['http' => 'invalid-type']);

        $this->expectException(UnsupportedProxyException::class);
        $this->expectExceptionMessage('Unsupported proxy scheme');

        $engine->request($this->makeRequest('http://proxy.example.com:8080'), null);
    }

    public function testRequestThrowsWhenProxyMissingHostEvenWithScheme(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $this->expectException(UnsupportedProxyException::class);
        $this->expectExceptionMessage('Invalid proxy URL');

        $engine->request($this->makeRequest('http:///'), null);
    }

    public function testRequestDoesNotSetProxyOptionsWhenProxyIsNull(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);
        $request = new Request('GET', 'https://example.com', null, [], [
            'timeout' => 1,
            'connect_timeout' => 1,
            'verify_peer' => false,
            'proxy' => null,
            'cafile' => null,
        ]);

        $engine->request($request, null);

        $this->assertArrayNotHasKey(CURLOPT_PROXYTYPE, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_PROXY, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_PROXYPORT, $curl->options);
    }
}
