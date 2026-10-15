<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Contract;

/**
 * Optional companion to {@see McpToolInterface}: learn who is calling.
 *
 * Before `execute()`, the server calls `withCaller()` with the caller name the request was
 * authenticated as (a named token of {@see \YiiMcp\McpServer\Http\BearerTokenMiddleware}), or null
 * when there is none (a plain token, or stdio). The server executes the instance it gets back, so
 * return a copy: the registered tool is shared by every request and must not keep the caller.
 *
 * @example
 * ```php
 * public function withCaller(?string $caller): static
 * {
 *     $clone = clone $this;
 *     $clone->caller = $caller;
 *
 *     return $clone;
 * }
 * ```
 */
interface McpCallerAwareInterface
{
    /**
     * @param string|null $caller Caller name, or null when the request carries none.
     * @return static A copy of the tool that knows the caller.
     */
    public function withCaller(?string $caller): static;
}
