<?php
//  Ame Writer — writing.php
//  AJAX endpoint for saving and loading writing content.
//  Only project collaborators can access.

session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

require_once 'database.php';
$db      = getDB();
$user_id = (int) $_SESSION['user_id'];
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

// Load content 
if ($action === 'load') {
    $project_id = (int) ($_GET['project_id'] ?? 0);

    // Must be a collaborator
    $stmt = $db->prepare(
        'SELECT pc.project_id FROM project_collaborators pc WHERE pc.project_id=? AND pc.user_id=?'
    );
    $stmt->execute([$project_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Access denied']); exit;
    }

    $stmt = $db->prepare('SELECT content FROM writing_content WHERE project_id=?');
    $stmt->execute([$project_id]);
    $row = $stmt->fetch();

    echo json_encode(['ok' => true, 'content' => $row['content'] ?? '']);
    exit;
}

// Save content 
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $content    = $_POST['content'] ?? '';

    // Must be a collaborator
    $stmt = $db->prepare(
        'SELECT pc.project_id FROM project_collaborators pc WHERE pc.project_id=? AND pc.user_id=?'
    );
    $stmt->execute([$project_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Access denied']); exit;
    }

    // Upsert — insert or update
    $stmt = $db->prepare(
        'INSERT INTO writing_content (project_id, content, last_edited_by, updated_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE content=VALUES(content), last_edited_by=VALUES(last_edited_by), updated_at=NOW()'
    );
    $stmt->execute([$project_id, $content, $user_id]);

    // Log to activity
    $db->prepare('INSERT INTO activity_log (user_id, action, target, target_id) VALUES (?,?,?,?)')
       ->execute([$user_id, 'edited_writing', 'projects', $project_id]);

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
