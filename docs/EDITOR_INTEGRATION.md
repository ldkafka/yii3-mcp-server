# Editor Integration Guide

**Yii3 MCP Server - Connecting AI Assistants to Your Application**

This guide explains how to integrate the Yii3 MCP Server with code editors and AI assistants that support the Model Context Protocol (MCP).

---

## Table of Contents

1. [Overview](#overview)
2. [GitHub Copilot Integration](#github-copilot-integration)
3. [Deployment Scenarios](#deployment-scenarios)
4. [Testing Your Integration](#testing-your-integration)
5. [Troubleshooting](#troubleshooting)

---

## Overview

The MCP Server communicates over **stdio** (standard input/output) using JSON-RPC 2.0. This means:

- **STDIN**: Receives requests from the AI assistant
- **STDOUT**: Sends responses back to the AI assistant  
- **STDERR**: Logs diagnostic messages (not sent to AI)

Your editor/IDE needs to spawn the `php yii mcp:serve` process and manage stdio communication.

---

## GitHub Copilot Integration

GitHub Copilot (in VS Code, Cursor, and other compatible editors) supports MCP servers natively.

### Basic Configuration

Add this to your workspace or user `settings.json`:

```json
{
  "github.copilot.chat.mcp.servers": {
    "my-yii3-tools": {
      "command": "php",
      "args": ["yii", "mcp:serve"],
      "cwd": "${workspaceFolder}"
    }
  }
}
```

**What this does:**
- `command`: The executable to run (`php`)
- `args`: Command arguments (runs `php yii mcp:serve`)
- `cwd`: Working directory (where to run the command)

### Using Workspace Variables

VS Code provides useful variables for paths:

```json
{
  "github.copilot.chat.mcp.servers": {
    "my-yii3-tools": {
      "command": "php",
      "args": ["${workspaceFolder}/yii", "mcp:serve"],
      "cwd": "${workspaceFolder}"
    }
  }
}
```

**Available variables:**
- `${workspaceFolder}` - Root directory of current workspace
- `${workspaceFolderBasename}` - Name of workspace folder
- `${file}` - Currently open file path
- `${env:VARIABLE_NAME}` - Environment variable value

---

## Deployment Scenarios

### Scenario 1: Local PHP (Development)

**When to use:** Local development, PHP installed on your machine.

```json
{
  "github.copilot.chat.mcp.servers": {
    "yii3-local": {
      "command": "php",
      "args": ["yii", "mcp:serve"],
      "cwd": "/path/to/your/yii3/project"
    }
  }
}
```

**Requirements:**
- PHP 8.1+ in PATH
- Composer dependencies installed
- Database accessible from localhost

---

### Scenario 2: Docker Container

**When to use:** Application runs in Docker, database in container network.

```json
{
  "github.copilot.chat.mcp.servers": {
    "yii3-docker": {
      "command": "docker",
      "args": [
        "exec", "-i",
        "your-app-container",
        "php", "yii", "mcp:serve"
      ]
    }
  }
}
```

**Requirements:**
- Docker running with named container
- Container must stay running (use `docker-compose up -d`)
- `-i` flag enables interactive stdin (required for MCP)

**Example with docker-compose:**

```json
{
  "github.copilot.chat.mcp.servers": {
    "yii3-docker": {
      "command": "docker",
      "args": [
        "compose", "exec", "-T",
        "app",
        "php", "yii", "mcp:serve"
      ],
      "cwd": "${workspaceFolder}"
    }
  }
}
```

**Note:** Use `-T` (disable TTY) instead of `-i` with `docker compose exec`.

---

### Scenario 3: WSL/WSL2 (Windows)

**When to use:** Development on Windows, code runs in WSL2 Linux environment.

```json
{
  "github.copilot.chat.mcp.servers": {
    "yii3-wsl": {
      "command": "wsl",
      "args": [
        "bash", "-c",
        "cd /mnt/c/path/to/project && php yii mcp:serve"
      ]
    }
  }
}
```

**With custom wrapper script (like aura_v5 pattern):**

```json
{
  "github.copilot.chat.mcp.servers": {
    "yii3-wsl": {
      "command": "wsl",
      "args": [
        "/usr/local/bin/your-cli-wrapper.sh",
        "php", "yii", "mcp:serve"
      ]
    }
  }
}
```

**Requirements:**
- WSL2 installed and configured
- Project accessible from WSL (usually `/mnt/c/...`)
- PHP and dependencies available in WSL environment

**Path Translation Tips:**
- Windows: `C:\work\project` → WSL: `/mnt/c/work/project`
- Use lowercase drive letter: `C:` → `/mnt/c`
- Replace backslashes with forward slashes

---

### Scenario 4: Remote Server (SSH)

**When to use:** Application runs on remote server, SSH access available.

```json
{
  "github.copilot.chat.mcp.servers": {
    "yii3-remote": {
      "command": "ssh",
      "args": [
        "user@server.example.com",
        "cd /var/www/project && php yii mcp:serve"
      ]
    }
  }
}
```

**Requirements:**
- SSH key authentication configured (no password prompts)
- SSH connection must be fast/stable
- Project path accessible to SSH user

**SSH Config Optimization** (`~/.ssh/config`):

```ssh-config
Host myserver
    HostName server.example.com
    User deploy
    IdentityFile ~/.ssh/id_rsa_deploy
    ControlMaster auto
    ControlPath ~/.ssh/sockets/%r@%h-%p
    ControlPersist 600
```

Then use in settings.json:

```json
{
  "github.copilot.chat.mcp.servers": {
    "yii3-remote": {
      "command": "ssh",
      "args": [
        "myserver",
        "cd /var/www/project && php yii mcp:serve"
      ]
    }
  }
}
```

**Performance Note:** SSH has latency overhead. Consider using SSH connection multiplexing (`ControlMaster`) to reduce connection time.

---

### Scenario 5: Custom Wrapper Script

**When to use:** Complex environment setup, multiple commands, custom logic.

**Create a wrapper script** (`mcp-wrapper.sh`):

```bash
#!/bin/bash

# Set PHP version (if using version manager)
# export PATH=/usr/local/php8.3/bin:$PATH

# Change to project directory
cd /path/to/project

# Run MCP server
exec php yii mcp:serve
```

**Make executable:**

```bash
chmod +x mcp-wrapper.sh
```

**Configure in settings.json:**

```json
{
  "github.copilot.chat.mcp.servers": {
    "yii3-custom": {
      "command": "/path/to/mcp-wrapper.sh"
    }
  }
}
```

---

## Testing Your Integration

### Step 1: Restart Editor

After modifying `settings.json`, restart VS Code/Cursor to reload configuration.

### Step 2: Open Copilot Chat

Click the Copilot icon in the sidebar or use keyboard shortcut (Ctrl+Shift+I / Cmd+Shift+I).

### Step 3: Test Tool Discovery

In Copilot Chat, ask:

```
What tools are available?
```

or

```
List MCP tools
```

You should see your registered tools (e.g., `query_database` if using MysqlQueryTool).

### Step 4: Test Tool Execution

Try executing a tool:

```
Use query_database to show all tables
```

or

```
What tables exist in the database?
```

Copilot should invoke the tool and return results.

---

## Troubleshooting

### Issue: "No tools available" or Tools Not Showing

**Possible causes:**

1. **MCP server not starting**
   - Test manually: `php yii mcp:serve` should not crash
   - Check STDERR logs: `php yii mcp:serve 2> mcp-error.log`

2. **Wrong working directory**
   - Verify `cwd` points to project root (where `yii` script exists)
   - Check paths: `php yii list` should work from that directory

3. **Settings not reloaded**
   - Restart VS Code completely
   - Check settings.json for syntax errors (trailing commas, quotes)

4. **PHP not in PATH**
   - Test: `which php` (Unix) or `where php` (Windows)
   - Use absolute path: `"command": "/usr/bin/php"`

### Issue: "Tool execution failed" or Timeout

**Possible causes:**

1. **Database connection failed**
   - Check params.local.php has correct credentials
   - Test connection: `php yii db/query "SELECT 1"`

2. **Dependency not installed**
   - Run: `composer install`
   - Check autoload: `composer dump-autoload`

3. **Permission issues**
   - Runtime directory must be writable: `chmod -R 777 runtime/`
   - Cache directory must exist

### Issue: STDOUT Contamination

**Symptom:** MCP protocol errors, JSON parse failures

**Cause:** Code writes to STDOUT (echo, var_dump, print_r)

**Solution:**
- All debug output MUST go to STDERR: `fwrite(STDERR, "debug\n");`
- Or to log files: `Yii::error('message');`
- Disable debug output in production: `YII_DEBUG=false`

**Test STDOUT cleanliness:**

```bash
echo '{"method":"initialize","id":1}' | php yii mcp:serve
```

Should output ONLY valid JSON-RPC response, nothing else.

### Issue: Slow Response Times

**Possible causes:**

1. **SSH latency** (remote server)
   - Enable SSH ControlMaster connection sharing
   - Consider running MCP server locally with SSH tunnel to database

2. **Database query timeout**
   - Check slow query log
   - Add query timeout limits in tool implementation

3. **Cold start (Docker)**
   - Keep container running with `docker-compose up -d`
   - Consider using lightweight base image

### Getting Help

1. **Check MCP server logs**
   ```bash
   php yii mcp:serve 2> mcp-debug.log
   # In another terminal, tail the log
   tail -f mcp-debug.log
   ```

2. **Test manually**
   ```bash
   # Send a valid MCP request
   echo '{"method":"tools/list","id":1}' | php yii mcp:serve
   # Should return JSON with tools array
   ```

3. **Enable Yii debug mode**
   ```php
   // config/params.php
   return [
       'yiisoft/yii-debug' => [
           'enabled' => true,
       ],
   ];
   ```

---

## Advanced Configuration

### Multiple Servers (Different Projects)

```json
{
  "github.copilot.chat.mcp.servers": {
    "project-a": {
      "command": "php",
      "args": ["yii", "mcp:serve"],
      "cwd": "/path/to/project-a"
    },
    "project-b": {
      "command": "docker",
      "args": ["exec", "-i", "projectb-app", "php", "yii", "mcp:serve"]
    }
  }
}
```

Copilot will automatically use the server matching your current workspace.

### Environment-Specific Configuration

Use VS Code's multi-root workspaces or separate settings files:

**`.vscode/settings.json` (development):**

```json
{
  "github.copilot.chat.mcp.servers": {
    "yii3-dev": {
      "command": "php",
      "args": ["yii", "mcp:serve"],
### Yii Environment Variables (Optional)

If your application uses Yii environment variables:

```json
{
  "github.copilot.chat.mcp.servers": {
    "yii3-app": {
      "command": "php",
      "args": ["yii", "mcp:serve"],
      "cwd": "${workspaceFolder}",
      "env": {
        "YII_ENV": "dev",
        "YII_DEBUG": "true"
      }
    }
  }
}
```

### Database Credentials

**Do NOT use environment variables for database credentials** - they don't work in Docker.

Instead, configure database credentials in Yii3 params:

1. Create `config/environments/dev/params.local.php`:

```php
<?php
return [
    'mcp' => [
        'db' => [
            'dsn' => 'mysql:host=localhost;dbname=myapp',
            'username' => 'readonly',
            'password' => 'your_password',
        ],
    ],
];
```

2. Add to `.gitignore`:

```gitignore
/config/environments/*/params.local.php
```

The `$params` variable is automatically available in your DI configs via `yiisoft/config`.

---

## Security Considerations

### Read-Only Database Access

For production environments, create a dedicated read-only MySQL user:

```sql
CREATE USER 'mcp_readonly'@'localhost' IDENTIFIED BY 'strong-password';
GRANT SELECT, SHOW VIEW ON myapp.* TO 'mcp_readonly'@'localhost';
FLUSH PRIVILEGES;
```

### Network Restrictions

If running MCP server on a remote server:

1. Use SSH tunnels, not direct database exposure
2. Restrict MCP server to localhost only
3. Use SSH key authentication, not passwords

### Audit Logging

Log all MCP tool executions for security audits:

```php
// In your tool's execute() method
\Yii::info("MCP Tool executed: {$this->getName()} with args: " . json_encode($args), 'mcp-audit');
```

---

## Next Steps

- [Creating Custom Tools](CREATING_TOOLS.md) - Build your own MCP tools
- [Deployment Guide](DEPLOYMENT.md) - Production deployment strategies
- [GitHub Copilot Documentation](https://docs.github.com/en/copilot) - Official Copilot docs

---

**Last Updated:** 2026-01-13
