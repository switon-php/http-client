<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit\Response;

use Switon\HttpClient\Response\Cookie;
use Switon\HttpClient\Tests\TestCase;

/**
 * Test cases for Cookie class.
 *
 * Tests cookie parsing and string conversion.
 */
class CookieTest extends TestCase
{
    /**
     * Test cookie construction with simple name=value.
     */
    public function testConstructWithSimpleCookie(): void
    {
        // Arrange & Act
        $cookie = new Cookie('session=abc123');

        // Assert
        $this->assertSame('session', $cookie->name);
        $this->assertSame('abc123', $cookie->value);
        $this->assertNull($cookie->expires);
        $this->assertNull($cookie->path);
        $this->assertNull($cookie->domain);
        $this->assertNull($cookie->secure);
        $this->assertNull($cookie->httponly);
    }

    /**
     * Test cookie construction with attributes.
     */
    public function testConstructWithAttributes(): void
    {
        // Arrange & Act
        $cookie = new Cookie('session=abc123; Path=/; Domain=example.com; Expires=Wed, 21 Oct 2025 07:28:00 GMT; Secure; HttpOnly');

        // Assert
        $this->assertSame('session', $cookie->name);
        $this->assertSame('abc123', $cookie->value);
        $this->assertSame('/', $cookie->path);
        $this->assertSame('example.com', $cookie->domain);
        $this->assertNotNull($cookie->expires);
        $this->assertTrue($cookie->secure);
        $this->assertTrue($cookie->httponly);
    }

    /**
     * Test cookie construction with domain attribute.
     */
    public function testConstructWithDomain(): void
    {
        // Arrange & Act
        $cookie = new Cookie('session=abc123; Domain=example.com');

        // Assert
        $this->assertSame('example.com', $cookie->domain);
    }

    /**
     * Test cookie construction with path attribute.
     */
    public function testConstructWithPath(): void
    {
        // Arrange & Act
        $cookie = new Cookie('session=abc123; Path=/api');

        // Assert
        $this->assertSame('/api', $cookie->path);
    }

    /**
     * Test cookie construction with expires attribute.
     */
    public function testConstructWithExpires(): void
    {
        // Arrange & Act
        $cookie = new Cookie('session=abc123; Expires=Wed, 21 Oct 2025 07:28:00 GMT');

        // Assert
        $this->assertNotNull($cookie->expires);
        $this->assertIsInt($cookie->expires);
    }

    /**
     * Test cookie construction ignores invalid expires values.
     */
    public function testConstructWithInvalidExpires(): void
    {
        // Arrange & Act
        $cookie = new Cookie('session=abc123; Expires=invalid-date');

        // Assert
        $this->assertNull($cookie->expires);
        $this->assertSame('session', $cookie->name);
        $this->assertSame('abc123', $cookie->value);
    }

    /**
     * Test cookie construction with Max-Age attribute.
     */
    public function testConstructWithMaxAge(): void
    {
        // Arrange & Act
        $cookie = new Cookie('session=abc123; Max-Age=3600');

        // Assert
        $this->assertNotNull($cookie->expires);
        $this->assertGreaterThan(time(), $cookie->expires);
    }

    /**
     * Test Max-Age overrides Expires.
     */
    public function testConstructWithMaxAgeOverridesExpires(): void
    {
        // Arrange & Act
        $cookie = new Cookie('session=abc123; Expires=Wed, 21 Oct 2025 07:28:00 GMT; Max-Age=0');

        // Assert
        $this->assertNotNull($cookie->expires);
        $this->assertLessThan(time(), $cookie->expires);
    }

    /**
     * Test cookie construction with secure flag.
     */
    public function testConstructWithSecure(): void
    {
        // Arrange & Act
        $cookie = new Cookie('session=abc123; Secure');

        // Assert
        $this->assertTrue($cookie->secure);
    }

    /**
     * Test cookie construction with httponly flag.
     */
    public function testConstructWithHttpOnly(): void
    {
        // Arrange & Act
        $cookie = new Cookie('session=abc123; HttpOnly');

        // Assert
        $this->assertTrue($cookie->httponly);
    }

    /**
     * Test __toString() returns name=value format.
     */
    public function testToString(): void
    {
        // Arrange
        $cookie = new Cookie('session=abc123');

        // Act
        $result = (string)$cookie;

        // Assert
        $this->assertSame('session=abc123', $result);
    }

    /**
     * Test __toString() with complex cookie.
     */
    public function testToStringWithComplexCookie(): void
    {
        // Arrange
        $cookie = new Cookie('session=abc123; Path=/; Domain=example.com');

        // Act
        $result = (string)$cookie;

        // Assert
        $this->assertSame('session=abc123', $result);
    }
}
