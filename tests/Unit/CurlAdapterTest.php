<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\HttpClient\Curl;

use function extension_loaded;

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

        $this->assertTrue($curl->setopt($handle, CURLOPT_URL, 'https://example.com'));
        $this->assertSame(0, $curl->errno($handle));
        $this->assertSame('', $curl->error($handle));
        $this->assertSame('https://example.com', $curl->getinfo($handle, CURLINFO_EFFECTIVE_URL));

        $curl->reset($handle);
        $curl->close($handle);
    }
}
