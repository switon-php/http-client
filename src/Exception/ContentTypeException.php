<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for invalid response content type.
 *
 * This exception indicates that a response was received, but the content type
 * is not as expected (e.g., not application/json when JSON was expected).
 *
 * @see \Switon\HttpClient\HttpClient::requestJson() Typical consumer
 * @see \Switon\HttpClient\Exception\BadResponseException Typical raise path
 */
class ContentTypeException extends ResponseParseException
{
}
