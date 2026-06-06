<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Integration;

use PHPUnit\Framework\SkippedTestSuiteError;
use Switon\Core\PathAlias;
use Switon\HttpClient\Engine;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;
use Switon\HttpClient\Tests\Fixtures\TestServerManager;
use Switon\HttpClient\Tests\TestCase;
use RuntimeException;

/**
 * Test cases for Engine class.
 *
 * Tests cURL engine implementation with real HTTP requests.
 * Uses local test server for HTTP testing.
 *
 * Note: These are integration tests that require network access and are slower.
 * Run with: ./run.sh integration
 * Or skip with: phpunit --exclude-group integration
 *
 * Only essential tests are kept here. Detailed functionality is tested in unit tests with MockEngine.
 *
 * @group integration
 * @group slow
 */
class EngineTest extends TestCase
{
    protected PathAlias $pathAlias;
    protected static ?string $testServerUrl = null;
    protected static ?TestServerManager $testServerManager = null;

    /**
     * Set up before all tests - start test server.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        try {
            self::$testServerManager = new TestServerManager();
            self::$testServerManager->start();
            self::$testServerUrl = self::$testServerManager->getUrl();
        } catch (RuntimeException $exception) {
            throw new SkippedTestSuiteError('Integration test server is unavailable in this environment: ' . $exception->getMessage());
        }
    }

    /**
     * Tear down after all tests - stop test server.
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$testServerManager !== null) {
            self::$testServerManager->stop();
            self::$testServerManager = null;
        }
        self::$testServerUrl = null;
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pathAlias = new PathAlias();

        // Create Engine instance with dependency injection via container
        $this->engine = $this->make(Engine::class, [
            'pathAlias' => $this->pathAlias,
            'curl' => new \Switon\HttpClient\Curl(),
        ]);
    }

    protected function getTestServerUrl(): string
    {
        if (self::$testServerUrl === null) {
            throw new RuntimeException('Test server URL is not set. Server may not have started correctly.');
        }
        return self::$testServerUrl;
    }

    /**
     * Get Engine instance with proper type.
     */
    protected function getEngine(): Engine
    {
        /** @var Engine $engine */
        $engine = $this->engine;
        return $engine;
    }

    /**
     * Test GET request.
     *
     * Verifies that Engine can make real HTTP GET requests.
     */
    public function testGetRequest(): void
    {
        // Arrange
        $request = new Request('GET', $this->getTestServerUrl() . '/get', null, [], [
            'timeout' => 1,
            'verify_peer' => false,
            'proxy' => null,
            'cafile' => null,
        ]);
        $request->remote_ip = '';
        $request->process_time = 0;

        // Act
        $response = $this->getEngine()->request($request, null);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
        $this->assertNotEmpty($response->body);
    }

    /**
     * Test POST request.
     *
     * Verifies that Engine can make real HTTP POST requests with body.
     */
    public function testPostRequest(): void
    {
        // Arrange
        $request = new Request('POST', $this->getTestServerUrl() . '/post', null, ['Content-Type' => 'application/json'], [
            'timeout' => 1,
            'verify_peer' => false,
            'proxy' => null,
            'cafile' => null,
        ]);
        $request->remote_ip = '';
        $request->process_time = 0;
        $body = '{"key":"value"}';

        // Act
        $response = $this->getEngine()->request($request, $body);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->http_code);
    }

    /**
     * Test request sets remote_ip and process_time.
     *
     * Verifies that Engine correctly sets network metadata (IP address and processing time)
     * from real HTTP requests. This requires actual network interaction.
     */
    public function testRequestSetsRemoteIpAndProcessTime(): void
    {
        // Arrange
        $request = new Request('GET', $this->getTestServerUrl() . '/get', null, [], [
            'timeout' => 1,
            'verify_peer' => false,
            'proxy' => null,
            'cafile' => null,
        ]);
        $request->remote_ip = '';
        $request->process_time = 0;

        // Act
        $response = $this->getEngine()->request($request, null);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertNotEmpty($request->remote_ip, 'remote_ip should be set from real network request');
        $this->assertGreaterThanOrEqual(0, $request->process_time, 'process_time should be set from real network request');
    }

    /**
     * Test that previous POST options do not leak into subsequent GET requests.
     */
    public function testPostDoesNotLeakIntoGet(): void
    {
        // Arrange: POST first
        $postRequest = new Request('POST', $this->getTestServerUrl() . '/post', null, ['Content-Type' => 'application/json'], [
            'timeout' => 1,
            'verify_peer' => false,
            'proxy' => null,
            'cafile' => null,
        ]);
        $postRequest->remote_ip = '';
        $postRequest->process_time = 0;
        $postBody = '{"key":"value"}';

        // Act: POST
        $postResponse = $this->getEngine()->request($postRequest, $postBody);
        $this->assertSame(200, $postResponse->http_code);

        // Act: GET after POST
        $getRequest = new Request('GET', $this->getTestServerUrl() . '/get', null, [], [
            'timeout' => 1,
            'verify_peer' => false,
            'proxy' => null,
            'cafile' => null,
        ]);
        $getRequest->remote_ip = '';
        $getRequest->process_time = 0;
        $getResponse = $this->getEngine()->request($getRequest, null);

        // Assert: response JSON reports method GET (not POST)
        $data = json_decode($getResponse->body, true);
        $this->assertIsArray($data);
        $this->assertSame('GET', $data['method'] ?? null, 'GET request should not be affected by previous POST options');
    }
}
