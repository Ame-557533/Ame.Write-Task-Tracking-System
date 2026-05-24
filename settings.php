<?php
// ============================================================
//  Ame Writer — settings.php
//  Account settings: notifications, about, danger zone.
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

    // ── Save theme preference ─────────────────────────────
    if ($action === 'save_preferences') {
        $theme = in_array($_POST['theme'] ?? '', ['light','dark']) ? $_POST['theme'] : 'light';

        // Keep existing notif_enabled if already saved
        $existing = json_decode($user['settings'] ?? '{}', true) ?: [];
        $prefs = json_encode([
            'notif_enabled' => $existing['notif_enabled'] ?? 1,
            'theme'         => $theme,
        ]);

        // Auto-create missing columns on first save
        try { $db->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS settings TEXT NULL DEFAULT NULL'); }
        catch (PDOException $e) {}
        try { $db->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT NULL'); }
        catch (PDOException $e) {}

        try {
            $db->prepare('UPDATE users SET settings=?, updated_at=NOW() WHERE id=?')
               ->execute([$prefs, $user_id]);
            $success = 'Preferences saved.';
        } catch (PDOException $e) {
            $error = 'Could not save preferences: ' . htmlspecialchars($e->getMessage());
        }

    // ── Delete account ────────────────────────────────────
    } elseif ($action === 'delete_account') {
        $confirm_text = trim($_POST['confirm_text'] ?? '');
        if ($confirm_text !== 'DELETE') {
            $error = 'Please type DELETE exactly to confirm.';
        } else {
            try { $db->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1'); }
            catch (PDOException $e) {}
            $db->prepare('DELETE FROM project_collaborators WHERE user_id=?')->execute([$user_id]);
            $db->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$user_id]);
            session_unset(); session_destroy();
            header('Location: login.php');
            exit;
        }
    }

    // Re-fetch user after save
    if (!$error) {
        $stmt = $db->prepare('SELECT * FROM users WHERE id=?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
}

// Parse saved prefs
$prefs_raw     = $user['settings'] ?? '{}';
$prefs         = json_decode($prefs_raw, true) ?: [];
$notif_enabled = isset($prefs['notif_enabled']) ? (int)$prefs['notif_enabled'] : 1;
$theme         = ($prefs['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';

$role_labels = [
    'speechwriter' => 'Speech Writer',
    'ghostwriter'  => 'Ghostwriter',
    'copywriter'   => 'Copywriter',
    'journalist'   => 'Journalist',
];

function s_initials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr($parts[count($parts)-1], 0, 1));
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
  <main class="main-content" style="max-width:680px;margin-left:auto;margin-right:auto">

    <!-- Page header -->
    <div class="page-header">
      <div>
        <h2>Settings</h2>
        <p>Manage your preferences and account.</p>
      </div>
      <a href="dashboard.php" class="btn-back-prominent">
        <svg style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><use href="#i-arrow-l"/></svg>
        Back to Dashboard
      </a>
    </div>

    <?php if ($error): ?>
    <div class="php-error"><svg><use href="#i-alert"/></svg><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="php-success"><svg><use href="#i-check-c"/></svg><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Account card -->
    <div class="profile-section">
      <h3 class="profile-section-title">Account</h3>
      <div class="settings-account-row">
        <div class="profile-avatar-lg" style="width:46px;height:46px;font-size:15px;flex-shrink:0">
          <?= htmlspecialchars(s_initials($user['name'])) ?>
        </div>
        <div class="settings-account-info">
          <span class="settings-account-name"><?= htmlspecialchars($user['name']) ?></span>
          <span class="settings-account-email"><?= htmlspecialchars($user['email']) ?></span>
          <span class="settings-account-role"><?= htmlspecialchars($role_labels[$user['role']] ?? ucfirst($user['role'])) ?></span>
          <span style="font-size:11px;color:var(--ink-3);margin-top:1px">User ID: <strong style="color:var(--ink-2)">#<?= sprintf('%05d', (int)$user['id']) ?></strong></span>
        </div>
        <a href="profile.php" class="btn-ghost" style="margin-left:auto;white-space:nowrap;flex-shrink:0">
          <svg style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2"><use href="#i-edit"/></svg>
          Edit Profile
        </a>
      </div>
    </div>

    <!-- Preferences: Appearance only -->
    <div class="profile-section">
      <h3 class="profile-section-title">Preferences</h3>
      <form method="POST" action="settings.php">
        <input type="hidden" name="action" value="save_preferences">

        <!-- Appearance -->
        <div class="pref-row">
          <div class="pref-row-label">
            <div class="settings-toggle-title">Appearance</div>
            <div class="settings-toggle-sub">Choose your preferred color theme.</div>
          </div>
          <div class="theme-picker">
            <label class="theme-option">
              <input type="radio" name="theme" value="light"
                     <?= $theme === 'light' ? 'checked' : '' ?>
                     onchange="applyThemePreview('light')">
              <span class="theme-swatch theme-swatch-light">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                Light
              </span>
            </label>
            <label class="theme-option">
              <input type="radio" name="theme" value="dark"
                     <?= $theme === 'dark' ? 'checked' : '' ?>
                     onchange="applyThemePreview('dark')">
              <span class="theme-swatch theme-swatch-dark">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                Dark
              </span>
            </label>
          </div>
        </div>

        <button class="btn-accent" type="submit" style="width:auto;margin-top:1.25rem">Save Preferences</button>
      </form>
    </div>

    <!-- About -->
    <div class="profile-section">
      <h3 class="profile-section-title">About</h3>
      <div class="pdc-grid" style="gap:.5rem;margin-bottom:1rem">
        <div class="pdc-item"><span class="pdc-key">App</span><span class="pdc-val">Ame Writer</span></div>
        <div class="pdc-item"><span class="pdc-key">Version</span><span class="pdc-val">1.0.0</span></div>
        <div class="pdc-item">
          <span class="pdc-key">Member since</span>
          <span class="pdc-val"><?= (new DateTime($user['created_at']))->format('F j, Y') ?></span>
        </div>
      </div>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap">
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
        Deleting your account will deactivate it and remove you from all projects. This cannot be undone.
      </p>
      <button class="btn-ghost" type="button" id="show-delete-btn"
              onclick="document.getElementById('delete-panel').style.display='block';this.style.display='none'"
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
            <button class="btn-ghost" type="button"
                    onclick="document.getElementById('delete-panel').style.display='none';document.getElementById('show-delete-btn').style.display=''">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

<script>
function applyThemePreview(theme) {
  document.documentElement.setAttribute('data-theme', theme);
}
</script>
</body>
</html>
