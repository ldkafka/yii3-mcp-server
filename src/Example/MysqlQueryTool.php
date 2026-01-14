<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Example;

use YiiMcp\McpServer\Contract\McpToolInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * ⚠️ EXAMPLE TOOL IMPLEMENTATION ⚠️
 *
 * MySQL Query Tool - Execute read-only SQL queries against a database
 *
 * This is a REFERENCE IMPLEMENTATION showing how to build MCP tools.
 * Use this as a template when creating your own custom tools.
 *
 * Key patterns demonstrated:
 * - Dependency injection (database connection via constructor)
 * - Input validation (SQL command whitelist)
 * - Security hardening (read-only operations only)
 * - Error handling (try/catch with structured error responses)
 * - MCP protocol compliance (proper response format)
 *
 * @package YiiMcp\McpServer\Example
 *
 * SECURITY CONSIDERATIONS:
 * - This tool enforces read-only operations (SELECT, SHOW, DESCRIBE, EXPLAIN)
 * - Always use database users with minimal permissions
 * - Consider IP restrictions and connection limits
 * - Log all queries for auditing
 *
 * @example Usage in DI container (config/di/mcp.php):
 * ```php
 * McpServer::class => [
 *     '__construct()' => [
 *         'tools' => [
 *             Reference::to(MysqlQueryTool::class),
 *         ],
 *     ],
 * ],
 * ```
 */
class MysqlQueryTool implements McpToolInterface
{
    /**
     * Database connection instance
     */
    private ConnectionInterface $db;

    /**
     * Initialize the MySQL query tool with a database connection
     *
     * @param ConnectionInterface $db Yii3 database connection (injected via DI)
     */
    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'query_database';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Execute a SELECT query against the MySQL database. Use this to inspect schema or retrieve data.';
    }

    /**
     * {@inheritdoc}
     *
     * Accepts two parameters:
     * - `sql` (required): The SQL query to execute
     * - `database` (optional): Database name to switch to before query
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sql' => [
                    'type' => 'string',
                    'description' => 'The SQL SELECT statement to execute'
                ],
                'database' => [
                    'type' => 'string',
                    'description' => 'Optional database name to use for this query (e.g., reporting, analytics)',
                    'default' => ''
                ]
            ],
            'required' => ['sql']
        ];
    }

    /**
     * {@inheritdoc}
     *
     * SECURITY PATTERN: This method demonstrates secure tool implementation:
     * 1. Input validation and sanitization
     * 2. Command whitelisting (only allow safe operations)
     * 3. Structured error responses
     * 4. Exception handling
     *
      * @param array{sql: string, database?: string} $args Query arguments
     * @return array MCP protocol response
     */
    public function execute(array $args): array
    {
        $sql = trim($args['sql'] ?? '');
          $database = $args['database'] ?? '';

        // SECURITY: Whitelist allowed SQL commands (read-only operations only)
        // This prevents data modification, deletion, or schema changes
        $allowedStartKeywords = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'];
        $isAllowed = false;

        // Check if query starts with an allowed command (case-insensitive)
        foreach ($allowedStartKeywords as $keyword) {
            if (stripos($sql, $keyword) === 0) {
                $isAllowed = true;
                break;
            }
        }

        // Reject queries that don't start with allowed commands
        if (!$isAllowed) {
            return [
                'content' => [
                    [
                        'type' => 'text', 
                        'text' => "Error: Only read-only queries (SELECT, SHOW, DESCRIBE, EXPLAIN) are allowed. Your query: $sql"
                    ]
                ]
            ];
        }

        try {
            // Switch database if specified (useful for multi-database applications)
            // NOTE: This uses backticks to prevent SQL injection in database name
            if ($database !== '') {
                $this->db->createCommand("USE `{$database}`")->execute();
            }

            // Execute the query and fetch all results
            $result = $this->db->createCommand($sql)->queryAll();
            
            // Return results as formatted JSON for readability
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result, JSON_PRETTY_PRINT)
                    ]
                ]
            ];
        } catch (\Throwable $e) {
            // Structured error response following MCP protocol
            return [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => 'SQL Error: ' . $e->getMessage()]]
            ];
        }
    }
}