<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Contract;

/**
 * Optional companion to {@see McpToolInterface}: advertise tool annotations in `tools/list`.
 *
 * Annotations are hints that let clients present a tool sensibly (for example, ask the user
 * before running a destructive tool). They are advisory only; never rely on them for security.
 *
 * Recognised keys (all optional):
 * - `title`: human readable display name (also surfaced as the top-level `title` of the tool)
 * - `readOnlyHint`: the tool does not modify its environment
 * - `destructiveHint`: the tool may perform destructive updates
 * - `idempotentHint`: repeated calls with the same arguments have no additional effect
 * - `openWorldHint`: the tool interacts with external entities (the web, third-party APIs)
 *
 * @example
 * ```php
 * public function getAnnotations(): array
 * {
 *     return ['title' => 'Query database', 'readOnlyHint' => true, 'idempotentHint' => true];
 * }
 * ```
 */
interface McpToolAnnotationsInterface
{
    /**
     * @return array{title?: string, readOnlyHint?: bool, destructiveHint?: bool, idempotentHint?: bool, openWorldHint?: bool}
     */
    public function getAnnotations(): array;
}
