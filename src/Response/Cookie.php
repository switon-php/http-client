<?php

declare(strict_types=1);

namespace Switon\HttpClient\Response;

use function explode;
use function preg_match;
use function sprintf;
use function strpos;
use function strtolower;
use function strtotime;
use function substr;
use function time;
use function trim;

/**
 * Parsed representation of one <code>Set-Cookie</code> header value.
 *
 * Use when response cookies need structured access (name/value/domain/path/expiry flags).
 *
 * @see \Switon\HttpClient\Response
 */
class Cookie
{
    public string $name;
    public string $value;
    public ?int $expires = null;
    public ?string $path = null;
    public ?string $domain = null;
    public ?bool $secure = null;
    public ?bool $httponly = null;

    public function __construct(string $cookie)
    {
        $maxAge = null;

        if (($pos = strpos($cookie, ';')) === false) {
            $parts = explode('=', $cookie, 2);
            $this->name = $parts[0] ?? '';
            $this->value = $parts[1] ?? '';
        } else {
            $parts = explode('=', substr($cookie, 0, $pos), 2);
            $this->name = $parts[0] ?? '';
            $this->value = $parts[1] ?? '';

            foreach (explode(';', substr($cookie, $pos + 1)) as $attr) {
                $attr = trim($attr);
                if (($pos = strpos($attr, '=')) === false) {
                    $attr = strtolower($attr);
                    if ($attr === 'secure') {
                        $this->secure = true;
                    } elseif ($attr === 'httponly') {
                        $this->httponly = true;
                    }
                } else {
                    $name = strtolower(substr($attr, 0, $pos));
                    $value = substr($attr, $pos + 1);

                    if ($name === 'domain') {
                        $this->domain = $value;
                    } elseif ($name === 'path') {
                        $this->path = $value;
                    } elseif ($name === 'max-age') {
                        if (preg_match('/^-?\d+$/', $value) === 1) {
                            $maxAge = (int)$value;
                        }
                    } elseif ($name === 'expires') {
                        $expires = strtotime($value);
                        $this->expires = $expires === false ? null : $expires;
                    }
                }
            }
        }

        if ($maxAge !== null) {
            $this->expires = $maxAge <= 0 ? time() - 1 : time() + $maxAge;
        }
    }

    public function __toString(): string
    {
        return sprintf('%s=%s', $this->name, $this->value);
    }
}
