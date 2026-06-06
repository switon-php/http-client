<?php

declare(strict_types=1);

namespace Switon\HttpClient\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\HttpClient\HttpClientInterface;
use Switon\HttpClient\Request;

/**
 * Event emitted immediately before engine request execution.
 *
 * Log category: <code>switon.http.client.requesting</code>
 *
 * @see \Switon\HttpClient\HttpClient
 * @see \Switon\HttpClient\Event\HttpClientStart
 * @see \Switon\HttpClient\Event\HttpClientRequested
 */
#[EventLevel(Severity::DEBUG)]
class HttpClientRequesting implements JsonSerializable
{
    /**
     * @param string|array<int|string, mixed> $url
     */
    public function __construct(
        public HttpClientInterface $client,
        public string              $method,
        public string|array        $url,
        public Request             $request
    ) {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['url' => $this->url,
            'method' => $this->method,
            'body' => $this->request->getBody(),
            'headers' => $this->request->getHeaders()];
    }
}
