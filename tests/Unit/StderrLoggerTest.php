<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use YiiMcp\McpServer\Log\StderrLogger;

use function fopen;
use function rewind;
use function stream_get_contents;

final class StderrLoggerTest extends TestCase
{
    public function testInterpolatesPlaceholdersAndAppendsRemainingContext(): void
    {
        $stream = fopen('php://memory', 'w+');
        $logger = new StderrLogger($stream);

        $logger->info('Tool {tool} took {ms} ms', ['tool' => 'echo', 'ms' => 12, 'rows' => 3]);

        rewind($stream);
        self::assertSame("mcp [info] Tool echo took 12 ms {\"rows\":3}\n", stream_get_contents($stream));
    }

    public function testDropsRecordsBelowMinimumLevel(): void
    {
        $stream = fopen('php://memory', 'w+');
        $logger = new StderrLogger($stream, LogLevel::WARNING, '');

        $logger->info('hidden');
        $logger->debug('hidden too');
        $logger->error('shown');

        rewind($stream);
        self::assertSame("[error] shown\n", stream_get_contents($stream));
    }

    public function testExceptionsInContextAreSummarised(): void
    {
        $stream = fopen('php://memory', 'w+');
        $logger = new StderrLogger($stream, LogLevel::DEBUG, '');

        $logger->error('failed: {error}', ['error' => 'boom', 'exception' => new RuntimeException('boom')]);

        rewind($stream);
        self::assertSame("[error] failed: boom {\"exception\":\"RuntimeException: boom\"}\n", stream_get_contents($stream));
    }
}
