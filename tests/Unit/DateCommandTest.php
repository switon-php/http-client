<?php

declare(strict_types=1);

namespace Switon\HttpClient\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Switon\Core\ConsoleInterface;
use Switon\Core\InputInterface;
use Switon\HttpClient\Command\DateCommand;
use Switon\HttpClient\HttpClientInterface;
use Switon\HttpClient\Request;
use Switon\HttpClient\Response;

#[AllowMockObjectsWithoutExpectations]
class DateCommandTest extends TestCase
{
    protected TestableDateCommand $command;
    protected ConsoleInterface&MockObject $console;
    protected HttpClientInterface&MockObject $httpClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new TestableDateCommand();
        $this->console = $this->createMock(ConsoleInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $this->command->setDependencies($this->console, $this->httpClient);
    }

    public function testRemoteActionPrintsRemoteDatetimeWhenTimestampAvailable(): void
    {
        $this->command->stubTimestamp = 1700000000;

        $this->console->expects($this->once())
            ->method('writeLn')
            ->with(date('Y-m-d H:i:s', 1700000000));

        $code = $this->command->remoteAction('example.test');

        $this->assertSame(0, $code);
    }

    public function testSyncActionReturnsErrorWhenRemoteTimestampCannotBeFetched(): void
    {
        $this->command->stubTimestamp = null;

        $this->console->expects($this->once())
            ->method('error')
            ->with('fetch remote timestamp failed: "{url}"', ['url' => 'https://broken.example'])
            ->willReturn(1);
        $this->console->expects($this->never())->method('writeLn');

        $code = $this->command->syncAction('https://broken.example');

        $this->assertSame(1, $code);
        $this->assertNull($this->command->updatedTimestamp);
    }

    public function testSetActionParsesIsoDateTimeAndInvokesUpdateDate(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects($this->once())
            ->method('get')
            ->with('', '')
            ->willReturn('2025-01-02T03:04:05');

        $this->console->expects($this->once())
            ->method('writeLn')
            ->with($this->matchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'));
        $this->console->expects($this->never())->method('error');

        $code = $this->command->setAction($input);

        $this->assertSame(0, $code);
        $this->assertSame(strtotime('2025-01-02 03:04:05'), $this->command->updatedTimestamp);
    }

    public function testSetActionReturnsErrorForInvalidTimeString(): void
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('get')->with('', '')->willReturn('bad-input');

        $this->console->expects($this->once())
            ->method('error')
            ->with(
                '"{str}" time format is invalid',
                $this->callback(static function (array $context): bool {
                    return isset($context['str'])
                        && str_contains($context['str'], 'bad')
                        && preg_match('/\d{2}:\d{2}:\d{2}$/', $context['str']) === 1;
                })
            )
            ->willReturn(1);
        $this->console->expects($this->never())->method('writeLn');

        $code = $this->command->setAction($input);

        $this->assertSame(1, $code);
        $this->assertNull($this->command->updatedTimestamp);
    }

    public function testGetRemoteTimestampReadsDateHeaderViaHead(): void
    {
        $request = new Request('HEAD', 'https://example.test', null, [], []);
        $request->remote_ip = '127.0.0.1';
        $request->process_time = 0.0;
        $response = new Response(
            $request,
            ['HTTP/1.1 200 OK', 'Date: Tue, 14 Nov 2023 22:13:20 GMT'],
            ''
        );

        $this->httpClient->expects($this->once())
            ->method('head')
            ->with('https://example.test')
            ->willReturn($response);

        $timestamp = $this->command->fetchRemoteTimestamp('example.test', true);

        $this->assertSame(strtotime('Tue, 14 Nov 2023 22:13:20 GMT'), $timestamp);
    }
}

class TestableDateCommand extends DateCommand
{
    public ?int $stubTimestamp = null;
    public ?int $updatedTimestamp = null;

    public function fetchRemoteTimestamp(string $url, bool $onlyOnce = false): ?int
    {
        return $this->getRemoteTimestamp($url, $onlyOnce);
    }

    public function setDependencies(ConsoleInterface $console, HttpClientInterface $httpClient): void
    {
        $this->console = $console;
        $this->httpClient = $httpClient;
    }

    protected function getRemoteTimestamp(string $url, bool $onlyOnce = false): ?int
    {
        if ($this->stubTimestamp !== null) {
            return $this->stubTimestamp;
        }

        return parent::getRemoteTimestamp($url, $onlyOnce);
    }

    protected function updateDate(int $timestamp): void
    {
        $this->updatedTimestamp = $timestamp;
    }
}
