<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user(); // id, name, email, role, etc.

$success = '';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['name'] ?? '');
    $curr    = $_POST['current_password'] ?? '';
    $new1    = $_POST['new_password'] ?? '';
    $new2    = $_POST['confirm_password'] ?? '';

    // 1) Update name (optional)
    if ($newName === '') {
        $errors[] = 'Name cannot be empty.';
    }

    // 2) Update password (optional)
    $changePw = ($curr !== '' || $new1 !== '' || $new2 !== '');
    if ($changePw) {
        if ($curr === '' || $new1 === '' || $new2 === '') {
            $errors[] = 'To change password, fill all password fields.';
        } elseif ($new1 !== $new2) {
            $errors[] = 'New passwords do not match.';
        } elseif (strlen($new1) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } else {
            // verify current password
            $stmt = db()->prepare("SELECT password_hash FROM users WHERE id=?");
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($curr, $row['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
    }

    if (!$errors) {
        // update name
        $stmt = db()->prepare("UPDATE users SET name=? WHERE id=?");
        $stmt->execute([$newName, $user['id']]);
        $user['name'] = $newName; // refresh local copy

        // update password if requested
        if ($changePw && $curr !== '' && $new1 !== '' && $new1 === $new2) {
            $stmt = db()->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $stmt->execute([password_hash($new1, PASSWORD_DEFAULT), $user['id']]);
        }

        $success = 'Profile updated successfully.';
    }
}

include __DIR__ . '/../templates/header.php';
?>
<h2>My Profile</h2>

<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Email (read-only)</label>
        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
        <div class="form-text">Email changes require re-verification. (We can add this later.)</div>
      </div>

      <div class="col-md-6">
        <label class="form-label">Name</label>
        <input name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
      </div>

      <div class="col-12"><hr></div>
      <div class="col-12">
        <div class="fw-semibold">Change password (optional)</div>
        <div class="form-text">Leave blank to keep your current password.</div>
      </div>

      <div class="col-md-4">
        <label class="form-label">Current password</label>
        <input type="password" class="form-control" name="current_password">
      </div>
      <div class="col-md-4">
        <label class="form-label">New password</label>
        <input type="password" class="form-control" name="new_password" minlength="6">
      </div>
      <div class="col-md-4">
        <label class="form-label">Confirm new password</label>
        <input type="password" class="form-control" name="confirm_password" minlength="6">
      </div>

      <div class="col-12">
        <button class="btn btn-primary">Save changes</button>
        <a class="btn btn-secondary" href="dashboard.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
