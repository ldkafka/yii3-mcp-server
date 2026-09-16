<?php

declare(strict_types=1);

namespace YiiMcp\McpServer;

use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use Throwable;
use YiiMcp\McpServer\Contract\McpToolAnnotationsInterface;
use YiiMcp\McpServer\Contract\McpToolInterface;
use YiiMcp\McpServer\Contract\McpToolOutputSchemaInterface;
use YiiMcp\McpServer\Protocol\ArgumentValidator;
use YiiMcp\McpServer\Protocol\JsonRpc;
use YiiMcp\McpServer\Protocol\JsonRpcException;
use YiiMcp\McpServer\Protocol\ProtocolVersion;
use YiiMcp\McpServer\Transport\StdioTransport;

use function array_is_list;
use function array_key_exists;
use function hrtime;
use function implode;
use function is_array;
use function is_bool;
use function is_int;
use function is_scalar;
use function is_string;
use function round;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * Transport-agnostic MCP server core: a tool registry plus the JSON-RPC request dispatcher.
 *
 * Feed it decoded messages with {@see dispatch()} / {@see dispatchPayload()}, or raw JSON with
 * {@see handleJson()}, and it returns the response to send back, or null when nothing must be sent
 * (notifications). Transports are separate: {@see StdioTransport} for editors that spawn a local
 * process, and {@see Http\McpHttpHandler} for Streamable HTTP.
 *
 * Supported methods: `initialize`, `ping`, `tools/list`, `tools/call`. Every `notifications/*`
 * message is accepted and ignored. Unknown methods answer with a JSON-RPC "method not found"
 * error. Tool failures are reported as `isError` results, as the specification requires, so the
 * assistant can read the message and recover instead of losing the whole request. Arguments are
 * checked against the tool's input schema first ({@see ArgumentValidator}); clear mismatches are
 * answered with `-32602 Invalid params` listing the violations, as the specification prescribes.
 *
 * @see https://modelcontextprotocol.io/ MCP Protocol Specification
 *
 * @example Serve over stdio from a console command:
 * ```php
 * $server = new McpServer([new MysqlQueryTool($db), new CustomTool()]);
 * $server->run(); // blocks until STDIN closes
 * ```
 */
class McpServer
{
    /**
     * Registry of available tools indexed by tool name.
     *
     * @var array<string, McpToolInterface>
     */
    private array $tools = [];

    private LoggerInterface $logger;

    private ?string $instructions;

    /** @var array{name: string, version: string, title?: string} */
    private array $serverInfo;

    private ?string $negotiatedProtocolVersion = null;

    private bool $validateArguments;

    /**
     * @param McpToolInterface[] $tools Tools to register.
     * @param LoggerInterface|null $logger Diagnostics sink; defaults to a null logger (the stdio
     *        transport substitutes its STDERR logger when none was given).
     * @param string|null $instructions Optional server instructions sent to the client on initialize.
     * @param array{name: string, version: string, title?: string}|null $serverInfo Identity advertised
     *        on initialize; defaults to this package's name and version.
     * @param bool $validateArguments Check `tools/call` arguments against the tool's input schema
     *        and reject clear mismatches with `-32602` before the tool runs (see {@see ArgumentValidator}).
     */
    public function __construct(
        array $tools = [],
        ?LoggerInterface $logger = null,
        ?string $instructions = null,
        ?array $serverInfo = null,
        bool $validateArguments = true,
    ) {
        foreach ($tools as $tool) {
            $this->registerTool($tool);
        }
        $this->logger = $logger ?? new NullLogger();
        $this->instructions = $instructions;
        $this->serverInfo = $serverInfo ?? Version::getServerInfo();
        $this->validateArguments = $validateArguments;
    }

    /**
     * Register a tool. Registering another tool with the same name replaces the earlier one.
     */
    public function registerTool(McpToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /**
     * @return array<string, McpToolInterface> Registered tools indexed by name.
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Whether a tool with the given name is registered.
     */
    public function hasTool(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * The registered tool with the given name, or null.
     */
    public function getTool(string $name): ?McpToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Replace the diagnostics sink.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * The current diagnostics sink.
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Set (or clear) the instructions sent to clients on initialize.
     */
    public function setInstructions(?string $instructions): void
    {
        $this->instructions = $instructions;
    }

    /**
     * The instructions sent to clients on initialize, or null.
     */
    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    /**
     * Enable or disable argument validation for `tools/call`.
     */
    public function setValidateArguments(bool $validate): void
    {
        $this->validateArguments = $validate;
    }

    /**
     * Whether `tools/call` arguments are validated against the tool's input schema.
     */
    public function isValidatingArguments(): bool
    {
        return $this->validateArguments;
    }

    /**
     * Protocol version agreed during the most recent initialize, or null before any handshake.
     */
    public function getNegotiatedProtocolVersion(): ?string
    {
        return $this->negotiatedProtocolVersion;
    }

    /**
     * Serve over standard input/output until the client closes the pipe (kept for compatibility;
     * equivalent to running a {@see StdioTransport}).
     */
    public function run(): void
    {
        (new StdioTransport($this))->run();
    }

    /**
     * Handle one raw JSON document (a request, a notification, or a batch) end to end.
     *
     * @return string|null Encoded JSON-RPC response (or batch of responses), or null when the input
     *         contained only notifications and nothing must be sent back.
     */
    public function handleJson(string $json): ?string
    {
        try {
            $payload = JsonRpc::decode($json);
        } catch (JsonException $e) {
            $this->logger->warning('Discarding message that is not valid JSON: {error}', ['error' => $e->getMessage()]);

            return JsonRpc::encode(JsonRpc::error(null, JsonRpc::PARSE_ERROR, 'Parse error: ' . $e->getMessage()));
        }

        $response = $this->dispatchPayload($payload);

        return $response === null ? null : JsonRpc::encode($response);
    }

    /**
     * Dispatch a decoded payload that may be a single message or a JSON-RPC batch.
     *
     * @return array<mixed>|null One response, a list of responses for a batch, or null when there is
     *         nothing to send back.
     */
    public function dispatchPayload(mixed $payload): ?array
    {
        if (!JsonRpc::isBatch($payload)) {
            return $this->dispatch($payload);
        }

        $responses = [];
        foreach ($payload as $message) {
            $response = $this->dispatch($message);
            if ($response !== null) {
                $responses[] = $response;
            }
        }

        return $responses === [] ? null : $responses;
    }

    /**
     * Dispatch one decoded JSON-RPC message.
     *
     * Requests (messages with an id) always produce a response. Notifications (no id) never do,
     * even when they fail, exactly as JSON-RPC 2.0 prescribes. Structurally invalid messages get an
     * "Invalid Request" error whose id is null when no usable id was present.
     *
     * @return array<mixed>|null Response envelope, or null for notifications.
     */
    public function dispatch(mixed $message): ?array
    {
        if (!is_array($message) || $message === [] || array_is_list($message)) {
            return JsonRpc::error(null, JsonRpc::INVALID_REQUEST, 'Invalid Request: expected a JSON-RPC request object.');
        }

        $id = $message['id'] ?? null;
        if ($id !== null && !is_int($id) && !is_string($id)) {
            return JsonRpc::error(null, JsonRpc::INVALID_REQUEST, 'Invalid Request: "id" must be a string or an integer.');
        }

        if (array_key_exists('jsonrpc', $message) && $message['jsonrpc'] !== JsonRpc::VERSION) {
            return JsonRpc::error($id, JsonRpc::INVALID_REQUEST, 'Invalid Request: "jsonrpc" must be "2.0".');
        }

        $method = $message['method'] ?? null;
        if (!is_string($method) || $method === '') {
            return JsonRpc::error($id, JsonRpc::INVALID_REQUEST, 'Invalid Request: "method" must be a non-empty string.');
        }

        if (str_starts_with($method, 'notifications/')) {
            $this->logger->debug('Notification received: {method}', ['method' => $method]);

            return null;
        }

        $params = $message['params'] ?? [];
        if (!is_array($params)) {
            return $this->errorFor($id, JsonRpcException::invalidParams('"params" must be an object.'));
        }

        try {
            $result = match ($method) {
                'initialize' => $this->handleInitialize($params),
                'ping' => new stdClass(),
                'tools/list' => $this->handleListTools($params),
                'tools/call' => $this->handleCallTool($params),
                default => throw JsonRpcException::methodNotFound($method),
            };
        } catch (JsonRpcException $e) {
            $this->logger->warning('{method} rejected: {error}', ['method' => $method, 'error' => $e->getMessage()]);

            return $this->errorFor($id, $e);
        } catch (Throwable $e) {
            $this->logger->error('{method} failed unexpectedly: {error}', [
                'method' => $method,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return $id === null ? null : JsonRpc::error($id, JsonRpc::INTERNAL_ERROR, $e->getMessage());
        }

        return $id === null ? null : JsonRpc::result($id, $result);
    }

    /**
     * Error response for a request, or nothing for a notification.
     */
    private function errorFor(int|string|null $id, JsonRpcException $exception): ?array
    {
        if ($id === null) {
            return null;
        }

        return JsonRpc::error($id, $exception->getCode(), $exception->getMessage(), $exception->getData());
    }

    /**
     * `initialize`: negotiate the protocol version and advertise capabilities.
     *
     * @param array<string, mixed> $params
     * @return array{protocolVersion: string, capabilities: array<string, mixed>, serverInfo: array<string, string>, instructions?: string}
     */
    private function handleInitialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;
        $requested = is_string($requested) ? $requested : null;
        $version = ProtocolVersion::negotiate($requested);
        $this->negotiatedProtocolVersion = $version;

        $client = $params['clientInfo'] ?? null;
        $clientName = is_array($client)
            ? trim((string) ($client['name'] ?? 'unknown') . ' ' . (string) ($client['version'] ?? ''))
            : 'unknown';
        $this->logger->info('Client "{client}" initialized: requested protocol {requested}, using {version}.', [
            'client' => $clientName,
            'requested' => $requested ?? 'none',
            'version' => $version,
        ]);

        $result = [
            'protocolVersion' => $version,
            // `tools` must be a JSON object. A bare `[]` encodes as an array, which strict clients
            // reject during initialize validation.
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => $this->serverInfo,
        ];
        if ($this->instructions !== null && $this->instructions !== '') {
            $result['instructions'] = $this->instructions;
        }

        return $result;
    }

    /**
     * `tools/list`: describe every registered tool.
     *
     * A `cursor` parameter is accepted for forward compatibility, but all tools always fit in one
     * page, so no `nextCursor` is returned.
     *
     * @param array<string, mixed> $params
     * @return array{tools: list<array<string, mixed>>}
     */
    private function handleListTools(array $params): array
    {
        $definitions = [];
        foreach ($this->tools as $tool) {
            $definition = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => self::normalizeInputSchema($tool->getInputSchema()),
            ];
            if ($tool instanceof McpToolAnnotationsInterface) {
                $annotations = $tool->getAnnotations();
                $title = $annotations['title'] ?? null;
                if (is_string($title) && $title !== '') {
                    $definition['title'] = $title;
                }
                if ($annotations !== []) {
                    $definition['annotations'] = $annotations;
                }
            }
            if ($tool instanceof McpToolOutputSchemaInterface) {
                $outputSchema = $tool->getOutputSchema();
                if ($outputSchema !== []) {
                    $definition['outputSchema'] = self::normalizeInputSchema($outputSchema);
                }
            }
            $definitions[] = $definition;
        }

        return ['tools' => $definitions];
    }

    /**
     * `tools/call`: run one tool.
     *
     * Bad parameters, unknown tool names and (when enabled) arguments that violate the tool's input
     * schema are protocol errors. Anything thrown by the tool itself becomes an `isError` result
     * carrying the exception message. Every call is logged at info level with its duration and
     * result size, so an operator can see what assistants are doing.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed> MCP tool result.
     */
    private function handleCallTool(array $params): array
    {
        $name = $params['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw JsonRpcException::invalidParams('"name" must be a non-empty string.');
        }

        $arguments = $params['arguments'] ?? [];
        if (!is_array($arguments)) {
            throw JsonRpcException::invalidParams('"arguments" must be an object.');
        }

        $tool = $this->tools[$name] ?? null;
        if ($tool === null) {
            throw JsonRpcException::invalidParams('Unknown tool: ' . $name);
        }

        if ($this->validateArguments) {
            $violations = ArgumentValidator::validate($tool->getInputSchema(), $arguments);
            if ($violations !== []) {
                throw JsonRpcException::invalidParams(
                    'Arguments for tool "' . $name . '" are invalid: ' . implode(' ', $violations),
                    ['tool' => $name, 'violations' => $violations]
                );
            }
        }

        $started = hrtime(true);
        try {
            $result = self::normalizeToolResult($tool->execute($arguments));
        } catch (Throwable $e) {
            $this->logger->error('Tool {tool} failed after {ms} ms: {error}', [
                'tool' => $name,
                'ms' => self::elapsedMs($started),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return ['isError' => true, 'content' => [['type' => 'text', 'text' => $e->getMessage()]]];
        }

        $this->logger->info('Tool {tool} {outcome} in {ms} ms, {bytes} bytes of content.', [
            'tool' => $name,
            'outcome' => !empty($result['isError']) ? 'returned an error' : 'completed',
            'ms' => self::elapsedMs($started),
            'bytes' => self::contentSize($result),
        ]);

        return $result;
    }

    /**
     * Milliseconds elapsed since an {@see hrtime()} mark, with one decimal.
     */
    private static function elapsedMs(int|float $started): float
    {
        return round((hrtime(true) - $started) / 1_000_000, 1);
    }

    /**
     * Approximate size of a tool result: the text of its content blocks (binary blocks count their
     * base64 payload). Cheap, and close enough to what the assistant will ingest.
     *
     * @param array<string, mixed> $result
     */
    private static function contentSize(array $result): int
    {
        $bytes = 0;
        foreach ((array) ($result['content'] ?? []) as $block) {
            if (is_array($block)) {
                foreach (['text', 'data'] as $key) {
                    if (isset($block[$key]) && is_string($block[$key])) {
                        $bytes += strlen($block[$key]);
                    }
                }
            }
        }

        return $bytes;
    }

    /**
     * Make sure a schema encodes as a JSON Schema object: `type` present and `properties` never an
     * empty JSON array (strict clients reject `"properties": []`).
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private static function normalizeInputSchema(array $schema): array
    {
        if (!isset($schema['type'])) {
            $schema = ['type' => 'object'] + $schema;
        }
        if (!isset($schema['properties']) || $schema['properties'] === []) {
            $schema['properties'] = new stdClass();
        }

        return $schema;
    }

    /**
     * Coerce whatever a tool returned into a valid MCP tool result.
     *
     * Tools that return a bare array (no `content`) get it wrapped as JSON text, so an assistant
     * still sees the data instead of an empty result.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private static function normalizeToolResult(array $result): array
    {
        if (!array_key_exists('content', $result)) {
            if (array_key_exists('structuredContent', $result)) {
                $result['content'] = [['type' => 'text', 'text' => JsonRpc::encode($result['structuredContent'])]];

                return $result;
            }

            return ['content' => [['type' => 'text', 'text' => JsonRpc::encode($result)]]];
        }

        $content = $result['content'];
        if (!is_array($content)) {
            $result['content'] = [['type' => 'text', 'text' => is_scalar($content) ? (string) $content : JsonRpc::encode($content)]];
        } elseif ($content !== [] && !array_is_list($content)) {
            $result['content'] = [$content];
        }

        if (array_key_exists('isError', $result) && !is_bool($result['isError'])) {
            $result['isError'] = (bool) $result['isError'];
        }

        return $result;
    }
}
