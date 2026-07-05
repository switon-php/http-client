<?php

declare(strict_types=1);

namespace Switon\HttpClient\Event;

use JsonSerializable;
use Switon\Core\SenderAwareInterface;
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
class HttpClientRequesting implements JsonSerializable, SenderAwareInterface
{
    /**
     * @param string|array<int|string, mixed> $url Request URL, or URL plus options
     */
    public function __construct(
        public HttpClientInterface $sender,
        public string              $method,
        public string|array        $url,
        public Request             $request
    ) {

    }

    public function getSender(): HttpClientInterface
    {
        return $this->sender;
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
