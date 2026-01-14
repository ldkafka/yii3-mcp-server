<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Command;

use YiiMcp\McpServer\McpServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MCP Server Console Command - Yii3 integration for Model Context Protocol
 *
 * This command starts the MCP server in stdio mode, enabling AI assistants
 * (like GitHub Copilot) to interact with your Yii3 application.
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
 * @package YiiMcp\McpServer\Command
 */
class McpCommand extends Command
{
    /**
     * Default command name for Yii3 console
     */
    protected static $defaultName = 'mcp:serve';
    
    /**
     * MCP Server instance (injected via DI)
     */
    private McpServer $server;

    /**
     * Initialize the command with an MCP server instance
     *
     * @param McpServer $server MCP server instance with registered tools
     */
    public function __construct(McpServer $server)
    {
        $this->server = $server;
        parent::__construct();
    }

    /**
     * Configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setDescription('Starts the MCP Server over stdio (for AI assistant integration)');
    }

    /**
     * Execute the MCP server
     *
     * CRITICAL: This command does NOT use normal console output!
     * - STDOUT is reserved for JSON-RPC communication with the MCP client
     * - All logging must go to STDERR or log files
     * - Do NOT use $output->writeln() - it will corrupt the protocol
     *
     * The server runs in an infinite loop until:
     * - The client disconnects (closes STDIN)
     * - The process is killed
     *
     * @param InputInterface $input Command input (unused)
     * @param OutputInterface $output Command output (MUST NOT BE USED)
     * @return int Command exit code (SUCCESS if server exits cleanly)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Start the MCP server - this blocks until client disconnects
        // WARNING: Do not write to $output - STDOUT must be clean for JSON-RPC
        $this->server->run();
        
        return Command::SUCCESS;
    }
}