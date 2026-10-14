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
     * A value of null is always accepted — the business-rule validator
     * decides whether null is legal for a given field.
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

            if ($value === null) {
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
        return match ($expected) {
            'string' => is_string($value),
            'int'    => is_int($value),
            'float'  => is_float($value) || is_int($value), // int is a legal float
            'bool'   => is_bool($value),
            default  => false,
        };
    }
}