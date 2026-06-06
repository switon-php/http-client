<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for 3xx redirection responses.
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 */
class RedirectionException extends HttpStatusException
{
}
