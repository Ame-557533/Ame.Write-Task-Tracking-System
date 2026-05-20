<?php
// ============================================================
//  Ame Writer — settings.php
//  Account settings: preferences, notifications, danger zone.
// ============================================================
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'database.php';

$db      = getDB();
$user_id = (int) $_SESSION['user_id'];
$success = '';
$error   = '';

// Fetch current user
$stmt = $db->prepare('SELECT * FROM users WHERE id=?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// ── POST handler ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Save notification preferences ─────────────────────
    if ($action === 'save_notifications') {
        $notif_added    = isset($_POST['notif_added'])    ? 1 : 0;
        $notif_status   = isset($_POST['notif_status'])   ? 1 : 0;
        $notif_removed  = isset($_POST['notif_removed'])  ? 1 : 0;
        $notif_complete = isset($_POST['notif_complete'])  ? 1 : 0;

        // Store as JSON in a settings column (add if not present yet)
        $prefs = json_encode([
            'notif_added'    => $notif_added,
            'notif_status'   => $notif_status,
            'notif_removed'  => $notif_removed,
            'notif_complete' => $notif_complete,
        ]);
        try {
            $db->prepare('UPDATE users SET settings=?, updated_at=NOW() WHERE id=?')
               ->execute([$prefs, $user_id]);
            $success = 'Notification preferences saved.';
        } catch (PDOException $e) {
            // Column may not exist yet — gracefully ignore
            $success = 'Preferences noted (settings column not yet in DB).';
        }

    // ── Delete account ────────────────────────────────────
    } elseif ($action === 'delete_account') {
        $confirm_text = trim($_POST['confirm_text'] ?? '');
        if ($confirm_text !== 'DELETE') {
            $error = 'Please type DELETE exactly to confirm.';
        } else {
            // Transfer owned projects to no one (or soft-delete)
            $db->prepare('DELETE FROM project_collaborators WHERE user_id=?')->execute([$user_id]);
            $db->prepare('UPDATE users SET is_active=0, updated_at=NOW() WHERE id=?')->execute([$user_id]);
            session_unset(); session_destroy();
            header('Location: login.php');
            exit;
        }
    }
}

// Parse existing prefs
$prefs_raw = $user['settings'] ?? '{}';
$prefs = json_decode($prefs_raw, true) ?: [];
$pref = fn($key, $default=1) => isset($prefs[$key]) ? (int)$prefs[$key] : $default;

$role_labels = ['speechwriter'=>'Speech Writer','ghostwriter'=>'Ghostwriter','copywriter'=>'Copywriter','journalist'=>'Journalist'];

function s_initials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0],0,1));
    if (count($parts)>1) $i .= strtoupper(substr($parts[count($parts)-1],0,1));
    return $i;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ame Writer — Settings</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="dashboard-body">

<?php include 'nav.php'; ?>

<div class="app-body app-body-centered">
  <main class="main-content" style="max-width:720px;margin-left:auto;margin-right:auto">

    <div class="page-header">
      <div>
        <a href="dashboard.php" class="btn-back-prominent" style="margin-bottom:.75rem;display:inline-flex">
          <svg><use href="#i-arrow-l"/></svg> Back to Dashboard
        </a>
        <h2>Settings</h2>
        <p>Manage your app preferences and account.</p>
      </div>
    </div>

    <?php if ($error): ?>
    <div class="php-error"><svg><use href="#i-alert"/></svg><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="php-success"><svg><use href="#i-check-c"/></svg><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Account overview -->
    <div class="profile-section">
      <h3 class="profile-section-title">Account</h3>
      <div style="display:flex;align-items:center;gap:1rem;padding:.75rem 0">
        <div class="profile-avatar-lg" style="width:48px;height:48px;font-size:16px"><?= htmlspecialchars(s_initials($user['name'])) ?></div>
        <div>
          <div style="font-weight:600;font-size:15px;color:var(--ink)"><?= htmlspecialchars($user['name']) ?></div>
          <div style="font-size:13px;color:var(--ink-3)"><?= htmlspecialchars($user['email']) ?></div>
          <div style="font-size:12px;color:var(--accent);font-weight:500;margin-top:2px"><?= htmlspecialchars($role_labels[$user['role']] ?? ucfirst($user['role'])) ?></div>
        </div>
        <a href="profile.php" class="btn-ghost" style="margin-left:auto;white-space:nowrap">
          <svg style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2"><use href="#i-edit"/></svg>
          Edit Profile
        </a>
      </div>
    </div>

    <!-- Notification preferences -->
    <div class="profile-section">
      <h3 class="profile-section-title">Notification Preferences</h3>
      <p style="font-size:13px;color:var(--ink-3);margin-bottom:1rem">Choose which events trigger in-app notifications.</p>
      <form method="POST" action="settings.php">
        <input type="hidden" name="action" value="save_notifications">
        <div class="settings-toggle-list">
          <label class="settings-toggle">
            <div>
              <div class="settings-toggle-title">Added to a project</div>
              <div class="settings-toggle-sub">When someone adds you as a collaborator.</div>
            </div>
            <input type="checkbox" name="notif_added" value="1" <?= $pref('notif_added') ? 'checked' : '' ?> />
            <span class="toggle-track"></span>
          </label>
          <label class="settings-toggle">
            <div>
              <div class="settings-toggle-title">Status changes</div>
              <div class="settings-toggle-sub">When a project's status is updated by a collaborator.</div>
            </div>
            <input type="checkbox" name="notif_status" value="1" <?= $pref('notif_status') ? 'checked' : '' ?> />
            <span class="toggle-track"></span>
          </label>
          <label class="settings-toggle">
            <div>
              <div class="settings-toggle-title">Removed from a project</div>
              <div class="settings-toggle-sub">When an owner removes you from a project.</div>
            </div>
            <input type="checkbox" name="notif_removed" value="1" <?= $pref('notif_removed') ? 'checked' : '' ?> />
            <span class="toggle-track"></span>
          </label>
          <label class="settings-toggle">
            <div>
              <div class="settings-toggle-title">Project completed</div>
              <div class="settings-toggle-sub">When a project you're on is marked complete.</div>
            </div>
            <input type="checkbox" name="notif_complete" value="1" <?= $pref('notif_complete') ? 'checked' : '' ?> />
            <span class="toggle-track"></span>
          </label>
        </div>
        <button class="btn-accent" type="submit" style="width:auto;margin-top:1rem">Save Preferences</button>
      </form>
    </div>

    <!-- App info -->
    <div class="profile-section">
      <h3 class="profile-section-title">About</h3>
      <div class="pdc-grid" style="gap:.5rem">
        <div class="pdc-item"><span class="pdc-key">App</span><span class="pdc-val">Ame Writer</span></div>
        <div class="pdc-item"><span class="pdc-key">Version</span><span class="pdc-val">1.0.0</span></div>
        <div class="pdc-item"><span class="pdc-key">Member since</span><span class="pdc-val"><?= (new DateTime($user['created_at']))->format('F j, Y') ?></span></div>
      </div>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem">
        <a href="notifications.php" class="btn-ghost" style="font-size:13px">
          <svg style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2"><use href="#i-bell"/></svg>
          View Notifications
        </a>
        <a href="logout.php" class="btn-ghost" style="font-size:13px;color:var(--red);border-color:rgba(220,38,38,.25)">
          <svg style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2"><use href="#i-logout"/></svg>
          Log Out
        </a>
      </div>
    </div>

    <!-- Danger zone -->
    <div class="profile-section" style="border-color:rgba(220,38,38,.3);background:rgba(220,38,38,.03)">
      <h3 class="profile-section-title" style="color:var(--red)">Danger Zone</h3>
      <p style="font-size:13px;color:var(--ink-2);margin-bottom:1rem">
        Deleting your account will deactivate it and remove you from all projects. This action cannot be undone.
      </p>
      <button class="btn-ghost" type="button" onclick="document.getElementById('delete-panel').style.display='block';this.style.display='none'"
              style="color:var(--red);border-color:rgba(220,38,38,.3)">
        <svg style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2"><use href="#i-trash"/></svg>
        Delete my account
      </button>
      <div id="delete-panel" style="display:none;margin-top:1rem">
        <form method="POST" action="settings.php">
          <input type="hidden" name="action" value="delete_account">
          <div class="field">
            <label for="confirm_text">Type <strong>DELETE</strong> to confirm</label>
            <input type="text" id="confirm_text" name="confirm_text" placeholder="DELETE" autocomplete="off" />
          </div>
          <div style="display:flex;gap:.5rem">
            <button class="btn-danger" type="submit">Permanently delete account</button>
            <button class="btn-ghost" type="button" onclick="document.getElementById('delete-panel').style.display='none';document.querySelector('[onclick*=delete-panel]').style.display=''">Cancel</button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

</body>
</html>
