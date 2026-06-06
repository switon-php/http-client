<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception for file-related errors in HttpClient.
 *
 * This exception is used for file operations in LocalFile,
 * such as file not found or file not readable.
 *
 * @see \Switon\HttpClient\LocalFile Typical raise site
 */
class FileException extends ConfigurationException
{
}
