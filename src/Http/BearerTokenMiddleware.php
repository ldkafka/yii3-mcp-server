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
use function array_filter;
use function array_map;
use function array_values;
use function hash_equals;
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
 * `OPTIONS` requests pass through unauthenticated: browsers never attach `Authorization` to a CORS
 * preflight, and the endpoint answers a preflight with an empty `204` that reveals nothing.
 */
final class BearerTokenMiddleware implements MiddlewareInterface
{
    /** Request attribute set to `true` once a token has been verified. */
    public const ATTRIBUTE_AUTHENTICATED = 'mcp.authenticated';

    /** @var list<string> */
    private array $tokens;

    /**
     * @param string|list<string> $tokens One or more accepted tokens.
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
        $tokens = array_values(array_filter(
            array_map(static fn (mixed $token): string => (string) $token, (array) $tokens),
            static fn (string $token): bool => $token !== ''
        ));
        if ($tokens === []) {
            throw new InvalidArgumentException('BearerTokenMiddleware requires at least one non-empty token.');
        }
        $this->tokens = $tokens;
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
            foreach ($this->tokens as $token) {
                if (hash_equals($token, $presented)) {
                    return $handler->handle($request->withAttribute(self::ATTRIBUTE_AUTHENTICATED, true));
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
