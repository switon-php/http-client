<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Fixtures;

use Switon\HttpClient\CurlInterface;
use stdClass;

class FakeCurl implements CurlInterface
{
    public mixed $handle;
    public int $errno = 0;
    public string $error = '';
    public string|bool $execResult = '';
    public array $info = [];
    public array $options = [];

    public function init(): mixed
    {
        $this->handle = new stdClass();
        return $this->handle;
    }

    public function reset(mixed $handle): void
    {
        $this->options = [];
    }

    public function setopt(mixed $handle, int $option, mixed $value): bool
    {
        $this->options[$option] = $value;
        return true;
    }

    public function exec(mixed $handle): string|bool
    {
        return $this->execResult;
    }

    public function errno(mixed $handle): int
    {
        return $this->errno;
    }

    public function error(mixed $handle): string
    {
        return $this->error;
    }

    public function getinfo(mixed $handle, int $option = 0): mixed
    {
        return $this->info[$option] ?? null;
    }

    public function close(mixed $handle): void
    {
        // no-op
    }
}
