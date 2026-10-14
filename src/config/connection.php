<?php

namespace TrackPet\config;

use PDO;
use PDOException;

class Connection
{
    public static ?PDO $connection = null;
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) return self::$connection;

        $envVars = parse_ini_file(__DIR__."/.env");

        foreach ($envVars as $key => $value) {
            putenv("$key=$value");
        }

        $server = getenv('SERVER');
        $database = getenv('DATABASE');
        $username = getenv('USERNAME');
        $password = getenv('PASSWORD');
        $options = [
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            $CONN = new PDO("mysql:host={$server};dbname={$database};charset=utf8mb4", $username, $password, $options);
        } catch (PDOException $e) {
            die(json_encode([
                "status"  => "error",
                "message" => "Database connection failed".$e->getMessage()
            ]));
        }
        return $CONN;
    }
}
