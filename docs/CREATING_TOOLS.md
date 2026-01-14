# Creating Custom MCP Tools

**Build Your Own AI-Powered Tools for Yii3**

This guide teaches you how to create custom MCP tools that AI assistants can use to interact with your Yii3 application.

---

## Table of Contents

1. [Understanding Tools](#understanding-tools)
2. [The Tool Interface](#the-tool-interface)
3. [Step-by-Step: Your First Tool](#step-by-step-your-first-tool)
4. [Advanced Patterns](#advanced-patterns)
5. [Security Best Practices](#security-best-practices)
6. [Testing Tools](#testing-tools)

---

## Understanding Tools

### What is a Tool?

A **tool** is a capability you expose to AI assistants by implementing the `McpToolInterface`. Each tool:

- Has a unique name (e.g., `query_database`, `read_file`)
- Describes what it does (for AI to understand when to use it)
- Defines input parameters (JSON Schema)
- Executes logic and returns results

**Key Concept**: All custom tools must implement `YiiMcp\McpServer\Contract\McpToolInterface` - this is the only requirement to create a tool that AI assistants can use.

### When to Create a Tool

Create tools for actions that AI assistants should be able to perform:

**Good tool ideas:**
- Query application database
- Read configuration files
- Search project code
- Analyze logs
- Check cache status
- Validate data
- Generate reports

**Avoid:**
- Data modification (unsafe for AI)
- Destructive operations (DELETE, DROP)
- Actions requiring human approval
- Security-sensitive operations

---

## The Tool Interface

All tools implement `McpToolInterface`:

```php
namespace YiiMcp\McpServer\Contract;

interface McpToolInterface
{
    public function getName(): string;           // Unique tool identifier
    public function getDescription(): string;     // What it does (for AI)
    public function getInputSchema(): array;      // JSON Schema for inputs
    public function execute(array $args): array;  // The actual logic
}
```

---

## Step-by-Step: Your First Tool

### Example: File Reader Tool

Let's build a tool that reads files from your project:

#### Step 1: Create the Tool Class

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use YiiMcp\McpServer\Contract\McpToolInterface;

class FileReaderTool implements McpToolInterface
{
    public function __construct(
        private string $projectRoot
    ) {}
    
    public function getName(): string
    {
        return 'read_file';
    }
    
    public function getDescription(): string
    {
        return 'Read the contents of a file from the project directory. ' .
               'Use this to inspect configuration files, source code, or documentation.';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path to the file (e.g., "config/main.php")'
                ]
            ],
            'required' => ['path']
        ];
    }
    
    public function execute(array $args): array
    {
        $relativePath = $args['path'] ?? '';
        
        // Security: Prevent directory traversal
        if (str_contains($relativePath, '..')) {
            return [
                'isError' => true,
                'content' => [[
                    'type' => 'text',
                    'text' => 'Error: Path traversal not allowed'
                ]]
            ];
        }
        
        // Build absolute path
        $absolutePath = $this->projectRoot . '/' . ltrim($relativePath, '/');
        
        // Check file exists
        if (!file_exists($absolutePath)) {
            return [
                'isError' => true,
                'content' => [[
                    'type' => 'text',
                    'text' => "Error: File not found: {$relativePath}"
                ]]
            ];
        }
        
        // Check it's a file (not directory)
        if (!is_file($absolutePath)) {
            return [
                'isError' => true,
                'content' => [[
                    'type' => 'text',
                    'text' => "Error: Path is not a file: {$relativePath}"
                ]]
            ];
        }
        
        // Read file content
        $content = file_get_contents($absolutePath);
        
        return [
            'content' => [[
                'type' => 'text',
                'text' => $content
            ]]
        ];
    }
}
```

#### Step 2: Register in DI Container

Edit `config/di-console.php`:

```php
use App\Mcp\Tools\FileReaderTool;
use YiiMcp\McpServer\McpServer;
use Yiisoft\Definitions\Reference;

return [
    McpServer::class => [
        '__construct()' => [
            'tools' => [
                Reference::to(FileReaderTool::class),
            ],
        ],
    ],
    
    FileReaderTool::class => [
        '__construct()' => [
            'projectRoot' => dirname(__DIR__), // Project root directory
        ],
    ],
];
```

#### Step 3: Test the Tool

Start the server:

```bash
php yii mcp:serve
```

In your editor, ask:
```
Show me the contents of config/main.php
```

The AI will use your `read_file` tool automatically!

---

## Advanced Patterns

### Pattern 1: Dependency Injection

Inject Yii components into your tools:

```php
class CacheInspectorTool implements McpToolInterface
{
    public function __construct(
        private CacheInterface $cache
    ) {}
    
    public function execute(array $args): array
    {
        $key = $args['key'];
        $value = $this->cache->get($key);
        
        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($value, JSON_PRETTY_PRINT)
            ]]
        ];
    }
}
```

**DI Configuration**: The DI container automatically injects dependencies.

### Pattern 2: Database Tools with Dependencies

Complete example showing database tool with proper DI configuration:

```php
// Your custom database tool
class DatabaseQueryTool implements McpToolInterface
{
    public function __construct(
        private ConnectionInterface $db
    ) {}

    public function getName(): string
    {
        return 'query_database';
    }

    public function getDescription(): string
    {
        return 'Execute SQL SELECT queries on the application database';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sql' => [
                    'type' => 'string',
                    'description' => 'SQL SELECT query to execute',
                ],
            ],
            'required' => ['sql'],
        ];
    }

    public function execute(array $arguments): array
    {
        $sql = $arguments['sql'];
        
        // Security: Only allow SELECT queries
        if (!preg_match('/^\s*SELECT\s+/i', $sql)) {
            throw new \InvalidArgumentException('Only SELECT queries allowed');
        }

        $rows = $this->db->createCommand($sql)->queryAll();

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($rows, JSON_PRETTY_PRINT),
            ]],
        ];
    }
}
```

**Complete DI Configuration** (`config/common/di/mcp.php`):

```php
<?php

use YiiMcp\McpServer\McpServer;
use App\Mcp\Tools\DatabaseQueryTool;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Cache\File\FileCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;
use Yiisoft\Definitions\Reference;

return [
    // MCP Server with your tools
    McpServer::class => static fn(DatabaseQueryTool $tool) => new McpServer([
        $tool,
    ]),

    // Your database tool
    DatabaseQueryTool::class => DatabaseQueryTool::class,

    // Database connection (CRITICAL: Use Reference::to() for dependencies!)
    ConnectionInterface::class => [
        'class' => Connection::class,
        '__construct()' => [
            'driver' => new Driver(
                dsn: 'mysql:host=localhost;dbname=myapp',
                username: 'root',
                password: '',
            ),
            'schemaCache' => Reference::to(SchemaCache::class), // ← Reference, not string!
        ],
    ],

    // Schema cache
    SchemaCache::class => [
        'class' => SchemaCache::class,
        '__construct()' => [
            'cache' => Reference::to(CacheInterface::class), // ← Reference, not string!
        ],
    ],

    // File cache
    CacheInterface::class => [
        'class' => FileCache::class,
        '__construct()' => [
            'cachePath' => 'runtime/cache',
        ],
    ],
];
```

> **Important**: When configuring database dependencies, always use `Reference::to(ClassName::class)` instead of `ClassName::class` strings in constructor arguments. The DI container needs references to resolve dependencies correctly.

### Pattern 3: Multiple Return Types
public function getInputSchema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'query' => ['type' => 'string'],
            'limit' => [
                'type' => 'integer',
                'default' => 100,
                'description' => 'Maximum results to return'
            ],
            'offset' => [
                'type' => 'integer',
                'default' => 0,
                'description' => 'Number of results to skip'
            ]
        ]
    ];
}

public function execute(array $args): array
{
    $limit = min($args['limit'] ?? 100, 1000); // Cap at 1000
    $offset = $args['offset'] ?? 0;
    
    $results = $this->repository->findAll($limit, $offset);
    
    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode([
                'results' => $results,
                'count' => count($results),
                'offset' => $offset,
                'hasMore' => count($results) === $limit
            ], JSON_PRETTY_PRINT)
        ]]
    ];
}
```

### Pattern 4: Structured Responses

Return rich data structures:

```php
public function execute(array $args): array
{
    $stats = $this->analyzer->getStats();
    
    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode([
                'summary' => [
                    'total_users' => $stats['users'],
                    'active_sessions' => $stats['sessions'],
                    'cache_hit_rate' => $stats['cache_rate']
                ],
                'details' => $stats['breakdown'],
                'timestamp' => date('c')
            ], JSON_PRETTY_PRINT)
        ]]
    ];
}
```

---

## Security Best Practices

### 1. Input Validation

Always validate inputs:

```php
public function execute(array $args): array
{
    $path = $args['path'] ?? '';
    
    // Whitelist allowed paths
    $allowedPaths = ['config/', 'docs/', 'public/'];
    $isAllowed = false;
    
    foreach ($allowedPaths as $allowed) {
        if (str_starts_with($path, $allowed)) {
            $isAllowed = true;
            break;
        }
    }
    
    if (!$isAllowed) {
        return $this->error("Access denied: {$path}");
    }
    
    // ... rest of logic
}
```

### 2. Read-Only Operations

Never allow modification:

```php
// ✅ GOOD
public function getDescription(): string {
    return 'Execute SELECT queries to read data';
}

// ❌ BAD
public function getDescription(): string {
    return 'Execute any SQL query'; // TOO DANGEROUS
}
```

### 3. Command Whitelisting

For SQL or command tools:

```php
$sql = trim($args['sql']);
$allowedCommands = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'];

$isAllowed = false;
foreach ($allowedCommands as $cmd) {
    if (stripos($sql, $cmd) === 0) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    return $this->error('Only read-only queries allowed');
}
```

### 4. Path Traversal Prevention

```php
// Prevent ../../../etc/passwd
if (str_contains($path, '..') || str_contains($path, '~')) {
    return $this->error('Invalid path');
}

// Ensure within project root
$realPath = realpath($projectRoot . '/' . $path);
if (!str_starts_with($realPath, $projectRoot)) {
    return $this->error('Path outside project');
}
```

### 5. Error Message Safety

Don't leak sensitive information:

```php
// ❌ BAD - exposes internal paths
return $this->error("File not found: /var/www/app/secret/config.php");

// ✅ GOOD - sanitized message
return $this->error("File not found: config.php");
```

### 6. Rate Limiting (Optional)

For expensive operations:

```php
class ExpensiveTool implements McpToolInterface
{
    private array $callCount = [];
    
    public function execute(array $args): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $now = time();
        
        // Allow max 10 calls per minute
        $this->callCount[$ip] = array_filter(
            $this->callCount[$ip] ?? [],
            fn($time) => $time > $now - 60
        );
        
        if (count($this->callCount[$ip]) >= 10) {
            return $this->error('Rate limit exceeded');
        }
        
        $this->callCount[$ip][] = $now;
        
        // ... actual logic
    }
}
```

---

## Testing Tools

### Manual Testing

Test with echo and pipe:

```bash
# Test tools/list
echo '{"method":"tools/list","id":1}' | php yii mcp:serve

# Test tools/call
echo '{"method":"tools/call","id":2,"params":{"name":"read_file","arguments":{"path":"README.md"}}}' | php yii mcp:serve
```

### Unit Tests

Create tests in `tests/Unit/Tools/`:

```php
<?php

namespace YiiMcp\McpServer\Tests\Unit\Tools;

use App\Mcp\Tools\FileReaderTool;
use PHPUnit\Framework\TestCase;

class FileReaderToolTest extends TestCase
{
    private FileReaderTool $tool;
    
    protected function setUp(): void
    {
        $this->tool = new FileReaderTool(__DIR__ . '/../../fixtures');
    }
    
    public function testReadExistingFile(): void
    {
        $result = $this->tool->execute(['path' => 'test.txt']);
        
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayNotHasKey('isError', $result);
    }
    
    public function testRejectPathTraversal(): void
    {
        $result = $this->tool->execute(['path' => '../../../etc/passwd']);
        
        $this->assertArrayHasKey('isError', $result);
        $this->assertTrue($result['isError']);
    }
    
    public function testFileNotFound(): void
    {
        $result = $this->tool->execute(['path' => 'nonexistent.txt']);
        
        $this->assertArrayHasKey('isError', $result);
        $this->assertTrue($result['isError']);
    }
}
```

---

## Helper Methods

Create a base class for common patterns:

```php
<?php

namespace App\Mcp\Tools;

use YiiMcp\McpServer\Contract\McpToolInterface;

abstract class BaseTool implements McpToolInterface
{
    protected function success(string $text): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $text]]
        ];
    }
    
    protected function successJson(mixed $data): array
    {
        return $this->success(json_encode($data, JSON_PRETTY_PRINT));
    }
    
    protected function error(string $message): array
    {
        return [
            'isError' => true,
            'content' => [['type' => 'text', 'text' => $message]]
        ];
    }
}
```

Then extend it:

```php
class MyTool extends BaseTool
{
    public function execute(array $args): array
    {
        if ($error) {
            return $this->error('Something went wrong');
        }
        
        return $this->successJson(['result' => $data]);
    }
}
```

---

## Example Tools Library

### 1. Log Viewer Tool

```php
class LogViewerTool implements McpToolInterface
{
    public function getName(): string {
        return 'view_logs';
    }
    
    public function getDescription(): string {
        return 'Read application log files (error.log, app.log)';
    }
    
    public function getInputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'file' => [
                    'type' => 'string',
                    'enum' => ['error', 'app', 'audit'],
                    'description' => 'Log file to read'
                ],
                'lines' => [
                    'type' => 'integer',
                    'default' => 50,
                    'description' => 'Number of lines from end'
                ]
            ],
            'required' => ['file']
        ];
    }
    
    public function execute(array $args): array {
        $logFile = "runtime/logs/{$args['file']}.log";
        $lines = $args['lines'] ?? 50;
        
        $content = `tail -n {$lines} {$logFile}`;
        
        return ['content' => [['type' => 'text', 'text' => $content]]];
    }
}
```

### 2. Configuration Inspector

```php
class ConfigInspectorTool implements McpToolInterface
{
    public function __construct(private array $config) {}
    
    public function getName(): string {
        return 'inspect_config';
    }
    
    public function execute(array $args): array {
        $key = $args['key'] ?? null;
        
        $value = $key ? $this->getConfigValue($key) : $this->config;
        
        return ['content' => [[
            'type' => 'text',
            'text' => json_encode($value, JSON_PRETTY_PRINT)
        ]]];
    }
    
    private function getConfigValue(string $key) {
        // Handle dot notation: 'db.dsn'
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            $value = $value[$k] ?? null;
        }
        
        return $value;
    }
}
```

---

## Next Steps

✅ **Tool created?** Move on to:

- **[Editor Integration](EDITOR_INTEGRATION.md)** - Test your tools in VS Code
- **[Examples](EXAMPLES.md)** - See complete working examples
- **[Deployment](DEPLOYMENT.md)** - Production deployment

---

**Last Updated:** 2026-01-14
