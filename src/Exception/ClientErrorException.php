<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Base exception for 4xx client error responses.
 *
 * Can be used directly for unhandled 4xx status codes.
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 */
class ClientErrorException extends HttpStatusException
{
}
