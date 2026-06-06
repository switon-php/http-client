<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\Core\PathAliasInterface;
use Switon\HttpClient\Engine;
use Switon\HttpClient\Request;
use Switon\HttpClient\Tests\Fixtures\FakeCurl;
use Switon\HttpClient\Tests\TestCase;

class EngineTlsOptionTest extends TestCase
{
    protected function makeRequest(array $overrides = []): Request
    {
        return new Request('GET', 'https://example.com', null, [], $overrides + [
                'timeout' => 1,
                'connect_timeout' => 1,
                'verify_peer' => true,
                'proxy' => null,
                'cafile' => null,
            ]);
    }

    protected function primeSuccess(FakeCurl $curl): void
    {
        $curl->execResult = "HTTP/1.1 200 OK\r\n\r\nok";
        $curl->info[CURLINFO_HEADER_SIZE] = 19;
        $curl->info[CURLINFO_PRIMARY_IP] = '127.0.0.1';
    }

    public function testRequestSetsCainfoFromResolvedAliasWhenCafileProvided(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);

        $pathAlias = $this->createMock(PathAliasInterface::class);
        $pathAlias->expects($this->once())
            ->method('resolve')
            ->with('@runtime/certs/ca.pem')
            ->willReturn('/abs/runtime/certs/ca.pem');

        $engine = $this->make(Engine::class, [
            'pathAlias' => $pathAlias,
            'curl' => $curl,
        ]);

        $engine->request($this->makeRequest(['cafile' => '@runtime/certs/ca.pem']), null);

        $this->assertSame('/abs/runtime/certs/ca.pem', $curl->options[CURLOPT_CAINFO] ?? null);
    }

    public function testRequestDisablesSslVerificationWhenVerifyPeerIsFalse(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);

        $engine = $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);

        $engine->request($this->makeRequest(['verify_peer' => false]), null);

        $this->assertFalse($curl->options[CURLOPT_SSL_VERIFYHOST] ?? null);
        $this->assertFalse($curl->options[CURLOPT_SSL_VERIFYPEER] ?? null);
    }

    public function testRequestKeepsDefaultSslVerificationWhenVerifyPeerIsTrue(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);

        $pathAlias = $this->createMock(PathAliasInterface::class);
        $pathAlias->expects($this->never())->method('resolve');

        $engine = $this->make(Engine::class, [
            'pathAlias' => $pathAlias,
            'curl' => $curl,
        ]);

        $request = $this->makeRequest([
            'verify_peer' => true,
            'cafile' => null,
        ]);
        $engine->request($request, null);

        $this->assertArrayNotHasKey(CURLOPT_SSL_VERIFYHOST, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_SSL_VERIFYPEER, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_CAINFO, $curl->options);
        $this->assertTrue($request->getOption('verify_peer'));
    }

    public function testRequestWithCafileAndVerifyFalseSetsCainfoAndDisablesVerification(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);

        $pathAlias = $this->createMock(PathAliasInterface::class);
        $pathAlias->expects($this->once())
            ->method('resolve')
            ->with('@runtime/certs/custom.pem')
            ->willReturn('/abs/runtime/certs/custom.pem');

        $engine = $this->make(Engine::class, [
            'pathAlias' => $pathAlias,
            'curl' => $curl,
        ]);

        $engine->request($this->makeRequest([
            'cafile' => '@runtime/certs/custom.pem',
            'verify_peer' => false,
        ]), null);

        $this->assertSame('/abs/runtime/certs/custom.pem', $curl->options[CURLOPT_CAINFO] ?? null);
        $this->assertFalse($curl->options[CURLOPT_SSL_VERIFYHOST] ?? null);
        $this->assertFalse($curl->options[CURLOPT_SSL_VERIFYPEER] ?? null);
    }

    public function testRequestWithCafileAndVerifyTrueSetsCainfoOnly(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);

        $pathAlias = $this->createMock(PathAliasInterface::class);
        $pathAlias->expects($this->once())
            ->method('resolve')
            ->with('@runtime/certs/strict.pem')
            ->willReturn('/abs/runtime/certs/strict.pem');

        $engine = $this->make(Engine::class, [
            'pathAlias' => $pathAlias,
            'curl' => $curl,
        ]);

        $engine->request($this->makeRequest([
            'cafile' => '@runtime/certs/strict.pem',
            'verify_peer' => true,
        ]), null);

        $this->assertSame('/abs/runtime/certs/strict.pem', $curl->options[CURLOPT_CAINFO] ?? null);
        $this->assertArrayNotHasKey(CURLOPT_SSL_VERIFYHOST, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_SSL_VERIFYPEER, $curl->options);
    }

    public function testRequestAlwaysSetsDefaultAcceptEncodingWhenMissing(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);

        $request = $this->makeRequest();
        $engine->request($request, null);

        $headers = $curl->options[CURLOPT_HTTPHEADER] ?? [];
        $this->assertContains('Accept-Encoding: gzip, deflate', $headers);
    }

    public function testRequestKeepsProvidedAcceptEncodingHeader(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);

        $request = $this->makeRequest();
        $request->setHeader('Accept-Encoding', 'br');
        $engine->request($request, null);

        $headers = $curl->options[CURLOPT_HTTPHEADER] ?? [];
        $this->assertContains('Accept-Encoding: br', $headers);
        $this->assertNotContains('Accept-Encoding: gzip, deflate', $headers);
    }

    public function testRequestKeepsProvidedAcceptEncodingHeaderWithLowercaseName(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);

        $request = $this->makeRequest();
        $request->setHeader('accept-encoding', 'br');
        $engine->request($request, null);

        $headers = $curl->options[CURLOPT_HTTPHEADER] ?? [];
        $this->assertContains('accept-encoding: br', $headers);
        $this->assertNotContains('Accept-Encoding: gzip, deflate', $headers);
    }

    public function testRequestSetsTimeoutAndConnectTimeoutOptions(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);

        $request = new Request('GET', 'https://example.com', null, [], [
            'timeout' => 3,
            'connect_timeout' => 2,
            'verify_peer' => false,
            'proxy' => null,
            'cafile' => null,
        ]);
        $engine->request($request, null);

        $this->assertSame(3, $curl->options[CURLOPT_TIMEOUT] ?? null);
        $this->assertSame(2, $curl->options[CURLOPT_CONNECTTIMEOUT] ?? null);
    }

    public function testRequestUsesTimeoutAsConnectTimeoutWhenNotProvided(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);

        $request = new Request('GET', 'https://example.com', null, [], [
            'timeout' => 4,
            'verify_peer' => false,
            'proxy' => null,
            'cafile' => null,
        ]);
        $engine->request($request, null);

        $this->assertSame(4, $curl->options[CURLOPT_TIMEOUT] ?? null);
        $this->assertSame(4, $curl->options[CURLOPT_CONNECTTIMEOUT] ?? null);
    }

    public function testRequestSetsUrlHeaderAndTransferOptions(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);

        $request = $this->makeRequest();
        $engine->request($request, null);

        $this->assertSame('https://example.com', $curl->options[CURLOPT_URL] ?? null);
        $this->assertSame(1, $curl->options[CURLOPT_RETURNTRANSFER] ?? null);
        $this->assertTrue($curl->options[CURLOPT_AUTOREFERER] ?? false);
        $this->assertTrue($curl->options[CURLOPT_FOLLOWLOCATION] ?? false);
        $this->assertSame(8, $curl->options[CURLOPT_MAXREDIRS] ?? null);
        $this->assertSame(1, $curl->options[CURLOPT_HEADER] ?? null);
    }

    public function testRequestSetsCustomHeadersFromRequest(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);

        $request = $this->makeRequest();
        $request->setHeader('X-Trace', 'abc');
        $engine->request($request, null);

        $headers = $curl->options[CURLOPT_HTTPHEADER] ?? [];
        $this->assertContains('X-Trace: abc', $headers);
    }

    public function testRequestSupportsIntegerHeaderIndexes(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);

        $request = $this->makeRequest();
        $request->setHeader(0, 'X-Raw: yes');
        $engine->request($request, null);

        $headers = $curl->options[CURLOPT_HTTPHEADER] ?? [];
        $this->assertContains('X-Raw: yes', $headers);
    }

    public function testRequestSetsRequestProcessTimeAsRoundedFloat(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);

        $request = $this->makeRequest();
        $engine->request($request, null);

        $this->assertIsFloat($request->process_time);
        $this->assertGreaterThanOrEqual(0.0, $request->process_time);
    }
}
