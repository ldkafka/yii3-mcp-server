<?php

declare(strict_types=1);

/**
 * MCP Server DI Configuration Template
 * 
 * Copy this file to your project's config/common/di/ directory as mcp.php
 * This configures the McpServer instance with your registered tools.
 *
 * @package YiiMcp\McpServer
 */

use YiiMcp\McpServer\McpServer;
use Yiisoft\Definitions\Reference;

return [
    McpServer::class => [
        'class' => McpServer::class,
        '__construct()' => [
            'tools' => [
                // Add tool references here:
                // Reference::to(MyCustomTool::class),
            ],
        ],
    ],
];
