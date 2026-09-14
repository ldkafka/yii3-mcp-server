<?php

declare(strict_types=1);

/**
 * MCP HTTP Transport DI Configuration Template (yiisoft/app layout)
 *
 * 1. Copy this file to your application's config/common/di/mcp-http.php. It sits next to
 *    config/common/di/mcp.php, which defines McpServer and the registered tools; stdio and HTTP
 *    share that same McpServer.
 * 2. Put the secrets in a gitignored params file, e.g. config/environments/prod/params.local.php:
 *
 *    return [
 *        'mcp' => [
 *            'http' => [
 *                'tokens' => array_filter([getenv('MCP_TOKEN') ?: '']),
 *                'allowedOrigins' => [], // browser origins only; CLI/desktop clients send none
 *            ],
 *        ],
 *    ];
 *
 * 3. Route the endpoint in config/common/routes.php, outside any group that applies session or
 *    CSRF middleware (a bare POST carries no CSRF token):
 *
 *    Route::methods(['POST', 'OPTIONS'], '/mcp')
 *        ->middleware(BearerTokenMiddleware::class)
 *        ->action(McpHttpHandler::class)
 *        ->name('mcp'),
 *
 * 4. Make sure the web entry point runs with APP_ENV set (public/index.php does not default it).
 *
 * See docs/HTTP_TRANSPORT.md for the full guide, reverse-proxy snippets and client configuration.
 *
 * @package YiiMcp\McpServer
 */

use Psr\Log\LoggerInterface;
use YiiMcp\McpServer\Http\BearerTokenMiddleware;
use YiiMcp\McpServer\Http\McpHttpHandler;
use YiiMcp\McpServer\McpServer;
use Yiisoft\Definitions\Reference;

/**
 * @var array $params
 * Injected automatically by yiisoft/config.
 */

return [
    // The Streamable HTTP endpoint. ResponseFactoryInterface and StreamFactoryInterface are
    // autowired (yiisoft/app binds them to httpsoft/http-message in config/web/di/psr17.php).
    McpHttpHandler::class => [
        '__construct()' => [
            'server' => Reference::to(McpServer::class),
            'allowedOrigins' => $params['mcp']['http']['allowedOrigins'] ?? [],
            'logger' => Reference::to(LoggerInterface::class),
        ],
    ],

    // Static bearer-token authentication. Throws at container build time when no token is
    // configured, so a missing secret cannot silently expose the endpoint.
    BearerTokenMiddleware::class => [
        '__construct()' => [
            'tokens' => $params['mcp']['http']['tokens'] ?? [],
            'realm' => 'MCP',
        ],
    ],
];
