<?php
$envVars = parse_ini_file(".env");
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
} catch (PDOException $e) { ?>

<?php
    die();
}
