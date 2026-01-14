# Yii3 MCP Server

**Build AI-powered tools for your Yii3 application with Model Context Protocol**

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://www.php.net/)
[![Yii Version](https://img.shields.io/badge/yii-3.x-brightgreen.svg)](https://www.yiiframework.com/)

A framework for building [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) servers in Yii3 applications. Enable AI assistants like GitHub Copilot to interact with your application through custom tools.

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
- **Type-safe** - Full PHP 8.1+ type declarations and PHPDoc
- **Production-ready** - Security patterns, error handling, validation
- **Easy integration** - Works with VS Code, Cursor, and other MCP clients
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
        // Your tool logic here
        return [
            'result' => 'Tool executed successfully',
            'data' => $arguments['input'],
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

**That's it!** Your AI assistant can now use your tools.

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

📖 **[Editor Integration](docs/EDITOR_INTEGRATION.md)** - VS Code, Docker, WSL, SSH setup  
📖 **[Creating Tools](docs/CREATING_TOOLS.md)** - Build custom tools guide  
📖 **[Installation](docs/INSTALLATION.md)** - Detailed setup instructions  
📖 **[Deployment](docs/DEPLOYMENT.md)** - Production deployment strategies  
📖 **[Examples](docs/EXAMPLES.md)** - Complete working examples  

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

See [EDITOR_INTEGRATION.md](docs/EDITOR_INTEGRATION.md) for more scenarios.

---

## Security

**Read-Only by Default**: The example `MysqlQueryTool` enforces read-only operations:

```php
// Only allows: SELECT, SHOW, DESCRIBE, EXPLAIN
$allowedKeywords = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'];
```

**Recommendations:**
- Use dedicated read-only database users
- Store credentials in params-local.php (gitignored)
- Validate all tool inputs
- Log tool usage for auditing

---

## Requirements

- **PHP:** 8.1 or higher
- **Yii3:** yiisoft/yii-console ^2.0
- **Optional:** Database extensions for DB tools

---

## Architecture

```
┌─────────────┐
│ AI Assistant │ (GitHub Copilot, Claude Desktop, etc.)
└──────┬──────┘
       │ JSON-RPC over stdio
       ▼
┌─────────────────┐
│   McpServer     │  Framework core (this package)
│  - Tool registry │
│  - Protocol impl│
└────────┬────────┘
         │
    ┌────┴────┬────────┬─────────┐
    ▼         ▼        ▼         ▼
┌────────┐ ┌────┐  ┌────┐   ┌────────┐
│MySQL   │ │File│  │Cache│  │Custom  │
│Query   │ │Read│  │Tool │  │Tools   │
│Tool    │ │Tool│  │     │  │        │
└────────┘ └────┘  └────┘   └────────┘
```

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
