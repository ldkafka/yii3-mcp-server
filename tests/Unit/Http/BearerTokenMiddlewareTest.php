<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Unit\Http;

use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequestFactory;
use HttpSoft\Message\StreamFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiMcp\McpServer\Http\BearerTokenMiddleware;

final class BearerTokenMiddlewareTest extends TestCase
{
    private ?ServerRequestInterface $seen = null;

    private function next(): RequestHandlerInterface
    {
        $test = $this;

        return new class ($test) implements RequestHandlerInterface {
            public function __construct(private BearerTokenMiddlewareTest $test)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->test->record($request);

                return (new ResponseFactory())->createResponse(200);
            }
        };
    }

    public function record(ServerRequestInterface $request): void
    {
        $this->seen = $request;
    }

    /**
     * @param string|list<string> $tokens
     */
    private function middleware(string|array $tokens = 'secret'): BearerTokenMiddleware
    {
        return new BearerTokenMiddleware($tokens, new ResponseFactory(), new StreamFactory());
    }

    private function request(?string $authorization): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.test/mcp');

        return $authorization === null ? $request : $request->withHeader('Authorization', $authorization);
    }

    public function testMissingHeaderIsUnauthorizedWithChallenge(): void
    {
        $response = $this->middleware()->process($this->request(null), $this->next());

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer realm="MCP"', $response->getHeaderLine('WWW-Authenticate'));
        self::assertStringContainsString('Unauthorized', (string) $response->getBody());
        self::assertNull($this->seen);
    }

    public function testWrongTokenAndWrongSchemeAreUnauthorized(): void
    {
        self::assertSame(401, $this->middleware()->process($this->request('Bearer nope'), $this->next())->getStatusCode());
        self::assertSame(401, $this->middleware()->process($this->request('Basic c2VjcmV0'), $this->next())->getStatusCode());
        self::assertSame(401, $this->middleware()->process($this->request('Bearer secret extra'), $this->next())->getStatusCode());
        self::assertNull($this->seen);
    }

    public function testValidTokenPassesThroughAndMarksTheRequest(): void
    {
        $response = $this->middleware()->process($this->request('Bearer secret'), $this->next());

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($this->seen);
        self::assertTrue($this->seen->getAttribute(BearerTokenMiddleware::ATTRIBUTE_AUTHENTICATED));
    }

    public function testSchemeIsCaseInsensitiveAndWhitespaceTolerant(): void
    {
        self::assertSame(200, $this->middleware()->process($this->request('  bearer   secret '), $this->next())->getStatusCode());
    }

    public function testAnyConfiguredTokenIsAccepted(): void
    {
        $middleware = $this->middleware(['old', 'new']);

        self::assertSame(200, $middleware->process($this->request('Bearer old'), $this->next())->getStatusCode());
        self::assertSame(200, $middleware->process($this->request('Bearer new'), $this->next())->getStatusCode());
        self::assertSame(401, $middleware->process($this->request('Bearer other'), $this->next())->getStatusCode());
    }

    public function testCustomRealmIsEscaped(): void
    {
        $middleware = new BearerTokenMiddleware('t', new ResponseFactory(), new StreamFactory(), 'My "App"');
        $response = $middleware->process($this->request(null), $this->next());

        self::assertSame('Bearer realm="My \"App\""', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testCorsPreflightPassesWithoutAToken(): void
    {
        $preflight = (new ServerRequestFactory())->createServerRequest('OPTIONS', 'https://example.test/mcp')
            ->withHeader('Origin', 'https://app.test')
            ->withHeader('Access-Control-Request-Method', 'POST');

        $response = $this->middleware()->process($preflight, $this->next());

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($this->seen);
        self::assertNull($this->seen->getAttribute(BearerTokenMiddleware::ATTRIBUTE_AUTHENTICATED), 'a preflight is not authenticated');
    }

    public function testEmptyTokenListIsRejectedAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->middleware(['', '']);
    }
}
