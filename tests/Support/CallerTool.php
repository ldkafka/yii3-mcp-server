<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Support;

use YiiMcp\McpServer\Contract\McpCallerAwareInterface;
use YiiMcp\McpServer\Contract\McpToolInterface;

/**
 * Returns the caller name it was handed, or "none".
 */
final class CallerTool implements McpToolInterface, McpCallerAwareInterface
{
    private ?string $caller = null;

    public function getName(): string
    {
        return 'whoami';
    }

    public function getDescription(): string
    {
        return 'Returns the caller name.';
    }

    public function getInputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function withCaller(?string $caller): static
    {
        $clone = clone $this;
        $clone->caller = $caller;

        return $clone;
    }

    public function execute(array $args): array
    {
        return ['content' => [['type' => 'text', 'text' => $this->caller ?? 'none']]];
    }
}
