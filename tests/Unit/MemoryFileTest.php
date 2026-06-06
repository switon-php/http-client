<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\HttpClient\MemoryFile;
use Switon\HttpClient\Tests\TestCase;

/**
 * Test cases for MemoryFile class.
 *
 * Tests memory file creation, content retrieval, and serialization.
 */
class MemoryFileTest extends TestCase
{
    /**
     * Test basic memory file construction.
     */
    public function testConstruct(): void
    {
        // Arrange & Act
        $file = new MemoryFile('file content', 'text/plain', 'test.txt');

        // Assert
        $this->assertSame('file content', $file->getContent());
        $this->assertSame('text/plain', $file->getMimeType());
        $this->assertSame('test.txt', $file->getPostName());
    }

    /**
     * Test getFileName() returns null.
     */
    public function testGetFileNameReturnsNull(): void
    {
        // Arrange
        $file = new MemoryFile('file content', 'text/plain', 'test.txt');

        // Act
        $result = $file->getFileName();

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test getMimeType() returns provided mime type.
     */
    public function testGetMimeType(): void
    {
        // Arrange
        $file = new MemoryFile('file content', 'application/json', 'test.json');

        // Act
        $result = $file->getMimeType();

        // Assert
        $this->assertSame('application/json', $result);
    }

    /**
     * Test getPostName() returns provided post name.
     */
    public function testGetPostName(): void
    {
        // Arrange
        $file = new MemoryFile('file content', 'text/plain', 'custom-name.txt');

        // Act
        $result = $file->getPostName();

        // Assert
        $this->assertSame('custom-name.txt', $result);
    }

    /**
     * Test getContent() returns file content.
     */
    public function testGetContent(): void
    {
        // Arrange
        $content = 'This is file content';
        $file = new MemoryFile($content, 'text/plain', 'test.txt');

        // Act
        $result = $file->getContent();

        // Assert
        $this->assertSame($content, $result);
    }

    /**
     * Test jsonSerialize() returns mime type and post name.
     */
    public function testJsonSerialize(): void
    {
        // Arrange
        $file = new MemoryFile('file content', 'text/plain', 'test.txt');

        // Act
        $result = $file->jsonSerialize();

        // Assert
        $this->assertIsArray($result);
        $this->assertSame('text/plain', $result['mimeType']);
        $this->assertSame('test.txt', $result['postName']);
    }
}
