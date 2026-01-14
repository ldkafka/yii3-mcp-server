<?php

declare(strict_types=1);

namespace YiiMcp\McpServer;

/**
 * Package Version Information
 * 
 * This class provides the current version number for the yii3-mcp-server package.
 * The version should match the git tag and composer.json version.
 */
final class Version
{
    /**
     * Current package version
     * 
     * Update this constant when releasing new versions.
     * Format: MAJOR.MINOR.PATCH (Semantic Versioning)
     */
    public const VERSION = '1.0.6';

    /**
     * Package name
     */
    public const NAME = 'yii3-mcp-server';

    /**
     * Get the full server info array for MCP protocol
     * 
     * @return array{name: string, version: string}
     */
    public static function getServerInfo(): array
    {
        return [
            'name' => self::NAME,
            'version' => self::VERSION,
        ];
    }
}
