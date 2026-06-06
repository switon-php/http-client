<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Base exception for non-2xx HTTP status responses.
 *
 * Guidance: Catch `HttpStatusException` when you care about the HTTP code after a response was received; JSON
 * content-type or JSON decoding failures belong to `ResponseParseException`, not here.
 *
 * @see \Switon\HttpClient\Exception\ResponseException
 * @see \Switon\HttpClient\HttpClient Typical raise site
 * @see \Switon\HttpClient\Exception\ResponseParseException
 */
abstract class HttpStatusException extends ResponseException
{
    /**
     * Returns the HTTP status code stored in exception context.
     */
    public function getStatusCode(): int
    {
        $context = $this->getContext();
        return $context['status_code'] ?? 0;
    }
}
