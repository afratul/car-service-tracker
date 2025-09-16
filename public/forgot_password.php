<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/mail.php';

// If already logged in you might want to redirect to dashboard (optional)
// require_once __DIR__ . '/../app/auth.php';
// if (!empty($_SESSION['user'])) { header('Location: dashboard.php'); exit; }

$info = null;
$error = null;

// --- simple rate limit: per-session, per 60s bucket ---
if (!isset($_SESSION['fp_last'])) $_SESSION['fp_last'] = 0;
if (!isset($_SESSION['fp_count'])) $_SESSION['fp_count'] = 0;

$now = time();
if ($now - $_SESSION['fp_last'] >= 60) { // new minute window
    $_SESSION['fp_last']  = $now;
    $_SESSION['fp_count'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // allow at most 3 requests per minute per session
if ($_SESSION['fp_count'] >= 3) {
    // Show generic message (no enumeration)
    $info = 'If that email is registered, a reset link has been sent. Please check your inbox.';
    include __DIR__ . '/../templates/header.php';
    echo '<h2>Forgot Password</h2><div class="alert alert-info">'.$info.'</div>';
    include __DIR__ . '/../templates/footer.php';
    return;
}
$_SESSION['fp_count']++;

    $email = strtolower(trim($_POST['email'] ?? ''));

    // Always show the same message to avoid email enumeration
    $info = 'If that email is registered, a reset link has been sent. Please check your inbox.';

    if ($email !== '') {
        // Find user (and optionally check verified)
        $stmt = db()->prepare("SELECT id, name, email, email_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && (int)$user['email_verified'] === 1) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');

    $upd = db()->prepare("UPDATE users SET password_reset_token=?, password_reset_expires=? WHERE id=?");
    $upd->execute([$token, $expiresAt, (int)$user['id']]);

    $sent = send_password_reset($user['email'], $user['name'], $token);
}

    if ($sent !== true) {
        error_log('Password reset mail failed: ' . (is_string($sent) ? $sent : 'unknown error'));
    }
}



            // (Optional) you could log/send an admin alert if $sent !== true
  }


include __DIR__ . '/../templates/header.php';
?>
<h2>Forgot Password</h2>

<?php if ($info): ?>
  <div class="alert alert-success"><?= htmlspecialchars($info) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" class="row g-3" novalidate>
  <div class="col-md-6">
    <label class="form-label">Your email</label>
    <input type="email" class="form-control" name="email" required autofocus>
    <div class="form-text">We’ll send a password reset link if this email is registered.</div>
  </div>
  <div class="col-12">
    <button class="btn btn-primary">Send reset link</button>
    <a class="btn btn-link" href="login.php">Back to login</a>
  </div>
</form>
<?php include __DIR__ . '/../templates/footer.php'; ?>
