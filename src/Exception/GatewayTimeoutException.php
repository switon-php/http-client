<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for 504 Gateway Timeout responses.
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 */
class GatewayTimeoutException extends ServerErrorException
{
}
