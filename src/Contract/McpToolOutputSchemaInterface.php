<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Contract;

/**
 * Optional companion to {@see McpToolInterface}: advertise an `outputSchema` in `tools/list`.
 *
 * Since protocol revision 2025-06-18 a tool may describe the shape of its structured result. A
 * tool that declares an output schema should return matching data in the `structuredContent` key
 * of its result (the server adds a text fallback in `content` when it is missing, so older
 * clients still see something).
 *
 * @example
 * ```php
 * public function getOutputSchema(): array
 * {
 *     return [
 *         'type' => 'object',
 *         'properties' => [
 *             'rows' => ['type' => 'array', 'items' => ['type' => 'object']],
 *             'count' => ['type' => 'integer'],
 *         ],
 *         'required' => ['rows', 'count'],
 *     ];
 * }
 * ```
 */
interface McpToolOutputSchemaInterface
{
    /**
     * @return array<string, mixed> JSON Schema of the `structuredContent` this tool returns.
     */
    public function getOutputSchema(): array;
}
