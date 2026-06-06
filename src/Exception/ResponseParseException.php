<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Base exception for response parsing errors.
 *
 * Guidance: Catch `ResponseParseException` when the response status may be successful but body decoding or
 * content validation failed after the response arrived.
 *
 * Road-signs:
 * - gzip/deflate decoding
 * - JSON validation
 * - unexpected response content type
 *
 * @see \Switon\HttpClient\Exception\ResponseException
 * @see \Switon\HttpClient\Response Typical raise site
 * @see \Switon\HttpClient\Exception\DecompressionException
 * @see \Switon\HttpClient\Exception\InvalidJsonException
 * @see \Switon\HttpClient\Exception\BadResponseException Typical raise path
 * @see \Switon\HttpClient\Exception\HttpStatusException
 */
abstract class ResponseParseException extends ResponseException
{
}
