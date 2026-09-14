<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Protocol;

use function in_array;

/**
 * MCP protocol revisions this server understands, and the version negotiation rule.
 *
 * During `initialize` the client proposes a version. If the server supports it, it answers with
 * the same version; otherwise it answers with the latest version it supports.
 *
 * @see https://modelcontextprotocol.io/specification
 */
final class ProtocolVersion
{
    public const V2024_11_05 = '2024-11-05';
    public const V2025_03_26 = '2025-03-26';
    public const V2025_06_18 = '2025-06-18';

    /** Newest revision implemented by this package. */
    public const LATEST = self::V2025_06_18;

    /** All revisions accepted during negotiation, newest first. */
    public const SUPPORTED = [
        self::V2025_06_18,
        self::V2025_03_26,
        self::V2024_11_05,
    ];

    /**
     * Whether the given revision is one this server can speak.
     */
    public static function isSupported(string $version): bool
    {
        return in_array($version, self::SUPPORTED, true);
    }

    /**
     * Pick the protocol version to answer an `initialize` request with.
     *
     * @param string|null $requested Version proposed by the client, or null when absent.
     */
    public static function negotiate(?string $requested): string
    {
        return $requested !== null && self::isSupported($requested) ? $requested : self::LATEST;
    }
}
