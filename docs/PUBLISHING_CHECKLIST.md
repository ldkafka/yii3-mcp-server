# Package Publishing Checklist

**Complete this checklist before publishing to Packagist**

---

## Pre-Publishing Checklist

### Code Quality

- [x] All namespaces updated to `YiiMcp\McpServer\`
- [x] All files have `declare(strict_types=1);`
- [x] Complete PHPDoc on all public methods
- [x] Type hints on all parameters and returns
- [x] No hardcoded credentials in code
- [x] Example tool marked as "EXAMPLE IMPLEMENTATION"

### Configuration

- [x] `composer.json` updated with package name `ldkafka/yii3-mcp-server`
- [x] Package type set to `library`
- [x] Autoload namespace: `YiiMcp\McpServer\`
- [x] Dependencies minimized (suggest optional deps)
- [x] `.env.example` created with safe defaults
- [x] Database credentials externalized to environment variables

### Documentation

- [x] README.md - Quick start guide
- [x] docs/INSTALLATION.md - Complete setup instructions
- [x] docs/CREATING_TOOLS.md - Custom tool development guide
- [x] docs/EDITOR_INTEGRATION.md - VS Code, Docker, WSL, SSH setup
- [ ] docs/DEPLOYMENT.md - Production deployment guide
- [ ] docs/EXAMPLES.md - Working code examples
- [x] LICENSE - BSD-3-Clause license file
- [ ] CHANGELOG.md - Version history (create for v1.0.0)

### Security

- [x] No passwords in git history
- [x] `.gitignore` includes `.env` files
- [x] Read-only operations enforced in example tool
- [x] Input validation in all tools
- [x] Path traversal prevention documented

### Testing

- [ ] Unit tests for McpServer class
- [ ] Unit tests for MysqlQueryTool
- [ ] Integration tests for protocol communication
- [ ] Manual test: `echo '{"method":"initialize","id":1}' | php yii mcp:serve`
- [ ] Editor integration test (VS Code)

### Repository Setup

- [ ] GitHub repository created: `ldkafka/yii3-mcp-server`
- [ ] Repository description set
- [ ] Topics added: yii3, mcp, ai-tools, github-copilot
- [ ] README badges configured
- [ ] Issues enabled
- [ ] Discussions enabled (optional)

### Packagist Registration

- [ ] Account created on packagist.org
- [ ] GitHub webhook configured
- [ ] Package submitted to Packagist
- [ ] Auto-update enabled
- [ ] Package verified on packagist.org/packages/ldkafka/yii3-mcp-server

---

## Post-Publishing Tasks

### Community

- [ ] Announce on Yii forums
- [ ] Post in Yii Slack/Discord
- [ ] Tweet about release (optional)
- [ ] Submit to awesome-yii list (if exists)

### Monitoring

- [ ] Watch Packagist for download stats
- [ ] Monitor GitHub issues
- [ ] Review pull requests
- [ ] Update documentation based on feedback

### Versioning

- [ ] Tag v1.0.0 release in git
- [ ] Create GitHub release with notes
- [ ] Plan roadmap for v1.1.0

---

## Quick Publish Commands

```bash
# 1. Ensure clean working directory
git status

# 2. Run tests
composer test

# 3. Tag release
git tag -a v1.0.0 -m "Initial release"
git push origin v1.0.0

# 4. Create GitHub release (via web UI or gh CLI)
gh release create v1.0.0 --title "v1.0.0 - Initial Release" --notes "See CHANGELOG.md"

# 5. Submit to Packagist (manual via web UI)
# Visit: https://packagist.org/packages/submit
```

---

## Files to Include in Package

✅ **Include:**
- `src/` - All framework source code
- `config/di-console.php` - Framework DI template
- `config/commands.php` - Command registration
- `docs/` - All documentation
- `LICENSE` - License file
- `README.md` - Main documentation
- `.env.example` - Environment template
- `composer.json` - Package metadata
- `configuration.php` - Yii config plugin settings

❌ **Exclude (via .gitignore):**
- `runtime/` - Application runtime directory
- `vendor/` - Composer dependencies
- `.env` - Environment with credentials
- `config/di/mcp.php` - Local config with passwords
- `tests/_output/` - Test artifacts
- IDE files (`.idea/`, `.vscode/`)

---

## Version Numbering

**v1.0.0** - Initial stable release
- Complete MCP server framework
- MysqlQueryTool example
- Full documentation
- Editor integration guide

**v1.1.0** - Planned features
- Additional example tools (FileReader, LogViewer)
- Enhanced testing suite
- Performance optimizations

**v2.0.0** - Breaking changes only
- Major API changes
- Namespace reorganization
- Protocol updates

---

**Last Updated:** 2026-01-14
