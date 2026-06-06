<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

use Switon\HttpClient\Exception;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;

/**
 * Base exception for failures detected after a response is received.
 *
 * Guidance: Catch `ResponseException` when the server answered but the result still failed; split into
 * `HttpStatusException` for non-2xx statuses and `ResponseParseException` for format/content problems.
 *
 * Road-signs:
 * - response exists in exception context
 * - non-2xx status -> HttpStatusException
 * - parse/content failure -> ResponseParseException
 *
 * @see \Switon\HttpClient\Exception
 * @see \Switon\HttpClient\Request
 * @see \Switon\HttpClient\Response
 * @see \Switon\HttpClient\HttpClient Typical raise site
 * @see \Switon\HttpClient\Exception\HttpStatusException
 * @see \Switon\HttpClient\Exception\ResponseParseException
 * @see \Switon\HttpClient\Exception\RequestException
 */
abstract class ResponseException extends Exception
{
    /**
     * Returns the request stored in exception context, if present.
     */
    protected function getRequest(): ?Request
    {
        $context = $this->getContext();
        return $context['request'] ?? null;
    }

    /**
     * Returns the response stored in exception context, if present.
     */
    protected function getResponse(): ?Response
    {
        $context = $this->getContext();
        return $context['response'] ?? null;
    }
}
