<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for 502 Bad Gateway responses.
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 */
class BadGatewayException extends ServerErrorException
{
}
