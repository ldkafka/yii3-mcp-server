<?php

declare(strict_types=1);

namespace YiiMcp\McpServer;

use YiiMcp\McpServer\Contract\McpToolInterface;

/**
 * MCP (Model Context Protocol) Server Implementation for Yii3
 *
 * This class implements a JSON-RPC 2.0 server that communicates over stdio (standard input/output)
 * to provide tools that AI assistants (like GitHub Copilot) can use to interact with your application.
 *
 * The MCP protocol enables AI assistants to:
 * - Discover available tools via `tools/list`
 * - Execute tools with parameters via `tools/call`
 * - Receive structured responses with results or errors
 *
 * @package YiiMcp\McpServer
 * @see https://modelcontextprotocol.io/ MCP Protocol Specification
 *
 * @example Basic usage in a Yii3 console command:
 * ```php
 * $server = new McpServer([
 *     new MysqlQueryTool($db),
 *     new CustomTool(),
 * ]);
 * $server->run(); // Blocks and handles stdio communication
 * ```
 */
class McpServer
{
    /**
     * Registry of available tools indexed by tool name
     *
     * @var array<string, McpToolInterface>
     */
    private array $tools = [];

    /**
     * Initialize the MCP server with a collection of tools
     *
     * @param McpToolInterface[] $tools Array of tool instances to register
     */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            $this->registerTool($tool);
        }
    }

    /**
     * Register a tool with the server
     *
     * Tools are indexed by their name, so registering a tool with the same name
     * will replace the previous registration.
     *
     * @param McpToolInterface $tool The tool instance to register
     * @return void
     */
    public function registerTool(McpToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /**
     * Start the MCP server and handle incoming requests
     *
     * This method enters an infinite loop that:
     * 1. Reads JSON-RPC requests from STDIN (one per line)
     * 2. Processes each request according to MCP protocol
     * 3. Sends JSON-RPC responses to STDOUT
     * 4. Logs diagnostic messages to STDERR
     *
     * CRITICAL: STDOUT must contain ONLY valid JSON-RPC messages.
     * All logging, debug output, or diagnostic messages MUST go to STDERR.
     *
     * The server runs until:
     * - STDIN is closed (EOF received)
     * - The process is terminated
     *
     * @return void This method blocks indefinitely
     */
    public function run(): void
    {
        // Log to STDERR (not STDOUT - that's reserved for JSON-RPC responses)
        fwrite(STDERR, "Yii3 MCP Server Started with " . count($this->tools) . " tools.\n");
        
        // Main protocol loop: read requests from STDIN, send responses to STDOUT
        while (true) {
            $line = fgets(STDIN);
            if ($line === false) break; // EOF - client disconnected
            
            // Parse JSON-RPC request
            $request = json_decode($line, true);
            if (!$request) continue; // Invalid JSON - skip silently

            $this->handleRequest($request);
        }
    }

    /**
     * Route incoming JSON-RPC requests to appropriate handlers
     *
     * Handles the following MCP protocol methods:
     * - `initialize`: Handshake to establish protocol version and capabilities
     * - `notifications/initialized`: Client acknowledgment (no response needed)
     * - `tools/list`: Return available tools and their schemas
     * - `tools/call`: Execute a specific tool with arguments
     *
     * @param array<string, mixed> $request JSON-RPC request object
     * @return void
     */
    private function handleRequest(array $request): void
    {
        $method = $request['method'] ?? '';
        $id = $request['id'] ?? null; // Requests with ID require a response

        try {
            // Route to appropriate handler based on method name
            $result = match ($method) {
                'initialize' => $this->handleInitialize(),
                'notifications/initialized' => null, // Notification - no response
                'tools/list' => $this->handleListTools(),
                'tools/call' => $this->handleCallTool($request['params'] ?? []),
                default => null // Unknown method - ignore
            };

            // Send response only if request had an ID (requests without ID are notifications)
            if ($id !== null) {
                $this->sendResponse($id, $result);
            }
        } catch (\Throwable $e) {
            // Log errors to STDERR for debugging
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            if ($id !== null) $this->sendError($id, $e->getMessage());
        }
    }

    /**
     * Handle the `initialize` request - MCP protocol handshake
     *
     * Returns server capabilities and protocol version to the client.
     * This is the first request sent by MCP clients.
     *
     * @return array{protocolVersion: string, capabilities: array, serverInfo: array}
     */
    private function handleInitialize(): array
    {
        return [
            'protocolVersion' => '2024-11-05', // MCP protocol version
            'capabilities' => ['tools' => []], // We support tools
            'serverInfo' => Version::getServerInfo()
        ];
    }

    /**
     * Handle the `tools/list` request - return available tools
     *
     * Returns a list of all registered tools with their:
     * - Name (unique identifier)
     * - Description (human-readable purpose)
     * - Input schema (JSON Schema for validation)
     *
     * @return array{tools: array<int, array{name: string, description: string, inputSchema: array}>}
     */
    private function handleListTools(): array
    {
        $toolDefinitions = [];
        foreach ($this->tools as $tool) {
            $toolDefinitions[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
            ];
        }
        return ['tools' => $toolDefinitions];
    }

    /**
     * Handle the `tools/call` request - execute a tool
     *
     * Looks up the requested tool by name and executes it with provided arguments.
     * Returns the tool's response directly.
     *
     * @param array<string, mixed> $params Request parameters containing 'name' and 'arguments'
     * @return array Tool execution result
     * @throws \Exception If the requested tool is not registered
     */
    private function handleCallTool(array $params): array
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        if (!isset($this->tools[$name])) {
            throw new \Exception("Tool not found: $name");
        }

        return $this->tools[$name]->execute($args);
    }

    /**
     * Send a successful JSON-RPC 2.0 response to STDOUT
     *
     * @param int|string|null $id Request ID from the original request
     * @param mixed $result Result data to return to the client
     * @return void
     */
    private function sendResponse($id, $result): void
    {
        echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]) . "\n";
        flush(); // Ensure immediate delivery to client
    }

    /**
     * Send a JSON-RPC 2.0 error response to STDOUT
     *
     * @param int|string|null $id Request ID from the original request
     * @param string $message Error message to send to the client
     * @return void
     */
    private function sendError($id, $message): void
    {
        echo json_encode([
            'jsonrpc' => '2.0', 
            'id' => $id, 
            'error' => [
                'code' => -32603, // Internal error code per JSON-RPC 2.0 spec
                'message' => $message
            ]
        ]) . "\n";
        flush(); // Ensure immediate delivery to client
    }
}