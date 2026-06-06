<?php

declare(strict_types=1);

namespace Switon\HttpClient\Exception;

use Switon\HttpClient\Exception;
use Switon\HttpClient\Request;

/**
 * Base exception for request configuration errors before dispatch.
 *
 * @see \Switon\HttpClient\Exception
 * @see \Switon\HttpClient\Request
 */
abstract class ConfigurationException extends Exception
{
    /**
     * Returns the request stored in exception context, if present.
     */
    protected function getRequest(): ?Request
    {
        $context = $this->getContext();
        return $context['request'] ?? null;
    }
}
