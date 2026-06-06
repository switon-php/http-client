<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception thrown when SSL/TLS handshake or verification fails.
 *
 * @see \Switon\HttpClient\Engine Typical raise site
 */
class SslException extends ConnectionException
{
}
