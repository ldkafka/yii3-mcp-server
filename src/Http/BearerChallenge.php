<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Http;

use function addcslashes;
use function implode;
use function preg_replace;

/**
 * `WWW-Authenticate` values for an MCP server acting as an OAuth 2.1 resource server.
 *
 * Framework-neutral: the methods return the header value as a string, for any response object.
 * Validating the token is the application's job; this class only words the challenge, as the MCP
 * authorization specification (2025-11-25), RFC 6750 section 3 and RFC 9728 section 5.1 describe:
 * - `401` when the token is missing ({@see unauthorized()}), or invalid, expired, revoked or issued
 *   for another resource ({@see unauthorized()} with `$invalidToken`);
 * - `403` when the token lacks a scope the request needs ({@see insufficientScope()}).
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization
 *
 * @example
 * ```php
 * $metadata = 'https://mcp.example.com/.well-known/oauth-protected-resource/mcp';
 * $response->withStatus(401)->withHeader('WWW-Authenticate', BearerChallenge::unauthorized($metadata, ['files:read']));
 * ```
 */
final class BearerChallenge
{
    public const ERROR_INVALID_TOKEN = 'invalid_token';
    public const ERROR_INSUFFICIENT_SCOPE = 'insufficient_scope';

    /**
     * Challenge for a `401 Unauthorized` response.
     *
     * @param string $resourceMetadataUrl URL of the protected resource metadata document (RFC 9728).
     * @param list<string> $scopes Scopes the client should request; the `scope` parameter is left
     *        out when empty.
     * @param bool $invalidToken A token was presented but failed validation: adds
     *        `error="invalid_token"`. Leave false when the request carried no token, which RFC 6750
     *        answers without an error code.
     * @param string|null $errorDescription Optional human-readable `error_description`.
     */
    public static function unauthorized(
        string $resourceMetadataUrl,
        array $scopes = [],
        bool $invalidToken = false,
        ?string $errorDescription = null,
    ): string {
        return self::build([
            'resource_metadata' => $resourceMetadataUrl,
            'scope' => implode(' ', $scopes),
            'error' => $invalidToken ? self::ERROR_INVALID_TOKEN : '',
            'error_description' => (string) $errorDescription,
        ]);
    }

    /**
     * Challenge for a `403 Forbidden` response to a valid token that lacks a needed scope.
     *
     * @param string $resourceMetadataUrl URL of the protected resource metadata document (RFC 9728).
     * @param list<string> $scopes Scopes the request needs; include those already granted that
     *        are still required, so the client does not lose them when it asks again.
     * @param string|null $errorDescription Optional human-readable `error_description`.
     */
    public static function insufficientScope(
        string $resourceMetadataUrl,
        array $scopes,
        ?string $errorDescription = null,
    ): string {
        return self::build([
            'error' => self::ERROR_INSUFFICIENT_SCOPE,
            'scope' => implode(' ', $scopes),
            'resource_metadata' => $resourceMetadataUrl,
            'error_description' => (string) $errorDescription,
        ]);
    }

    /**
     * `Bearer` followed by the non-empty parameters as quoted strings. Characters RFC 6750 does not
     * allow in these values (controls, non-ASCII) are dropped, so the result is always one safe
     * header line.
     *
     * @param array<string, string> $params Parameter values by name, in output order.
     */
    private static function build(array $params): string
    {
        $parts = [];
        foreach ($params as $name => $value) {
            $value = (string) preg_replace('/[^\x20-\x7E]/', '', $value);
            if ($value !== '') {
                $parts[] = $name . '="' . addcslashes($value, '"\\') . '"';
            }
        }

        return 'Bearer ' . implode(', ', $parts);
    }
}
