<?php

declare(strict_types=1);

/**
 * Console Commands Configuration for MCP Server
 *
 * This file registers the MCP server command with Yii3's console application.
 *
 * @package YiiMcp\McpServer
 */

use YiiMcp\McpServer\Command\McpCommand;

return [
    'mcp:serve' => McpCommand::class,
];
