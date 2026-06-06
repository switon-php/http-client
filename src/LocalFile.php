<?php

declare(strict_types=1);

namespace Switon\HttpClient;

use JsonSerializable;
use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\HttpClient\Exception\FileException;
use Throwable;

use function basename;
use function mime_content_type;

/**
 * Upload payload backed by a local filesystem path.
 *
 * Use when file content should be streamed from disk at send time.
 * Path aliases are resolved before existence/readability validation.
 *
 * @see \Switon\HttpClient\FileInterface
 * @see \Switon\HttpClient\MemoryFile
 * @see \Switon\HttpClient\Exception\FileException Related failure path
 * @see \Switon\Core\PathAliasInterface @public resolve boundary
 */
class LocalFile implements FileInterface, JsonSerializable
{
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected PathAliasInterface $pathAlias;

    protected string $fileName;
    protected ?string $mimeType;
    protected ?string $postName;

    public function __construct(string $fileName, ?string $mimeType = null, ?string $postName = null)
    {
        $fileName = $this->pathAlias->resolve($fileName);

        if (!$this->filesystem->exists($fileName)) {
            FileException::raise('File "{fileName}" not found', ['fileName' => $fileName]);
        }

        try {
            $this->filesystem->read($fileName);
        } catch (Throwable) {
            FileException::raise('File "{fileName}" not readable.', ['fileName' => $fileName]);
        }

        $this->fileName = $fileName;
        $this->mimeType = $mimeType;
        $this->postName = $postName;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getMimeType(): string
    {
        $mimeType = $this->mimeType ?? mime_content_type($this->fileName);

        return is_string($mimeType) && $mimeType !== '' ? $mimeType : 'application/octet-stream';
    }

    public function getPostName(): string
    {
        return $this->postName ?? basename($this->fileName);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['fileName' => $this->fileName];
    }

    public function getContent(): string
    {
        try {
            return $this->filesystem->read($this->fileName);
        } catch (Throwable) {
            FileException::raise('File "{fileName}" not readable.', ['fileName' => $this->fileName]);
        }
    }
}
