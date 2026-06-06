<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception thrown when HTTP client request times out.
 *
 * @see \Switon\HttpClient\Engine Typical raise site
 */
class TimeoutException extends RequestException
{
}
