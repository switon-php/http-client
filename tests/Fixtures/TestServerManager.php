<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Fixtures;

use RuntimeException;

use function curl_exec;
use function curl_init;
use function curl_setopt;
use function exec;
use function is_resource;
use function proc_close;
use function proc_open;
use function proc_terminate;
use function socket_bind;
use function socket_close;
use function socket_create;
use function sprintf;
use function usleep;

/**
 * Test server manager for HTTP Client integration tests.
 *
 * Manages the lifecycle of a local test HTTP server:
 * - Starts server with random IP (127.0.0.1-127.0.0.255) and random port (10000-65535)
 * - Detects server readiness
 * - Provides server URL
 * - Stops server process
 */
class TestServerManager
{
    protected ?string $ip = null;
    protected ?int $port = null;
    protected $process = null;
    protected string $serverUrl = '';
    protected string $serverScript = '';
    protected string $serverDir = '';
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
            return; // Already running
        }
        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function(function (): void {
                $this->stop();
            });
        }

        // Disable proxy environment variables for test server
        // Save original values to restore later if needed
        $this->disableProxyEnvironment();

        // Use fixed IP but random available port to avoid conflicts
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

        if (!$this->startServer($ip, $port)) {
            throw new RuntimeException('Failed to start PHP built-in server process.');
        }

        // Wait for server to be ready
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
            // Get process PID from proc_get_status
            $status = proc_get_status($this->process);
            if ($status !== false && isset($status['pid']) && $status['pid'] > 0) {
                // Terminate the process
                proc_terminate($this->process, 15); // SIGTERM
                // Wait a bit for graceful shutdown
                usleep(100000); // 0.1 second
                // Force kill if still running
                if ($this->isProcessRunning($status['pid'])) {
                    proc_terminate($this->process, 9); // SIGKILL
                }
            }
            proc_close($this->process);
            $this->process = null;
        }

        // Also try to kill any remaining processes on this port
        if ($this->port !== null) {
            $this->killProcessOnPort($this->ip ?? '127.0.0.1', $this->port);
        }

        $this->ip = null;
        $this->port = null;
        $this->serverUrl = '';
    }

    /**
     * Get the server URL.
     *
     * @return string Server URL (e.g., "http://127.0.0.123:18000")
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
     *
     * @return bool True if server is running
     */
    public function isRunning(): bool
    {
        if ($this->serverUrl === '' || $this->port === null) {
            return false;
        }

        // Check if process is still running
        if ($this->process !== null) {
            $status = proc_get_status($this->process);
            if ($status !== false && !$status['running']) {
                return false;
            }
        }

        // Try to connect to the server
        return $this->checkServerReady($this->serverUrl);
    }

    /**
     * Generate IP address for test server.
     *
     * Fixed to 127.0.0.1 for simplicity and compatibility.
     * Most systems only support 127.0.0.1 for local testing.
     *
     * @return string IP address (always 127.0.0.1)
     */
    protected function generateRandomIp(): string
    {
        return '127.0.0.1';
    }

    /**
     * Generate a port for test server.
     *
     * Random port in the dynamic/private range to minimize conflicts.
     *
     * @return int Port number (always 51234)
     */
    protected function generateRandomPort(): int
    {
        return random_int(49152, 65535);
    }

    /**
     * Try to bind to the given IP and port to check if it's available.
     *
     * @param string $ip IP address
     * @param int $port Port number
     *
     * @return bool True if binding is successful (port is available)
     */
    protected function tryBind(string $ip, int $port): bool
    {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            return false;
        }

        // Set socket option to allow reuse
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        $result = @socket_bind($socket, $ip, $port);
        socket_close($socket);

        return $result !== false;
    }

    /**
     * Start the PHP built-in server.
     *
     * @param string $ip IP address
     * @param int $port Port number
     *
     * @return bool True if server process started successfully
     */
    protected function startServer(string $ip, int $port): bool
    {
        $command = sprintf(
            '%s -S %s:%d -t %s %s 2>&1',
            PHP_BINARY,
            $ip,
            $port,
            escapeshellarg($this->serverDir),
            escapeshellarg($this->serverScript)
        );

        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = @proc_open($command, $descriptorspec, $pipes, null, null, [
            'suppress_errors' => true,
        ]);

        if (!is_resource($process)) {
            return false;
        }

        // Store process resource
        $this->process = $process;

        // Close pipes (we don't need them)
        if (isset($pipes[0])) {
            fclose($pipes[0]);
        }
        if (isset($pipes[1])) {
            fclose($pipes[1]);
        }
        if (isset($pipes[2])) {
            fclose($pipes[2]);
        }

        return true;
    }

    /**
     * Wait for server to be ready.
     *
     * Polls the server until it responds or timeout is reached.
     *
     * @param int $maxWaitMs Maximum wait time in milliseconds (default: 1000ms)
     *
     * @return bool True if server is ready
     */
    protected function waitForServer(int $maxWaitMs = 1000): bool
    {
        $pollInterval = 15000; // 15ms in microseconds
        $maxAttempts = max(1, (int)($maxWaitMs / 15));

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($this->checkServerReady($this->serverUrl)) {
                return true;
            }
            usleep($pollInterval);
        }

        return false;
    }

    /**
     * Check if server is ready by sending a test request.
     *
     * @param string $url Server URL
     *
     * @return bool True if server responds
     */
    protected function checkServerReady(string $url): bool
    {
        // First check if TCP port is open
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'], $parts['port'])) {
            return false;
        }
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($parts['host'], (int)$parts['port'], $errno, $errstr, 0.1);
        if ($fp === false) {
            return false;
        }
        fclose($fp);

        // Then try a real HTTP request to ensure server is actually processing requests
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '/get');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 200); // 200ms timeout for health check
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 100);
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        // IMPORTANT: Disable proxy completely for local test server
        curl_setopt($ch, CURLOPT_PROXY, ''); // Empty string disables proxy
        curl_setopt($ch, CURLOPT_NOPROXY, '*'); // Bypass proxy for all hosts

        $result = @curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // Server is ready if we get any HTTP response (even 404 is OK, means server is running)
        return $result !== false || $httpCode > 0;
    }

    /**
     * Check if a process is still running.
     *
     * @param int $pid Process ID
     *
     * @return bool True if process is running
     */
    protected function isProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: use tasklist
            exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output, $returnCode);
            return $returnCode === 0 && !empty($output);
        } else {
            // Unix-like: use ps
            exec("ps -p $pid > /dev/null 2>&1", $output, $returnCode);
            return $returnCode === 0;
        }
    }

    /**
     * Kill any process listening on the given IP and port.
     *
     * @param string $ip IP address
     * @param int $port Port number
     */
    protected function killProcessOnPort(string $ip, int $port): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: use netstat and taskkill
            @exec("netstat -ano | findstr :$port 2>NUL", $output);
            foreach ($output as $line) {
                if (str_contains($line, (string)$port)) {
                    $parts = preg_split('/\s+/', trim($line));
                    if (!empty($parts) && is_numeric($parts[count($parts) - 1])) {
                        $pid = (int)$parts[count($parts) - 1];
                        @exec("taskkill /F /PID $pid 2>NUL");
                    }
                }
            }
        } else {
            // Unix-like: use lsof or fuser
            @exec("lsof -ti $ip:$port 2>/dev/null | xargs kill -9 2>/dev/null", $output, $returnCode);
            // Fallback to fuser if lsof is not available
            if ($returnCode !== 0) {
                @exec("fuser -k $port/tcp 2>/dev/null", $output, $returnCode);
            }
        }
    }

    /**
     * Disable proxy environment variables for test execution.
     */
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
                putenv($var); // Unset the environment variable
            }
        }
        // Set NO_PROXY to bypass proxy for all hosts
        $_ENV['NO_PROXY'] = '*';
        $_ENV['no_proxy'] = '*';
        putenv('NO_PROXY=*');
        putenv('no_proxy=*');
    }

    /**
     * Cleanup on destruction.
     */
    public function __destruct()
    {
        $this->stop();
    }
}
