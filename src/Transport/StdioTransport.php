<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Transport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YiiMcp\McpServer\Log\StderrLogger;
use YiiMcp\McpServer\McpServer;

use function count;
use function defined;
use function fflush;
use function fgets;
use function fopen;
use function fwrite;
use function trim;

/**
 * Newline-delimited JSON-RPC over standard input/output: the transport editors spawn locally.
 *
 * Reads one message per line from the input stream, hands it to {@see McpServer::handleJson()}
 * and writes the response (if any) as one line to the output stream. STDOUT must stay clean:
 * every diagnostic goes through the logger, which defaults to STDERR.
 */
final class StdioTransport
{
    /** @var resource */
    private $input;

    /** @var resource */
    private $output;

    private LoggerInterface $logger;

    /**
     * @param McpServer $server Server that dispatches the decoded messages.
     * @param resource|null $input Stream to read requests from; defaults to STDIN.
     * @param resource|null $output Stream to write responses to; defaults to STDOUT.
     * @param LoggerInterface|null $logger Diagnostics sink; defaults to a STDERR logger.
     */
    public function __construct(private McpServer $server, $input = null, $output = null, ?LoggerInterface $logger = null)
    {
        $this->input = $input ?? (defined('STDIN') ? STDIN : fopen('php://stdin', 'rb'));
        $this->output = $output ?? (defined('STDOUT') ? STDOUT : fopen('php://stdout', 'wb'));
        $this->logger = $logger ?? new StderrLogger();
    }

    /**
     * Serve until the input stream reaches EOF (the client closed the pipe).
     *
     * A server without a logger of its own adopts this transport's logger, so tool failures are
     * visible on STDERR the way they were in earlier releases.
     */
    public function run(): void
    {
        if ($this->server->getLogger() instanceof NullLogger) {
            $this->server->setLogger($this->logger);
        }

        $this->logger->info('Yii3 MCP Server started over stdio with {count} tool(s).', [
            'count' => count($this->server->getTools()),
        ]);

        while (($line = fgets($this->input)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $response = $this->server->handleJson($line);
            if ($response !== null) {
                fwrite($this->output, $response . "\n");
                fflush($this->output);
            }
        }

        $this->logger->info('Yii3 MCP Server stopped: input closed.');
    }
}
