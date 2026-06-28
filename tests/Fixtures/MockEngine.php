<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Fixtures;

use Switon\HttpClient\EngineInterface;
use Switon\HttpClient\HttpClientInterface;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;
use Throwable;

class MockEngine implements EngineInterface
{
    protected HttpClientInterface $owner;

    public array $requests = [];
    public ?Response $response = null;
    public ?Throwable $exception = null;

    public function __construct(HttpClientInterface $owner = new StubHttpClient())
    {
        $this->owner = $owner;
    }

    public function getOwner(): HttpClientInterface
    {
        return $this->owner;
    }

    public function request(Request $request, ?string $body): Response
    {
        $this->requests[] = ['request' => $request, 'body' => $body];

        if ($this->exception !== null) {
            throw $this->exception;
        }

        if ($this->response === null) {
            $this->response = new Response($request, [
                'HTTP/1.1 200 OK',
                'Content-Type: application/json',
                'Content-Length: 2',
            ], '{}');
        }

        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function setException(Throwable $exception): void
    {
        $this->exception = $exception;
    }

    public function clear(): void
    {
        $this->requests = [];
        $this->response = null;
        $this->exception = null;
    }
}
