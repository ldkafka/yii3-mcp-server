<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiMcp\McpServer\McpServer;
use YiiMcp\McpServer\Transport\StdioTransport;

/**
 * MCP Server Console Command - Yii3 integration for Model Context Protocol
 *
 * This command starts the MCP server over stdio, the transport editors and AI assistants use when
 * they spawn a local process (GitHub Copilot, Claude Code, Claude Desktop, Cursor, ...).
 *
 * Usage from command line:
 * ```bash
 * php yii mcp:serve
 * ```
 *
 * Integration with editors (VS Code settings.json):
 * ```json
 * {
 *   "github.copilot.chat.mcp.servers": {
 *     "my-yii3-app": {
 *       "command": "php",
 *       "args": ["yii", "mcp:serve"],
 *       "cwd": "/path/to/project"
 *     }
 *   }
 * }
 * ```
 *
 * For the HTTP transport see {@see \YiiMcp\McpServer\Http\McpHttpHandler}: it is served by your
 * web application, not by a console command.
 *
 * @package YiiMcp\McpServer\Command
 */
#[AsCommand(name: self::NAME, description: self::DESCRIPTION)]
class McpCommand extends Command
{
    public const NAME = 'mcp:serve';
    public const DESCRIPTION = 'Starts the MCP Server over stdio (for AI assistant integration)';

    /**
     * @param McpServer $server MCP server instance with registered tools (injected via DI).
     */
    public function __construct(private McpServer $server)
    {
        parent::__construct();
    }

    /**
     * Configure the console command.
     *
     * The name is set explicitly as well as through the attribute, so it does not depend on the
     * installed symfony/console version.
     */
    protected function configure(): void
    {
        $this->setName(self::NAME)->setDescription(self::DESCRIPTION);
    }

    /**
     * Execute the MCP server.
     *
     * CRITICAL: This command does NOT use normal console output!
     * - STDOUT is reserved for JSON-RPC communication with the MCP client
     * - All logging goes to STDERR (see StderrLogger)
     * - Do NOT use $output->writeln() - it will corrupt the protocol
     *
     * The server runs until the client disconnects (closes STDIN) or the process is killed.
     *
     * @param InputInterface $input Command input (unused)
     * @param OutputInterface $output Command output (MUST NOT BE USED)
     * @return int Command exit code (SUCCESS if server exits cleanly)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new StdioTransport($this->server))->run();

        return Command::SUCCESS;
    }
}
