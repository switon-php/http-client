<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\App;
use Switon\Core\InjectorInterface;
use Switon\Di\Container as DiContainer;
use Switon\Di\ServiceProvider as DiServiceProvider;
use Switon\HttpClient\EngineInterface;
use Switon\HttpClient\HttpClientInterface;
use Switon\HttpClient\Tests\Fixtures\StubHttpClient;
use Switon\Pooling\PoolManagerInterface;

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
 */
abstract class TestCase extends BaseTestCase
{
    protected DiContainer $container;
    protected InjectorInterface $injector;
    protected EventDispatcherInterface|MockObject $eventDispatcher;
    protected PoolManagerInterface|MockObject $poolManager;
    protected EngineInterface|MockObject $engine;
    protected HttpClientInterface $httpClientOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new DiContainer();
        $diProvider = new DiServiceProvider();
        $diProvider->register($this->container);
        $diProvider->boot();

        App::setContainer($this->container);

        $this->injector = $this->container->get(InjectorInterface::class);
        $this->setUpContainer();
        $this->injector->inject($this);

        $this->httpClientOwner = new StubHttpClient();
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->poolManager = $this->createStub(PoolManagerInterface::class);
        $this->engine = $this->createStub(EngineInterface::class);
    }

    protected function setUpContainer(): void
    {
    }

    protected function make(string $name, array $parameters = []): mixed
    {
        return $this->container->make($name, $parameters);
    }

    protected function engineParameters(array $parameters = []): array
    {
        return ['owner' => $this->httpClientOwner] + $parameters;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->eventDispatcher, $this->poolManager, $this->engine);
    }

    protected function createEventDispatcherStub(): EventDispatcherStub
    {
        return new EventDispatcherStub();
    }
}
