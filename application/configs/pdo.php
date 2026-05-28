<?php
$config = require __DIR__ . '/database.php';

$host = $config['host'];
$db   = $config['dbname'];
$user = $config['username'];
$pass = $config['password'];
$port = $config['port'];
$driver = $config['driver'];
$charset = 'utf8mb4';

$dsn = "$driver:host=$host;port=$port;dbname=$db;charset=$charset";

$options = array(
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
);

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    if ($driver === 'mysql') {
        $pdo->exec("SET time_zone = '+07:00'");
    }
} catch (\PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
