<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for response charset conversion failures.
 *
 * @see \Switon\HttpClient\Response::getUtf8Body() Typical raise site
 */
class CharsetConversionException extends ResponseParseException
{
}
