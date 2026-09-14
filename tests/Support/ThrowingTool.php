<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Support;

use RuntimeException;
use YiiMcp\McpServer\Contract\McpToolInterface;

/**
 * Always fails during execution, to exercise the isError path.
 */
final class ThrowingTool implements McpToolInterface
{
    public function getName(): string
    {
        return 'boom';
    }

    public function getDescription(): string
    {
        return 'Throws on every call.';
    }

    public function getInputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(array $args): array
    {
        throw new RuntimeException('boom');
    }
}
