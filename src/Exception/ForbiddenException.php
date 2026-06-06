<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for 403 Forbidden responses.
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 */
class ForbiddenException extends ClientErrorException
{
}
