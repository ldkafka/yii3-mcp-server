# Package Structure Overview

**Yii3 MCP Server - Files and Directories**

This document explains the purpose of each file/directory in the package.

---

## Core Framework Files

### Source Code (`src/`)

```
src/
├── McpServer.php           - Core MCP protocol server
├── Contract/
│   └── McpToolInterface.php - Interface for building tools
├── Command/
│   └── McpCommand.php      - Yii3 console command integration
└── Example/
    └── MysqlQueryTool.php  - Reference implementation (example)
```

**Purpose:**
- `McpServer.php` - Handles JSON-RPC protocol, tool registry, stdio communication
- `McpToolInterface.php` - Contract that all tools must implement
- `McpCommand.php` - Registers `mcp:serve` console command
- `MysqlQueryTool.php` - Example showing how to build database tools

---

## Configuration Files

### `config/`

```
config/
├── di-console.php          - Minimal framework DI (no tools)
├── commands.php            - Command registration
└── di/
    └── mcp.php            - Full example with database tools (local)
```

**Purpose:**
- `di-console.php` - Clean template for package users to copy
- `commands.php` - Registers `mcp:serve` command
- `di/mcp.php` - Complete example with MysqlQueryTool (not distributed as-is)

### `configuration.php`

Yii3 config plugin settings - defines how config files are merged.

### `.env.example`

Template for environment variables (database credentials, etc.).

**Users copy this to `.env` and customize.**

---

## Documentation (`docs/`)

```
docs/
├── INSTALLATION.md         - Setup and configuration guide
├── CREATING_TOOLS.md       - How to build custom tools
├── EDITOR_INTEGRATION.md   - VS Code, Docker, WSL, SSH setup
├── PHASE1_COMPLETE.md      - Development progress log
└── PUBLISHING_CHECKLIST.md - Pre-release validation
```

**Purpose:**
- Complete guides for users to integrate and extend the package
- Reference for package maintainers

---

## Package Metadata

### `composer.json`

Defines package metadata:
- Name: `ldkafka/yii3-mcp-server`
- Type: `library` (not `project`)
- Autoload: `YiiMcp\McpServer\` namespace
- Dependencies and suggestions

### `LICENSE`

BSD-3-Clause license file.

### `README.md`

Main package documentation:
- What is MCP?
- Quick start guide
- Features overview
- Links to detailed docs

---

## Development Files (Not in Package Distribution)

### `tests/`

Unit and integration tests (for development only).

### `runtime/`

Application runtime directory - **not included in package**.

### `vendor/`

Composer dependencies - **not included in package**.

### `.env`

Local environment variables with credentials - **never committed**.

---

## Files Excluded from Distribution

Via `.gitignore`:

```gitignore
runtime/              # Runtime cache/logs
vendor/               # Composer deps
.env                  # Credentials
.env.local           # Local overrides
composer.lock        # Locked versions (libraries don't include)
tests/_output/       # Test artifacts
.idea/               # IDE config
.vscode/             # Editor config
*.log                # Log files
```

---

## Namespace Organization

**Package Namespace:** `YiiMcp\McpServer\`

```
YiiMcp\McpServer\
├── McpServer              - Core server class
├── Contract\
│   └── McpToolInterface   - Tool interface
├── Command\
│   └── McpCommand         - Console command
└── Example\
    └── MysqlQueryTool     - Example tool
```

**User Tools:** `App\Mcp\Tools\` (in user's application)

---

## Installation Flow for End Users

1. **Install package:**
   ```bash
   composer require ldkafka/yii3-mcp-server
   ```

2. **Copy config template:**
   ```bash
   cp vendor/ldkafka/yii3-mcp-server/config/di-console.php config/di-console.php
   ```

3. **Register command:**
   Edit `config/commands.php`, add `McpCommand::class`

4. **Configure tools:**
   Edit `config/di-console.php`, add custom tools

5. **Run server:**
   ```bash
   php yii mcp:serve
   ```

---

## Development vs Distribution

| Item | Development (this repo) | Distribution (Packagist) |
|------|-------------------------|--------------------------|
| Source files | ✅ | ✅ |
| Documentation | ✅ | ✅ |
| Config templates | ✅ | ✅ |
| Runtime directory | ✅ | ❌ |
| Vendor directory | ✅ | ❌ |
| .env files | ✅ (example only) | ✅ (.env.example) |
| Tests | ✅ | ✅ (optional for users) |
| composer.lock | ✅ | ❌ |

---

**Last Updated:** 2026-01-14
