# HTTP Transport

**Serve your MCP tools over HTTPS instead of a local process**

Since 1.1.0 the package speaks MCP *Streamable HTTP* in addition to stdio. The same `McpServer`
and the same tools are exposed through a PSR-15 request handler, so a remote AI assistant
(Claude Code, VS Code, a cloud agent, a colleague's machine) can use them without SSH access,
Docker or a PHP installation on the client side.

---

## Table of Contents

1. [How it works](#how-it-works)
2. [Wiring in a Yii3 application](#wiring-in-a-yii3-application)
3. [Other PSR-15 stacks](#other-psr-15-stacks)
4. [Reverse proxy](#reverse-proxy)
5. [Testing the endpoint](#testing-the-endpoint)
6. [Client configuration](#client-configuration)
7. [Security checklist](#security-checklist)
8. [Protocol notes](#protocol-notes)

---

## How it works

```
AI assistant  ──POST /mcp (JSON-RPC)──▶  BearerTokenMiddleware  ──▶  McpHttpHandler  ──▶  McpServer  ──▶  your tools
              ◀──200 application/json──                                                       (same core as stdio)
```

`YiiMcp\McpServer\Http\McpHttpHandler` implements both `Psr\Http\Server\RequestHandlerInterface`
and `MiddlewareInterface`, so it fits any PSR-15 pipeline. It is **stateless**: every request is
complete on its own, which makes it a natural fit for PHP-FPM, FrankenPHP, RoadRunner or the
built-in server alike.

| Request | Response |
|---------|----------|
| `POST` with a JSON-RPC request or batch | `200`, `application/json`, the JSON-RPC response(s) |
| `POST` with notifications only | `202`, empty body |
| `POST` that is not valid JSON | `400`, JSON-RPC parse error |
| `POST` with a non-JSON `Content-Type` | `415` |
| `POST` with an unsupported `MCP-Protocol-Version` header | `400` |
| Any request from a browser `Origin` that is not allow-listed | `403` |
| `GET`, `DELETE`, anything else | `405` with `Allow: POST, OPTIONS` |
| `OPTIONS` (CORS preflight) | `204`; passes `BearerTokenMiddleware` unauthenticated, because browsers never send `Authorization` on a preflight |

`GET` returning `405` is explicitly permitted by the specification for servers that do not push
server-initiated messages, and no `Mcp-Session-Id` is issued because nothing needs a session.

---

## Wiring in a Yii3 application

The steps below assume the `yiisoft/app` layout. Adapt paths for custom applications.

### 1. Define the services

Copy `vendor/ldkafka/yii3-mcp-server/config/di/mcp-http-template.php` to
`config/common/di/mcp-http.php`. It defines the handler and the authentication middleware and
reads tokens and origins from `$params`:

```php
use Psr\Log\LoggerInterface;
use YiiMcp\McpServer\Http\BearerTokenMiddleware;
use YiiMcp\McpServer\Http\McpHttpHandler;
use YiiMcp\McpServer\McpServer;
use Yiisoft\Definitions\Reference;

return [
    McpHttpHandler::class => [
        '__construct()' => [
            'server' => Reference::to(McpServer::class),
            'allowedOrigins' => $params['mcp']['http']['allowedOrigins'] ?? [],
            'logger' => Reference::to(LoggerInterface::class),
        ],
    ],
    BearerTokenMiddleware::class => [
        '__construct()' => [
            'tokens' => $params['mcp']['http']['tokens'] ?? [],
        ],
    ],
];
```

`McpServer` itself comes from your existing `config/common/di/mcp.php` (the one that registers your
tools), so stdio and HTTP share exactly the same tool set. The PSR-17 factories are autowired;
`yiisoft/app` binds them to `httpsoft/http-message` in `config/web/di/psr17.php`.

### 2. Keep the secrets out of git

`config/environments/prod/params.local.php` (gitignored):

```php
return [
    'mcp' => [
        'http' => [
            // Accept the old and the new token during a rotation, then drop the old one.
            'tokens' => array_filter([getenv('MCP_TOKEN') ?: '', getenv('MCP_TOKEN_PREVIOUS') ?: '']),
            // Only needed for browser-based clients. CLI/desktop clients send no Origin header.
            'allowedOrigins' => [],
        ],
    ],
];
```

Generate a token with `openssl rand -hex 32` and inject it through the environment or a Docker
secret. `BearerTokenMiddleware` throws at construction when the list is empty, so a missing secret
fails the deployment loudly instead of exposing an open endpoint.

### 3. Route the endpoint

`config/common/routes.php`:

```php
use YiiMcp\McpServer\Http\BearerTokenMiddleware;
use YiiMcp\McpServer\Http\McpHttpHandler;
use Yiisoft\Router\Route;

return [
    // ... your web routes ...

    Route::methods(['POST', 'OPTIONS'], '/mcp')
        ->middleware(BearerTokenMiddleware::class)
        ->action(McpHttpHandler::class)
        ->name('mcp'),
];
```

In production mode the Yii3 template caches the FastRoute dispatch data (for example in
`runtime/cache/ro/routes-cache.bin`). After adding the route, clear `runtime/cache` or the endpoint
keeps answering `404` from the stale cache.

### 4. Take CSRF and session middleware off this route

The default `yiisoft/app` template runs `SessionMiddleware` and `CsrfTokenMiddleware` for **every**
request in `config/web/di/application.php`. A bare `POST /mcp` carries no CSRF token and would be
rejected. Move those two middleware from the global dispatcher into the route group that serves
your HTML pages, and leave the `/mcp` route outside that group:

```php
// config/web/di/application.php - global stack without session/CSRF
'withMiddlewares()' => [[
    ErrorCatcher::class,
    FormatDataResponse::class,
    RequestCatcherMiddleware::class,
    Router::class,
]],

// config/common/routes.php - session/CSRF only where HTML forms live
Group::create()
    ->middleware(SessionMiddleware::class)
    ->middleware(CsrfTokenMiddleware::class)
    ->routes(
        Route::get('/')->action(HomePage\Action::class)->name('home'),
        // ...
    ),
Route::methods(['POST', 'OPTIONS'], '/mcp')->middleware(BearerTokenMiddleware::class)->action(McpHttpHandler::class)->name('mcp'),
```

### 5. Set `APP_ENV` for the web entry point

`yii` (the console script) defaults `APP_ENV`; `public/index.php` does not. Set `APP_ENV=prod` in the
environment of the process that serves the endpoint (PHP-FPM pool, Docker service, systemd unit).

---

## Other PSR-15 stacks

Anything that can build a `ServerRequestInterface` and emit a `ResponseInterface` works:

```php
$server = new McpServer([new MyTool()]);
$handler = new McpHttpHandler($server, $responseFactory, $streamFactory, allowedOrigins: []);
$auth = new BearerTokenMiddleware(getenv('MCP_TOKEN'), $responseFactory, $streamFactory);

$response = $auth->process($request, $handler);  // $request from your PSR-7 library
// emit $response with your framework's emitter
```

Slim, Mezzano, Laminas, Spiral and RoadRunner all accept `McpHttpHandler` as a route handler and
`BearerTokenMiddleware` as route middleware without adapters.

---

## Reverse proxy

Terminate TLS in front of PHP and forward only the MCP path. nginx example for a PHP-FPM upstream:

```nginx
limit_req_zone $binary_remote_addr zone=mcp:1m rate=30r/m;

location = /mcp {
    limit_req zone=mcp burst=20 nodelay;
    # allow 203.0.113.10;   # optional IP allow-list
    # deny  all;

    client_max_body_size 1m;
    fastcgi_pass         app-fpm:9000;
    fastcgi_read_timeout 60s;
    include              fastcgi_params;
    fastcgi_param        SCRIPT_FILENAME /app/public/index.php;
    fastcgi_param        APP_ENV prod;
}
```

Keep the response unbuffered only if you later add streaming; the stateless handler returns small
JSON documents, so default buffering is fine.

---

## Testing the endpoint

```bash
TOKEN=...; URL=https://example.test/mcp
H=(-H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json, text/event-stream")

# Handshake
curl -s "${H[@]}" $URL -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}'
curl -s -o /dev/null -w '%{http_code}\n' "${H[@]}" $URL -d '{"jsonrpc":"2.0","method":"notifications/initialized"}'   # 202

# Tools
curl -s "${H[@]}" $URL -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
curl -s "${H[@]}" $URL -d '{"jsonrpc":"2.0","id":3,"method":"ping"}'                                                    # {"jsonrpc":"2.0","id":3,"result":{}}

# Negative checks
curl -s -o /dev/null -w '%{http_code}\n' -X GET $URL                                                                    # 405
curl -s -o /dev/null -w '%{http_code}\n' -H "Content-Type: application/json" $URL -d '{}'                              # 401 (no token)
```

Unit tests for the handler live in `tests/Unit/Http`; run them with `composer test`.

---

## Client configuration

### Claude Code

Project `.mcp.json` (or `claude mcp add --transport http my-app https://example.test/mcp --header "Authorization: Bearer ${MCP_TOKEN}"`):

```json
{
  "mcpServers": {
    "my-app": {
      "type": "http",
      "url": "https://example.test/mcp",
      "headers": {
        "Authorization": "Bearer ${MCP_TOKEN}"
      }
    }
  }
}
```

`${MCP_TOKEN}` is expanded from the environment of the Claude Code process, so the token never lands
in the repository.

### VS Code (GitHub Copilot agent mode)

`.vscode/mcp.json`:

```json
{
  "servers": {
    "my-app": {
      "type": "http",
      "url": "https://example.test/mcp",
      "headers": {
        "Authorization": "Bearer ${env:MCP_TOKEN}"
      }
    }
  }
}
```

### Any other client

Point it at the URL with the `Authorization` header. Clients that require OAuth can still be
served: put your own OAuth-validating middleware in place of `BearerTokenMiddleware`; the handler
does not care how the request was authenticated.

---

## Security checklist

- **TLS only.** The bearer token is a password; never serve `/mcp` over plain HTTP.
- **Long random tokens**, injected through the environment or secrets, rotated with the two-token
  overlap shown above.
- **Least-privilege tools.** The endpoint exposes whatever the tools can reach. Use read-only
  database users, allow-listed file roots, bounded result sizes.
- **Origin allow-list.** Leave it empty unless a browser-based client needs access; requests
  without an `Origin` header (CLI and desktop clients) always pass.
- **Rate limit and, where possible, IP-restrict** the location in the reverse proxy.
- **Body size limit** (`client_max_body_size`, `post_max_size`) to a few megabytes.
- **Audit log.** Pass a PSR-3 logger to `McpServer`; every rejected request, unknown method and
  failing tool is logged with its method and tool name. Add your own logging tool wrapper if you
  need every call recorded.
- **No sessions, no cookies.** Keep session and CSRF middleware off the route; there is nothing for
  them to protect and they would only reject legitimate requests.

---

## Protocol notes

- Implements the Streamable HTTP transport of the `2025-03-26` and `2025-06-18` revisions; the
  `2024-11-05` revision (pre-dating Streamable HTTP) is still negotiated for clients that request it.
- The optional server-initiated SSE stream (`GET`) is not offered; `405` is the specified answer.
- Batches are accepted and answered in order; a batch of notifications only yields `202`.
- `MCP-Protocol-Version` request headers are validated; unsupported values get `400`.
- Responses carry `Cache-Control: no-store`.
