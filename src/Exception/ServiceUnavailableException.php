<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for 503 Service Unavailable responses.
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 */
class ServiceUnavailableException extends ServerErrorException
{
}
