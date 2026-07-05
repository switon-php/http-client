<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

/**
 * Exception thrown when a request is aborted via {@see RequestOptions::SHOULD_ABORT}.
 *
 * @see \Switon\HttpClient\Engine Typical raise site
 */
class AbortedException extends RequestException
{
}
