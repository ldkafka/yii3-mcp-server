<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Support;

use YiiMcp\McpServer\Contract\McpToolInterface;

/**
 * Returns a plain array instead of an MCP result, as early tool authors often did.
 */
final class RawArrayTool implements McpToolInterface
{
    public function getName(): string
    {
        return 'raw';
    }

    public function getDescription(): string
    {
        return 'Returns a bare associative array.';
    }

    public function getInputSchema(): array
    {
        return [];
    }

    public function execute(array $args): array
    {
        return ['answer' => 42];
    }
}
