<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config.php';

$token  = $_GET['token'] ?? '';
$error  = null;
$userId = null;

if ($token && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = db()->prepare("
      SELECT id, name, email, password_reset_expires
      FROM users
      WHERE password_reset_token = ?
      LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    $userEmail = $row['email'] ?? null;
    $userName  = $row['name']  ?? null;

    if ($row) {
        $expires = new DateTime($row['password_reset_expires']);
        $now     = new DateTime();

        if ($expires >= $now) {
            $userId = (int)$row['id'];
        } else {
            $error = 'This reset link has expired. Please request a new one.';
        }
    } else {
        $error = 'Invalid reset link.';
    }
} else {
    $error = 'Invalid reset link.';
}

// Handle new password
// Handle new password
if ($userId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['confirm_password'] ?? '';

    if (strlen($p1) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($p1 !== $p2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $upd = db()->prepare("
            UPDATE users
               SET password_hash=?,
                   password_reset_token=NULL,
                   password_reset_expires=NULL
             WHERE id=?
             LIMIT 1
        ");
        $upd->execute([$hash, $userId]);

        // best-effort notification; don’t block on failure
        if (!empty($userEmail) && !empty($userName)) {
            require_once __DIR__ . '/../app/mail.php';
            @send_password_changed($userEmail, $userName);
        }

        // Redirect to login with a success flash
        header('Location: login.php?reset=1');
        exit;
    }
}




include __DIR__ . '/../templates/header.php';?>
<h2>Set a new password</h2>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($userId && !$error): ?>
  <form method="post" class="row g-3" novalidate>
    <div class="col-md-6">
      <label class="form-label">New password</label>
      <input type="password" class="form-control" name="password" minlength="6" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Confirm new password</label>
      <input type="password" class="form-control" name="confirm_password" minlength="6" required>
    </div>
    <div class="col-12">
      <button class="btn btn-primary">Update password</button>
      <a class="btn btn-link" href="login.php">Back to login</a>
    </div>
  </form>
<?php else: ?>
  <a class="btn btn-primary" href="forgot_password.php">Request a new reset link</a>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
