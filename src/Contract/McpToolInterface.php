<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Contract;

/**
 * MCP Tool Interface - Contract for building custom MCP tools
 *
 * Implement this interface to create tools that AI assistants can use to interact
 * with your Yii3 application. Each tool represents a discrete capability like:
 * - Querying a database
 * - Reading files
 * - Executing commands
 * - Analyzing data
 *
 * @package YiiMcp\McpServer\Contract
 *
 * @example Minimal tool implementation:
 * ```php
 * class GreetingTool implements McpToolInterface
 * {
 *     public function getName(): string {
 *         return 'greet';
 *     }
 *
 *     public function getDescription(): string {
 *         return 'Generate a personalized greeting';
 *     }
 *
 *     public function getInputSchema(): array {
 *         return [
 *             'type' => 'object',
 *             'properties' => [
 *                 'name' => ['type' => 'string', 'description' => 'Name to greet']
 *             ],
 *             'required' => ['name']
 *         ];
 *     }
 *
 *     public function execute(array $args): array {
 *         return [
 *             'content' => [[
 *                 'type' => 'text',
 *                 'text' => "Hello, {$args['name']}!"
 *             ]]
 *         ];
 *     }
 * }
 * ```
 */
interface McpToolInterface
{
    /**
     * Get the unique identifier for this tool
     *
     * This name is used by AI assistants to invoke the tool.
     * Must be unique within the MCP server instance.
     *
     * Naming conventions:
     * - Use lowercase with underscores: `query_database`, `read_file`
     * - Be descriptive but concise
     * - Avoid generic names like `tool` or `helper`
     *
     * @return string Unique tool name (e.g., "query_database")
     */
    public function getName(): string;

    /**
     * Get a human-readable description of what this tool does
     *
     * This description is shown to AI assistants to help them understand
     * when and how to use the tool. Be clear and specific about:
     * - What the tool does
     * - What kind of input it expects
     * - What kind of output it returns
     * - Any limitations or security considerations
     *
     * @return string Description for AI consumption (1-2 sentences recommended)
     */
    public function getDescription(): string;

    /**
     * Get the JSON Schema defining the tool's input parameters
     *
     * The schema describes what arguments the tool accepts and how they should
     * be validated. AI assistants use this to understand how to call the tool.
     *
     * The schema must follow JSON Schema specification (draft-07 or later).
     *
     * @return array JSON Schema describing input parameters
     * @see https://json-schema.org/ JSON Schema specification
     *
     * @example Simple schema with one required string parameter:
     * ```php
     * return [
     *     'type' => 'object',
     *     'properties' => [
     *         'query' => [
     *             'type' => 'string',
     *             'description' => 'SQL SELECT statement to execute'
     *         ]
     *     ],
     *     'required' => ['query']
     * ];
     * ```
     */
    public function getInputSchema(): array;

    /**
     * Execute the tool with provided arguments
     *
     * This method contains the actual business logic of the tool.
     * It receives validated arguments (validated against inputSchema by the client)
     * and returns a structured response.
     *
     * Response format MUST follow MCP protocol:
     * ```php
     * [
     *     'content' => [
     *         ['type' => 'text', 'text' => 'result data here']
     *     ]
     * ]
     * ```
     *
     * For errors, return:
     * ```php
     * [
     *     'isError' => true,
     *     'content' => [
     *         ['type' => 'text', 'text' => 'Error: description']
     *     ]
     * ]
     * ```
     *
     * @param array<string, mixed> $args Arguments matching the inputSchema
     * @return array MCP protocol response with 'content' array
     * @throws \Throwable If execution fails (will be caught and returned as error to client)
     */
    public function execute(array $args): array;
}