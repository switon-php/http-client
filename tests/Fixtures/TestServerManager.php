<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Fixtures;

use RuntimeException;

use function exec;
use function file_put_contents;
use function fsockopen;
use function hrtime;
use function is_file;
use function is_resource;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function socket_bind;
use function socket_close;
use function socket_create;
use function sprintf;
use function unlink;
use function usleep;

/**
 * Test server manager for HTTP Client integration tests.
 *
 * Manages the lifecycle of a local test HTTP server:
 * - Starts PHP built-in server on 127.0.0.1 and a random high port
 * - Marks readiness via a temp file once TCP accepts (no curl health polling)
 * - Provides server URL and stops the server process on teardown
 */
class TestServerManager
{
    protected ?string $ip = null;
    protected ?int $port = null;
    protected $process = null;
    protected string $serverUrl = '';
    protected string $serverScript = '';
    protected string $serverDir = '';
    protected string $readyFile = '';
    protected bool $shutdownRegistered = false;
    protected array $originalProxyEnv = [];

    public function __construct()
    {
        $this->serverScript = __DIR__ . '/TestServer.php';
        $this->serverDir = __DIR__;
    }

    /**
     * Start the test server.
     *
     * Finds an available random IP:port combination and starts the server.
     *
     * @throws RuntimeException If server cannot be started after retries
     */
    public function start(): void
    {
        if ($this->isRunning()) {
            return;
        }
        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function(function (): void {
                $this->stop();
            });
        }

        $this->disableProxyEnvironment();

        $ip = $this->generateRandomIp();
        $port = null;
        $maxAttempts = 20;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = $this->generateRandomPort();
            if ($this->tryBind($ip, $candidate)) {
                $port = $candidate;
                break;
            }
        }
        if ($port === null) {
            throw new RuntimeException('Unable to find an available port for the test server after multiple attempts.');
        }

        $this->ip = $ip;
        $this->port = $port;
        $this->serverUrl = sprintf('http://%s:%d', $ip, $port);
        $this->readyFile = $this->readyFilePath($port);
        $this->removeReadyFile();

        if (!$this->startServer($ip, $port)) {
            throw new RuntimeException('Failed to start PHP built-in server process.');
        }

        if (!$this->waitForServer()) {
            $this->stop();
            throw new RuntimeException('Server process started but did not respond within timeout period.');
        }
    }

    /**
     * Stop the test server.
     */
    public function stop(): void
    {
        if ($this->process !== null && is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status !== false && isset($status['pid']) && $status['pid'] > 0) {
                proc_terminate($this->process, 15);
                usleep(50000);
                if ($this->isProcessRunning($status['pid'])) {
                    proc_terminate($this->process, 9);
                }
            }
            proc_close($this->process);
            $this->process = null;
        }

        if ($this->port !== null) {
            $this->killProcessOnPort($this->ip ?? '127.0.0.1', $this->port);
        }

        $this->removeReadyFile();
        $this->ip = null;
        $this->port = null;
        $this->serverUrl = '';
    }

    /**
     * Get the server URL.
     *
     * @return string Server URL (e.g., "http://127.0.0.1:18000")
     */
    public function getUrl(): string
    {
        if ($this->serverUrl === '') {
            throw new RuntimeException('Server is not started. Call start() first.');
        }

        return $this->serverUrl;
    }

    /**
     * Check if server is running.
     */
    public function isRunning(): bool
    {
        if ($this->serverUrl === '' || $this->port === null) {
            return false;
        }

        if ($this->process !== null) {
            $status = proc_get_status($this->process);
            if ($status !== false && !$status['running']) {
                return false;
            }
        }

        return $this->readyFile !== '' && is_file($this->readyFile);
    }

    protected function readyFilePath(int $port): string
    {
        return sys_get_temp_dir() . '/switon-http-client-test-' . $port . '.ready';
    }

    protected function markReady(): void
    {
        if ($this->readyFile === '') {
            return;
        }

        file_put_contents($this->readyFile, (string) ($this->port ?? 0));
    }

    protected function removeReadyFile(): void
    {
        if ($this->readyFile !== '' && is_file($this->readyFile)) {
            @unlink($this->readyFile);
        }
    }

    protected function generateRandomIp(): string
    {
        return '127.0.0.1';
    }

    protected function generateRandomPort(): int
    {
        return random_int(49152, 65535);
    }

    protected function tryBind(string $ip, int $port): bool
    {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            return false;
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        $result = @socket_bind($socket, $ip, $port);
        socket_close($socket);

        return $result !== false;
    }

    protected function startServer(string $ip, int $port): bool
    {
        $command = sprintf(
            '%s -S %s:%d -t %s %s 2>&1',
            PHP_BINARY,
            $ip,
            $port,
            escapeshellarg($this->serverDir),
            escapeshellarg($this->serverScript),
        );

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = getenv();
        if (!is_array($env)) {
            $env = [];
        }
        $env['SWITON_HTTP_TEST_READY'] = $this->readyFile;

        $process = @proc_open($command, $descriptorspec, $pipes, $this->serverDir, $env, [
            'suppress_errors' => true,
        ]);

        if (!is_resource($process)) {
            return false;
        }

        $this->process = $process;

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        return true;
    }

    /**
     * Wait until the ready file exists or TCP accepts connections.
     */
    protected function waitForServer(int $maxWaitMs = 500): bool
    {
        $deadline = hrtime(true) + $maxWaitMs * 1_000_000;

        while (hrtime(true) < $deadline) {
            if ($this->readyFile !== '' && is_file($this->readyFile)) {
                return true;
            }

            if ($this->isTcpReady()) {
                $this->markReady();

                return true;
            }

            usleep(2000);
        }

        return false;
    }

    protected function isTcpReady(): bool
    {
        if ($this->ip === null || $this->port === null) {
            return false;
        }

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($this->ip, $this->port, $errno, $errstr, 0.05);
        if ($fp === false) {
            return false;
        }

        fclose($fp);

        return true;
    }

    protected function isProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output, $returnCode);

            return $returnCode === 0 && $output !== [];
        }

        exec("ps -p $pid > /dev/null 2>&1", $output, $returnCode);

        return $returnCode === 0;
    }

    protected function killProcessOnPort(string $ip, int $port): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            @exec("netstat -ano | findstr :$port 2>NUL", $output);
            foreach ($output as $line) {
                if (str_contains($line, (string) $port)) {
                    $parts = preg_split('/\s+/', trim($line));
                    if ($parts !== false && $parts !== [] && is_numeric($parts[count($parts) - 1])) {
                        $pid = (int) $parts[count($parts) - 1];
                        @exec("taskkill /F /PID $pid 2>NUL");
                    }
                }
            }

            return;
        }

        @exec("lsof -ti $ip:$port 2>/dev/null | xargs kill -9 2>/dev/null", $output, $returnCode);
        if ($returnCode !== 0) {
            @exec("fuser -k $port/tcp 2>/dev/null");
        }
    }

    protected function disableProxyEnvironment(): void
    {
        $proxyVars = ['HTTP_PROXY', 'HTTPS_PROXY', 'http_proxy', 'https_proxy', 'ALL_PROXY', 'all_proxy', 'NO_PROXY', 'no_proxy'];
        foreach ($proxyVars as $var) {
            if (isset($_ENV[$var])) {
                $this->originalProxyEnv[$var] = $_ENV[$var];
                unset($_ENV[$var]);
            }
            if (getenv($var) !== false) {
                if (!isset($this->originalProxyEnv[$var])) {
                    $this->originalProxyEnv[$var] = getenv($var);
                }
                putenv($var);
            }
        }

        $_ENV['NO_PROXY'] = '*';
        $_ENV['no_proxy'] = '*';
        putenv('NO_PROXY=*');
        putenv('no_proxy=*');
    }

    public function __destruct()
    {
        $this->stop();
    }
}
