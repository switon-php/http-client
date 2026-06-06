<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\HttpClient\Exception\FileException;
use Switon\HttpClient\LocalFile;
use Switon\HttpClient\Tests\TestCase;
use RuntimeException;

/**
 * Test cases for LocalFile class.
 *
 * Tests local file creation, content retrieval, and error handling.
 */
class LocalFileTest extends TestCase
{
    protected string $testFile;
    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary test directory
        $this->testDir = sys_get_temp_dir() . '/http-client-test-' . uniqid('', true);
        mkdir($this->testDir, 0777, true);

        // Create test file
        $this->testFile = $this->testDir . '/test.txt';
        file_put_contents($this->testFile, 'test content');
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        if (is_dir($this->testDir)) {
            rmdir($this->testDir);
        }

        parent::tearDown();
    }

    /**
     * Create LocalFile with injected PathAlias.
     *
     * Container's make() method injects #[Autowired] properties before calling constructor,
     * so LocalFile can use pathAlias in its constructor.
     */
    protected function createLocalFile(string $fileName, ?string $mimeType = null, ?string $postName = null): LocalFile
    {
        return $this->make(LocalFile::class, [
            'fileName' => $fileName,
            'mimeType' => $mimeType,
            'postName' => $postName,
        ]);
    }

    /**
     * Test basic local file construction.
     */
    public function testConstruct(): void
    {
        // Arrange & Act
        $file = $this->createLocalFile($this->testFile, 'text/plain', 'test.txt');

        // Assert
        $this->assertSame($this->testFile, $file->getFileName());
        $this->assertSame('text/plain', $file->getMimeType());
        $this->assertSame('test.txt', $file->getPostName());
    }

    /**
     * Test local file construction without mime type (auto-detect).
     */
    public function testConstructWithoutMimeType(): void
    {
        // Arrange & Act
        // postName must be string, so pass empty string
        $file = $this->createLocalFile($this->testFile, null, '');

        // Assert
        $this->assertIsString($file->getMimeType());
        $this->assertNotEmpty($file->getMimeType());
    }

    /**
     * Test local file construction without post name falls back to basename.
     */
    public function testConstructWithoutPostNameFallsBackToBasename(): void
    {
        $file = $this->createLocalFile($this->testFile, 'text/plain', null);

        $this->assertSame('test.txt', $file->getPostName());
    }

    /**
     * Test getFileName() returns file path.
     */
    public function testGetFileName(): void
    {
        // Arrange
        $file = $this->createLocalFile($this->testFile, 'text/plain', 'test.txt');

        // Act
        $result = $file->getFileName();

        // Assert
        $this->assertSame($this->testFile, $result);
    }

    /**
     * Test getMimeType() returns provided mime type.
     */
    public function testGetMimeType(): void
    {
        // Arrange
        $file = $this->createLocalFile($this->testFile, 'application/json', 'test.json');

        // Act
        $result = $file->getMimeType();

        // Assert
        $this->assertSame('application/json', $result);
    }

    /**
     * Test getMimeType() auto-detects when not provided.
     */
    public function testGetMimeTypeAutoDetect(): void
    {
        // Arrange
        // postName must be string (not nullable), so pass a valid string
        // mimeType can be null, which will trigger auto-detection
        $file = $this->createLocalFile($this->testFile, null, 'test.txt');

        // Act
        $result = $file->getMimeType();

        // Assert
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test getPostName() returns provided post name.
     */
    public function testGetPostName(): void
    {
        // Arrange
        $file = $this->createLocalFile($this->testFile, 'text/plain', 'custom-name.txt');

        // Act
        $result = $file->getPostName();

        // Assert
        $this->assertSame('custom-name.txt', $result);
    }

    /**
     * Test getPostName() returns provided post name.
     */
    public function testGetPostNameReturnsProvidedValue(): void
    {
        // Arrange
        $file = $this->createLocalFile($this->testFile, 'text/plain', 'custom-name.txt');

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
        $file = $this->createLocalFile($this->testFile, 'text/plain', 'test.txt');

        // Act
        $result = $file->getContent();

        // Assert
        $this->assertSame('test content', $result);
    }

    public function testGetContentThrowsExceptionWhenReadFails(): void
    {
        $filesystem = $this->createStub(FilesystemInterface::class);
        $filesystem->method('exists')->willReturn(true);
        $filesystem->method('read')->willReturnCallback(static function () {
            static $callCount = 0;

            if ($callCount++ === 0) {
                return 'test content';
            }

            throw new RuntimeException('read failed');
        });

        $pathAlias = $this->createStub(PathAliasInterface::class);
        $pathAlias->method('resolve')->willReturnCallback(static fn (string $path): string => $path);

        $this->container->replace(FilesystemInterface::class, $filesystem);
        $this->container->replace(PathAliasInterface::class, $pathAlias);

        $file = $this->make(LocalFile::class, ['fileName' => $this->testFile, 'mimeType' => 'text/plain', 'postName' => 'test.txt']);

        $this->expectException(FileException::class);
        $file->getContent();
    }

    /**
     * Test jsonSerialize() returns file name.
     */
    public function testJsonSerialize(): void
    {
        // Arrange
        $file = $this->createLocalFile($this->testFile, 'text/plain', 'test.txt');

        // Act
        $result = $file->jsonSerialize();

        // Assert
        $this->assertIsArray($result);
        $this->assertSame($this->testFile, $result['fileName']);
    }

    /**
     * Test construction throws FileException when file doesn't exist.
     */
    public function testConstructThrowsExceptionWhenFileNotFound(): void
    {
        // Arrange
        $nonExistentFile = $this->testDir . '/nonexistent.txt';

        // Act & Assert
        $this->expectException(FileException::class);
        $this->createLocalFile($nonExistentFile, 'text/plain', 'test.txt');
    }

    /**
     * Test construction throws FileException when file is not readable.
     */
    public function testConstructThrowsExceptionWhenFileNotReadable(): void
    {
        // Skip on Windows as chmod may not work as expected
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('File permissions test skipped on Windows');
        }

        // Arrange
        $unreadableFile = $this->testDir . '/unreadable.txt';
        file_put_contents($unreadableFile, 'content');
        chmod($unreadableFile, 0000);

        try {
            // Act & Assert
            $this->expectException(FileException::class);
            $this->createLocalFile($unreadableFile, 'text/plain', 'test.txt');
        } finally {
            // Clean up
            chmod($unreadableFile, 0644);
            if (file_exists($unreadableFile)) {
                unlink($unreadableFile);
            }
        }
    }
}
