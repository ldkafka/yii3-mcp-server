<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use YiiMcp\McpServer\Http\BearerChallenge;

final class BearerChallengeTest extends TestCase
{
    private const METADATA = 'https://mcp.example.com/.well-known/oauth-protected-resource/mcp';

    public function testMissingTokenNamesTheMetadataAndOptionalScopesWithoutAnErrorCode(): void
    {
        self::assertSame(
            'Bearer resource_metadata="' . self::METADATA . '"',
            BearerChallenge::unauthorized(self::METADATA)
        );
        self::assertSame(
            'Bearer resource_metadata="' . self::METADATA . '", scope="files:read files:write"',
            BearerChallenge::unauthorized(self::METADATA, ['files:read', 'files:write'])
        );
    }

    public function testInvalidTokenAddsTheErrorCodeAndDescription(): void
    {
        self::assertSame(
            'Bearer resource_metadata="' . self::METADATA . '", error="invalid_token", error_description="Token expired"',
            BearerChallenge::unauthorized(self::METADATA, [], true, 'Token expired')
        );
    }

    public function testInsufficientScopeFollowsTheSpecificationExample(): void
    {
        self::assertSame(
            'Bearer error="insufficient_scope", scope="files:read files:write user:profile", resource_metadata="'
                . self::METADATA . '", error_description="Additional file write permission required"',
            BearerChallenge::insufficientScope(
                self::METADATA,
                ['files:read', 'files:write', 'user:profile'],
                'Additional file write permission required'
            )
        );
    }

    public function testValuesStayOneQuotedHeaderLine(): void
    {
        $header = BearerChallenge::unauthorized(self::METADATA, [], true, "Bad \"token\"\r\nX-Injected: 1");

        self::assertStringEndsWith('error_description="Bad \\"token\\"X-Injected: 1"', $header);
        self::assertStringNotContainsString("\n", $header);
    }
}
