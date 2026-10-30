# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to
[Semantic Versioning](https://semver.org/).

## [1.5.0] - 2026-10-29

### Added

- **Protocol revision `2025-11-25`.** It is now the latest revision and is negotiated on
  `initialize`; `MCP-Protocol-Version: 2025-11-25` is accepted over HTTP. For what this package
  implements (tools over stdio and stateless HTTP POST) the revision needs no other server change:
  the `Origin` check already answers `403`, as the revision now states. Earlier revisions are still
  negotiated.
- **`McpToolMetaInterface`** (`getMeta(): array`): a tool's free-form `_meta` object in `tools/list`,
  for client-specific keys such as OpenAI's `securitySchemes`. Left out when empty.
- **`Http\BearerChallenge`**: framework-neutral `WWW-Authenticate` values for an OAuth-protected
  endpoint, per the MCP 2025-11-25 authorization specification, RFC 6750 and RFC 9728:
  `unauthorized()` for a `401` (`resource_metadata`, optional `scope`, optional
  `error="invalid_token"` and `error_description`) and `insufficientScope()` for a `403`. It only
  words the challenge; validating tokens stays with the application.
- **Using the core from any framework.** `docs/HTTP_TRANSPORT.md` shows a Yii2 controller calling
  `McpServer::dispatchPayload()` with a decoded body and its caller, and an OAuth challenge example.

### Changed

- **The console packages are optional.** `symfony/console` and `yiisoft/yii-console` moved from
  `require` to `suggest`, so the core installs in applications without them (a Yii2 app, for
  example). Only the stdio command (`McpCommand`, `mcp:serve`) uses them, and it works as before
  when they are installed. **Upgrade note:** a Yii3 application that runs `mcp:serve` and did not
  require `yiisoft/yii-console` itself must now add it (`composer require yiisoft/yii-console`);
  the Yii3 application templates already do.
- **Annotation hints are sent as booleans.** `readOnlyHint`, `destructiveHint`, `idempotentHint`
  and `openWorldHint` go out as JSON `true`/`false` whatever scalar the tool returned; a null hint
  is left out. `title` works as before (top level and inside `annotations`).

## [1.4.0] - 2026-10-15

### Added

- **Named bearer tokens.** `BearerTokenMiddleware` also accepts tokens keyed by caller name:
  `name => token` or `name => list of tokens` (rotation without downtime). Integer keys are plain
  tokens, as before, and can be mixed in; empty tokens are skipped. On a match it sets the request
  attribute `mcp.caller` (`BearerTokenMiddleware::ATTRIBUTE_CALLER`), null for a plain token;
  comparisons stay constant-time. `McpHttpHandler` passes it on: `McpServer::dispatchPayload()` and `dispatch()` take
  an optional `?string $caller`.
- **Per-caller tool scopes.** New `McpServer` constructor parameter `scopes`
  (`caller => list of tool names`). A scoped caller sees only those tools in `tools/list`; a call to
  any other tool gets the same `-32602 Unknown tool` error as a missing one. Callers without a scope,
  plain tokens and stdio see every tool, as before.
- **`McpCallerAwareInterface`** (`withCaller(?string $caller): static`): the server hands the caller
  to such a tool before `execute()` and runs the copy it returns.
- The tool-call log line carries `caller` when there is one. Argument values are never logged.

Backward compatible: a plain token list and no scopes behave exactly as 1.3.0.

## [1.3.0] - 2026-09-23

### Added

- **`CHECKSUM TABLE` in the example `MysqlQueryTool`.** It is read-only: it needs only the `SELECT`
  privilege and blocks no reads or writes, and it compares a table's contents on two servers.
  `EXTENDED` reads every row. The statement check is now `MysqlQueryTool::isReadOnlyStatement()`
  (pattern `READ_ONLY_STATEMENT`), which matches whole keywords at the start of the query and allows
  any whitespace between `CHECKSUM` and `TABLE`.

## [1.2.0] - 2026-09-16

### Added

- **Argument validation** (`YiiMcp\McpServer\Protocol\ArgumentValidator`): `tools/call` arguments are
  checked against the tool's `inputSchema` before the tool runs. Missing `required` properties,
  properties rejected by `additionalProperties: false`, values outside an `enum`, clear `type`
  mismatches and numbers outside `minimum`/`maximum` are answered with `-32602 Invalid params`, with
  the violations listed in `error.data.violations`, as the specification prescribes. Lenient by
  design (numeric strings for numbers, boolean-like strings for booleans) and limited to the top
  level of the schema; arguments reach the tool unmodified. Enabled by default; opt out with the new
  `McpServer` constructor parameter `validateArguments` or `setValidateArguments(false)`.
- **`outputSchema`** support (protocol 2025-06-18) through the optional
  `YiiMcp\McpServer\Contract\McpToolOutputSchemaInterface`, advertised in `tools/list` and
  normalised like input schemas.
- **Tool-call logging**: every `tools/call` is logged at info level with the tool name, duration
  in milliseconds and the size of the returned content; failures are logged with their duration too.
- `McpServer::isValidatingArguments()`, `setValidateArguments()`.

### Fixed

- **CORS preflight no longer needs a token.** `BearerTokenMiddleware` let `OPTIONS` requests hit
  the 401 path, but browsers never attach `Authorization` to a preflight, so browser-hosted clients
  could never reach the endpoint. `OPTIONS` now passes through to `McpHttpHandler`, which answers
  with an empty `204`.
- `StderrLogger` imports `str_contains` explicitly.

### Changed

- Release history: 1.1.0 and 1.1.1 were re-tagged on rewritten commits after publication, which
  Packagist does not accept; 1.2.0 is the first release cut on the current history. Require `^1.2`.

## [1.1.1] - 2026-09-14

### Fixed

- **Stdio transport is isolated from autowired application loggers.** Dependency-injection containers
  (yiisoft/di among them) autowire an application `LoggerInterface` into the new `McpServer` logger
  parameter, and the Yii3 application template's logger ships a `StreamTarget` writing to
  `php://stdout`. In 1.1.0 that leaked log records into the JSON-RPC stream. `StdioTransport` now
  routes the server's logging through its own logger (STDERR by default) for the duration of the
  run and restores the previous logger afterwards. Pass `useServerLogger: true` to keep an
  application logger whose targets are known to stay away from STDOUT.

### Changed

- Documentation: HTTP transport guide notes the FastRoute dispatch cache (`runtime/cache`) that must be
  cleared after adding the `/mcp` route in production mode; editor guide gains a troubleshooting entry
  for application loggers that write to STDOUT.

## [1.1.0] - 2026-09-14

### Added

- **HTTP transport** (`YiiMcp\McpServer\Http\McpHttpHandler`): MCP *Streamable HTTP* as a PSR-15
  request handler (also usable as middleware). Stateless single endpoint: `POST` returns the JSON-RPC
  response, notifications get `202`, `GET`/`DELETE` get `405`. Validates `Origin` (DNS-rebinding
  protection), `MCP-Protocol-Version` and the JSON content type; adds CORS headers for allowed origins.
- **Bearer-token authentication** (`YiiMcp\McpServer\Http\BearerTokenMiddleware`): constant-time
  comparison against one or more tokens (rotate without downtime), `401` with a `WWW-Authenticate`
  challenge, fails closed when no token is configured.
- **`ping`** request support (returns `{}`); clients use it to keep long-lived connections alive.
- **Protocol version negotiation** (`YiiMcp\McpServer\Protocol\ProtocolVersion`): `2025-06-18`,
  `2025-03-26` and `2024-11-05` are accepted; the client's version is echoed when supported,
  otherwise the latest one is offered.
- **JSON-RPC batches** are dispatched (one response per request in the batch).
- **Proper JSON-RPC errors**: `-32700` parse error, `-32600` invalid request, `-32601` method not
  found (unknown methods used to be silently ignored), `-32602` invalid params (unknown tool, bad
  arguments), `-32603` internal error.
- **Tool failures as `isError` results**: an exception thrown by a tool no longer aborts the request
  with a protocol error; its message is returned in an `isError` tool result, as the MCP
  specification requires, so the assistant can read it and recover.
- **Result normalisation**: tools that return a bare array (no `content`) get it wrapped as JSON
  text; `structuredContent` without `content` gets a text fallback; an empty `properties` map in an
  input schema encodes as `{}`, never `[]`.
- **Tool annotations** via the optional `YiiMcp\McpServer\Contract\McpToolAnnotationsInterface`
  (`title`, `readOnlyHint`, `destructiveHint`, `idempotentHint`, `openWorldHint`).
- **Server instructions** and custom `serverInfo` (`McpServer` constructor, `setInstructions()`).
- **PSR-3 logging**: `McpServer` accepts any `LoggerInterface`; `YiiMcp\McpServer\Log\StderrLogger`
  is the default for stdio (STDERR, minimum level, placeholder interpolation).
- **`StdioTransport`** (`YiiMcp\McpServer\Transport\StdioTransport`): the stdio loop extracted from
  `McpServer`, with injectable streams for testing. `McpServer::run()` still works and delegates to it.
- **Transport-agnostic API**: `McpServer::dispatch()`, `dispatchPayload()` and `handleJson()` return
  responses instead of printing them, so any transport can be built on top.
- `McpServer::getTools()`, `hasTool()`, `getTool()`, `getNegotiatedProtocolVersion()`.
- `MysqlQueryTool` example: `limit` argument (default 200, max 1000) with streamed truncation, a
  validated `database` identifier, `isError` on rejected queries, and annotations.
- PHPUnit test suite and a GitHub Actions workflow (PHP 8.1 to 8.4).
- `config/di/mcp-http-template.php` and `docs/HTTP_TRANSPORT.md`.

### Changed

- `initialize` advertises `serverInfo.title` and negotiates the protocol version instead of always
  answering `2024-11-05`.
- `McpCommand` uses the `#[AsCommand]` attribute (symfony/console 7 dropped the static
  `$defaultName`) and delegates to `StdioTransport`.
- Composer: the package now requires the PSR HTTP and logging interfaces (`psr/http-message`,
  `psr/http-factory`, `psr/http-server-handler`, `psr/http-server-middleware`, `psr/log`).
  `httpsoft/http-message` and PHPUnit replace the unused Codeception development dependencies.

### Backwards compatibility

- `McpToolInterface` is unchanged; existing tools keep working. Tools that threw exceptions now
  produce `isError` results instead of JSON-RPC errors.
- `McpServer::run()` and `php yii mcp:serve` behave as before: startup and errors on STDERR,
  JSON-RPC on STDOUT.
- Unknown methods receive a `-32601` error instead of no response; unknown notifications are still
  ignored.

## [1.0.7] - 2026-06-17

### Fixed

- Advertise the `tools` capability as a JSON object, not an array; strict clients rejected `initialize`.

## [1.0.6] - 2026-01-14

- Initial public release: stdio transport, `McpToolInterface`, `MysqlQueryTool` example.
