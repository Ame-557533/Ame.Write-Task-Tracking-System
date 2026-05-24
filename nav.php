<?php
// ============================================================
//  Ame Writer — nav.php
//  Shared top nav + sidebar. Include after session_start()
//  and require_once 'database.php' on every protected page.
// ============================================================

$role_labels = [
    'speechwriter' => 'Speech Writer',
    'ghostwriter'  => 'Ghostwriter',
    'copywriter'   => 'Copywriter',
    'journalist'   => 'Journalist',
];

// Load user theme preference + unread notification count
$_nav_theme = 'light';
$_unread    = 0;
try {
    $_db_nav = getDB();

    // Load saved theme preference
    $_stmt_theme = $_db_nav->prepare('SELECT settings FROM users WHERE id=?');
    $_stmt_theme->execute([$_SESSION['user_id']]);
    $_settings_raw = $_stmt_theme->fetchColumn();
    if ($_settings_raw) {
        $_settings_arr = json_decode($_settings_raw, true) ?: [];
        if (($_settings_arr['theme'] ?? 'light') === 'dark') {
            $_nav_theme = 'dark';
        }
    }

    // Unread notification count
    $_stmt_unread = $_db_nav->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
    $_stmt_unread->execute([$_SESSION['user_id']]);
    $_unread = (int) $_stmt_unread->fetchColumn();

} catch (PDOException $e) {
    $_nav_theme = 'light';
    $_unread    = 0;
}

function nav_initials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr($parts[count($parts)-1], 0, 1));
    return $i;
}
?>
<?php include 'icons.php'; ?>
<script>document.documentElement.setAttribute('data-theme','<?= $_nav_theme ?>');</script>

<nav class="app-nav">
  <a href="dashboard.php" class="app-nav-brand">
    <div class="mark"><span>A</span></div>
    Ame Writer
  </a>
  <div class="nav-right">
    <a href="notifications.php" class="nav-notif-btn" title="Notifications">
      <svg><use href="#i-bell"/></svg>
      <?php if ($_unread > 0): ?>
      <span class="notif-dot"><?= $_unread > 9 ? '9+' : $_unread ?></span>
      <?php endif; ?>
    </a>
    <a href="profile.php" class="nav-avatar" title="My Profile" style="text-decoration:none;font-size:12px;width:32px;height:32px">
      <?= htmlspecialchars(nav_initials($_SESSION['user_name'])) ?>
    </a>
  </div>
</nav>


