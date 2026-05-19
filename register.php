<?php
// ============================================================
//  Ame Writer — register.php
//  Writer self-registration with role selection.
// ============================================================
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'database.php';

$error   = '';
$success = '';
$old     = ['name' => '', 'email' => '', 'role' => 'copywriter'];

// ── POST handler ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $role     = trim($_POST['role']     ?? 'copywriter');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    $old = ['name' => $name, 'email' => $email, 'role' => $role];

    $allowed_roles = ['speechwriter', 'ghostwriter', 'copywriter', 'journalist'];

    if (empty($name)) {
        $error = 'Full name is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($role, $allowed_roles)) {
        $error = 'Please select a valid writer role.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare(
                'INSERT INTO users (name, email, role, password_hash, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$name, $email, $role, $hash]);
            $success = 'Account created! Redirecting to sign in…';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ame Writer — Create Account</title>
  <?php if ($success): ?>
  <meta http-equiv="refresh" content="2;url=login.php">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>
<body>

<?php include 'icons.php'; ?>

<div class="auth-page">
  <div class="auth-brand">
    <div class="auth-mark"><span>A</span></div>
    <span class="auth-brand-name">Ame Writer</span>
  </div>

  <div class="auth-card">
    <h1>Create your account</h1>
    <p class="auth-sub">Join Ame Writer and start collaborating.</p>

    <?php if ($error): ?>
    <div class="php-error">
      <svg><use href="#i-alert"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="php-success">
      <svg><use href="#i-check-c"/></svg>
      <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>
      <div class="field">
        <label for="name">Full name</label>
        <input type="text" id="name" name="name" placeholder="Jane Smith"
               autocomplete="name" value="<?= htmlspecialchars($old['name']) ?>" required />
      </div>

      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" placeholder="you@amewriter.com"
               autocomplete="email" value="<?= htmlspecialchars($old['email']) ?>" required />
      </div>

      <div class="field">
        <label for="role">Writer role</label>
        <select id="role" name="role">
          <option value="speechwriter" <?= $old['role']==='speechwriter' ? 'selected':'' ?>>Speech Writer</option>
          <option value="ghostwriter"  <?= $old['role']==='ghostwriter'  ? 'selected':'' ?>>Ghostwriter</option>
          <option value="copywriter"   <?= $old['role']==='copywriter'   ? 'selected':'' ?>>Copywriter</option>
          <option value="journalist"   <?= $old['role']==='journalist'   ? 'selected':'' ?>>Journalist</option>
        </select>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password" placeholder="Min. 8 characters"
                 oninput="checkStrength(this.value)" autocomplete="new-password" required />
          <button class="pw-toggle" onclick="togglePw('password',this)" type="button" aria-label="Show password">
            <svg><use href="#i-eye"/></svg>
          </button>
        </div>
        <div class="strength-bar">
          <div class="s-seg" id="s0"></div><div class="s-seg" id="s1"></div>
          <div class="s-seg" id="s2"></div><div class="s-seg" id="s3"></div>
        </div>
        <p class="pw-hint" id="str-label"></p>
      </div>

      <div class="field">
        <label for="confirm">Confirm password</label>
        <div class="pw-wrap">
          <input type="password" id="confirm" name="confirm" placeholder="••••••••"
                 autocomplete="new-password" required />
          <button class="pw-toggle" onclick="togglePw('confirm',this)" type="button" aria-label="Show password">
            <svg><use href="#i-eye"/></svg>
          </button>
        </div>
      </div>

      <button class="btn-primary" type="submit">Create account</button>
    </form>

    <div class="divider">or</div>
    <p class="auth-footer">
      Already have an account?&nbsp;
      <a href="login.php" class="link-btn">Sign in</a>
    </p>
  </div>

  <p class="auth-page-footer">&copy; 2026 Ame Writer. All rights reserved.</p>
</div>

<script>
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  const showing = inp.type === 'text';
  inp.type = showing ? 'password' : 'text';
  btn.querySelector('use').setAttribute('href', showing ? '#i-eye' : '#i-eye-off');
  btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
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
    document.getElementById('s' + i).style.background =
      i < score ? colors[Math.min(score-1,3)] : 'rgba(0,0,0,0.09)';
  }
  document.getElementById('str-label').textContent = v.length ? labels[Math.min(score,3)] : '';
}
</script>
</body>
</html>
