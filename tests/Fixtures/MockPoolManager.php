<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Fixtures;

use Switon\Pooling\PoolGuard;
use Switon\Pooling\PoolManagerInterface;
use RuntimeException;

class MockPoolManager implements PoolManagerInterface
{
    public array $pools = [];
    public array $added = [];
    public array $popped = [];
    public array $pushed = [];
    public array $created = [];
    public array $exists = [];

    public function remove(object $owner, ?string $type = null): void
    {
        $ownerId = spl_object_id($owner);
        if ($type === null) {
            unset($this->pools[$ownerId], $this->added[$ownerId], $this->popped[$ownerId], $this->pushed[$ownerId], $this->created[$ownerId], $this->exists[$ownerId]);
            return;
        }

        unset(
            $this->pools[$ownerId][$type],
            $this->added[$ownerId][$type],
            $this->popped[$ownerId][$type],
            $this->pushed[$ownerId][$type],
            $this->created[$ownerId][$type],
            $this->exists[$ownerId][$type],
        );
    }

    public function create(object $owner, int $capacity, string $type = 'default'): void
    {
        $ownerId = spl_object_id($owner);
        $this->created[$ownerId][$type] = ['capacity' => $capacity];
    }

    public function add(object $owner, object|array $sample, int $size = 1, string $type = 'default'): void
    {
        $ownerId = spl_object_id($owner);
        $this->pools[$ownerId][$type] ??= [];

        for ($i = 0; $i < $size; $i++) {
            $this->pools[$ownerId][$type][] = $sample;
        }

        $this->added[$ownerId][$type][] = ['sample' => $sample, 'size' => $size];
    }

    public function push(object $owner, object $instance, string $type = 'default'): void
    {
        $ownerId = spl_object_id($owner);
        $this->pools[$ownerId][$type] ??= [];
        $this->pools[$ownerId][$type][] = $instance;
        $this->pushed[$ownerId][$type][] = $instance;
    }

    public function pop(object $owner, ?float $timeout = null, string $type = 'default'): object
    {
        $ownerId = spl_object_id($owner);
        if (empty($this->pools[$ownerId][$type])) {
            throw new RuntimeException("Pool is empty for owner {$ownerId} and type {$type}");
        }

        $instance = array_pop($this->pools[$ownerId][$type]);
        $this->popped[$ownerId][$type][] = $instance;

        return $instance;
    }

    public function acquire(object $owner, ?float $timeout = null, string $type = 'default'): object
    {
        return $this->pop($owner, $timeout, $type);
    }

    public function release(object $owner, object $instance, string $type = 'default', ?float $elapsed = null): void
    {
        $this->push($owner, $instance, $type);
    }

    public function guard(object $owner, ?float $timeout = null, string $type = 'default'): PoolGuard
    {
        return new PoolGuard($this, $owner, $this->acquire($owner, $timeout, $type), $type);
    }

    public function exists(object $owner, string $type = 'default'): bool
    {
        $ownerId = spl_object_id($owner);

        return isset($this->exists[$ownerId][$type])
            ? $this->exists[$ownerId][$type]
            : !empty($this->pools[$ownerId][$type] ?? []);
    }

    public function size(object $owner, string $type = 'default'): int
    {
        $ownerId = spl_object_id($owner);

        return isset($this->pools[$ownerId][$type]) ? count($this->pools[$ownerId][$type]) : 0;
    }

    public function isEmpty(object $owner, string $type = 'default'): bool
    {
        $ownerId = spl_object_id($owner);

        return empty($this->pools[$ownerId][$type]);
    }

    public function setExists(object $owner, string $type, bool $exists): void
    {
        $ownerId = spl_object_id($owner);
        $this->exists[$ownerId] ??= [];
        $this->exists[$ownerId][$type] = $exists;
    }
}
