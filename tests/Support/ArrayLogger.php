<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Collects log records in memory for assertions.
 */
final class ArrayLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<string>
     */
    public function levels(): array
    {
        return array_map(static fn (array $record): string => $record['level'], $this->records);
    }
}
