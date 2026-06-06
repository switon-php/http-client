<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

use Switon\HttpClient\Response;

/**
 * Exception for invalid JSON response content.
 *
 * @see \Switon\HttpClient\Response::getJsonBody() Typical raise site
 */
class InvalidJsonException extends ResponseParseException
{
}
