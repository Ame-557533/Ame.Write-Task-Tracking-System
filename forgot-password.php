<?php
// ============================================================
//  Ame Writer — forgot-password.php
//  3-step password recovery:
//    Step 1 — Enter username (full name)
//    Step 2 — Enter User ID as recovery code
//    Step 3 — Set new password
// ============================================================
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'database.php';

$error   = '';
$success = '';
$step    = (int) ($_SESSION['pw_reset_step'] ?? 1);

// ── POST handler ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db     = getDB();
    $action = $_POST['action'] ?? '';

    // ── Step 1: verify username ───────────────────────────
    if ($action === 'verify_name') {
        $name = trim($_POST['username'] ?? '');
        if (empty($name)) {
            $error = 'Please enter your full name.';
            $step  = 1;
        } else {
            $stmt = $db->prepare('SELECT id FROM users WHERE name = ?');
            $stmt->execute([$name]);
            $user = $stmt->fetch();
            if (!$user) {
                // Vague on purpose — don't reveal whether name exists
                $error = 'No account found with that name. Check your spelling.';
                $step  = 1;
            } else {
                $_SESSION['pw_reset_step']    = 2;
                $_SESSION['pw_reset_name']    = $name;
                $_SESSION['pw_reset_user_id'] = null; // not verified yet
                $step = 2;
            }
        }

    // ── Step 2: verify User ID ────────────────────────────
    } elseif ($action === 'verify_id') {
        $entered_id = trim($_POST['user_id'] ?? '');
        $name       = $_SESSION['pw_reset_name'] ?? '';

        if (empty($entered_id) || !is_numeric($entered_id)) {
            $error = 'Please enter your numeric User ID.';
            $step  = 2;
        } else {
            $stmt = $db->prepare('SELECT id FROM users WHERE name = ? AND id = ?');
            $stmt->execute([$name, (int) $entered_id]);
            $user = $stmt->fetch();
            if (!$user) {
                $error = 'Incorrect User ID. Please check and try again.';
                $step  = 2;
            } else {
                $_SESSION['pw_reset_step']    = 3;
                $_SESSION['pw_reset_user_id'] = (int) $user['id'];
                $step = 3;
            }
        }

    // ── Step 3: set new password ──────────────────────────
    } elseif ($action === 'reset_password') {
        $reset_uid = (int) ($_SESSION['pw_reset_user_id'] ?? 0);
        $new_pw    = $_POST['new_password']     ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        if ($reset_uid === 0) {
            $error = 'Session expired. Please start over.';
            // Clear session and restart
            unset($_SESSION['pw_reset_step'], $_SESSION['pw_reset_name'], $_SESSION['pw_reset_user_id']);
            $step = 1;
        } elseif (strlen($new_pw) < 8) {
            $error = 'Password must be at least 8 characters.';
            $step  = 3;
        } elseif ($new_pw !== $confirm) {
            $error = 'Passwords do not match.';
            $step  = 3;
        } else {
            $hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([$hash, $reset_uid]);
            // Clear reset session
            unset($_SESSION['pw_reset_step'], $_SESSION['pw_reset_name'], $_SESSION['pw_reset_user_id']);
            $success = 'Password reset successfully! You can now sign in.';
            $step    = 0; // done
        }
    }
} else {
    // GET request — if no active reset session, start at step 1
    if (!isset($_SESSION['pw_reset_step'])) {
        $step = 1;
    }
    // Allow restarting from step 2 back to step 1
    if (isset($_GET['restart'])) {
        unset($_SESSION['pw_reset_step'], $_SESSION['pw_reset_name'], $_SESSION['pw_reset_user_id']);
        $step = 1;
    }
}

$step_name = $_SESSION['pw_reset_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ame Writer — Reset Password</title>
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
    <a href="login.php" class="back-link">
      <svg><use href="#i-arrow-l"/></svg>
      Back to sign in
    </a>

    <h1>Reset password</h1>

    <?php if ($error): ?>
    <div class="php-error">
      <svg><use href="#i-alert"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($step === 0): ?>
    <!-- ── Done ─────────────────────────────────────── -->
    <div class="php-success">
      <svg><use href="#i-check-c"/></svg>
      <?= htmlspecialchars($success) ?>
    </div>
    <a href="login.php" class="btn-primary" style="text-align:center;margin-top:.5rem;text-decoration:none;display:block">
      Sign in
    </a>

    <?php elseif ($step === 1): ?>
    <!-- ── Step 1: Username ──────────────────────────── -->
    <p class="auth-sub">Enter the full name on your account.</p>
    <div class="reset-steps">
      <span class="reset-step active">1</span>
      <span class="reset-step-line"></span>
      <span class="reset-step">2</span>
      <span class="reset-step-line"></span>
      <span class="reset-step">3</span>
    </div>
    <form method="POST" action="forgot-password.php" novalidate>
      <input type="hidden" name="action" value="verify_name">
      <div class="field">
        <label for="username">Full name</label>
        <input type="text" id="username" name="username" placeholder="Jane Smith"
               autocomplete="name" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required />
      </div>
      <button class="btn-primary" type="submit">Continue</button>
    </form>

    <?php elseif ($step === 2): ?>
    <!-- ── Step 2: User ID ───────────────────────────── -->
    <p class="auth-sub">
      Enter your <strong>User ID</strong> — the number shown on your profile and in your account details.
    </p>
    <div class="reset-steps">
      <span class="reset-step done">✓</span>
      <span class="reset-step-line done"></span>
      <span class="reset-step active">2</span>
      <span class="reset-step-line"></span>
      <span class="reset-step">3</span>
    </div>
    <form method="POST" action="forgot-password.php" novalidate>
      <input type="hidden" name="action" value="verify_id">
      <div class="field">
        <label for="user_id">Your User ID</label>
        <input type="text" id="user_id" name="user_id" placeholder="e.g. 42"
               inputmode="numeric" pattern="[0-9]+" autocomplete="off" required />
        <p class="pw-hint" style="margin-top:4px">This is the numeric ID from your profile page.</p>
      </div>
      <button class="btn-primary" type="submit">Verify</button>
    </form>
    <a href="forgot-password.php?restart=1" class="link-btn" style="font-size:13px;color:var(--ink-3);display:inline-block;margin-top:.5rem">
      ← Try a different name
    </a>

    <?php elseif ($step === 3): ?>
    <!-- ── Step 3: New password ──────────────────────── -->
    <p class="auth-sub">Choose a new password for your account.</p>
    <div class="reset-steps">
      <span class="reset-step done">✓</span>
      <span class="reset-step-line done"></span>
      <span class="reset-step done">✓</span>
      <span class="reset-step-line done"></span>
      <span class="reset-step active">3</span>
    </div>
    <form method="POST" action="forgot-password.php" novalidate>
      <input type="hidden" name="action" value="reset_password">
      <div class="field">
        <label for="new_password">New password</label>
        <div class="pw-wrap">
          <input type="password" id="new_password" name="new_password"
                 placeholder="Min. 8 characters"
                 oninput="checkStrength(this.value)"
                 autocomplete="new-password" required />
          <button class="pw-toggle" onclick="togglePw('new_password',this)" type="button" aria-label="Show password">
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
        <label for="confirm_password">Confirm new password</label>
        <div class="pw-wrap">
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="••••••••" autocomplete="new-password" required />
          <button class="pw-toggle" onclick="togglePw('confirm_password',this)" type="button" aria-label="Show password">
            <svg><use href="#i-eye"/></svg>
          </button>
        </div>
      </div>
      <button class="btn-primary" type="submit">Reset Password</button>
    </form>
    <?php endif; ?>

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
