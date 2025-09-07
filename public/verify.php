<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config.php';

$email = strtolower(trim($_GET['email'] ?? ($_POST['email'] ?? '')));
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!preg_match('/^\d{6}$/', $code)) $errors[] = 'Enter the 6-digit code.';

    if (!$errors) {
        $stmt = db()->prepare("SELECT id, name, email_verification_code, email_verification_expires, email_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if (!$u) {
            $errors[] = 'No account found for this email.';
        } elseif ((int)$u['email_verified'] === 1) {
            $success = 'Email already verified. You can log in.';
        } else {
            $now = new DateTime();
            $exp = new DateTime($u['email_verification_expires'] ?? '1970-01-01');
            if (!$u['email_verification_code'] || $code !== $u['email_verification_code']) {
                $errors[] = 'Incorrect code.';
            } elseif ($now > $exp) {
                $errors[] = 'Code expired. Please request a new code.';
            } else {
                db()->prepare("UPDATE users SET email_verified=1, email_verification_code=NULL, email_verification_expires=NULL WHERE id=?")
                   ->execute([$u['id']]);
                $success = 'Email verified! You can log in now.';
            }
        }
    }
}

include __DIR__ . '/../templates/header.php';
?>
<h2>Verify your email</h2>
<p>We sent a 6-digit code to <strong><?=htmlspecialchars($email)?></strong>.</p>

<?php if ($errors): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>".htmlspecialchars($e)."</div>"; ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?=htmlspecialchars($success)?></div>
<p><a class="btn btn-primary" href="login.php">Go to login</a></p>
<?php else: ?>
<form method="post" class="row g-3">
  <input type="hidden" name="email" value="<?=htmlspecialchars($email)?>">
  <div class="col-md-4">
    <label class="form-label">6-digit code</label>
    <input class="form-control" name="code" maxlength="6" required>
  </div>
  <div class="col-12">
    <button class="btn btn-primary">Verify</button>
    <a class="btn btn-link" href="resend_code.php?email=<?=urlencode($email)?>">Resend code</a>
  </div>
</form>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
