<?php
//  Ame Writer — profile.php
//  View and edit name, email, role, and password.

session_start();
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
require_once 'database.php';

$db      = getDB();
$user_id = (int) $_SESSION['user_id'];
$success = '';
$error   = '';

// Fetch current user
$stmt = $db->prepare('SELECT id, name, email, role, created_at FROM users WHERE id=?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// POST handler 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update profile info 
    if ($action === 'update_profile') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = trim($_POST['role']  ?? '');
        $allowed_roles = ['speechwriter','ghostwriter','copywriter','journalist'];

        if (empty($name)) {
            $error = 'Full name is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!in_array($role, $allowed_roles)) {
            $error = 'Please select a valid role.';
        } else {
            // Check email not taken by another user
            $stmt = $db->prepare('SELECT id FROM users WHERE email=? AND id != ?');
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'That email is already in use by another account.';
            } else {
                $db->prepare('UPDATE users SET name=?, email=?, role=? WHERE id=?')
                   ->execute([$name, $email, $role, $user_id]);
                // Update session
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role']  = $role;
                $success = 'Profile updated successfully.';
                // Refresh user data
                $stmt = $db->prepare('SELECT id, name, email, role, created_at FROM users WHERE id=?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        }

    // Change password 
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new_pw  = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Fetch current hash
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id=?');
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if (!password_verify($current, $row['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_pw) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new_pw !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')
               ->execute([$hash, $user_id]);
            $success = 'Password changed successfully.';
        }
    }
}

// Stats
$stmt = $db->prepare('SELECT COUNT(*) FROM projects WHERE owner_id=?');
$stmt->execute([$user_id]);
$owned = (int) $stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(DISTINCT pc.project_id) FROM project_collaborators pc
                      JOIN projects p ON p.id = pc.project_id
                      WHERE pc.user_id=? AND p.owner_id != ?');
$stmt->execute([$user_id, $user_id]);
$collab_count = (int) $stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM projects p
                      JOIN project_collaborators pc ON pc.project_id=p.id
                      WHERE pc.user_id=? AND p.status="complete"');
$stmt->execute([$user_id]);
$completed = (int) $stmt->fetchColumn();

$role_labels = ['speechwriter'=>'Speech Writer','ghostwriter'=>'Ghostwriter','copywriter'=>'Copywriter','journalist'=>'Journalist'];

function prof_initials(string $name): string {
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
  <title>Ame Writer — Profile</title>
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
        <h2>My Profile</h2>
        <p>Manage your account details and password.</p>
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

    <!--Profile header card-->
    <div class="profile-hero">
      <div class="profile-avatar-lg"><?= htmlspecialchars(prof_initials($user['name'])) ?></div>
      <div>
        <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="profile-role"><?= htmlspecialchars($role_labels[$user['role']] ?? ucfirst($user['role'])) ?></div>
        <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
        <div style="margin-top:4px;font-size:11.5px;color:var(--ink-3)">
          User ID: <strong style="color:var(--ink-2)">#<?= (int)$user['id'] ?></strong>
          <span style="margin-left:6px;font-size:10.5px;color:var(--ink-3)">(used for password recovery)</span>
        </div>
      </div>
      <div class="profile-stats">
        <div class="profile-stat"><span class="profile-stat-val"><?= $owned ?></span><span class="profile-stat-label">Owned</span></div>
        <div class="profile-stat"><span class="profile-stat-val"><?= $collab_count ?></span><span class="profile-stat-label">Collaborations</span></div>
        <div class="profile-stat"><span class="profile-stat-val green"><?= $completed ?></span><span class="profile-stat-label">Completed</span></div>
      </div>
    </div>

    <!--Edit profile form-->
    <div class="profile-section">
      <h3 class="profile-section-title">Account Information</h3>
      <form method="POST" action="profile.php">
        <input type="hidden" name="action" value="update_profile">
        <div class="field">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required />
        </div>
        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required />
        </div>
        <div class="field">
          <label for="role">Writer role</label>
          <select id="role" name="role">
            <option value="speechwriter" <?= $user['role']==='speechwriter'?'selected':'' ?>>Speech Writer</option>
            <option value="ghostwriter"  <?= $user['role']==='ghostwriter' ?'selected':'' ?>>Ghostwriter</option>
            <option value="copywriter"   <?= $user['role']==='copywriter'  ?'selected':'' ?>>Copywriter</option>
            <option value="journalist"   <?= $user['role']==='journalist'  ?'selected':'' ?>>Journalist</option>
          </select>
        </div>
        <button class="btn-accent" type="submit" style="width:auto">Save Changes</button>
      </form>
    </div>

    <!--Change password form-->
    <div class="profile-section">
      <h3 class="profile-section-title">Change Password</h3>
      <form method="POST" action="profile.php">
        <input type="hidden" name="action" value="change_password">
        <div class="field">
          <label for="current_password">Current password</label>
          <div class="pw-wrap">
            <input type="password" id="current_password" name="current_password" placeholder="••••••••" required />
            <button class="pw-toggle" onclick="togglePw('current_password',this)" type="button"><svg><use href="#i-eye"/></svg></button>
          </div>
        </div>
        <div class="field">
          <label for="new_password">New password</label>
          <div class="pw-wrap">
            <input type="password" id="new_password" name="new_password" placeholder="Min. 8 characters"
                   oninput="checkStrength(this.value)" required />
            <button class="pw-toggle" onclick="togglePw('new_password',this)" type="button"><svg><use href="#i-eye"/></svg></button>
          </div>
          <div class="strength-bar">
            <div class="s-seg" id="s0"></div><div class="s-seg" id="s1"></div>
            <div class="s-seg" id="s2"></div><div class="s-seg" id="s3"></div>
          </div>
          <p class="pw-hint" id="str-label"></p>
        </div>
        <div class="field">
          <label for="confirm_password">Confirm new password</label>
          <div class="pw-wrap">
            <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required />
            <button class="pw-toggle" onclick="togglePw('confirm_password',this)" type="button"><svg><use href="#i-eye"/></svg></button>
          </div>
        </div>
        <button class="btn-accent" type="submit" style="width:auto">Change Password</button>
      </form>
    </div>

    <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--border);">
      <p style="font-size:12px;color:var(--ink-3)">
        Member since <?= (new DateTime($user['created_at']))->format('F j, Y') ?>
        &nbsp;·&nbsp; User ID: <strong>#<?= (int)$user['id'] ?></strong>
      </p>
    </div>

  </main>
</div>

<script>
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  const showing = inp.type === 'text';
  inp.type = showing ? 'password' : 'text';
  btn.querySelector('use').setAttribute('href', showing ? '#i-eye' : '#i-eye-off');
}
function checkStrength(v) {
  let score = 0;
  if (v.length >= 8) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  const colors = ['#DC2626','#D97706','#1A9E6A','#0f6e56'];
  const labels = ['Too short','Weak','Good','Strong'];
  for (let i = 0; i < 4; i++) {
    document.getElementById('s'+i).style.background = i < score ? colors[Math.min(score-1,3)] : 'rgba(0,0,0,0.09)';
  }
  document.getElementById('str-label').textContent = v.length ? labels[Math.min(score,3)] : '';
}
</script>
</body>
</html>
