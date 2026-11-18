<?php
namespace TrackPet\utils;
use PDOException;
use TrackPet\model\response\Response;

class DbErrorHandler
{
    /**
     * Map a custom SQLSTATE to a user-safe message.
     * Returns null if the SQLSTATE is not one of our custom codes.
     */
    public static function customMessage(string $sqlState): ?string
    {
        return match ($sqlState) {
            '45000' => 'Resource not found',
            '45001' => 'Username already exists',
            '45002' => 'Email or phone already in use',
            '45003' => 'User does not exist',
            '45004' => 'User is not available',
            '45005' => 'Email, phone or username already in use',
            '45006' => 'Owner does not exist',
            '45007' => 'Pet ID collision, please retry',
            '45008' => 'Pet does not exist or is not active',
            '45009' => 'New owner does not exist',
            '45010' => 'Both owner IDs are the same',
            '45011' => 'User, pet or ownership does not exist',
            '45012' => 'Cierra el reporte activo antes de transferir la mascota.',
            default => null,
        };
    }

    /**
     * True if the exception is one we deliberately raised via SIGNAL.
     * Custom SQLSTATEs must start with '45' (user-defined) per SQL standard.
     */
    public static function isCustom(PDOException $e): bool
    {
        return str_starts_with((string)$e->getCode(), '45');
    }

    /**
     * Apply the exception's meaning to a Response object.
     * Returns true if it was a custom error (already handled),
     * false if it was an unexpected DB error.
     */
    public static function apply(PDOException $e, Response $response): bool
    {
        if (self::isCustom($e)) {
            $response->flag = match ((string)$e->getCode()) {
                '45000', '45003', '45006', '45008', '45009' => Response::FLAG_NOT_FOUND,
                '45001', '45002', '45005', '45007', '45012' => Response::FLAG_CONFLICT,
                '45004', '45011' => Response::FLAG_FORBIDDEN,
                default => Response::FLAG_USER_ERROR,
            };
            $response->status  = Response::STATUS_ERROR;
            $response->message = self::customMessage((string)$e->getCode())
                ?? 'Invalid request';
            return true;
        }

        if ((string)$e->getCode() === '23000' && ($e->errorInfo[1] ?? null) === 1062) {
            $response->flag = Response::FLAG_CONFLICT;
            $response->message = 'A unique value is already in use';
            return true;
        }
        // Generic / sensitive error — do NOT expose $e->getMessage()
        error_log('[DB ERROR] ' . $e->getMessage());
        $response->flag    = Response::FLAG_SERVER_ERROR;
        $response->status  = Response::STATUS_ERROR;
        $response->message = 'There was an unexpected error, please try again';
        return false;
    }
}
