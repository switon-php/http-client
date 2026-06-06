<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\HttpClient\EngineInterface;
use Switon\Pooling\PoolManagerInterface;
use Switon\Testing\TestCase as BaseTestCase;

/**
 * Event dispatcher stub for testing.
 *
 * Collects all dispatched events in a public array for verification.
 */
class EventDispatcherStub implements EventDispatcherInterface
{
    public array $dispatchedEvents = [];

    public function dispatch(object $event): object
    {
        $this->dispatchedEvents[] = $event;
        return $event;
    }
}

/**
 * Base test case for HTTP Client tests.
 *
 * Provides common functionality for all HTTP Client tests, including mocks for dependencies.
 */
abstract class TestCase extends BaseTestCase
{
    protected EventDispatcherInterface|MockObject $eventDispatcher;
    protected PoolManagerInterface|MockObject $poolManager;
    protected EngineInterface|MockObject $engine;

    protected function setUp(): void
    {
        parent::setUp();

        // Create stub event dispatcher (use createStub since most tests don't need expectations)
        // Tests that need to verify event dispatching will replace this with a mock
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        // Create stub pool manager (use createStub since most tests don't need expectations)
        // Tests that need to verify pool manager calls will replace this with a mock
        $this->poolManager = $this->createStub(PoolManagerInterface::class);

        // Create stub engine (use createStub since most tests don't need expectations)
        // Tests that need to verify engine calls will replace this with a mock
        $this->engine = $this->createStub(EngineInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->eventDispatcher, $this->poolManager, $this->engine);
    }

    /**
     * Creates a test event dispatcher stub that collects dispatched events.
     *
     * Returns an EventDispatcherInterface implementation that stores all
     * dispatched events in a public array for testing purposes.
     *
     * @return EventDispatcherStub Event dispatcher stub with dispatchedEvents array
     */
    protected function createEventDispatcherStub(): EventDispatcherStub
    {
        return new EventDispatcherStub();
    }
}
