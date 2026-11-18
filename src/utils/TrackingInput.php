<?php
namespace TrackPet\utils;

use DateTimeImmutable;
use DateTimeZone;

class TrackingInput
{
    public static function shape(array $data, array $schema, array $required = []): array
    {
        $errors = Validator::validateShape($data, $schema);
        foreach (array_diff(array_keys($data), array_keys($schema)) as $field) $errors[] = "Campo no admitido: $field";
        foreach ($required as $field) if (!array_key_exists($field, $data) || $data[$field] === null) $errors[] = "Falta el campo $field";
        if ($errors) throw new ApiException('Revisa los datos enviados.', data: $errors);
        return Validator::clean($data);
    }

    public static function text(string $value, string $name, int $max, bool $required = false): string
    {
        if (($required && $value === '') || mb_strlen($value) > $max) throw new ApiException("$name debe contener " . ($required ? 'entre 1 y ' : 'hasta ') . "$max caracteres.");
        return $value;
    }

    public static function number(int|float $value, string $name, float $min, float $max, int $decimals = 6): float
    {
        if (!is_finite((float)$value) || $value < $min || $value > $max) throw new ApiException("$name debe estar entre $min y $max.");
        return round((float)$value, $decimals);
    }

    /** Input timestamps must include their timezone. All database timestamps are UTC. */
    public static function date(string $value, string $name): DateTimeImmutable
    {
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:0\d|1[0-4]):[0-5]\d)\z/', $value)) {
            throw new ApiException("$name debe ser una fecha ISO 8601 con zona horaria.");
        }
        try { $date = new DateTimeImmutable($value); } catch (\Exception $e) { throw new ApiException("$name no es una fecha válida."); }
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) throw new ApiException("$name no es una fecha válida.");
        $date = $date->setTimezone(new DateTimeZone('UTC'));
        if ($date->format('Y') < '1970' || $date->format('Y') > '9999') throw new ApiException("$name está fuera del rango permitido.");
        if ($date > self::now()->modify('+5 minutes')) throw new ApiException("$name no puede estar en el futuro.");
        return $date;
    }

    public static function now(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone('UTC')); }
    public static function databaseDate(DateTimeImmutable $date): string { return $date->format('Y-m-d H:i:s.u'); }
    public static function outputDate(?string $value): ?string
    {
        return $value === null ? null : (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
    }
    public static function uuid(mixed $value, string $name): string
    {
        if (!Uuid::isValid($value)) throw new ApiException("$name debe ser un UUID válido.");
        return Uuid::stringToBytes($value);
    }
}
