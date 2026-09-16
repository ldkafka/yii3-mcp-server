<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Support;

use YiiMcp\McpServer\Contract\McpToolInterface;
use YiiMcp\McpServer\Contract\McpToolOutputSchemaInterface;

/**
 * Declares an output schema and returns structured content without a text block.
 */
final class StructuredTool implements McpToolInterface, McpToolOutputSchemaInterface
{
    public function getName(): string
    {
        return 'structured';
    }

    public function getDescription(): string
    {
        return 'Returns a structured result.';
    }

    public function getInputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['n' => ['type' => 'integer']], 'additionalProperties' => false];
    }

    public function getOutputSchema(): array
    {
        return [
            'properties' => ['doubled' => ['type' => 'integer']],
            'required' => ['doubled'],
        ];
    }

    public function execute(array $args): array
    {
        return ['structuredContent' => ['doubled' => 2 * (int) ($args['n'] ?? 0)]];
    }
}
