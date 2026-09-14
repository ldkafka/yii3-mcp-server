<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YiiMcp\McpServer\Protocol\ProtocolVersion;

final class ProtocolVersionTest extends TestCase
{
    public function testLatestIsFirstInSupportedList(): void
    {
        self::assertSame(ProtocolVersion::LATEST, ProtocolVersion::SUPPORTED[0]);
        self::assertTrue(ProtocolVersion::isSupported(ProtocolVersion::LATEST));
    }

    public function testNegotiationEchoesSupportedVersionsAndFallsBackOtherwise(): void
    {
        self::assertSame(ProtocolVersion::V2024_11_05, ProtocolVersion::negotiate(ProtocolVersion::V2024_11_05));
        self::assertSame(ProtocolVersion::LATEST, ProtocolVersion::negotiate('2000-01-01'));
        self::assertSame(ProtocolVersion::LATEST, ProtocolVersion::negotiate(null));
        self::assertSame(ProtocolVersion::LATEST, ProtocolVersion::negotiate(''));
    }
}
