<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for 401 Unauthorized responses.
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 */
class UnauthorizedException extends ClientErrorException
{
}
