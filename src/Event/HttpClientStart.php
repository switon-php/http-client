<?php

declare(strict_types=1);

namespace Switon\HttpClient\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\HttpClient\HttpClientInterface;
use Switon\HttpClient\Request;

/**
 * Event emitted before HTTP client request execution starts.
 *
 * Log category: <code>switon.http.client.start</code>
 *
 * @see \Switon\HttpClient\HttpClient
 * @see \Switon\HttpClient\Event\HttpClientRequesting
 * @see \Switon\HttpClient\Event\HttpClientComplete
 */
#[EventLevel(Severity::DEBUG)]
class HttpClientStart
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
}
