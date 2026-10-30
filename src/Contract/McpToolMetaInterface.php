<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Contract;

/**
 * Optional companion to {@see McpToolInterface}: advertise a `_meta` object in `tools/list`.
 *
 * `_meta` is the specification's free-form extension point. Clients read their own keys from it;
 * OpenAI, for example, reads the OAuth scopes a tool needs from `securitySchemes`. The server
 * passes the array through unchanged and leaves the key out when it is empty.
 *
 * @example
 * ```php
 * public function getMeta(): array
 * {
 *     return ['securitySchemes' => [['type' => 'oauth2', 'scopes' => ['stays:search']]]];
 * }
 * ```
 */
interface McpToolMetaInterface
{
    /**
     * @return array<string, mixed> Keys and values of the tool's `_meta` object.
     */
    public function getMeta(): array;
}
