<?php

declare(strict_types=1);

namespace Switon\HttpClient\Command;

use DateTime;
use DateTimeZone;
use Switon\Command\Attribute\Hidden;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;
use Switon\Core\InputInterface;
use Switon\HttpClient\HttpClientInterface;
use Throwable;

use function count;
use function date;
use function explode;
use function preg_match;
use function str_contains;
use function str_pad;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtotime;
use function substr;
use function substr_count;
use function system;
use function time;
use function trim;

/**
 * Inspect and synchronize system time using remote HTTP <code>Date</code> headers.
 *
 * @see \Switon\HttpClient\HttpClientInterface Typical dependency
 * @see \Switon\HttpClient\HttpClient Typical runtime implementation
 * @see \Switon\HttpClient\Response::getHeaders() Date header source
 * @see \Switon\Core\ConsoleInterface Output boundary
 */
#[Hidden]
class DateCommand
{
    protected const string DEFAULT_TIME_URL = 'https://www.baidu.com';

    #[Autowired] protected ConsoleInterface $console;
    #[Autowired] protected HttpClientInterface $httpClient;

    /**
     * Reads remote HTTP <code>Date</code> header as timestamp.
     *
     * @noinspection PhpUnusedLocalVariableInspection
     */
    protected function getRemoteTimestamp(string $url, bool $onlyOnce = false): ?int
    {
        if (!str_contains($url, '://')) {
            $url = 'https://' . $url;
        }

        $prev_timestamp = 0;
        do {
            try {
                $dateHeader = $this->httpClient->head($url)->getHeaders()['Date'] ?? null;
                if (!is_string($dateHeader) || $dateHeader === '') {
                    return null;
                }

                $timestamp = strtotime($dateHeader);
            } catch (Throwable $exception) {
                return null;
            }

            if ($timestamp === false) {
                return null;
            }

            if ($prev_timestamp !== 0 && $prev_timestamp !== $timestamp) {
                break;
            }

            $prev_timestamp = $timestamp;
        } while (!$onlyOnce);

        return $timestamp;
    }

    /**
     * Synchronize local system time from a remote HTTP server.
     *
     * @param string $url Remote URL used to read the HTTP Date header
     */
    public function syncAction(string $url = self::DEFAULT_TIME_URL): int
    {
        $timestamp = $this->getRemoteTimestamp($url);
        if ($timestamp === null) {
            return $this->console->error('fetch remote timestamp failed: "{url}"', ['url' => $url]);
        } else {
            $this->updateDate($timestamp);
            $this->console->writeLn(date('Y-m-d H:i:s'));
            return 0;
        }
    }

    /**
     * Show remote server time.
     *
     * @param string $url Remote URL used to read the HTTP Date header
     */
    public function remoteAction(string $url = self::DEFAULT_TIME_URL): int
    {
        $timestamp = $this->getRemoteTimestamp($url);
        if ($timestamp === null) {
            return $this->console->error('fetch remote timestamp failed: "{url}"', ['url' => $url]);
        } else {
            $this->console->writeLn(date('Y-m-d H:i:s', $timestamp));
            return 0;
        }
    }

    /**
     * Show local and remote time difference in seconds.
     *
     * @param string $url Remote URL used to read the HTTP Date header
     */
    public function diffAction(string $url = self::DEFAULT_TIME_URL): int
    {
        $remote_ts = $this->getRemoteTimestamp($url);
        $local_ts = time();
        if ($remote_ts === null) {
            return $this->console->error('fetch remote timestamp failed: "{url}"', ['url' => $url]);
        } else {
            $this->console->writeLn(' local: ' . date('Y-m-d H:i:s', $local_ts));
            $this->console->writeLn('remote: ' . date('Y-m-d H:i:s', $remote_ts));
            $this->console->writeLn('  diff: ' . ($local_ts - $remote_ts));
            return 0;
        }
    }

    /**
     * Set local system time from parsed date and time values.
     *
     * @param InputInterface $input Raw CLI input for shorthand parsing
     * @param string $date Date value; empty means current date
     * @param string $time Time value; empty means current time
     */
    public function setAction(InputInterface $input, string $date = '', string $time = ''): int
    {
        [$date, $time] = $this->parseInputDateAndTime($input, $date, $time);
        $date = $this->normalizeDate($date);
        $time = $this->normalizeTime($time);

        $str = $date . ' ' . $time;
        $timestamp = strtotime($str);
        if ($timestamp === false || preg_match('#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#', $str) !== 1) {
            return $this->console->error('"{str}" time format is invalid', ['str' => $str]);
        } else {
            $this->updateDate($timestamp);
            $this->console->writeLn(date('Y-m-d H:i:s'));

            return 0;
        }
    }

    /**
     * Parse shorthand input from CLI raw argument into date/time parts.
     *
     * @return array{0:string,1:string}
     */
    protected function parseInputDateAndTime(InputInterface $input, string $date, string $time): array
    {
        $arguments = explode(' ', $input->get('', ''));
        if (count($arguments) !== 1) {
            return [$date, $time];
        }

        $argument = $arguments[0];
        if (str_starts_with($argument, 't')) {
            return ['', substr($argument, 1)];
        }

        $str = trim(str_replace(['T', 't'], [' ', ' '], $argument));
        if (str_contains($str, ' ')) {
            [$date, $time] = explode(' ', $str);
            return [$date, $time];
        }

        if (str_contains($str, ':')) {
            return ['', $str];
        }

        return [$str, ''];
    }

    /** Normalize date string to <code>Y-m-d</code>. */
    protected function normalizeDate(string $date): string
    {
        $date = $date ? str_replace('/', '-', $date) : date('Y-m-d');
        $date = trim(trim($date), '-');

        switch (substr_count($date, '-')) {
            case 0:
                $date = date('Y-m-') . $date;
                break;
            case 1:
                $date = date('Y-') . $date;
                break;
        }

        $parts = explode('-', $date);

        $year = substr(date('Y'), 0, 4 - strlen($parts[0])) . $parts[0];
        $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        $day = str_pad($parts[2], 2, '0', STR_PAD_LEFT);

        return $year . '-' . $month . '-' . $day;
    }

    /** Normalize time string to <code>H:i:s</code>. */
    protected function normalizeTime(string $time): string
    {
        $time = $time ? trim($time) : date('H:i:s');
        if (str_starts_with($time, ':')) {
            $time = date('H') . $time;
        }

        switch (substr_count($time, ':')) {
            case 0:
                $time .= date(':i:s');
                break;
            case 1:
                $time .= date(':s');
                break;
        }

        $parts = explode(':', $time);

        $hour = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
        $minute = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        $second = str_pad($parts[2], 2, '0', STR_PAD_LEFT);

        return $hour . ':' . $minute . ':' . $second;
    }

    /** Updates local system date/time to the given timestamp. */
    protected function updateDate(int $timestamp): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->runSystemCommand('date ' . date('Y-m-d', $timestamp));
            $this->runSystemCommand('time ' . date('H:i:s', $timestamp));
        } elseif (PHP_OS === 'Darwin') {
            $dt = (new DateTime())->setTimestamp($timestamp)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('mdHiy');
            $this->runSystemCommand('date -u ' . $dt);
        } else {
            $this->runSystemCommand('date --set "' . date('Y-m-d H:i:s', $timestamp) . '"');
        }
    }

    /** Executes a system command used for local time updates. */
    protected function runSystemCommand(string $command): void
    {
        system($command);
    }
}
