<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for response decompression failures.
 *
 * This exception indicates that a response was received with compressed content,
 * but decompression failed (e.g., gzip/deflate decode failed).
 *
 * @see \Switon\HttpClient\Response Typical raise site
 * @see \Switon\HttpClient\Exception\ResponseParseException Related failure base
 */
class DecompressionException extends ResponseParseException
{
}
