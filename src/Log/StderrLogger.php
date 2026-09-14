<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

use function array_diff_key;
use function defined;
use function fopen;
use function fwrite;
use function get_class;
use function is_scalar;
use function json_encode;
use function sprintf;
use function strtr;

/**
 * Minimal PSR-3 logger that writes one line per record to STDERR (or any stream).
 *
 * The stdio transport reserves STDOUT for JSON-RPC, so diagnostics must go elsewhere. This is the
 * default logger of {@see \YiiMcp\McpServer\Transport\StdioTransport}. Records below the configured
 * minimum level are dropped. Placeholders in the form `{key}` are interpolated from the context;
 * remaining context entries are appended as JSON.
 */
final class StderrLogger implements LoggerInterface
{
    use LoggerTrait;

    private const SEVERITY = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /** @var resource */
    private $stream;

    /**
     * @param resource|null $stream Target stream; defaults to STDERR.
     * @param string $minLevel Lowest PSR-3 level that is written; anything below is dropped.
     * @param string $prefix Short tag prepended to every line; empty string for none.
     */
    public function __construct($stream = null, private string $minLevel = LogLevel::INFO, private string $prefix = 'mcp')
    {
        $this->stream = $stream ?? (defined('STDERR') ? STDERR : fopen('php://stderr', 'wb'));
    }

    /**
     * Write a single log line.
     *
     * `$message` is intentionally left untyped so the class satisfies psr/log 1.x, 2.x and 3.x.
     *
     * @param mixed $level PSR-3 level name.
     * @param string|Stringable $message Message with optional `{placeholders}`.
     * @param array<string, mixed> $context Placeholder values and extra data.
     */
    public function log($level, $message, array $context = []): void
    {
        $level = (string) $level;
        if ((self::SEVERITY[$level] ?? 0) < (self::SEVERITY[$this->minLevel] ?? 0)) {
            return;
        }

        [$text, $rest] = self::interpolate((string) $message, $context);
        $line = sprintf('%s[%s] %s', $this->prefix === '' ? '' : $this->prefix . ' ', $level, $text);
        if ($rest !== []) {
            $line .= ' ' . json_encode(
                $rest,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
        }

        @fwrite($this->stream, $line . "\n");
    }

    /**
     * Replace `{key}` placeholders and return the text plus the context entries not consumed.
     *
     * @param array<string, mixed> $context
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function interpolate(string $message, array $context): array
    {
        $replace = [];
        $used = [];
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $context[$key] = get_class($value) . ': ' . $value->getMessage();
                $value = $context[$key];
            }
            if ($value === null || is_scalar($value) || $value instanceof Stringable) {
                $placeholder = '{' . $key . '}';
                if (str_contains($message, $placeholder)) {
                    $replace[$placeholder] = (string) $value;
                    $used[$key] = true;
                }
            }
        }

        return [$replace === [] ? $message : strtr($message, $replace), array_diff_key($context, $used)];
    }
}
