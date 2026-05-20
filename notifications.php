<?php
// ============================================================
//  Ame Writer — notifications.php
//  View all notifications. Mark as read individually or all.
//  Delete with confirmation modal.
// ============================================================
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'database.php';

$db      = getDB();
$user_id = (int) $_SESSION['user_id'];

// ── POST handler ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')
           ->execute([$id, $user_id]);

    } elseif ($action === 'mark_unread') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->prepare('UPDATE notifications SET is_read=0 WHERE id=? AND user_id=?')
           ->execute([$id, $user_id]);

    } elseif ($action === 'mark_all_read') {
        $db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')
           ->execute([$user_id]);

    } elseif ($action === 'delete_notif') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM notifications WHERE id=? AND user_id=?')
           ->execute([$id, $user_id]);
    }

    header('Location: notifications.php');
    exit;
}

// Fetch notifications newest first
$stmt = $db->prepare(
    'SELECT n.*, u.name AS from_name, p.title AS project_title
     FROM notifications n
     JOIN users u ON u.id = n.from_user
     JOIN projects p ON p.id = n.project_id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC'
);
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$unread = count(array_filter($notifications, fn($n) => !$n['is_read']));

function notif_icon(string $type): string {
    return match($type) {
        'added_to_project'      => '#i-collab',
        'removed_from_project'  => '#i-x',
        'project_complete'      => '#i-check-c',
        'status_changed'        => '#i-pen',
        'project_deleted'       => '#i-trash',
        default                 => '#i-bell',
    };
}
function notif_time(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return (new DateTime($dt))->format('M j, Y');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ame Writer — Notifications</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="dashboard-body">

<?php include 'nav.php'; ?>

<div class="app-body app-body-centered">
  <main class="main-content" style="max-width:680px;margin-left:auto;margin-right:auto">

    <div class="page-header">
      <div>
        <a href="dashboard.php" class="btn-back-prominent" style="margin-bottom:.75rem;display:inline-flex">
          <svg><use href="#i-arrow-l"/></svg> Back to Dashboard
        </a>
        <h2>Notifications</h2>
        <p><?= $unread ?> unread notification<?= $unread !== 1 ? 's' : '' ?></p>
      </div>
      <?php if ($unread > 0): ?>
      <form method="POST" action="notifications.php">
        <input type="hidden" name="action" value="mark_all_read">
        <button class="btn-ghost" type="submit">
          <svg style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2"><use href="#i-check"/></svg>
          Mark all read
        </button>
      </form>
      <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
    <div class="empty-state">
      <svg><use href="#i-bell"/></svg>
      <h3>No notifications yet</h3>
      <p>You'll be notified when someone adds you to a project or updates a status.</p>
    </div>
    <?php else: ?>
    <div class="notif-list">
      <?php foreach ($notifications as $n): ?>
      <div class="notif-item<?= !$n['is_read'] ? ' unread' : '' ?>">
        <div class="notif-icon-wrap type-<?= htmlspecialchars($n['type']) ?>">
          <svg><use href="<?= notif_icon($n['type']) ?>"/></svg>
        </div>
        <div class="notif-body">
          <p class="notif-msg"><?= htmlspecialchars($n['message']) ?></p>
          <div class="notif-meta">
            <span class="notif-time"><?= notif_time($n['created_at']) ?></span>
            <?php if ($n['type'] !== 'project_deleted' && $n['type'] !== 'removed_from_project'): ?>
            <a href="project.php?id=<?= (int)$n['project_id'] ?>" class="link-btn" style="font-size:12px">View project</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="notif-actions">
          <?php if (!$n['is_read']): ?>
          <!-- Mark as read -->
          <form method="POST" action="notifications.php">
            <input type="hidden" name="action" value="mark_read">
            <input type="hidden" name="id"     value="<?= (int)$n['id'] ?>">
            <button class="btn-icon notif-action-btn" type="submit" title="Mark as read">
              <svg><use href="#i-check"/></svg>
            </button>
          </form>
          <?php else: ?>
          <!-- Mark as unread -->
          <form method="POST" action="notifications.php">
            <input type="hidden" name="action" value="mark_unread">
            <input type="hidden" name="id"     value="<?= (int)$n['id'] ?>">
            <button class="btn-icon notif-action-btn" type="submit" title="Mark as unread">
              <svg><use href="#i-inbox"/></svg>
            </button>
          </form>
          <?php endif; ?>
          <!-- Delete with confirmation -->
          <button class="btn-icon notif-action-btn notif-delete-btn"
                  type="button"
                  title="Delete notification"
                  onclick="openDeleteModal(<?= (int)$n['id'] ?>, <?= htmlspecialchars(json_encode($n['message']), ENT_QUOTES) ?>)">
            <svg><use href="#i-trash"/></svg>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- Delete confirmation modal -->
<div class="modal-backdrop" id="delete-notif-modal">
  <div class="modal" style="max-width:400px" role="dialog" aria-modal="true">
    <div class="modal-header">
      <h3>Delete Notification</h3>
      <button class="modal-close" onclick="closeDeleteModal()" type="button" aria-label="Close">
        <svg><use href="#i-x"/></svg>
      </button>
    </div>
    <div class="confirm-body">
      <div class="confirm-icon"><svg><use href="#i-warn"/></svg></div>
      <p>Are you sure you want to delete this notification?<br>
        <span id="del-notif-preview" style="display:block;margin-top:.5rem;font-size:12.5px;color:var(--ink-3);font-style:italic;line-height:1.5"></span>
      </p>
    </div>
    <form method="POST" action="notifications.php" id="delete-notif-form">
      <input type="hidden" name="action" value="delete_notif">
      <input type="hidden" name="id"     id="del-notif-id" value="">
      <div class="modal-footer">
        <button class="btn-ghost" type="button" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn-danger" type="submit">
          <svg style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><use href="#i-trash"/></svg>
          Delete
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openDeleteModal(id, message) {
  document.getElementById('del-notif-id').value       = id;
  document.getElementById('del-notif-preview').textContent = message;
  document.getElementById('delete-notif-modal').classList.add('open');
}
function closeDeleteModal() {
  document.getElementById('delete-notif-modal').classList.remove('open');
}
document.getElementById('delete-notif-modal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});
</script>

</body>
</html>
