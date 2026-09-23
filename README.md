# Yii3 MCP Server

**Build AI-powered tools for your Yii3 application with Model Context Protocol**

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://www.php.net/)
[![Yii Version](https://img.shields.io/badge/yii-3.x-brightgreen.svg)](https://www.yiiframework.com/)

A framework for building [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) servers in Yii3 applications. Enable AI assistants like GitHub Copilot, Claude Code and Claude Desktop to interact with your application through custom tools, over **stdio** (a local process) or **Streamable HTTP** (a remote endpoint).

---

## What is MCP?

**Model Context Protocol** is an open protocol that enables AI assistants to interact with external tools and data sources. Think of it as an API for AI - instead of REST endpoints for humans, MCP provides a standardized way for AI to:

- Query your database
- Read project files  
- Execute commands
- Analyze data
- Interact with your application

---

## Features

- **Framework, not an app** - Integrate into any Yii3 project
- **Tool-based architecture** - Build custom tools by implementing `McpToolInterface`
- **Simple API** - One interface (`McpToolInterface`) is all you need to create powerful AI tools
- **Two transports** - stdio for editors that spawn a local process, and Streamable HTTP (PSR-15 handler + bearer-token middleware) for remote clients; both serve the same `McpServer`
- **Spec-compliant core** - `initialize` with protocol version negotiation (`2024-11-05` to `2025-06-18`), `ping`, `tools/list` with annotations and `outputSchema`, `tools/call` with `isError` results, JSON-RPC batches and error codes
- **Argument validation** - `tools/call` arguments are checked against the tool's `inputSchema` (required, enum, types, ranges; lenient about numeric strings) and clear mismatches are answered with `-32602` listing the violations, before the tool runs
- **Observable** - every tool call is logged with its duration and result size; failures with their exception
- **PSR everywhere** - PSR-3 logging, PSR-7/15/17 HTTP; no framework lock-in beyond the console command
- **Type-safe** - Full PHP 8.1+ type declarations and PHPDoc
- **Production-ready** - Security patterns, error handling, validation
- **Tested** - PHPUnit suite, CI on PHP 8.1 to 8.4
- **Easy integration** - Works with VS Code, Claude Code, Cursor, and other MCP clients
- **Well-documented** - Comprehensive guides and examples

## Quick Start

### 1. Install via Composer

```bash
composer require ldkafka/yii3-mcp-server
```

### 2. Copy Configuration Templates

Configure based on your Yii3 app structure:

```bash
# For yiisoft/app template (uses config/console/ and config/common/di/):
cp vendor/ldkafka/yii3-mcp-server/config/commands.php config/console/commands.php
cp vendor/ldkafka/yii3-mcp-server/config/di/mcp-template.php config/common/di/mcp.php

# For custom Yii3 apps (uses config/ root level):
# cp vendor/ldkafka/yii3-mcp-server/config/commands.php config/commands.php
# cp vendor/ldkafka/yii3-mcp-server/config/di-console.php config/di-console.php
```

**Note**: The standard `yiisoft/app` template uses `config/console/` for console commands and `config/common/di/` for DI configuration.

Or manually edit `config/console/commands.php` (for yiisoft/app template):

```php
use YiiMcp\McpServer\Command\McpCommand;

return [
    'hello' => Console\HelloCommand::class,
    'mcp:serve' => McpCommand::class, // Add this line
];
```

### 3. Configure DI Container

Create `config/common/di/mcp.php` (for yiisoft/app template):

```php
<?php

declare(strict_types=1);

use YiiMcp\McpServer\McpServer;

return [
    McpServer::class => static fn() => new McpServer([
        // Add your tool instances here
        // new MyCustomTool(),
    ]),
];
```

For custom tool registration, optionally create `config/di-console.php` to define tool dependencies.

### 4. Install Optional Dependencies (If Using Database Tools)

```bash
composer require yiisoft/db-mysql yiisoft/cache-file
```

### 5. Configure Database (Optional - For MySQL Query Tool)

Create `config/environments/dev/params.local.php`:

```php
<?php

declare(strict_types=1);

return [
    'mcp' => [
        'db' => [
            'dsn' => 'mysql:host=localhost;dbname=your_database',
            'username' => 'your_username',
            'password' => 'your_password',
        ],
    ],
];
```

Then edit `config/environments/dev/params.php` to load it:

```php
<?php

declare(strict_types=1);

$params = [];

// Load local params if exists (gitignored credentials)
$localParams = __DIR__ . '/params.local.php';
if (file_exists($localParams)) {
    $params = array_merge($params, require $localParams);
}

return $params;
```

Add to `.gitignore`:

```gitignore
/config/environments/*/params.local.php
```

### 6. Set APP_ENV (Yii3 Framework Requirement)

Create `.env` in project root:

```dotenv
APP_ENV=dev
APP_DEBUG=1
```

Or for Docker environments, add fallback to `yii` entry point (before autoload):

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

// Default to 'dev' if APP_ENV not set (for Docker environments)
if (empty($_ENV['APP_ENV']) && empty(getenv('APP_ENV'))) {
    putenv('APP_ENV=dev');
    $_ENV['APP_ENV'] = 'dev';
}
```

### 7. Rebuild Config Cache

```bash
composer yii-config-rebuild
```

### 8. Verify Installation

```bash
php yii list
```

You should see `mcp:serve` in the command list.

### 9. Run the Server

```bash
php yii mcp:serve
```

### 10. Create Your Own Tools

**The power of this framework is in creating custom tools by implementing `McpToolInterface`:**

```php
use YiiMcp\McpServer\Contract\McpToolInterface;

class MyCustomTool implements McpToolInterface
{
    public function getName(): string
    {
        return 'my_custom_tool';
    }

    public function getDescription(): string
    {
        return 'Does something useful for AI assistants';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'input' => ['type' => 'string', 'description' => 'Input parameter'],
            ],
            'required' => ['input'],
        ];
    }

    public function execute(array $arguments): array
    {
        // Your tool logic here. Return MCP content; throw on failure and the server
        // reports it to the assistant as an `isError` result.
        return [
            'content' => [
                ['type' => 'text', 'text' => 'Received: ' . $arguments['input']],
            ],
        ];
    }
}
```

Then register your tool in `config/common/di/mcp.php`:

```php
return [
    McpServer::class => static fn() => new McpServer([
        new MyCustomTool(),
    ]),
];
```

See [docs/CREATING_TOOLS.md](docs/CREATING_TOOLS.md) for detailed guide.

### 11. Integrate with Your Editor

Add to VS Code `settings.json`:

```json
{
  "github.copilot.chat.mcp.servers": {
    "my-yii3-app": {
      "command": "php",
      "args": ["yii", "mcp:serve"],
      "cwd": "/path/to/your/project"
    }
  }
}
```

Or, for Claude Code, add to the project's `.mcp.json`:

```json
{
  "mcpServers": {
    "my-yii3-app": {
      "command": "php",
      "args": ["yii", "mcp:serve"]
    }
  }
}
```

**That's it!** Your AI assistant can now use your tools.

### 12. Optional: Serve Over HTTP

The same server can be exposed as a remote endpoint (`POST /mcp`) protected by a bearer token, so
assistants on other machines can use your tools without a local PHP process. See
[docs/HTTP_TRANSPORT.md](docs/HTTP_TRANSPORT.md).

---

## Example: Database Query Tool

The package includes `MysqlQueryTool` as a reference implementation:

```php
use YiiMcp\McpServer\Contract\McpToolInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

class MysqlQueryTool implements McpToolInterface
{
    public function __construct(private ConnectionInterface $db) {}
    
    public function getName(): string {
        return 'query_database';
    }
    
    public function getDescription(): string {
        return 'Execute read-only SQL queries';
    }
    
    public function getInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'sql' => ['type' => 'string', 'description' => 'SQL SELECT query']
            ],
            'required' => ['sql']
        ];
    }
    
    public function execute(array $args): array {
        // Validate, execute, return results
    }
}
```

Ask Copilot: _"What tables are in the database?"_ and it will use this tool automatically!

---

## Creating Custom Tools

Implement `McpToolInterface`:

```php
use YiiMcp\McpServer\Contract\McpToolInterface;

class MyCustomTool implements McpToolInterface
{
    public function getName(): string {
        return 'my_tool';
    }
    
    public function getDescription(): string {
        return 'What this tool does for the AI';
    }
    
    public function getInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'param' => ['type' => 'string']
            ]
        ];
    }
    
    public function execute(array $args): array {
        // Your logic here
        return [
            'content' => [
                ['type' => 'text', 'text' => 'Result data']
            ]
        ];
    }
}
```

Register in DI:

```php
McpServer::class => [
    '__construct()' => [
        'tools' => [
            Reference::to(MyCustomTool::class),
        ],
    ],
],
```

---

## Documentation

📖 **[Editor Integration](docs/EDITOR_INTEGRATION.md)** - VS Code, Claude Code, Docker, WSL, SSH setup  
📖 **[Creating Tools](docs/CREATING_TOOLS.md)** - Build custom tools guide  
📖 **[Installation](docs/INSTALLATION.md)** - Detailed setup instructions  
📖 **[HTTP Transport](docs/HTTP_TRANSPORT.md)** - Remote endpoint, authentication, reverse proxy, client config  
📖 **[Changelog](CHANGELOG.md)** - Release notes  

---

## Deployment Scenarios

### Local Development
```json
{
  "command": "php",
  "args": ["yii", "mcp:serve"],
  "cwd": "${workspaceFolder}"
}
```

### Docker
```json
{
  "command": "docker",
  "args": ["exec", "-i", "app-container", "php", "yii", "mcp:serve"]
}
```

### WSL (Windows)
```json
{
  "command": "wsl",
  "args": ["bash", "-c", "cd /mnt/c/project && php yii mcp:serve"]
}
```

### Remote (HTTP)
```json
{
  "type": "http",
  "url": "https://example.test/mcp",
  "headers": { "Authorization": "Bearer ${MCP_TOKEN}" }
}
```

See [EDITOR_INTEGRATION.md](docs/EDITOR_INTEGRATION.md) for more scenarios and [HTTP_TRANSPORT.md](docs/HTTP_TRANSPORT.md) for serving the endpoint.

---

## Security

**Read-Only by Default**: The example `MysqlQueryTool` enforces read-only operations:

```php
// Only allows: SELECT, SHOW, DESCRIBE, EXPLAIN, CHECKSUM TABLE
MysqlQueryTool::isReadOnlyStatement($sql); // matches MysqlQueryTool::READ_ONLY_STATEMENT
```

**Recommendations:**
- Use dedicated read-only database users
- Store credentials in params-local.php (gitignored)
- Validate all tool inputs
- Log tool usage for auditing

---

## Requirements

- **PHP:** 8.1 or higher (`ext-json`)
- **Yii3:** yiisoft/yii-console ^2.0 for the stdio command; any PSR-15 stack for HTTP
- **PSR interfaces:** psr/log, psr/http-message, psr/http-factory, psr/http-server-handler, psr/http-server-middleware
- **Optional:** yiisoft/db + yiisoft/db-mysql for the database tool, yiisoft/router to route the HTTP endpoint

---

## Architecture

```
┌─────────────┐           ┌─────────────┐
│ AI Assistant │           │ AI Assistant │   GitHub Copilot, Claude Code, Claude Desktop, ...
└──────┬──────┘           └──────┬──────┘
       │ JSON-RPC over stdio     │ JSON-RPC over HTTPS (bearer token)
       ▼                         ▼
┌──────────────┐          ┌────────────────┐
│StdioTransport│          │ McpHttpHandler │   PSR-15, behind BearerTokenMiddleware
└──────┬───────┘          └───────┬────────┘
       └────────────┬─────────────┘
                    ▼
          ┌─────────────────┐
          │   McpServer     │   Framework core: tool registry + JSON-RPC dispatch
          └────────┬────────┘
                   │
      ┌────────────┼──────────┬─────────────┐
      ▼            ▼          ▼             ▼
 ┌──────────┐ ┌──────────┐ ┌────────┐ ┌────────────┐
 │MySQL     │ │File      │ │Cache   │ │Custom      │
 │Query Tool│ │Read Tool │ │Tool    │ │Tools       │
 └──────────┘ └──────────┘ └────────┘ └────────────┘
```

---

## Testing

```bash
composer install
composer test   # PHPUnit
```

The suite covers the JSON-RPC dispatcher, both transports and the authentication middleware; CI runs it on PHP 8.1 to 8.4.

---

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new features
4. Submit a pull request

---

## License

This project is licensed under the [BSD-3-Clause License](LICENSE).

---

## Support

- **Issues:** [GitHub Issues](https://github.com/ldkafka/yii3-mcp-server/issues)
- **Documentation:** [docs/](docs/)
- **MCP Specification:** [modelcontextprotocol.io](https://modelcontextprotocol.io/)

---

## Credits

Built with ❤️ for the Yii3 community.

- **MCP Protocol:** [Anthropic](https://www.anthropic.com/)
- **Yii Framework:** [Yii Software](https://www.yiiframework.com/)

---

**Ready to empower your AI assistant?** Install now and start building custom tools!

```bash
composer require ldkafka/yii3-mcp-server
```
