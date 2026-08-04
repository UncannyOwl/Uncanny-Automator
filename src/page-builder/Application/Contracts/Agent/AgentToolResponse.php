<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Contracts\Agent;

/**
 * Generic agent tool response — validates output against agent-tools.json.
 *
 * Replaces per-tool DTO classes. The JSON contract is the single source
 * of truth for both input parameters and output shape.
 */
final class AgentToolResponse
{
    private static ?array $contract = null;

    /**
     * Validate and return a response array for a named tool.
     *
     * @param string $toolName Tool name matching agent-tools.json.
     * @param array<string, mixed> $data The response data to validate.
     * @return array<string, mixed> The validated data (pass-through).
     *
     * @throws \InvalidArgumentException If the tool is not found in the contract.
     * @throws \UnexpectedValueException If required fields are missing or wrong type.
     */
    public static function validate(string $toolName, array $data): array
    {
        $toolSpec = self::findToolSpec($toolName);
        $output = $toolSpec['output'] ?? null;

        if ($output === null) {
            return $data;
        }

        self::validateFields($data, $output, $toolName);

        return $data;
    }

    private static function findToolSpec(string $toolName): array
    {
        if (self::$contract === null) {
            self::$contract = [];
            foreach (glob(UNCANNY_PB_PATH . 'tools/*.json') as $file) {
                $tool = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                self::$contract[$tool['name']] = $tool;
            }
        }

        if (isset(self::$contract[$toolName])) {
            return self::$contract[$toolName];
        }

        throw new \InvalidArgumentException("Unknown agent tool: {$toolName}");
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $schema
     */
    private static function validateFields(array $data, array $schema, string $context): void
    {
        $required = $schema['required'] ?? [];
        $fields = $schema['fields'] ?? [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \UnexpectedValueException(
                    "{$context}: missing required field '{$field}'"
                );
            }
        }

        foreach ($fields as $field => $spec) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if (is_string($spec)) {
                self::validateType($value, $spec, "{$context}.{$field}");
            } elseif (is_array($spec)) {
                $type = $spec['type'] ?? 'mixed';

                if ($type === 'array' && is_array($value) && isset($spec['items'])) {
                    foreach ($value as $i => $item) {
                        if (is_array($item)) {
                            self::validateFields($item, $spec['items'], "{$context}.{$field}[{$i}]");
                        }
                    }
                }
            }
        }
    }

    private static function validateType(mixed $value, string $expectedType, string $context): void
    {
        $valid = match ($expectedType) {
            'integer' => is_int($value),
            'string'  => is_string($value),
            'boolean' => is_bool($value),
            'number'  => is_int($value) || is_float($value),
            'array'   => is_array($value),
            'object'  => is_array($value) || is_object($value),
            default   => true,
        };

        if (!$valid) {
            $actual = get_debug_type($value);
            throw new \UnexpectedValueException(
                "{$context}: expected {$expectedType}, got {$actual}"
            );
        }
    }
}
