<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Unit\Example;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiMcp\McpServer\Example\MysqlQueryTool;

/**
 * The example query tool runs read-only statements only; CHECKSUM TABLE is one of them.
 */
final class MysqlQueryToolTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function readOnlyStatements(): iterable
    {
        yield 'select' => ['SELECT 1'];
        yield 'lower case' => ['select id from t'];
        yield 'no space after the keyword' => ['SELECT*FROM t'];
        yield 'show' => ['SHOW TABLES'];
        yield 'describe' => ['DESCRIBE t'];
        yield 'explain' => ['EXPLAIN SELECT 1'];
        yield 'checksum' => ['CHECKSUM TABLE t'];
        yield 'checksum of several tables, extended' => ['checksum table a, `b` EXTENDED'];
        yield 'checksum across a line break' => ["CHECKSUM\n  TABLE t QUICK"];
    }

    /** @return iterable<string, array{string}> */
    public static function otherStatements(): iterable
    {
        yield 'insert' => ['INSERT INTO t VALUES (1)'];
        yield 'update' => ['UPDATE t SET a = 1'];
        yield 'delete' => ['DELETE FROM t'];
        yield 'set' => ['SET @a = 1'];
        yield 'checksum without TABLE' => ['CHECKSUM t'];
        yield 'checksum run together' => ['CHECKSUMTABLE t'];
        yield 'check table' => ['CHECK TABLE t'];
        yield 'optimize table' => ['OPTIMIZE TABLE t'];
        yield 'keyword inside a longer word' => ['SELECTED'];
    }

    #[DataProvider('readOnlyStatements')]
    public function testReadOnlyStatementsAreAllowed(string $sql): void
    {
        self::assertTrue(MysqlQueryTool::isReadOnlyStatement($sql));
    }

    #[DataProvider('otherStatements')]
    public function testOtherStatementsAreRefused(string $sql): void
    {
        self::assertFalse(MysqlQueryTool::isReadOnlyStatement($sql));
    }
}
