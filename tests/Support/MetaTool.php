<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Support;

use YiiMcp\McpServer\Contract\McpToolAnnotationsInterface;
use YiiMcp\McpServer\Contract\McpToolInterface;
use YiiMcp\McpServer\Contract\McpToolMetaInterface;

/**
 * Sets loosely typed annotation hints and a `_meta` object.
 */
final class MetaTool implements McpToolInterface, McpToolAnnotationsInterface, McpToolMetaInterface
{
    public function getName(): string
    {
        return 'meta';
    }

    public function getDescription(): string
    {
        return 'Advertises annotations and _meta.';
    }

    public function getInputSchema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false];
    }

    public function getAnnotations(): array
    {
        return ['title' => 'Meta', 'readOnlyHint' => 1, 'destructiveHint' => 0, 'openWorldHint' => false, 'idempotentHint' => null];
    }

    public function getMeta(): array
    {
        return ['securitySchemes' => [['type' => 'oauth2', 'scopes' => ['stays:search']]]];
    }

    public function execute(array $args): array
    {
        return ['content' => [['type' => 'text', 'text' => 'ok']]];
    }
}
