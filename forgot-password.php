<?php
// ============================================================
//  Ame Co. Workspace — forgot-password.php
//  Accepts an email, checks if it exists, and (in production)
//  would send a password reset email. Here we just show the
//  success message — wire up your mailer (e.g. PHPMailer /
//  Mailgun) in the TODO section below.
// ============================================================
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'database.php';

$error   = '';
$success = '';
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $old_email = $email;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show success to prevent user-enumeration attacks
        if ($user) {
            // TODO: Generate a secure token and email the reset link
            // $token = bin2hex(random_bytes(32));
            // $expiry = date('Y-m-d H:i:s', time() + 3600);
            // $db->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)')
            //    ->execute([$user['id'], hash('sha256', $token), $expiry]);
            // mail($email, 'Reset your Ame Co. password', "https://ameco.dev/reset.php?token=$token");
        }

        $success = 'If that email is registered, a reset link is on its way. Check your inbox.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ame Co. Workspace — Reset Password</title>
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

    <a href="login.php" class="back-link">
      <svg><use href="#i-arrow-l"/></svg>
      Back to sign in
    </a>

    <h1>Reset password</h1>
    <p class="auth-sub">Enter your work email and we'll send a reset link.</p>

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

    <?php if (!$success): ?>
    <form method="POST" action="forgot-password.php" novalidate>

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

      <button class="btn-primary" type="submit">Send reset link</button>

    </form>
    <?php else: ?>
    <a href="login.php" class="btn-primary" style="text-align:center;margin-top:.5rem">
      Back to sign in
    </a>
    <?php endif; ?>

  </div>

  <p class="auth-page-footer">&copy; 2026 Ame Co. All rights reserved.</p>
</div>

</body>
</html>
