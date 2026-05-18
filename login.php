<?php
// ============================================================
//  Ame Co. Workspace — login.php
//  Employee login. Verifies email + bcrypt password against
//  the users table, writes session, redirects to dashboard.
// ============================================================
session_start();

// Already logged in → go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'database.php';

$error     = '';
$old_email = '';

// ── POST handler ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email']    ?? '');
    $password  = $_POST['password']      ?? '';
    $old_email = $email;

    if (empty($email) || empty($password)) {
        $error = 'Please fill in your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Generic message — don't reveal whether email exists
            $error = 'Incorrect email or password. Please try again.';
        } else {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ame Co. Workspace — Sign In</title>
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
    <span class="auth-brand-name">Ame Co. Workspace</span>
  </div>

  <div class="auth-card">
    <h1>Welcome back</h1>
    <p class="auth-sub">Sign in to your Ame Co. employee account.</p>

    <?php if ($error): ?>
    <div class="php-error">
      <svg><use href="#i-alert"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>

      <div class="field">
        <label for="email">Work email</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="jane@ameco.dev"
          autocomplete="email"
          value="<?= htmlspecialchars($old_email) ?>"
          required
        />
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="pw-wrap">
          <input
            type="password"
            id="password"
            name="password"
            placeholder="••••••••"
            autocomplete="current-password"
            required
          />
          <button class="pw-toggle" onclick="togglePw('password',this)" type="button" aria-label="Show password">
            <svg><use href="#i-eye"/></svg>
          </button>
        </div>
        <div class="forgot-row">
          <a href="forgot-password.php" class="link-btn">Forgot password?</a>
        </div>
      </div>

      <button class="btn-primary" type="submit">Sign in</button>

    </form>

    <div class="divider">or</div>
    <p class="auth-footer">
      New to Ame Co.?&nbsp;
      <a href="register.php" class="link-btn">Request access</a>
    </p>
  </div>

  <p class="auth-page-footer">&copy; 2026 Ame Co. All rights reserved.</p>
</div>

<script>
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  const showing = inp.type === 'text';
  inp.type = showing ? 'password' : 'text';
  btn.querySelector('use').setAttribute('href', showing ? '#i-eye' : '#i-eye-off');
  btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
}
</script>

</body>
</html>
