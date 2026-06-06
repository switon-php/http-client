<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

use Switon\HttpClient\Exception;
use Switon\HttpClient\Request;

/**
 * Base exception for transport errors while sending a request.
 *
 * Guidance: Catch `RequestException` when the request did not complete at the HTTP response level; use
 * `ResponseException` for failures detected after a response arrives.
 *
 * Road-signs:
 * - DNS / SSL / connection failures
 * - request timeout and connect timeout
 * - request may have no response object
 *
 * @see \Switon\HttpClient\Exception
 * @see \Switon\HttpClient\Request
 * @see \Switon\HttpClient\Engine Typical raise site
 * @see \Switon\HttpClient\Exception\ConnectionException
 * @see \Switon\HttpClient\Exception\TimeoutException
 * @see \Switon\HttpClient\Exception\ConnectTimeoutException
 * @see \Switon\HttpClient\Exception\ResponseException
 */
abstract class RequestException extends Exception
{
    /**
     * Returns the request stored in exception context, if present.
     */
    protected function getRequest(): ?Request
    {
        $context = $this->getContext();
        return $context['request'] ?? null;
    }
}
