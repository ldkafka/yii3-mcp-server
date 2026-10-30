<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use YiiMcp\McpServer\Command\McpCommand;
use YiiMcp\McpServer\McpServer;

use function dirname;
use function file_get_contents;
use function preg_match;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The console packages are optional (`suggest`): only the stdio command may use them.
 */
final class CoreWithoutConsoleTest extends TestCase
{
    public function testOnlyTheCommandReferencesConsoleClasses(): void
    {
        $src = dirname(__DIR__, 2) . '/src';
        $offenders = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($src) + 1));
            if (str_starts_with($relative, 'Command/')) {
                continue;
            }
            if (preg_match('/Symfony\\\\Component\\\\Console|Yiisoft\\\\Yii\\\\Console/', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = $relative;
            }
        }

        self::assertSame([], $offenders);
    }

    public function testTheServeCommandStillWorksWhenConsoleIsInstalled(): void
    {
        $command = new McpCommand(new McpServer());

        self::assertSame(McpCommand::NAME, $command->getName());
    }
}
