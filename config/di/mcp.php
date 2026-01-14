<?php

declare(strict_types=1);

/**
 * MCP Server Dependency Injection Configuration (Example)
 *
 * This file demonstrates how to configure the MCP server with tools.
 * 
 * USAGE IN YII3 APPLICATIONS:
 * 
 * 1. Copy this file to your application's config/common/di/mcp.php
 * 2. The $params variable is automatically available (injected by yiisoft/config)
 * 3. Add database credentials to config/environments/dev/params.local.php:
 * 
 *    return [
 *        'mcp' => [
 *            'db' => [
 *                'dsn' => 'mysql:host=localhost;dbname=your_db',
 *                'username' => 'your_user',
 *                'password' => 'your_pass',
 *            ],
 *        ],
 *    ];
 * 
 * 4. Add params.local.php to .gitignore in environments/dev/
 */

use YiiMcp\McpServer\McpServer;
use YiiMcp\McpServer\Example\MysqlQueryTool;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Cache\File\FileCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;
use Yiisoft\Definitions\Reference;

/** 
 * @var array $params
 * This variable is automatically injected by yiisoft/config in Yii3 applications.
 * Access database credentials via: $params['mcp']['db']['dsn'], etc.
 */

return [
    // 1. MCP Server with registered tools
    // Add your custom tools to the 'tools' array
    McpServer::class => [
        '__construct()' => [
            'tools' => [
                Reference::to(MysqlQueryTool::class), // Example tool - remove if not needed
            ],
        ],
    ],

    // 2. Example Tool: MySQL Query (OPTIONAL - remove if not using database tools)
    MysqlQueryTool::class => MysqlQueryTool::class,

    // 3. Database Connection (OPTIONAL - only needed for database tools)
    // Credentials from $params['mcp']['db'] (automatically available in Yii3)
    ConnectionInterface::class => [
        'class' => Connection::class,
        '__construct()' => [
            'driver' => new Driver(
                dsn: $params['mcp']['db']['dsn'] ?? 'mysql:host=localhost;dbname=app',
                username: $params['mcp']['db']['username'] ?? 'root',
                password: $params['mcp']['db']['password'] ?? '',
            ),
            'schemaCache' => Reference::to(SchemaCache::class),
        ],
    ],

    // 4. Database Schema Cache (OPTIONAL - only needed for database tools)
    SchemaCache::class => [
        'class' => SchemaCache::class,
        '__construct()' => [
            'cache' => Reference::to(CacheInterface::class),
        ],
    ],

    // 5. File Cache for schema caching
    CacheInterface::class => [
        'class' => FileCache::class,
        '__construct()' => [
            'cachePath' => 'runtime/cache',
        ],
    ],
];