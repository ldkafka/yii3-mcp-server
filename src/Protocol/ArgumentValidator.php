<?php

declare(strict_types=1);

namespace YiiMcp\McpServer\Protocol;

use function array_is_list;
use function array_key_exists;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * Checks `tools/call` arguments against a tool's `inputSchema` before the tool runs.
 *
 * This is deliberately a subset of JSON Schema, and a lenient one: assistants routinely send
 * `"50"` for an integer or `"true"` for a boolean, and tools cast such values anyway, so numeric
 * strings and boolean-like strings are accepted. Only clear mistakes are reported, which the MCP
 * specification classifies as protocol errors (`-32602 Invalid params`): missing required
 * properties, properties not allowed by `additionalProperties: false`, values outside an `enum`,
 * values of the wrong kind (an array where a string is expected), and numbers outside
 * `minimum`/`maximum`. Only the top level of the schema is inspected; nested objects are the
 * tool's business.
 *
 * Arguments are never modified: the tool still receives exactly what the client sent.
 */
final class ArgumentValidator
{
    /**
     * Validate arguments against a schema.
     *
     * @param array<string, mixed> $schema The tool's input schema.
     * @param array<string, mixed> $arguments Arguments from the `tools/call` request.
     * @return list<string> Human readable violations; empty when the arguments are acceptable.
     */
    public static function validate(array $schema, array $arguments): array
    {
        $violations = [];
        $properties = $schema['properties'] ?? [];
        $properties = is_array($properties) ? $properties : [];

        $required = $schema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $name) {
                if (is_string($name) && !array_key_exists($name, $arguments)) {
                    $violations[] = sprintf('"%s" is required.', $name);
                }
            }
        }

        if (($schema['additionalProperties'] ?? null) === false) {
            foreach ($arguments as $name => $value) {
                if (!array_key_exists((string) $name, $properties)) {
                    $violations[] = sprintf('"%s" is not an accepted argument.', (string) $name);
                }
            }
        }

        foreach ($properties as $name => $definition) {
            if (!is_array($definition) || !array_key_exists((string) $name, $arguments)) {
                continue;
            }
            $violation = self::check((string) $name, $definition, $arguments[$name]);
            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * Check one argument against its property definition.
     *
     * @param array<string, mixed> $definition
     */
    private static function check(string $name, array $definition, mixed $value): ?string
    {
        $types = $definition['type'] ?? null;
        if (is_string($types)) {
            $types = [$types];
        }
        if (is_array($types) && $types !== []) {
            $matched = false;
            foreach ($types as $type) {
                if (is_string($type) && self::isOfType($type, $value)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return sprintf('"%s" must be of type %s.', $name, implode('|', array_map('strval', $types)));
            }
        }

        $enum = $definition['enum'] ?? null;
        if (is_array($enum) && $enum !== [] && !self::inEnum($value, $enum)) {
            return sprintf('"%s" must be one of: %s.', $name, implode(', ', array_map(
                static fn (mixed $option): string => is_scalar($option) ? (string) $option : JsonRpc::encode($option),
                $enum
            )));
        }

        if (is_numeric($value) && !is_bool($value)) {
            $number = $value + 0;
            if (isset($definition['minimum']) && is_numeric($definition['minimum']) && $number < $definition['minimum']) {
                return sprintf('"%s" must be at least %s.', $name, (string) $definition['minimum']);
            }
            if (isset($definition['maximum']) && is_numeric($definition['maximum']) && $number > $definition['maximum']) {
                return sprintf('"%s" must be at most %s.', $name, (string) $definition['maximum']);
            }
        }

        return null;
    }

    /**
     * Lenient JSON Schema type test.
     */
    private static function isOfType(string $type, mixed $value): bool
    {
        return match ($type) {
            'string' => is_string($value) || is_int($value) || is_float($value),
            'integer' => is_int($value)
                || (is_float($value) && (float) (int) $value === $value)
                || (is_string($value) && preg_match('/^\s*-?\d+\s*$/', $value) === 1),
            'number' => (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) && !is_bool($value),
            'boolean' => is_bool($value)
                || in_array($value, [0, 1, '0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'], true),
            'array' => is_array($value) && ($value === [] || array_is_list($value)),
            'object' => is_array($value) && ($value === [] || !array_is_list($value)),
            'null' => $value === null,
            default => true,
        };
    }

    /**
     * Enum membership, comparing scalars by their string form so `"5"` matches `5`.
     *
     * @param list<mixed> $enum
     */
    private static function inEnum(mixed $value, array $enum): bool
    {
        foreach ($enum as $option) {
            if ($option === $value) {
                return true;
            }
            if (is_scalar($option) && is_scalar($value) && !is_bool($option) && !is_bool($value)
                && (string) $option === (string) $value) {
                return true;
            }
        }

        return false;
    }
}
