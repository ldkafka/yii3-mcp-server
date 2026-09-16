<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YiiMcp\McpServer\Protocol\ArgumentValidator;

final class ArgumentValidatorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sql' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                'ratio' => ['type' => 'number'],
                'verbose' => ['type' => 'boolean'],
                'tags' => ['type' => 'array'],
                'options' => ['type' => 'object'],
                'format' => ['type' => 'string', 'enum' => ['text', 'json']],
                'page' => ['type' => ['integer', 'null']],
                'anything' => ['description' => 'no type'],
            ],
            'required' => ['sql'],
            'additionalProperties' => false,
        ];
    }

    public function testWellFormedArgumentsPass(): void
    {
        self::assertSame([], ArgumentValidator::validate($this->schema(), [
            'sql' => 'SELECT 1',
            'limit' => 50,
            'ratio' => 0.5,
            'verbose' => true,
            'tags' => ['a', 'b'],
            'options' => ['k' => 'v'],
            'format' => 'json',
            'page' => null,
            'anything' => ['whatever' => [1, 2]],
        ]));
    }

    public function testLenientScalarsPass(): void
    {
        self::assertSame([], ArgumentValidator::validate($this->schema(), [
            'sql' => 42,              // number where a string is expected
            'limit' => '50',          // numeric string for an integer
            'ratio' => '0.25',        // numeric string for a number
            'verbose' => 'false',     // boolean-like string
            'format' => 'text',
            'tags' => [],             // empty array is both a list and an object
            'options' => [],
            'page' => 3.0,            // integral float
        ]));
    }

    public function testMissingRequiredAndUnknownArgumentsAreReported(): void
    {
        $violations = ArgumentValidator::validate($this->schema(), ['bogus' => 1]);

        self::assertSame(['"sql" is required.', '"bogus" is not an accepted argument.'], $violations);
    }

    public function testUnknownArgumentsAreFineWithoutAdditionalPropertiesFalse(): void
    {
        $schema = $this->schema();
        unset($schema['additionalProperties']);

        self::assertSame([], ArgumentValidator::validate($schema, ['sql' => 'x', 'bogus' => 1]));
    }

    public function testClearTypeMismatchesAreReported(): void
    {
        $violations = ArgumentValidator::validate($this->schema(), [
            'sql' => ['not', 'a', 'string'],
            'limit' => 'fifty',
            'ratio' => true,
            'verbose' => 'maybe',
            'tags' => ['k' => 'v'],
            'options' => 'flat',
            'page' => 'x',
        ]);

        self::assertSame([
            '"sql" must be of type string.',
            '"limit" must be of type integer.',
            '"ratio" must be of type number.',
            '"verbose" must be of type boolean.',
            '"tags" must be of type array.',
            '"options" must be of type object.',
            '"page" must be of type integer|null.',
        ], $violations);
    }

    public function testEnumAndRangeAreChecked(): void
    {
        self::assertSame(
            ['"format" must be one of: text, json.'],
            ArgumentValidator::validate($this->schema(), ['sql' => 'x', 'format' => 'xml'])
        );
        self::assertSame(
            ['"limit" must be at least 1.'],
            ArgumentValidator::validate($this->schema(), ['sql' => 'x', 'limit' => 0])
        );
        self::assertSame(
            ['"limit" must be at most 1000.'],
            ArgumentValidator::validate($this->schema(), ['sql' => 'x', 'limit' => '5000'])
        );
    }

    public function testEnumComparesScalarsByStringForm(): void
    {
        $schema = ['properties' => ['n' => ['enum' => [1, 2, 3]]]];

        self::assertSame([], ArgumentValidator::validate($schema, ['n' => '2']));
        self::assertSame(['"n" must be one of: 1, 2, 3.'], ArgumentValidator::validate($schema, ['n' => '4']));
    }

    public function testSchemasWithoutPropertiesOrWithOddShapesAreTolerated(): void
    {
        self::assertSame([], ArgumentValidator::validate([], ['a' => 1]));
        self::assertSame([], ArgumentValidator::validate(['type' => 'object', 'properties' => 'nope', 'required' => 'sql'], []));
        self::assertSame([], ArgumentValidator::validate(['properties' => ['a' => 'not-an-array']], ['a' => 1]));
    }
}
