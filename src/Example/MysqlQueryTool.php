<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Example;

use Throwable;
use YiiMcp\McpServer\Contract\McpToolAnnotationsInterface;
use YiiMcp\McpServer\Contract\McpToolInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

use function count;
use function is_string;
use function json_encode;
use function max;
use function min;
use function preg_match;
use function sprintf;
use function stripos;
use function trim;

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
 * - Input validation (SQL command whitelist, identifier validation)
 * - Security hardening (read-only operations only, bounded result size)
 * - Error handling (structured `isError` responses)
 * - MCP protocol compliance (proper response format, tool annotations)
 *
 * @package YiiMcp\McpServer\Example
 *
 * SECURITY CONSIDERATIONS:
 * - This tool enforces read-only operations (SELECT, SHOW, DESCRIBE, EXPLAIN)
 * - Always use database users with minimal permissions: the keyword whitelist is a guard rail,
 *   the database grants are the security boundary
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
class MysqlQueryTool implements McpToolInterface, McpToolAnnotationsInterface
{
    /** Rows returned when the caller does not ask for a specific limit. */
    public const DEFAULT_LIMIT = 200;

    /** Upper bound for the `limit` argument, so one call can never dump a whole table. */
    public const MAX_LIMIT = 1000;

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
        return sprintf(
            'Execute a read-only SQL query (SELECT, SHOW, DESCRIBE, EXPLAIN) against the MySQL database. '
            . 'Use this to inspect schema or retrieve data. At most %d rows are returned per call (default %d).',
            self::MAX_LIMIT,
            self::DEFAULT_LIMIT
        );
    }

    /**
     * {@inheritdoc}
     *
     * Accepts three parameters:
     * - `sql` (required): The SQL query to execute
     * - `database` (optional): Database name to switch to before the query
     * - `limit` (optional): Maximum number of rows to return
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sql' => [
                    'type' => 'string',
                    'description' => 'The SQL SELECT statement to execute',
                ],
                'database' => [
                    'type' => 'string',
                    'description' => 'Optional database name to use for this query (e.g., reporting, analytics)',
                    'default' => '',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'default' => self::DEFAULT_LIMIT,
                    'description' => 'Maximum number of rows to return',
                ],
            ],
            'required' => ['sql'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAnnotations(): array
    {
        return [
            'title' => 'Query database (read-only)',
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * SECURITY PATTERN: This method demonstrates secure tool implementation:
     * 1. Input validation and sanitization
     * 2. Command whitelisting (only allow safe operations)
     * 3. Bounded output (never more than MAX_LIMIT rows)
     * 4. Structured error responses
     *
     * @param array{sql?: mixed, database?: mixed, limit?: mixed} $args Query arguments
     * @return array MCP protocol response
     */
    public function execute(array $args): array
    {
        $sql = $args['sql'] ?? null;
        if (!is_string($sql) || trim($sql) === '') {
            return $this->error('The "sql" argument must be a non-empty string.');
        }
        $sql = trim($sql);

        $database = $args['database'] ?? '';
        if (!is_string($database)) {
            return $this->error('The "database" argument must be a string.');
        }
        $database = trim($database);
        if ($database !== '' && preg_match('/^[A-Za-z0-9_$-]+$/', $database) !== 1) {
            return $this->error('The "database" argument must be a plain schema name (letters, digits, "_", "$", "-").');
        }

        $limit = (int) ($args['limit'] ?? self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

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
            return $this->error(
                'Only read-only queries (SELECT, SHOW, DESCRIBE, EXPLAIN) are allowed. Your query: ' . $sql
            );
        }

        try {
            // Switch database if specified (useful for multi-database applications).
            // The name was validated above, so it cannot break out of the backticks.
            if ($database !== '') {
                $this->db->createCommand("USE `{$database}`")->execute();
            }

            // Stream the result and stop after $limit rows: a runaway SELECT cannot exhaust memory
            // or flood the assistant's context.
            $rows = [];
            $truncated = false;
            foreach ($this->db->createCommand($sql)->query() as $row) {
                if (count($rows) >= $limit) {
                    $truncated = true;
                    break;
                }
                $rows[] = $row;
            }

            // Return results as formatted JSON for readability
            $text = (string) json_encode($rows, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($truncated) {
                $text .= sprintf("\n\n[Result truncated to %d rows. Narrow the query or raise \"limit\" (max %d).]", $limit, self::MAX_LIMIT);
            }

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $text,
                    ],
                ],
            ];
        } catch (Throwable $e) {
            return $this->error('SQL Error: ' . $e->getMessage());
        }
    }

    /**
     * Structured error response following MCP protocol.
     *
     * @return array{isError: bool, content: list<array{type: string, text: string}>}
     */
    private function error(string $message): array
    {
        return [
            'isError' => true,
            'content' => [['type' => 'text', 'text' => $message]],
        ];
    }
}
