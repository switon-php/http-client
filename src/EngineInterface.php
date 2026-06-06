<?php

declare(strict_types=1);

namespace Switon\HttpClient;

/**
 * Defines low-level transport execution for one prepared HTTP request.
 *
 * Use this behind `HttpClientInterface`; application code should not depend on transport engines directly.
 *
 * @see \Switon\HttpClient\Engine
 * @see \Switon\HttpClient\Request
 * @see \Switon\HttpClient\Response
 * @see \Switon\HttpClient\HttpClient Typical consumer
 */
interface EngineInterface
{
    /**
     * Execute one request and return a parsed response object.
     *
     * @param string|null $body Serialized request body (or null when not needed)
     */
    public function request(Request $request, ?string $body): Response;
}
