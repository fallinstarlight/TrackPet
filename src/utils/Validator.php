<?php
namespace TrackPet\utils;

class Validator
{
    /**
     * Validate a whole payload against a field schema.
     *
     * Schema format: ['fieldName' => 'type', ...]
     * Allowed types: 'string', 'int', 'float', 'bool'
     *
     * Fields not present in $data are skipped (so optional fields pass).
     * Null is accepted only for types suffixed with '?'.
     *
     * Returns an array of human-readable error strings (empty = OK).
     */
    public static function validateShape(array $data, array $schema): array
    {
        $errors = [];

        foreach ($schema as $field => $expectedType) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];

            if ($value === null && str_ends_with($expectedType, '?')) {
                continue;
            }

            if (!self::matchesType($value, $expectedType)) {
                $actual = get_debug_type($value);
                $errors[] = "$field must be of type $expectedType (got $actual)";
            }
        }

        return $errors;
    }

    private static function matchesType(mixed $value, string $expected): bool
    {
        return match (rtrim($expected, '?')) {
            'string' => is_string($value),
            'int'    => is_int($value),
            'float'  => is_float($value) || is_int($value), // int is a legal float
            'bool'   => is_bool($value),
            default  => false,
        };
    }

    public static function clean(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && !in_array($key, ['password', 'userId', 'petId', 'ownerId'], true)) {
                $data[$key] = (new Sanitizer($value, true, $key !== 'petDescription'))->cleanString();
            }
        }
        return $data;
    }
}
