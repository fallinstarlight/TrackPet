<?php

namespace TrackPet\config;

use PDO;
use RuntimeException;

class Connection
{
    public static ?PDO $connection = null;
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) return self::$connection;

        $envVars = is_file(__DIR__ . '/.env')
            ? parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW) : [];
        if ($envVars === false) throw new RuntimeException('Invalid database configuration');
        $value = static function (string $key, ?string $default = null) use ($envVars): ?string {
            $environment = getenv($key);
            return $environment !== false ? $environment : ($envVars[$key] ?? $default);
        };
        $server = $value('SERVER', '127.0.0.1');
        $port = $value('PORT', '3306');
        $database = $value('DATABASE');
        $username = $value('DB_USER') ?? $envVars['USER'] ?? $envVars['USERNAME']
            ?? $value('USER') ?? $value('USERNAME');
        $password = $value('PASSWORD', '');
        $socket = $value('DB_SOCKET');
        if (!$database || $username === null || !ctype_digit($port)
            || (int)$port < 1 || (int)$port > 65535) {
            throw new RuntimeException('Missing or invalid database configuration');
        }
        foreach ([$server, $database, $socket] as $part) {
            if ($part !== null && preg_match('/[;\r\n]/', $part)) {
                throw new RuntimeException('Invalid database configuration');
            }
        }
        $options = [
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ];

        $address = $socket ? "unix_socket={$socket}" : "host={$server};port={$port}";
        self::$connection = new PDO("mysql:{$address};dbname={$database};charset=utf8mb4", $username, $password, $options);
        return self::$connection;
    }
}
