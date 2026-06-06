<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\Core\PathAliasInterface;
use Switon\HttpClient\Engine;
use Switon\HttpClient\Exception\ConnectionException;
use Switon\HttpClient\Exception\ConnectTimeoutException;
use Switon\HttpClient\Exception\DnsException;
use Switon\HttpClient\Exception\SslException;
use Switon\HttpClient\Exception\TimeoutException;
use Switon\HttpClient\Request;
use Switon\HttpClient\Tests\Fixtures\FakeCurl;
use Switon\HttpClient\Tests\TestCase;
use Throwable;

class EngineCurlErrorTest extends TestCase
{
    protected function makeEngineWithFakeCurl(FakeCurl $curl): Engine
    {
        return $this->make(Engine::class, [
            'pathAlias' => $this->createStub(PathAliasInterface::class),
            'curl' => $curl,
        ]);
    }

    protected function makeRequest(): Request
    {
        return new Request('GET', 'https://example.com', null, [], [
            'timeout' => 1,
            'verify_peer' => false,
            'proxy' => null,
            'cafile' => null,
        ]);
    }

    public function testDnsException(): void
    {
        $curl = new FakeCurl();
        $curl->errno = CURLE_COULDNT_RESOLVE_HOST;
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(DnsException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testSslException(): void
    {
        $curl = new FakeCurl();
        $curl->errno = CURLE_SSL_CONNECT_ERROR;
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(SslException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testConnectTimeoutException(): void
    {
        $curl = new FakeCurl();
        $curl->errno = CURLE_OPERATION_TIMEDOUT;
        $curl->info[CURLINFO_TOTAL_TIME] = 1.0;
        $curl->info[CURLINFO_CONNECT_TIME] = 0.99;
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(ConnectTimeoutException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testTimeoutException(): void
    {
        $curl = new FakeCurl();
        $curl->errno = CURLE_OPERATION_TIMEDOUT;
        $curl->info[CURLINFO_TOTAL_TIME] = 1.0;
        $curl->info[CURLINFO_CONNECT_TIME] = 0.1;
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(TimeoutException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testConnectionExceptionFallback(): void
    {
        $curl = new FakeCurl();
        $curl->errno = CURLE_COULDNT_CONNECT;
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(ConnectionException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testRetriesWithEncodingNoneWhenErrnoIs23ThenSucceeds(): void
    {
        $curl = new class () extends FakeCurl {
            public int $execCalls = 0;
            public array $errnoByCall = [23, 0];

            public function exec(mixed $handle): string|bool
            {
                $this->execCalls++;
                return "HTTP/1.1 200 OK\r\n\r\nok";
            }

            public function errno(mixed $handle): int
            {
                return $this->errnoByCall[$this->execCalls - 1] ?? 0;
            }
        };
        $curl->info[CURLINFO_HEADER_SIZE] = 19;
        $curl->info[CURLINFO_PRIMARY_IP] = '127.0.0.1';

        $engine = $this->makeEngineWithFakeCurl($curl);
        $response = $engine->request($this->makeRequest(), null);

        $this->assertSame(2, $curl->execCalls);
        $this->assertSame('none', $curl->options[CURLOPT_ENCODING] ?? null);
        $this->assertSame('ok', $response->body);
    }

    public function testRetriesWithEncodingNoneWhenErrnoIs61ThenSucceeds(): void
    {
        $curl = new class () extends FakeCurl {
            public int $execCalls = 0;
            public array $errnoByCall = [61, 0];

            public function exec(mixed $handle): string|bool
            {
                $this->execCalls++;
                return "HTTP/1.1 200 OK\r\n\r\nok";
            }

            public function errno(mixed $handle): int
            {
                return $this->errnoByCall[$this->execCalls - 1] ?? 0;
            }
        };
        $curl->info[CURLINFO_HEADER_SIZE] = 19;
        $curl->info[CURLINFO_PRIMARY_IP] = '127.0.0.1';

        $engine = $this->makeEngineWithFakeCurl($curl);
        $response = $engine->request($this->makeRequest(), null);

        $this->assertSame(2, $curl->execCalls);
        $this->assertSame('none', $curl->options[CURLOPT_ENCODING] ?? null);
        $this->assertSame('ok', $response->body);
    }

    public function testDnsExceptionForProxyResolutionFailure(): void
    {
        $curl = new FakeCurl();
        $curl->errno = CURLE_COULDNT_RESOLVE_PROXY;
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(DnsException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testSslExceptionForCertProblem(): void
    {
        $curl = new FakeCurl();
        $curl->errno = CURLE_SSL_CERTPROBLEM;
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(SslException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testSslExceptionForCaCertError(): void
    {
        $curl = new FakeCurl();
        $curl->errno = CURLE_SSL_CACERT;
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(SslException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testTimeoutExceptionWhenErrnoIs28AndConnectTimeIsLow(): void
    {
        $curl = new FakeCurl();
        $curl->errno = 28;
        $curl->info[CURLINFO_TOTAL_TIME] = 1.0;
        $curl->info[CURLINFO_CONNECT_TIME] = 0.2;
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(TimeoutException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testConnectTimeoutExceptionWhenErrnoIs28AndConnectNearTotal(): void
    {
        $curl = new FakeCurl();
        $curl->errno = 28;
        $curl->info[CURLINFO_TOTAL_TIME] = 1.0;
        $curl->info[CURLINFO_CONNECT_TIME] = 1.0;
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(ConnectTimeoutException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testRetryAfterErrno23ThenDnsFailureThrowsDnsException(): void
    {
        $curl = new class () extends FakeCurl {
            public int $execCalls = 0;
            public array $errnoByCall = [23, CURLE_COULDNT_RESOLVE_HOST];

            public function exec(mixed $handle): string|bool
            {
                $this->execCalls++;
                return "HTTP/1.1 200 OK\r\n\r\nok";
            }

            public function errno(mixed $handle): int
            {
                return $this->errnoByCall[$this->execCalls - 1] ?? 0;
            }
        };
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(DnsException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testRetryAfterErrno61ThenConnectionFailureThrowsConnectionException(): void
    {
        $curl = new class () extends FakeCurl {
            public int $execCalls = 0;
            public array $errnoByCall = [61, CURLE_COULDNT_CONNECT];

            public function exec(mixed $handle): string|bool
            {
                $this->execCalls++;
                return "HTTP/1.1 200 OK\r\n\r\nok";
            }

            public function errno(mixed $handle): int
            {
                return $this->errnoByCall[$this->execCalls - 1] ?? 0;
            }
        };
        $engine = $this->makeEngineWithFakeCurl($curl);

        $this->expectException(ConnectionException::class);
        $engine->request($this->makeRequest(), null);
    }

    public function testRequestFailureClosesHandleAndNextRequestReinitializes(): void
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
        $engine = $this->makeEngineWithFakeCurl($curl);

        $curl->errno = CURLE_COULDNT_CONNECT;
        try {
            $engine->request($this->makeRequest(), null);
        } catch (Throwable) {
        }

        $curl->errno = 0;
        $curl->execResult = "HTTP/1.1 200 OK\r\n\r\nok";
        $curl->info[CURLINFO_HEADER_SIZE] = 19;
        $curl->info[CURLINFO_PRIMARY_IP] = '127.0.0.1';
        $engine->request($this->makeRequest(), null);

        $this->assertSame(2, $curl->initCount);
        $this->assertSame(1, $curl->closeCount);
    }

    public function testSuccessfulRequestSetsRemoteIpAndProcessTimeOnRequest(): void
    {
        $curl = new FakeCurl();
        $curl->execResult = "HTTP/1.1 200 OK\r\n\r\nok";
        $curl->info[CURLINFO_HEADER_SIZE] = 19;
        $curl->info[CURLINFO_PRIMARY_IP] = '10.0.0.8';

        $engine = $this->makeEngineWithFakeCurl($curl);
        $request = $this->makeRequest();
        $engine->request($request, null);

        $this->assertSame('10.0.0.8', $request->remote_ip);
        $this->assertIsFloat($request->process_time);
    }

    public function testResponseHeadersAndBodyAreParsedUsingHeaderSize(): void
    {
        $curl = new FakeCurl();
        $curl->execResult = "HTTP/1.1 200 OK\r\nX-Test: 1\r\n\r\npayload";
        $curl->info[CURLINFO_HEADER_SIZE] = 30;
        $curl->info[CURLINFO_PRIMARY_IP] = '127.0.0.1';

        $engine = $this->makeEngineWithFakeCurl($curl);
        $response = $engine->request($this->makeRequest(), null);

        $this->assertSame('payload', $response->body);
        $this->assertNotEmpty($response->headers);
    }
}
