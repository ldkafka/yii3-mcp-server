<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiMcp\McpServer\Protocol\JsonRpc;

use function addcslashes;
use function hash_equals;
use function is_int;
use function preg_match;
use function sprintf;
use function strtoupper;

/**
 * Static bearer-token authentication for the HTTP transport.
 *
 * Compares the `Authorization: Bearer <token>` header against one or more configured tokens in
 * constant time and answers `401` with a `WWW-Authenticate` challenge otherwise. Accepting several
 * tokens at once lets you rotate a token without downtime. Keep tokens out of version control
 * (environment variables, Docker secrets, a local params file) and always serve over TLS.
 *
 * Tokens may be grouped under a caller name (`name => token` or `name => list of tokens`; integer
 * keys are plain tokens). The matched name is set as the `mcp.caller` request attribute; {@see McpHttpHandler} passes it to the server, which can
 * limit each caller to a list of tools (see the `scopes` argument of {@see \YiiMcp\McpServer\McpServer}).
 *
 * `OPTIONS` requests pass through unauthenticated: browsers never attach `Authorization` to a CORS
 * preflight, and the endpoint answers a preflight with an empty `204` that reveals nothing.
 */
final class BearerTokenMiddleware implements MiddlewareInterface
{
    /** Request attribute set to `true` once a token has been verified. */
    public const ATTRIBUTE_AUTHENTICATED = 'mcp.authenticated';

    /** Request attribute set to the matched token's caller name (null for a token without one). */
    public const ATTRIBUTE_CALLER = 'mcp.caller';

    /** @var list<array{0: string, 1: string|null}> Pairs of accepted token and caller name. */
    private array $tokens = [];

    /**
     * @param string|array<array-key, string|list<string>> $tokens One or more accepted tokens: a
     *        list of plain tokens, `caller name => token`, `caller name => list of tokens` (rotation
     *        without downtime), or a mix (integer keys are plain tokens). Empty tokens are skipped.
     * @param ResponseFactoryInterface $responseFactory PSR-17 factory used to build the 401 response.
     * @param StreamFactoryInterface $streamFactory PSR-17 factory used to build the 401 body.
     * @param string $realm Realm reported in the `WWW-Authenticate` challenge.
     * @throws InvalidArgumentException When no non-empty token is configured; failing at boot
     *         instead of silently accepting nothing (or everything) at request time.
     */
    public function __construct(
        string|array $tokens,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private string $realm = 'MCP',
    ) {
        foreach ((array) $tokens as $key => $value) {
            foreach ((array) $value as $token) {
                $token = (string) $token;
                if ($token !== '') {
                    $this->tokens[] = [$token, is_int($key) ? null : $key];
                }
            }
        }
        if ($this->tokens === []) {
            throw new InvalidArgumentException('BearerTokenMiddleware requires at least one non-empty token.');
        }
    }

    /**
     * Let the request through when it carries a valid token, otherwise challenge with 401.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $handler->handle($request);
        }

        $presented = self::extractToken($request->getHeaderLine('Authorization'));
        if ($presented !== null) {
            foreach ($this->tokens as [$token, $caller]) {
                if (hash_equals($token, $presented)) {
                    return $handler->handle($request
                        ->withAttribute(self::ATTRIBUTE_AUTHENTICATED, true)
                        ->withAttribute(self::ATTRIBUTE_CALLER, $caller));
                }
            }
        }

        $body = JsonRpc::encode(JsonRpc::error(null, JsonRpc::INVALID_REQUEST, 'Unauthorized.'));

        return $this->responseFactory->createResponse(401)
            ->withHeader('WWW-Authenticate', sprintf('Bearer realm="%s"', addcslashes($this->realm, '"\\')))
            ->withHeader('Content-Type', McpHttpHandler::CONTENT_TYPE_JSON)
            ->withBody($this->streamFactory->createStream($body));
    }

    /**
     * Extract the credential from an `Authorization` header, or null when it is not a bearer token.
     */
    private static function extractToken(string $header): ?string
    {
        return preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches) === 1 ? $matches[1] : null;
    }
}
