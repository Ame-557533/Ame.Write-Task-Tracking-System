<?php
//  Ame Co. Tasks — Database Connection
//  File: database.php
//
//  For localhost:  fill in the credentials below.
//  For deployment: replace with your hosting credentials.

define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'ameco_tasks');
define('DB_USER',     'root');       // change on deployment
define('DB_PASSWORD', '');           // change on deployment
define('DB_CHARSET',  'utf8mb4');

//Create connection
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }

    return $pdo;
}
?>
