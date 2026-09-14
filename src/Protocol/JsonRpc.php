<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Protocol;

use JsonException;

use function array_is_list;
use function is_array;
use function json_decode;
use function json_encode;

/**
 * JSON-RPC 2.0 helpers shared by every transport.
 *
 * Builds response and error envelopes and encodes/decodes messages with one consistent set of
 * flags, so stdio and HTTP produce identical payloads for the same request.
 *
 * @see https://www.jsonrpc.org/specification
 */
final class JsonRpc
{
    public const VERSION = '2.0';

    /** Invalid JSON was received by the server. */
    public const PARSE_ERROR = -32700;
    /** The JSON sent is not a valid Request object. */
    public const INVALID_REQUEST = -32600;
    /** The method does not exist / is not available. */
    public const METHOD_NOT_FOUND = -32601;
    /** Invalid method parameter(s). */
    public const INVALID_PARAMS = -32602;
    /** Internal JSON-RPC error. */
    public const INTERNAL_ERROR = -32603;

    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR;

    private const MAX_DEPTH = 512;

    /**
     * Build a success response envelope.
     *
     * @param int|string $id Request id being answered.
     * @param mixed $result Result payload. Use `new \stdClass()` for an empty JSON object.
     * @return array{jsonrpc: string, id: int|string, result: mixed}
     */
    public static function result(int|string $id, mixed $result): array
    {
        return ['jsonrpc' => self::VERSION, 'id' => $id, 'result' => $result];
    }

    /**
     * Build an error response envelope.
     *
     * @param int|string|null $id Request id, or null when it could not be determined (parse errors).
     * @param int $code One of the error code constants of this class.
     * @param string $message Human readable error message.
     * @param mixed $data Optional additional error data.
     * @return array{jsonrpc: string, id: int|string|null, error: array{code: int, message: string, data?: mixed}}
     */
    public static function error(int|string|null $id, int $code, string $message, mixed $data = null): array
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return ['jsonrpc' => self::VERSION, 'id' => $id, 'error' => $error];
    }

    /**
     * Encode a message (or batch of messages) to a single-line JSON string.
     *
     * Invalid UTF-8 (for example binary content returned by a tool) is substituted instead of
     * failing the whole response.
     *
     * @throws JsonException When the value cannot be represented as JSON at all.
     */
    public static function encode(mixed $message): string
    {
        return (string) json_encode($message, self::ENCODE_FLAGS, self::MAX_DEPTH);
    }

    /**
     * Decode a JSON document into PHP arrays.
     *
     * @throws JsonException When the document is not valid JSON.
     */
    public static function decode(string $json): mixed
    {
        return json_decode($json, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
    }

    /**
     * Whether a decoded payload is a JSON-RPC batch: a non-empty JSON array of messages.
     */
    public static function isBatch(mixed $payload): bool
    {
        return is_array($payload) && $payload !== [] && array_is_list($payload);
    }
}
