<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Support;

use YiiMcp\McpServer\Contract\McpToolAnnotationsInterface;
use YiiMcp\McpServer\Contract\McpToolInterface;

use function json_encode;

/**
 * Echoes its arguments back as JSON text; advertises annotations.
 */
final class EchoTool implements McpToolInterface, McpToolAnnotationsInterface
{
    public function getName(): string
    {
        return 'echo';
    }

    public function getDescription(): string
    {
        return 'Echoes its arguments back as JSON text.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'description' => 'Text to echo'],
            ],
            'required' => ['message'],
        ];
    }

    public function getAnnotations(): array
    {
        return ['title' => 'Echo', 'readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function execute(array $args): array
    {
        return ['content' => [['type' => 'text', 'text' => (string) json_encode($args)]]];
    }
}
