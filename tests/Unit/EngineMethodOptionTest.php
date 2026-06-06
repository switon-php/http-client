<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\Core\PathAliasInterface;
use Switon\HttpClient\Engine;
use Switon\HttpClient\Request;
use Switon\HttpClient\Tests\Fixtures\FakeCurl;
use Switon\HttpClient\Tests\TestCase;

class EngineMethodOptionTest extends TestCase
{
    protected function makeRequest(string $method, ?string $body = null): Request
    {
        return new Request($method, 'https://example.com', null, [], [
            'timeout' => 1,
            'connect_timeout' => 1,
            'verify_peer' => false,
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

    protected function makeEngine(FakeCurl $curl): Engine
    {
        return $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);
    }

    public function testGetDoesNotSetMethodSpecificCurlOptions(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('GET'), null);

        $this->assertArrayNotHasKey(CURLOPT_POST, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_CUSTOMREQUEST, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_NOBODY, $curl->options);
    }

    public function testHeadSetsCustomRequestAndNoBodyWithoutPostFields(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('HEAD'), 'ignored-body');

        $this->assertSame('HEAD', $curl->options[CURLOPT_CUSTOMREQUEST] ?? null);
        $this->assertTrue($curl->options[CURLOPT_NOBODY] ?? false);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_POST, $curl->options);
    }

    public function testPutSetsCustomRequestAndPostFieldsWhenBodyProvided(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('PUT'), '{"name":"mark"}');

        $this->assertSame('PUT', $curl->options[CURLOPT_CUSTOMREQUEST] ?? null);
        $this->assertSame('{"name":"mark"}', $curl->options[CURLOPT_POSTFIELDS] ?? null);
        $this->assertArrayNotHasKey(CURLOPT_POST, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_NOBODY, $curl->options);
    }

    public function testDeleteSetsCustomRequestButSkipsPostFieldsEvenWithBody(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('DELETE'), '{"force":true}');

        $this->assertSame('DELETE', $curl->options[CURLOPT_CUSTOMREQUEST] ?? null);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_POST, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_NOBODY, $curl->options);
    }

    public function testPostUsesCurlPostAndPostFields(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('POST'), 'a=1&b=2');

        $this->assertSame(1, $curl->options[CURLOPT_POST] ?? null);
        $this->assertSame('a=1&b=2', $curl->options[CURLOPT_POSTFIELDS] ?? null);
        $this->assertArrayNotHasKey(CURLOPT_CUSTOMREQUEST, $curl->options);
    }

    public function testOptionsSetsCustomRequestAndSkipsPostFieldsEvenWithBody(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('OPTIONS'), '{"ignored":true}');

        $this->assertSame('OPTIONS', $curl->options[CURLOPT_CUSTOMREQUEST] ?? null);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_POST, $curl->options);
        $this->assertArrayNotHasKey(CURLOPT_NOBODY, $curl->options);
    }

    public function testRequestReusesHandleByResettingOnSecondCall(): void
    {
        $curl = new class () extends FakeCurl {
            public int $initCount = 0;
            public int $resetCount = 0;

            public function init(): mixed
            {
                $this->initCount++;
                return parent::init();
            }

            public function reset(mixed $handle): void
            {
                $this->resetCount++;
                parent::reset($handle);
            }
        };
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('GET'), null);
        $engine->request($this->makeRequest('GET'), null);

        $this->assertSame(1, $curl->initCount);
        $this->assertSame(1, $curl->resetCount);
    }

    public function testCloneClosesExistingHandleThenCreatesNewOneOnNextRequest(): void
    {
        $curl = new class () extends FakeCurl {
            public int $initCount = 0;
            public int $closeCount = 0;

            public function init(): mixed
            {
                $this->initCount++;
                return parent::init();
            }

            public function close(mixed $handle): void
            {
                $this->closeCount++;
            }
        };
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('GET'), null);
        $cloned = clone $engine;
        $cloned->request($this->makeRequest('GET'), null);

        $this->assertSame(2, $curl->initCount);
        $this->assertSame(1, $curl->closeCount);
    }

    public function testPatchWithoutBodySkipsPostFields(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('PATCH'), null);

        $this->assertSame('PATCH', $curl->options[CURLOPT_CUSTOMREQUEST] ?? null);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $curl->options);
    }

    public function testPatchWithBodySetsPostFields(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('PATCH'), '{"name":"patched"}');

        $this->assertSame('PATCH', $curl->options[CURLOPT_CUSTOMREQUEST] ?? null);
        $this->assertSame('{"name":"patched"}', $curl->options[CURLOPT_POSTFIELDS] ?? null);
    }

    public function testPostWithNullBodyStillSetsPostOptionAndNullPostFields(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('POST'), null);

        $this->assertSame(1, $curl->options[CURLOPT_POST] ?? null);
        $this->assertArrayHasKey(CURLOPT_POSTFIELDS, $curl->options);
        $this->assertNull($curl->options[CURLOPT_POSTFIELDS]);
    }

    public function testLowercaseGetIsTreatedAsCustomMethod(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('get'), null);

        $this->assertSame('get', $curl->options[CURLOPT_CUSTOMREQUEST] ?? null);
        $this->assertArrayNotHasKey(CURLOPT_POST, $curl->options);
    }

    public function testHeadWithNullBodyStillSetsNoBodyOption(): void
    {
        $curl = new FakeCurl();
        $this->primeSuccess($curl);
        $engine = $this->makeEngine($curl);

        $engine->request($this->makeRequest('HEAD'), null);

        $this->assertSame('HEAD', $curl->options[CURLOPT_CUSTOMREQUEST] ?? null);
        $this->assertTrue($curl->options[CURLOPT_NOBODY] ?? false);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $curl->options);
    }
}
