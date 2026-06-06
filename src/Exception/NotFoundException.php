<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for 404 Not Found responses.
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 */
class NotFoundException extends ClientErrorException
{
}
