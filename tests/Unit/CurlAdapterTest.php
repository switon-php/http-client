<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\HttpClient\Curl;

use function extension_loaded;
use function is_string;

use const PHP_OS_FAMILY;

class CurlAdapterTest extends TestCase
{
    public function testAdapterDelegatesToNativeCurlFunctions(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('ext-curl is not loaded');
        }

        $curl = new Curl();
        $handle = $curl->init();
        $this->assertNotFalse($handle);

        $this->assertTrue(
            $curl->setopt($handle, CURLOPT_URL, 'https://example.com'),
            $curl->error($handle),
        );
        $this->assertSame(0, $curl->errno($handle));
        $this->assertSame('', $curl->error($handle));
        $effective = $curl->getinfo($handle, CURLINFO_EFFECTIVE_URL);
        if (PHP_OS_FAMILY === 'Windows') {
            // Some Windows libcurl builds leave this unset, or return another string, before a transfer.
            $this->assertTrue($effective === false || is_string($effective));
        } else {
            // libcurl may append a slash before any transfer.
            $this->assertIsString($effective);
            $this->assertContains($effective, ['https://example.com', 'https://example.com/']);
        }

        $curl->reset($handle);
        $curl->close($handle);
    }
}
