<?php
// DATABASE CREDENTIALS
$host     = getenv('MYSQLHOST')     ?: 'localhost';
$port     = getenv('MYSQLPORT')     ?: '3306';
$dbname   = getenv('MYSQLDATABASE') ?: 'ims_db';
$username = getenv('MYSQLUSER')     ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';

try {
    // CONNECTION STRING
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    // PDO
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Shows SQL errors clearly
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetches data as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Maximizes SQL injection security
    ]);

    echo "Database connected successfully!"; 

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>