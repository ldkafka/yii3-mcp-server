<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Http;

use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YiiMcp\McpServer\McpServer;
use YiiMcp\McpServer\Protocol\JsonRpc;
use YiiMcp\McpServer\Protocol\ProtocolVersion;

use function array_map;
use function array_values;
use function implode;
use function in_array;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * MCP "Streamable HTTP" transport as a PSR-15 request handler (and middleware).
 *
 * One endpoint, stateless:
 * - `POST` with a JSON-RPC request (or batch) returns `200` with the JSON-RPC response;
 * - `POST` carrying only notifications returns `202` with an empty body;
 * - `GET` returns `405`: this server does not open a server-initiated event stream, which the
 *   specification explicitly allows; `DELETE` returns `405` because no sessions are issued.
 *
 * The handler validates the `Origin` header against an allow-list (DNS rebinding protection),
 * checks the `MCP-Protocol-Version` header and requires a JSON content type. Authentication is
 * deliberately separate: put {@see BearerTokenMiddleware} (or your own) in front of it.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/basic/transports#streamable-http
 */
final class McpHttpHandler implements RequestHandlerInterface, MiddlewareInterface
{
    public const HEADER_PROTOCOL_VERSION = 'MCP-Protocol-Version';
    public const CONTENT_TYPE_JSON = 'application/json';

    /** @var list<string> */
    private array $allowedOrigins;

    private LoggerInterface $logger;

    /**
     * @param McpServer $server Server that dispatches the decoded messages.
     * @param ResponseFactoryInterface $responseFactory PSR-17 factory used to build responses.
     * @param StreamFactoryInterface $streamFactory PSR-17 factory used to build response bodies.
     * @param list<string> $allowedOrigins Origins (`scheme://host[:port]`) allowed to call the
     *        endpoint from a browser context; `*` allows any. Requests without an `Origin` header
     *        (CLI and desktop clients) are always accepted.
     * @param LoggerInterface|null $logger Diagnostics sink; defaults to a null logger.
     */
    public function __construct(
        private McpServer $server,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        array $allowedOrigins = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->allowedOrigins = array_values(array_map(
            static fn (string $origin): string => self::normalizeOrigin($origin),
            $allowedOrigins
        ));
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * PSR-15 middleware entry point. The pipeline ends here: `$handler` is never called.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->handle($request);
    }

    /**
     * Callable entry point for routers that expect invokable actions.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle($request);
    }

    /**
     * Serve one HTTP request carrying zero or more JSON-RPC messages.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        if (!$this->isOriginAllowed($origin)) {
            $this->logger->warning('MCP HTTP request rejected: origin not allowed.', ['origin' => $origin]);

            return $this->errorResponse(403, JsonRpc::INVALID_REQUEST, 'Origin not allowed.');
        }

        $method = strtoupper($request->getMethod());
        if ($method === 'OPTIONS') {
            $response = $this->responseFactory->createResponse(204)->withHeader('Allow', 'POST, OPTIONS');

            return $this->withCors($response, $origin);
        }
        if ($method !== 'POST') {
            $response = $this->errorResponse(405, JsonRpc::INVALID_REQUEST, 'Method not allowed: use POST.')
                ->withHeader('Allow', 'POST, OPTIONS');

            return $this->withCors($response, $origin);
        }

        $protocolVersion = trim($request->getHeaderLine(self::HEADER_PROTOCOL_VERSION));
        if ($protocolVersion !== '' && !ProtocolVersion::isSupported($protocolVersion)) {
            $message = sprintf(
                'Unsupported protocol version "%s". Supported: %s.',
                $protocolVersion,
                implode(', ', ProtocolVersion::SUPPORTED)
            );

            return $this->withCors($this->errorResponse(400, JsonRpc::INVALID_REQUEST, $message), $origin);
        }

        $contentType = strtolower(trim($request->getHeaderLine('Content-Type')));
        if ($contentType !== '' && !str_starts_with($contentType, self::CONTENT_TYPE_JSON)) {
            $response = $this->errorResponse(415, JsonRpc::INVALID_REQUEST, 'Unsupported media type: send application/json.');

            return $this->withCors($response, $origin);
        }

        try {
            $payload = JsonRpc::decode((string) $request->getBody());
        } catch (JsonException $e) {
            $response = $this->errorResponse(400, JsonRpc::PARSE_ERROR, 'Parse error: ' . $e->getMessage());

            return $this->withCors($response, $origin);
        }

        $result = $this->server->dispatchPayload($payload);
        if ($result === null) {
            // Notifications only: acknowledged, nothing to return.
            return $this->withCors($this->responseFactory->createResponse(202), $origin);
        }

        return $this->withCors($this->jsonResponse(200, $result), $origin);
    }

    /**
     * Requests without an Origin header are non-browser clients and always pass.
     */
    private function isOriginAllowed(string $origin): bool
    {
        if ($origin === '') {
            return true;
        }
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return in_array(self::normalizeOrigin($origin), $this->allowedOrigins, true);
    }

    private static function normalizeOrigin(string $origin): string
    {
        return rtrim(strtolower(trim($origin)), '/');
    }

    /**
     * Add CORS headers when the (already validated) request came from a browser origin.
     */
    private function withCors(ResponseInterface $response, string $origin): ResponseInterface
    {
        if ($origin === '') {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, ' . self::HEADER_PROTOCOL_VERSION)
            ->withHeader('Vary', 'Origin');
    }

    private function errorResponse(int $status, int $code, string $message): ResponseInterface
    {
        return $this->jsonResponse($status, JsonRpc::error(null, $code, $message));
    }

    /**
     * @param array<mixed> $payload JSON-RPC response or batch of responses.
     */
    private function jsonResponse(int $status, array $payload): ResponseInterface
    {
        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', self::CONTENT_TYPE_JSON)
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streamFactory->createStream(JsonRpc::encode($payload)));
    }
}
