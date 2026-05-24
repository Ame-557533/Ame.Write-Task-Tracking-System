<?php
// ============================================================
//  Ame Writer — database.php
//  Shared PDO connection. Include in every PHP page.
//
//  Railway environment variables with localhost fallbacks.
// ============================================================

$host   = $_ENV['MYSQLHOST']     ?? getenv('MYSQLHOST')     ?? 'localhost';
$port   = $_ENV['MYSQLPORT']     ?? getenv('MYSQLPORT')     ?? '3306';
$dbname = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? 'amewriter_db';
$user   = $_ENV['MYSQLUSER']     ?? getenv('MYSQLUSER')     ?? 'root';
$pass   = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? getenv('MYSQLROOT_PASSWORD') ?? '';

define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO instance.
 * Shows a friendly error page on connection failure.
 */
function getDB(): PDO {
    static $pdo = null;

    // Use the variables defined above
    global $host, $port, $dbname, $user, $pass;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host, $port, $dbname, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('
            <div style="font-family:sans-serif;max-width:500px;margin:80px auto;padding:2rem;
                        background:#fff1f1;border:1px solid #fca5a5;border-radius:12px;color:#7f1d1d;">
                <strong>Database connection failed.</strong><br><br>
                ' . htmlspecialchars($e->getMessage()) . '<br><br>
                <small>Check your environment variables in Railway or credentials in <code>database.php</code>.</small>
            </div>');
        }
    }

    return $pdo;
}

/**
 * Check if a user has notifications enabled (defaults to true if no setting saved).
 * Safe to call even if the settings column doesn't exist yet.
 */
function userWantsNotification(int $user_id): bool {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT settings FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $raw  = $stmt->fetchColumn();
        if (!$raw) return true;
        $prefs = json_decode($raw, true) ?: [];
        return ($prefs['notif_enabled'] ?? 1) == 1;
    } catch (PDOException $e) {
        return true; // column missing — default to on
    }
}

/**
 * Insert a notification only if the recipient has them enabled.
 */
function sendNotification(int $to_user_id, int $from_user_id, int $project_id, string $type, string $message): void {
    if (!userWantsNotification($to_user_id)) return;
    try {
        $pdo = getDB();
        $pdo->prepare('INSERT INTO notifications (user_id, from_user, project_id, type, message) VALUES (?,?,?,?,?)')
            ->execute([$to_user_id, $from_user_id, $project_id, $type, $message]);
    } catch (PDOException $e) {
        // notifications table may not exist yet — silently skip
    }
}