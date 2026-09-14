<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Protocol;

use RuntimeException;
use Throwable;

/**
 * A protocol-level failure that maps directly onto a JSON-RPC error response.
 *
 * Throw this from request handlers when the request itself is at fault (unknown method, bad
 * params). Failures inside a tool are not protocol errors: they are reported as `isError` tool
 * results so the AI assistant can read the message and recover.
 */
final class JsonRpcException extends RuntimeException
{
    /**
     * @param string $message Error message sent to the client.
     * @param int $code JSON-RPC error code, see the constants on {@see JsonRpc}.
     * @param mixed $data Optional structured data attached to the error.
     */
    public function __construct(
        string $message,
        int $code = JsonRpc::INTERNAL_ERROR,
        private mixed $data = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Additional error data, or null.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Error for a message whose shape is not a valid JSON-RPC request.
     */
    public static function invalidRequest(string $message): self
    {
        return new self('Invalid Request: ' . $message, JsonRpc::INVALID_REQUEST);
    }

    /**
     * Error for an unknown or unsupported method.
     */
    public static function methodNotFound(string $method): self
    {
        return new self('Method not found: ' . $method, JsonRpc::METHOD_NOT_FOUND);
    }

    /**
     * Error for invalid method parameters.
     */
    public static function invalidParams(string $message, mixed $data = null): self
    {
        return new self('Invalid params: ' . $message, JsonRpc::INVALID_PARAMS, $data);
    }
}
