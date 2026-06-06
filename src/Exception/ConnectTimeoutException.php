<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception thrown when connection establishment times out.
 *
 * @see \Switon\HttpClient\Engine Typical raise site
 * @see \Switon\HttpClient\Exception\TimeoutException
 */
class ConnectTimeoutException extends TimeoutException
{
}
