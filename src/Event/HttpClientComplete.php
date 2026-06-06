<?php

declare(strict_types=1);

namespace Switon\HttpClient\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\HttpClient\HttpClientInterface;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;

/**
 * Event emitted after request lifecycle completion (success or failure).
 *
 * Log category: <code>switon.http.client.complete</code>
 *
 * @see \Switon\HttpClient\HttpClient
 * @see \Switon\HttpClient\Event\HttpClientStart
 * @see \Switon\HttpClient\Event\HttpClientSuccess
 * @see \Switon\HttpClient\Event\HttpClientError
 */
#[EventLevel(Severity::DEBUG)]
class HttpClientComplete
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
}
