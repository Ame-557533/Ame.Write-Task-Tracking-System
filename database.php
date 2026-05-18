<?php
// ============================================================
//  Ame Co. Workspace — database.php
//  Shared PDO connection. Include in every PHP page.
//
//  Localhost defaults are set below.
//  On deployment: swap credentials or use environment vars.
// ============================================================

define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'ameco_db');
define('DB_USER',     'root');
define('DB_PASSWORD', '');           // change on deployment
define('DB_CHARSET',  'utf8mb4');

/**
 * Returns a singleton PDO instance.
 * Terminates with a 500 error on connection failure.
 */
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
            // Show a friendly error instead of a blank page
            http_response_code(500);
            die('
            <div style="font-family:sans-serif;max-width:500px;margin:80px auto;padding:2rem;
                        background:#fff1f1;border:1px solid #fca5a5;border-radius:12px;color:#7f1d1d;">
                <strong>Database connection failed.</strong><br><br>
                ' . htmlspecialchars($e->getMessage()) . '<br><br>
                <small>Check your credentials in <code>database.php</code> and make sure MySQL is running.</small>
            </div>');
        }
    }

    return $pdo;
}
