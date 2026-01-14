# Installation Guide

**Yii3 MCP Server - Complete Installation Instructions**

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Installation Methods](#installation-methods)
3. [Configuration](#configuration)
4. [Verification](#verification)
5. [Troubleshooting](#troubleshooting)

---

## System Requirements

### Minimum Requirements

- **PHP:** 8.1 or higher
- **Composer:** 2.0 or higher
- **Yii3:** yiisoft/yii-console ^2.0

### Optional Requirements

For database tools (like `MysqlQueryTool`):
- `yiisoft/db` ^1.3
- `yiisoft/db-mysql` ^1.2 (or db-pgsql, db-sqlite)
- `yiisoft/cache-file` ^3.2

---

## Installation Methods

### Method 1: Composer (Recommended)

Install the package into an existing Yii3 project:

```bash
composer require ldkafka/yii3-mcp-server
```

### Method 2: Development Installation

Clone for package development:

```bash
git clone https://github.com/ldkafka/yii3-mcp-server.git
cd yii3-mcp-server
composer install
```

---

## Configuration

### Step 1: Register the Console Command

**For yiisoft/app template**, edit `config/console/commands.php`:

```php
<?php

declare(strict_types=1);

use App\Console\HelloCommand;
use YiiMcp\McpServer\Command\McpCommand;

return [
    'mcp:serve' => McpCommand::class,
    // ... your other commands
];
```

### Step 2: Configure Dependency Injection

> **Important**: The framework's power comes from creating custom tools by implementing `McpToolInterface`. Start with an empty tools array and add your tools as you build them.

**For yiisoft/app template**, create `config/common/di/mcp.php`:

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

**For custom Yii3 apps**, you may need to edit `config/di-console.php` instead:

```php
<?php

use YiiMcp\McpServer\McpServer;

return [
    McpServer::class => static fn() => new McpServer([]),
];
```

> **Note**: The `yiisoft/app` template loads common DI configs from `config/common/di/*.php` which are available to both web and console applications.

### Step 3: Database Configuration (For MySQL Tool)

If you plan to use the MySQL query tool, add database credentials to your Yii3 params.

Create `config/environments/dev/params.local.php.example`:

```php
<?php

declare(strict_types=1);

/**
 * Local Development Parameters Example
 * Copy to params.local.php and configure for your environment.
 */

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

Then copy and configure:

```bash
cp config/environments/dev/params.local.php.example config/environments/dev/params.local.php
```

Edit `params.local.php` with your actual credentials.

**Important: Configure params.php to load params.local.php**

Edit `config/environments/dev/params.php` (or your active environment) to load the local params:

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

**Security:** The `params.local.php` file should be gitignored. Add to your `.gitignore`:

```gitignore
/config/environments/*/params.local.php
```

**How params work in Yii3:**

The `yiisoft/config` plugin automatically makes `$params` available in all DI configuration files. The MCP server DI config (`config/common/di/mcp.php`) accesses database credentials via:

```php
$params['mcp']['db']['dsn']
$params['mcp']['db']['username']  
$params['mcp']['db']['password']
```

This approach works in Docker and all environments without relying on `.env` files.

### Step 4: Rebuild Config Cache (If Using yiisoft/config)

After adding new DI configs, rebuild the config cache:

```bash
composer yii-config-rebuild
```

---

## Verification

### Test 1: Command Registration

Verify the command is registered:

```bash
php yii list
```

You should see `mcp:serve` in the command list.

### Test 2: Server Startup

Start the server manually:

```bash
php yii mcp:serve
```

You should see on STDERR:

```
Yii3 MCP Server Started with X tools.
```

The server will block, waiting for input. Press `Ctrl+C` to stop.

### Test 3: Protocol Communication

Test the MCP protocol manually:

```bash
# Send initialize request
echo '{"method":"initialize","id":1}' | php yii mcp:serve
```

Expected output (JSON):

```json
{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2024-11-05","capabilities":{"tools":[]},"serverInfo":{"name":"yii3-mcp-server","version":"1.0.0"}}}
```

### Test 4: Tool Discovery

```bash
echo '{"method":"tools/list","id":2}' | php yii mcp:serve
```

Expected output includes your registered tools.

### Test 5: Editor Integration

See [EDITOR_INTEGRATION.md](EDITOR_INTEGRATION.md) for connecting to VS Code, Cursor, etc.

---

## Troubleshooting

### Issue: "Command not found: mcp:serve"

**Cause:** Command not registered or DI misconfigured.

**Solution:**
1. Check `config/console/commands.php` (or `config/commands.php`) has `McpCommand::class`
2. Verify namespace: `YiiMcp\McpServer\Command\McpCommand`
3. Run `composer dump-autoload`
4. Rebuild config: `composer yii-config-rebuild`

### Issue: "Class not found: YiiMcp\McpServer\McpServer"

**Cause:** Package not installed or autoload not generated.

**Solution:**
```bash
composer install
composer dump-autoload
```

### Issue: Database connection failed

**Cause:** Invalid credentials or missing database.

**Solution:**
1. Check `.env` file has correct credentials
2. Test connection manually:
   ```bash
   php yii db/query "SELECT 1"
   ```
3. Verify database exists:
   ```bash
   mysql -u root -p -e "SHOW DATABASES;"
   ```

### Issue: "Permission denied" on runtime directory

**Cause:** Web server or PHP-FPM user cannot write to `runtime/`.

**Solution:**
```bash
chmod -R 775 runtime/
chown -R www-data:www-data runtime/  # Adjust user/group
```

### Issue: Server starts but no output

**Cause:** STDOUT contamination or buffering issue.

**Solution:**
1. Ensure no `echo`, `print_r`, `var_dump` in code
2. All debug output must go to STDERR:
   ```php
   fwrite(STDERR, "Debug message\n");
   ```
3. Disable Yii debug output in MCP context

### Issue: "No tools available" in editor

**Cause:** Tools not registered in DI or server not starting.

**Solution:**
1. Check `config/common/di/mcp.php` (or `config/di-console.php`) exists and defines McpServer
2. Verify tool classes exist and are autoloaded
3. Check editor settings.json has correct `command` and `cwd`
4. Restart editor after changing settings

### Issue: "APP_ENV environment variable is empty" error

**Cause:** Yii3 framework requires `APP_ENV` environment variable to be set (separate from MCP database configuration).

**Solution (Option 1 - .env file):**
Create `.env` file in project root:
```dotenv
APP_ENV=dev
APP_DEBUG=1
```

**Solution (Option 2 - Docker/Environment Variable):**
Set in docker-compose.yml:
```yaml
services:
  app:
    environment:
      - APP_ENV=dev
```

**Solution (Option 3 - Fallback in yii entry point):**
For Docker mounted volumes where .env isn't loaded, add to `yii` file before autoload:
```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

// Default to 'dev' if APP_ENV not set (for Docker environments)
if (empty($_ENV['APP_ENV']) && empty(getenv('APP_ENV'))) {
    putenv('APP_ENV=dev');
    $_ENV['APP_ENV'] = 'dev';
}

use App\Environment;
// ... rest of yii entry point
```

> **Note:** This is a Yii3 framework requirement, not an MCP package requirement. MCP database configuration uses params.local.php as documented above.

---

## Next Steps

✅ Installation complete? Move on to:

- **[Creating Custom Tools](CREATING_TOOLS.md)** - Build your own tools
- **[Editor Integration](EDITOR_INTEGRATION.md)** - Connect to VS Code
- **[Deployment Guide](DEPLOYMENT.md)** - Production setup
- **[Examples](EXAMPLES.md)** - Working code examples

---

## Getting Help

- **Issues:** [GitHub Issues](https://github.com/ldkafka/yii3-mcp-server/issues)
- **Documentation:** [docs/](../)
- **Community:** Yii Forums and Slack

---

**Last Updated:** 2026-01-14
