<?php

declare(strict_types=1);

namespace Switon\HttpClient\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\HttpClient\HttpClientInterface;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;

/**
 * Event emitted after a response is received from transport.
 *
 * Log category: <code>switon.http.client.requested</code>
 *
 * @see \Switon\HttpClient\HttpClient
 * @see \Switon\HttpClient\Event\HttpClientRequesting
 * @see \Switon\HttpClient\Event\HttpClientSuccess
 * @see \Switon\HttpClient\Event\HttpClientError
 */
#[EventLevel(Severity::DEBUG)]
class HttpClientRequested implements JsonSerializable
{
    /**
     * @param string|array<int|string, mixed> $url
     */
    public function __construct(
        public HttpClientInterface $client,
        public string              $method,
        public string|array        $url,
        public Request             $request,
        public ?Response           $response,
        public float               $elapsed,
    ) {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'url' => $this->url,
            'method' => $this->method,
            'response' => $this->response?->jsonSerialize(),
        ];
    }
}
