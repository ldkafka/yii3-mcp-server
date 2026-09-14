<?php

declare(strict_types=1);

namespace YiiMcp\McpServer;

/**
 * Package Version Information
 *
 * This class provides the current version number for the yii3-mcp-server package.
 * The version should match the git tag of the release.
 */
final class Version
{
    /**
     * Current package version
     *
     * Update this constant when releasing new versions.
     * Format: MAJOR.MINOR.PATCH (Semantic Versioning)
     */
    public const VERSION = '1.1.0';

    /**
     * Package name (the `serverInfo.name` advertised on initialize)
     */
    public const NAME = 'yii3-mcp-server';

    /**
     * Human readable name (the `serverInfo.title` advertised on initialize)
     */
    public const TITLE = 'Yii3 MCP Server';

    /**
     * Get the full server info array for MCP protocol
     *
     * @return array{name: string, version: string, title: string}
     */
    public static function getServerInfo(): array
    {
        return [
            'name' => self::NAME,
            'version' => self::VERSION,
            'title' => self::TITLE,
        ];
    }
}
