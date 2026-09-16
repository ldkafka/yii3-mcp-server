<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YiiMcp\McpServer\McpServer;
use YiiMcp\McpServer\Protocol\JsonRpc;
use YiiMcp\McpServer\Protocol\ProtocolVersion;
use YiiMcp\McpServer\Tests\Support\ArrayLogger;
use YiiMcp\McpServer\Tests\Support\BrokenSchemaTool;
use YiiMcp\McpServer\Tests\Support\EchoTool;
use YiiMcp\McpServer\Tests\Support\RawArrayTool;
use YiiMcp\McpServer\Tests\Support\StructuredTool;
use YiiMcp\McpServer\Tests\Support\ThrowingTool;
use YiiMcp\McpServer\Version;

use function json_decode;

final class McpServerTest extends TestCase
{
    private function server(?ArrayLogger $logger = null): McpServer
    {
        return new McpServer([new EchoTool(), new ThrowingTool(), new RawArrayTool(), new StructuredTool()], $logger);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<mixed>|null
     */
    private function request(McpServer $server, string $method, array $params = [], int|string $id = 1): ?array
    {
        return $server->dispatch(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
    }

    public function testInitializeEchoesASupportedVersionAndAdvertisesTools(): void
    {
        $server = $this->server();
        $response = $this->request($server, 'initialize', [
            'protocolVersion' => ProtocolVersion::V2025_03_26,
            'capabilities' => [],
            'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
        ]);

        self::assertSame(ProtocolVersion::V2025_03_26, $response['result']['protocolVersion']);
        self::assertSame(['listChanged' => false], $response['result']['capabilities']['tools']);
        self::assertSame(Version::NAME, $response['result']['serverInfo']['name']);
        self::assertSame(Version::VERSION, $response['result']['serverInfo']['version']);
        self::assertArrayNotHasKey('instructions', $response['result']);
        self::assertSame(ProtocolVersion::V2025_03_26, $server->getNegotiatedProtocolVersion());
    }

    public function testInitializeFallsBackToLatestForUnknownOrMissingVersion(): void
    {
        $server = $this->server();

        $unknown = $this->request($server, 'initialize', ['protocolVersion' => '1999-01-01']);
        self::assertSame(ProtocolVersion::LATEST, $unknown['result']['protocolVersion']);

        $missing = $this->request($server, 'initialize');
        self::assertSame(ProtocolVersion::LATEST, $missing['result']['protocolVersion']);
    }

    public function testInitializeIncludesInstructionsWhenConfigured(): void
    {
        $server = new McpServer([], null, 'Only read data; never guess table names.');
        $response = $this->request($server, 'initialize');

        self::assertSame('Only read data; never guess table names.', $response['result']['instructions']);
    }

    public function testCustomServerInfoIsAdvertised(): void
    {
        $server = new McpServer([], null, null, ['name' => 'my-app', 'version' => '9.9.9']);
        $response = $this->request($server, 'initialize');

        self::assertSame(['name' => 'my-app', 'version' => '9.9.9'], $response['result']['serverInfo']);
    }

    public function testPingReturnsAnEmptyObject(): void
    {
        $json = $this->server()->handleJson('{"jsonrpc":"2.0","id":"p1","method":"ping"}');

        self::assertSame('{"jsonrpc":"2.0","id":"p1","result":{}}', $json);
    }

    public function testToolsListIncludesSchemaTitleAndAnnotations(): void
    {
        $response = $this->request($this->server(), 'tools/list');
        $tools = [];
        foreach ($response['result']['tools'] as $tool) {
            $tools[$tool['name']] = $tool;
        }

        self::assertSame(['echo', 'boom', 'raw', 'structured'], array_keys($tools));
        self::assertSame('Echo', $tools['echo']['title']);
        self::assertSame(['title' => 'Echo', 'readOnlyHint' => true, 'idempotentHint' => true], $tools['echo']['annotations']);
        self::assertSame(['message'], $tools['echo']['inputSchema']['required']);
        self::assertArrayNotHasKey('annotations', $tools['boom']);
        self::assertArrayNotHasKey('title', $tools['boom']);
        self::assertArrayNotHasKey('outputSchema', $tools['echo']);
        self::assertSame('object', $tools['structured']['outputSchema']['type']);
        self::assertSame(['doubled'], $tools['structured']['outputSchema']['required']);
    }

    public function testToolsListEncodesEmptyPropertiesAsObjectAndDefaultsType(): void
    {
        $json = $this->server()->handleJson('{"jsonrpc":"2.0","id":1,"method":"tools/list"}');
        $decoded = json_decode((string) $json, true);
        $raw = array_values(array_filter($decoded['result']['tools'], static fn (array $t): bool => $t['name'] === 'raw'))[0];

        self::assertStringContainsString('"properties":{}', (string) $json);
        self::assertStringNotContainsString('"properties":[]', (string) $json);
        self::assertSame('object', $raw['inputSchema']['type']);
    }

    public function testToolsListSurvivesCursorParameter(): void
    {
        $response = $this->request($this->server(), 'tools/list', ['cursor' => 'abc']);

        self::assertCount(4, $response['result']['tools']);
        self::assertArrayNotHasKey('nextCursor', $response['result']);
    }

    public function testToolsCallReturnsTheToolResult(): void
    {
        $response = $this->request($this->server(), 'tools/call', ['name' => 'echo', 'arguments' => ['message' => 'hi']]);

        self::assertArrayNotHasKey('error', $response);
        self::assertSame('{"message":"hi"}', $response['result']['content'][0]['text']);
        self::assertArrayNotHasKey('isError', $response['result']);
    }

    public function testToolsCallWithUnknownToolIsInvalidParams(): void
    {
        $response = $this->request($this->server(), 'tools/call', ['name' => 'nope']);

        self::assertSame(JsonRpc::INVALID_PARAMS, $response['error']['code']);
        self::assertStringContainsString('Unknown tool: nope', $response['error']['message']);
    }

    public function testToolsCallWithoutNameOrWithBadArgumentsIsInvalidParams(): void
    {
        $server = $this->server();

        $noName = $this->request($server, 'tools/call', ['arguments' => []]);
        self::assertSame(JsonRpc::INVALID_PARAMS, $noName['error']['code']);

        $badArguments = $this->request($server, 'tools/call', ['name' => 'echo', 'arguments' => 'oops']);
        self::assertSame(JsonRpc::INVALID_PARAMS, $badArguments['error']['code']);
    }

    public function testToolsCallRejectsArgumentsThatViolateTheSchema(): void
    {
        $logger = new ArrayLogger();
        $server = $this->server($logger);

        $missing = $this->request($server, 'tools/call', ['name' => 'echo', 'arguments' => []]);
        self::assertSame(JsonRpc::INVALID_PARAMS, $missing['error']['code']);
        self::assertStringContainsString('"message" is required.', $missing['error']['message']);
        self::assertSame(['tool' => 'echo', 'violations' => ['"message" is required.']], $missing['error']['data']);

        $unknown = $this->request($server, 'tools/call', ['name' => 'structured', 'arguments' => ['n' => 2, 'x' => 1]]);
        self::assertSame(JsonRpc::INVALID_PARAMS, $unknown['error']['code']);
        self::assertSame(['"x" is not an accepted argument.'], $unknown['error']['data']['violations']);

        self::assertNotContains('info', $logger->levels(), 'the tool never ran');
        self::assertContains('warning', $logger->levels());
    }

    public function testToolsCallLenientlyAcceptsNumericStringsAndCanSkipValidation(): void
    {
        $server = $this->server();

        $lenient = $this->request($server, 'tools/call', ['name' => 'structured', 'arguments' => ['n' => '21']]);
        self::assertSame(['doubled' => 42], $lenient['result']['structuredContent']);
        self::assertSame('{"doubled":42}', $lenient['result']['content'][0]['text']);

        self::assertTrue($server->isValidatingArguments());
        $server->setValidateArguments(false);
        $off = $this->request($server, 'tools/call', ['name' => 'structured', 'arguments' => ['n' => 1, 'x' => 1]]);
        self::assertArrayNotHasKey('error', $off);
        self::assertSame(['doubled' => 2], $off['result']['structuredContent']);

        $disabledAtConstruction = new McpServer([new EchoTool()], null, null, null, false);
        self::assertFalse($disabledAtConstruction->isValidatingArguments());
        self::assertArrayNotHasKey('error', $this->request($disabledAtConstruction, 'tools/call', ['name' => 'echo']));
    }

    public function testToolCallsAreLoggedWithDurationAndSize(): void
    {
        $logger = new ArrayLogger();
        $this->request($this->server($logger), 'tools/call', ['name' => 'echo', 'arguments' => ['message' => 'hi']]);

        $info = array_values(array_filter($logger->records, static fn (array $r): bool => $r['level'] === 'info'));
        self::assertCount(1, $info);
        self::assertStringContainsString('{tool} {outcome} in {ms} ms, {bytes} bytes', $info[0]['message']);
        self::assertSame('echo', $info[0]['context']['tool']);
        self::assertSame('completed', $info[0]['context']['outcome']);
        self::assertIsFloat($info[0]['context']['ms']);
        self::assertSame(strlen('{"message":"hi"}'), $info[0]['context']['bytes']);
    }

    public function testToolExceptionBecomesAnIsErrorResultAndIsLogged(): void
    {
        $logger = new ArrayLogger();
        $response = $this->request($this->server($logger), 'tools/call', ['name' => 'boom']);

        self::assertArrayNotHasKey('error', $response);
        self::assertTrue($response['result']['isError']);
        self::assertSame('boom', $response['result']['content'][0]['text']);
        self::assertContains('error', $logger->levels());
    }

    public function testBareArrayToolResultIsWrappedAsText(): void
    {
        $response = $this->request($this->server(), 'tools/call', ['name' => 'raw']);

        self::assertSame([['type' => 'text', 'text' => '{"answer":42}']], $response['result']['content']);
    }

    public function testUnknownMethodIsMethodNotFound(): void
    {
        $response = $this->request($this->server(), 'resources/list');

        self::assertSame(JsonRpc::METHOD_NOT_FOUND, $response['error']['code']);
        self::assertSame(1, $response['id']);
    }

    public function testNotificationsNeverGetAResponse(): void
    {
        $server = $this->server();

        self::assertNull($server->dispatch(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
        self::assertNull($server->dispatch(['jsonrpc' => '2.0', 'method' => 'notifications/cancelled', 'params' => ['requestId' => 1]]));
        self::assertNull($server->dispatch(['jsonrpc' => '2.0', 'method' => 'notifications/does-not-exist']));
        // A request without an id is a notification, even for known methods and even when it fails.
        self::assertNull($server->dispatch(['jsonrpc' => '2.0', 'method' => 'ping']));
        self::assertNull($server->dispatch(['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => ['name' => 'nope']]));
        self::assertNull($server->dispatch(['jsonrpc' => '2.0', 'method' => 'no/such/method']));
    }

    public function testStructurallyInvalidMessagesAreInvalidRequests(): void
    {
        $server = $this->server();

        foreach (['nope', 42, null, [], [1, 2]] as $garbage) {
            $response = $server->dispatch($garbage);
            self::assertSame(JsonRpc::INVALID_REQUEST, $response['error']['code']);
            self::assertNull($response['id']);
        }

        $noMethod = $server->dispatch(['jsonrpc' => '2.0', 'id' => 7]);
        self::assertSame(JsonRpc::INVALID_REQUEST, $noMethod['error']['code']);
        self::assertSame(7, $noMethod['id']);

        $badId = $server->dispatch(['jsonrpc' => '2.0', 'id' => [1], 'method' => 'ping']);
        self::assertSame(JsonRpc::INVALID_REQUEST, $badId['error']['code']);
        self::assertNull($badId['id']);

        $wrongVersion = $server->dispatch(['jsonrpc' => '1.0', 'id' => 3, 'method' => 'ping']);
        self::assertSame(JsonRpc::INVALID_REQUEST, $wrongVersion['error']['code']);
        self::assertSame(3, $wrongVersion['id']);
    }

    public function testMissingJsonrpcFieldIsToleratedForManualTesting(): void
    {
        $response = $this->server()->dispatch(['id' => 1, 'method' => 'ping']);

        self::assertArrayHasKey('result', $response);
    }

    public function testParamsMustBeAnObject(): void
    {
        $response = $this->server()->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping', 'params' => 'x']);

        self::assertSame(JsonRpc::INVALID_PARAMS, $response['error']['code']);
    }

    public function testUnexpectedHandlerFailureIsAnInternalError(): void
    {
        $logger = new ArrayLogger();
        $server = new McpServer([new BrokenSchemaTool()], $logger);
        $response = $this->request($server, 'tools/list');

        self::assertSame(JsonRpc::INTERNAL_ERROR, $response['error']['code']);
        self::assertSame('schema unavailable', $response['error']['message']);
        self::assertContains('error', $logger->levels());
    }

    public function testHandleJsonReturnsAParseErrorForInvalidJson(): void
    {
        $json = $this->server()->handleJson('{not json');
        $decoded = json_decode((string) $json, true);

        self::assertSame(JsonRpc::PARSE_ERROR, $decoded['error']['code']);
        self::assertNull($decoded['id']);
    }

    public function testHandleJsonProcessesBatches(): void
    {
        $json = $this->server()->handleJson(
            '[{"jsonrpc":"2.0","id":1,"method":"ping"},'
            . '{"jsonrpc":"2.0","method":"notifications/initialized"},'
            . '{"jsonrpc":"2.0","id":2,"method":"nope"}]'
        );
        $decoded = json_decode((string) $json, true);

        self::assertCount(2, $decoded);
        self::assertSame(1, $decoded[0]['id']);
        self::assertSame(2, $decoded[1]['id']);
        self::assertSame(JsonRpc::METHOD_NOT_FOUND, $decoded[1]['error']['code']);
    }

    public function testBatchOfOnlyNotificationsProducesNothing(): void
    {
        $json = $this->server()->handleJson('[{"jsonrpc":"2.0","method":"notifications/initialized"}]');

        self::assertNull($json);
    }

    public function testEmptyBatchIsAnInvalidRequest(): void
    {
        $decoded = json_decode((string) $this->server()->handleJson('[]'), true);

        self::assertSame(JsonRpc::INVALID_REQUEST, $decoded['error']['code']);
    }

    public function testRegistryHelpers(): void
    {
        $server = new McpServer();
        self::assertFalse($server->hasTool('echo'));
        self::assertNull($server->getTool('echo'));

        $first = new EchoTool();
        $second = new EchoTool();
        $server->registerTool($first);
        $server->registerTool($second);

        self::assertTrue($server->hasTool('echo'));
        self::assertSame($second, $server->getTool('echo'));
        self::assertCount(1, $server->getTools());
    }

    public function testInstructionsAndLoggerAreMutable(): void
    {
        $server = new McpServer();
        $logger = new ArrayLogger();

        $server->setInstructions('x');
        $server->setLogger($logger);

        self::assertSame('x', $server->getInstructions());
        self::assertSame($logger, $server->getLogger());
    }
}
