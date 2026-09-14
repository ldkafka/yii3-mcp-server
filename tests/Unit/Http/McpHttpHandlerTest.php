<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Unit\Http;

use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequestFactory;
use HttpSoft\Message\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiMcp\McpServer\Http\McpHttpHandler;
use YiiMcp\McpServer\McpServer;
use YiiMcp\McpServer\Protocol\JsonRpc;
use YiiMcp\McpServer\Protocol\ProtocolVersion;
use YiiMcp\McpServer\Tests\Support\EchoTool;

use function json_decode;

final class McpHttpHandlerTest extends TestCase
{
    private const PING = '{"jsonrpc":"2.0","id":1,"method":"ping"}';

    /**
     * @param list<string> $allowedOrigins
     */
    private function handler(array $allowedOrigins = []): McpHttpHandler
    {
        return new McpHttpHandler(new McpServer([new EchoTool()]), new ResponseFactory(), new StreamFactory(), $allowedOrigins);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method, string $body = '', array $headers = []): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'https://example.test/mcp');
        if ($body !== '' && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== '') {
            $request = $request->withBody((new StreamFactory())->createStream($body));
        }

        return $request;
    }

    /**
     * @return array<mixed>
     */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }

    public function testGetIsMethodNotAllowedBecauseNoEventStreamIsOffered(): void
    {
        $response = $this->handler()->handle($this->request('GET'));

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('POST, OPTIONS', $response->getHeaderLine('Allow'));
        self::assertSame(JsonRpc::INVALID_REQUEST, $this->json($response)['error']['code']);
    }

    public function testDeleteIsMethodNotAllowedBecauseThereAreNoSessions(): void
    {
        self::assertSame(405, $this->handler()->handle($this->request('DELETE'))->getStatusCode());
    }

    public function testPostRequestReturnsJsonRpcResponse(): void
    {
        $response = $this->handler()->handle($this->request('POST', self::PING));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('{"jsonrpc":"2.0","id":1,"result":{}}', (string) $response->getBody());
    }

    public function testPostNotificationIsAcceptedWithoutBody(): void
    {
        $response = $this->handler()->handle($this->request('POST', '{"jsonrpc":"2.0","method":"notifications/initialized"}'));

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testInvalidJsonIsBadRequestWithParseError(): void
    {
        $response = $this->handler()->handle($this->request('POST', '{oops'));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(JsonRpc::PARSE_ERROR, $this->json($response)['error']['code']);
    }

    public function testEmptyBodyIsBadRequest(): void
    {
        $response = $this->handler()->handle($this->request('POST', '', ['Content-Type' => 'application/json']));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testNonJsonContentTypeIsUnsupportedMediaType(): void
    {
        $response = $this->handler()->handle($this->request('POST', self::PING, ['Content-Type' => 'text/plain']));

        self::assertSame(415, $response->getStatusCode());
    }

    public function testJsonContentTypeWithCharsetIsAccepted(): void
    {
        $response = $this->handler()->handle($this->request('POST', self::PING, ['Content-Type' => 'application/json; charset=utf-8']));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestsWithoutOriginAlwaysPass(): void
    {
        self::assertSame(200, $this->handler(['https://app.test'])->handle($this->request('POST', self::PING))->getStatusCode());
    }

    public function testDisallowedOriginIsForbiddenBeforeAnythingElse(): void
    {
        $response = $this->handler(['https://app.test'])->handle($this->request('POST', self::PING, ['Origin' => 'https://evil.test']));

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testEmptyAllowListRejectsEveryBrowserOrigin(): void
    {
        $response = $this->handler()->handle($this->request('POST', self::PING, ['Origin' => 'https://app.test']));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAllowedOriginGetsCorsHeaders(): void
    {
        $response = $this->handler(['https://App.test/'])->handle($this->request('POST', self::PING, ['Origin' => 'https://app.test']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('https://app.test', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('Origin', $response->getHeaderLine('Vary'));
    }

    public function testWildcardAllowsAnyOrigin(): void
    {
        $response = $this->handler(['*'])->handle($this->request('POST', self::PING, ['Origin' => 'https://anything.test']));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testOptionsPreflightForAllowedOrigin(): void
    {
        $response = $this->handler(['https://app.test'])->handle($this->request('OPTIONS', '', ['Origin' => 'https://app.test']));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('POST, OPTIONS', $response->getHeaderLine('Allow'));
        self::assertStringContainsString('Authorization', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function testUnsupportedProtocolVersionHeaderIsBadRequest(): void
    {
        $response = $this->handler()->handle($this->request('POST', self::PING, [McpHttpHandler::HEADER_PROTOCOL_VERSION => '1999-01-01']));

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Unsupported protocol version', $this->json($response)['error']['message']);
    }

    public function testSupportedProtocolVersionHeaderIsAccepted(): void
    {
        $response = $this->handler()->handle($this->request('POST', self::PING, [McpHttpHandler::HEADER_PROTOCOL_VERSION => ProtocolVersion::LATEST]));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testBatchOverHttp(): void
    {
        $body = '[{"jsonrpc":"2.0","id":1,"method":"ping"},{"jsonrpc":"2.0","id":2,"method":"tools/list"}]';
        $response = $this->handler()->handle($this->request('POST', $body));
        $decoded = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $decoded);
        self::assertSame('echo', $decoded[1]['result']['tools'][0]['name']);
    }

    public function testFullHandshakeSequence(): void
    {
        $handler = $this->handler();

        $init = $handler->handle($this->request('POST', '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"t","version":"1"}}}'));
        self::assertSame('2025-06-18', $this->json($init)['result']['protocolVersion']);

        $initialized = $handler->handle($this->request('POST', '{"jsonrpc":"2.0","method":"notifications/initialized"}', [McpHttpHandler::HEADER_PROTOCOL_VERSION => '2025-06-18']));
        self::assertSame(202, $initialized->getStatusCode());

        $call = $handler->handle($this->request('POST', '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"echo","arguments":{"message":"hi"}}}', [McpHttpHandler::HEADER_PROTOCOL_VERSION => '2025-06-18']));
        self::assertSame('{"message":"hi"}', $this->json($call)['result']['content'][0]['text']);
    }

    public function testMiddlewareAndInvokableEntryPointsDelegateToHandle(): void
    {
        $handler = $this->handler();
        $request = $this->request('POST', self::PING);
        $next = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('next handler must not be called');
            }
        };

        self::assertSame(200, $handler->process($request, $next)->getStatusCode());
        self::assertSame(200, $handler($request)->getStatusCode());
    }
}
