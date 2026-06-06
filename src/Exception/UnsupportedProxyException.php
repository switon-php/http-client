<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception thrown when HTTP client proxy configuration is not supported.
 *
 * @see \Switon\HttpClient\Engine Typical raise site
 */
class UnsupportedProxyException extends ConfigurationException
{
}
