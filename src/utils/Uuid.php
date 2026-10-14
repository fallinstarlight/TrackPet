<?php

namespace TrackPet\utils;

use InvalidArgumentException;

class Uuid
{
    /**
     * Generate an RFC 9562 UUIDv7 as raw 16 bytes (for BINARY(16) columns).
     *
     * Layout:
     *  48 bits unix_ts_ms | 4 bits version(7) | 12 bits rand_a
     *  2 bits variant(10) | 62 bits rand_b
     */
    public static function v7Bytes(): string
    {
        $ms = (int) floor(microtime(true) * 1000);

        // 48-bit big-endian timestamp: 12 hex chars
        $tsHex = str_pad(dechex($ms), 12, '0', STR_PAD_LEFT);

        // 12 bits rand_a, prefixed by version nibble '7' → 4 hex chars total
        $randA = random_int(0, 0xFFF);
        $verAndA = '7' . str_pad(dechex($randA), 3, '0', STR_PAD_LEFT);

        // 62 bits rand_b, with top 2 bits forced to '10' (variant) → 16 hex chars
        $randB = random_int(0, (1 << 62) - 1);
        $variantBits = (0b10 << 62) | $randB;                 // 64-bit value
        $randBHex = str_pad(dechex($variantBits), 16, '0', STR_PAD_LEFT);

        $hex = $tsHex . $verAndA . $randBHex;                 // 12 + 4 + 16 = 32
        return hex2bin($hex);                                 // 16 bytes
    }

    /** Same as v7Bytes() but as the canonical string form (for display / API output). */
    public static function v7String(): string
    {
        $b = self::v7Bytes();
        $h = bin2hex($b);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($h, 0, 8),
            substr($h, 8, 4),
            substr($h, 12, 4),
            substr($h, 16, 4),
            substr($h, 20, 12)
        );
    }

    /** Convert a canonical UUID string to raw 16 bytes. */
    public static function stringToBytes(string $uuid): string
    {
        $hex = str_replace('-', '', $uuid);
        if (!ctype_xdigit($hex) || strlen($hex) !== 32) {
            throw new InvalidArgumentException('Invalid UUID string');
        }
        return hex2bin($hex);
    }

    /** Convert raw 16 bytes to canonical UUID string. */
    public static function bytesToString(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            throw new InvalidArgumentException('UUID bytes must be exactly 16 bytes');
        }
        $h = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($h, 0, 8),
            substr($h, 8, 4),
            substr($h, 12, 4),
            substr($h, 16, 4),
            substr($h, 20, 12)
        );
    }
}
