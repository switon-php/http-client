<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for bad HTTP responses (e.g., invalid content, unexpected format).
 *
 * This exception indicates that a response was received, but there was an error
 * or the response is invalid (e.g., unexpected content type, malformed response).
 *
 * Note: For HTTP status code errors, use the specific status code exception classes
 * (e.g., BadRequestException, UnauthorizedException, etc.).
 *
 * @see \Switon\HttpClient\HttpClient Typical raise site
 * @see \Switon\HttpClient\Exception\ContentTypeException Related failure path
 * @see \Switon\HttpClient\Exception\InvalidJsonException Related failure path
 */
class BadResponseException extends ResponseParseException
{
}
