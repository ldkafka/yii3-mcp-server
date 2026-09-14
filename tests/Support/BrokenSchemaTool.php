<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Support;

use LogicException;
use YiiMcp\McpServer\Contract\McpToolInterface;

/**
 * Fails while describing itself, to exercise the internal-error path of tools/list.
 */
final class BrokenSchemaTool implements McpToolInterface
{
    public function getName(): string
    {
        return 'broken';
    }

    public function getDescription(): string
    {
        return 'Cannot describe its schema.';
    }

    public function getInputSchema(): array
    {
        throw new LogicException('schema unavailable');
    }

    public function execute(array $args): array
    {
        return ['content' => []];
    }
}
