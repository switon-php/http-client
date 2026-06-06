<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Base exception for 5xx server error responses.
 *
 * Can be used directly for unhandled 5xx status codes.
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 */
class ServerErrorException extends HttpStatusException
{
}
