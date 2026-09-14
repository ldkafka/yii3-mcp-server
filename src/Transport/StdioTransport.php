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
     * @param bool $useServerLogger Keep the logger already configured on the server for the
     *        duration of the run. By default the server is switched to this transport's logger
     *        while serving: DI containers happily autowire an application logger into McpServer,
     *        and an application logger may well have a target that writes to STDOUT (the Yii3
     *        app template's StreamTarget does), which would corrupt the protocol stream. Opt in
     *        only when you know every target of the server's logger stays away from STDOUT.
     */
    public function __construct(
        private McpServer $server,
        $input = null,
        $output = null,
        ?LoggerInterface $logger = null,
        private bool $useServerLogger = false,
    ) {
        $this->input = $input ?? (defined('STDIN') ? STDIN : fopen('php://stdin', 'rb'));
        $this->output = $output ?? (defined('STDOUT') ? STDOUT : fopen('php://stdout', 'wb'));
        $this->logger = $logger ?? new StderrLogger();
    }

    /**
     * Serve until the input stream reaches EOF (the client closed the pipe).
     *
     * While serving, the server logs through this transport's logger (STDERR by default) so that
     * nothing but JSON-RPC ever reaches STDOUT; the server's own logger is restored afterwards.
     * With `$useServerLogger` the server keeps its logger, unless it has none (a NullLogger), in
     * which case it adopts this transport's logger so tool failures stay visible.
     */
    public function run(): void
    {
        $previousLogger = $this->server->getLogger();
        if (!$this->useServerLogger || $previousLogger instanceof NullLogger) {
            $this->server->setLogger($this->logger);
        }

        try {
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
        } finally {
            $this->server->setLogger($previousLogger);
        }
    }
}
