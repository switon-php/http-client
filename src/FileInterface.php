<?php

declare(strict_types=1);

namespace Switon\HttpClient;

/**
 * Defines uploadable file payloads used in multipart/form-data requests.
 *
 * Use this for request body entries that should be serialized as file parts.
 *
 * @see \Switon\HttpClient\LocalFile
 * @see \Switon\HttpClient\MemoryFile
 * @see \Switon\HttpClient\Request
 */
interface FileInterface
{
    /**
     * Return the source filename when available.
     */
    public function getFileName(): ?string;

    /**
     * Return the MIME type for the multipart part.
     */
    public function getMimeType(): string;

    /**
     * Return the multipart filename sent to the remote server.
     */
    public function getPostName(): string;

    /**
     * Return the raw file content for multipart serialization.
     */
    public function getContent(): string;
}
