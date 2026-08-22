<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Fixtures;

use Switon\HttpClient\HttpClientInterface;
use Switon\HttpClient\RequestOptions;
use Switon\HttpClient\Response;
use LogicException;

/** @internal Minimal {@see HttpClientInterface} stand-in for engine owner typing in tests. */
class StubHttpClient implements HttpClientInterface
{
    public function request(
        string $method,
        string|array $url,
        null|string|array $body = null,
        array $headers = [],
        ?RequestOptions $options = null
    ): Response {
        throw new LogicException('StubHttpClient is not callable in tests.');
    }

    public function head(string|array $url, array $headers = [], ?RequestOptions $options = null): Response
    {
        throw new LogicException('StubHttpClient is not callable in tests.');
    }

    public function get(string|array $url, array $headers = [], ?RequestOptions $options = null): Response
    {
        throw new LogicException('StubHttpClient is not callable in tests.');
    }

    public function post(string|array $url, string|array $body = [], array $headers = [], ?RequestOptions $options = null): Response
    {
        throw new LogicException('StubHttpClient is not callable in tests.');
    }

    public function delete(string|array $url, array $headers = [], ?RequestOptions $options = null): Response
    {
        throw new LogicException('StubHttpClient is not callable in tests.');
    }

    public function put(string|array $url, string|array $body = [], array $headers = [], ?RequestOptions $options = null): Response
    {
        throw new LogicException('StubHttpClient is not callable in tests.');
    }

    public function patch(string|array $url, string|array $body = [], array $headers = [], ?RequestOptions $options = null): Response
    {
        throw new LogicException('StubHttpClient is not callable in tests.');
    }

    public function fetch(string $url, string $method = 'GET', array $headers = [], ?string $body = null, array $options = []): array
    {
        throw new LogicException('StubHttpClient is not callable in tests.');
    }
}
