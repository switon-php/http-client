<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for 429 Too Many Requests responses.
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 */
class TooManyRequestsException extends ClientErrorException
{
}
