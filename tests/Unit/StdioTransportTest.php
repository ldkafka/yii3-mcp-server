<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YiiMcp\McpServer\McpServer;
use YiiMcp\McpServer\Protocol\JsonRpc;
use YiiMcp\McpServer\Tests\Support\ArrayLogger;
use YiiMcp\McpServer\Tests\Support\EchoTool;
use YiiMcp\McpServer\Transport\StdioTransport;

use function explode;
use function fopen;
use function fwrite;
use function json_decode;
use function rewind;
use function stream_get_contents;

final class StdioTransportTest extends TestCase
{
    public function testServesLineDelimitedMessagesUntilEof(): void
    {
        $input = fopen('php://memory', 'w+');
        fwrite($input, implode("\n", [
            '{"jsonrpc":"2.0","id":1,"method":"ping"}',
            '',
            '{"jsonrpc":"2.0","method":"notifications/initialized"}',
            'not json at all',
            '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"echo","arguments":{"message":"hi"}}}',
        ]) . "\n");
        rewind($input);
        $output = fopen('php://memory', 'w+');
        $logger = new ArrayLogger();
        $server = new McpServer([new EchoTool()]);

        (new StdioTransport($server, $input, $output, $logger))->run();

        rewind($output);
        $lines = array_values(array_filter(explode("\n", (string) stream_get_contents($output))));
        self::assertCount(3, $lines);

        $ping = json_decode($lines[0], true);
        self::assertSame(1, $ping['id']);
        self::assertSame([], $ping['result']);

        $parseError = json_decode($lines[1], true);
        self::assertSame(JsonRpc::PARSE_ERROR, $parseError['error']['code']);
        self::assertNull($parseError['id']);

        $call = json_decode($lines[2], true);
        self::assertSame('{"message":"hi"}', $call['result']['content'][0]['text']);

        // The server had no logger of its own, so it adopted the transport's.
        self::assertSame($logger, $server->getLogger());
        self::assertStringContainsString('started over stdio', $logger->records[0]['message']);
        self::assertStringContainsString('stopped', $logger->records[count($logger->records) - 1]['message']);
    }

    public function testOutputContainsOnlyJsonLines(): void
    {
        $input = fopen('php://memory', 'w+');
        fwrite($input, '{"jsonrpc":"2.0","id":"a","method":"initialize","params":{"protocolVersion":"2024-11-05"}}' . "\n");
        rewind($input);
        $output = fopen('php://memory', 'w+');

        (new StdioTransport(new McpServer(), $input, $output, new ArrayLogger()))->run();

        rewind($output);
        $raw = (string) stream_get_contents($output);
        self::assertSame("\n", substr($raw, -1));
        self::assertSame('2024-11-05', json_decode(trim($raw), true)['result']['protocolVersion']);
    }
}
