<?php

declare(strict_types=1);

/**
 * MCP Tools Registration (Console DI Configuration)
 * 
 * Register your MCP tool implementations here.
 * Place this file in your project's config/ directory (root level for console-specific DI).
 *
 * Example tool registration:
 * 
 * use YiiMcp\McpServer\Example\Tools\MysqlQueryTool;
 * use Yiisoft\Db\Connection\ConnectionInterface;
 * 
 * return [
 *     MysqlQueryTool::class => [
 *         '__construct()' => [
 *             'db' => Reference::to(ConnectionInterface::class),
 *         ],
 *     ],
 * ];
 *
 * @package YiiMcp\McpServer
 */

return [
    // Register your MCP tool classes here with their dependencies
    // Example:
    // MyCustomTool::class => [
    //     '__construct()' => [
    //         'dependency' => Reference::to(SomeDependency::class),
    //     ],
    // ],
];
