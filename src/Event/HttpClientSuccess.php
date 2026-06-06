<?php

declare(strict_types=1);

namespace Switon\HttpClient\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\HttpClient\HttpClientInterface;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;

/**
 * Event emitted when request execution completes successfully.
 *
 * Log category: <code>switon.http.client.success</code>
 *
 * @see \Switon\HttpClient\HttpClient
 * @see \Switon\HttpClient\Event\HttpClientRequested Typical upstream
 * @see \Switon\HttpClient\Event\HttpClientError
 * @see \Switon\HttpClient\Event\HttpClientComplete
 */
#[EventLevel(Severity::DEBUG)]
class HttpClientSuccess
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
