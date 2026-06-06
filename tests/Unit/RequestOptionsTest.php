<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use Switon\HttpClient\RequestOptions;
use Switon\HttpClient\Tests\TestCase;

class RequestOptionsTest extends TestCase
{
    public function testOfReturnsOptionsArray(): void
    {
        $options = RequestOptions::of([
            RequestOptions::TIMEOUT => 5,
            RequestOptions::VERIFY_PEER => false,
        ]);

        $this->assertSame([
            'timeout' => 5,
            'verify_peer' => false,
        ], $options->all());
    }

    public function testOfNormalizesNumericTimeout(): void
    {
        $options = RequestOptions::of(3);

        $this->assertSame(['timeout' => 3], $options->all());
        $this->assertSame(3, $options->get(RequestOptions::TIMEOUT));
    }

    public function testOfNormalizesFloatTimeout(): void
    {
        $options = RequestOptions::of(0.25);

        $this->assertSame(['timeout' => 0.25], $options->all());
        $this->assertSame(0.25, $options->get(RequestOptions::TIMEOUT));
    }
}
