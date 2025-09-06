<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $role  = in_array($_POST['role'] ?? 'OWNER', ['OWNER','WORKSHOP']) ? $_POST['role'] : 'OWNER';

    if ($name === '') $errors[] = 'Name is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters';

    if (!$errors) {
        try {
            $stmt = db()->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)");
            $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] === 1062) $errors[] = 'Email already exists';
            else throw $e;
        }
    }
}
include __DIR__ . '/../templates/header.php';
?>
<h2>Create account</h2>
<?php if ($errors): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>".htmlspecialchars($e)."</div>"; ?></div>
<?php endif; ?>
<form method="post" class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Name</label>
    <input class="form-control" name="name" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Email</label>
    <input type="email" class="form-control" name="email" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Password</label>
    <input type="password" class="form-control" name="password" required minlength="6">
  </div>
  <div class="col-md-6">
    <label class="form-label">Role</label>
    <select class="form-select" name="role">
      <option value="OWNER">Owner</option>
      <option value="WORKSHOP">Workshop</option>
    </select>
  </div>
  <div class="col-12">
    <button class="btn btn-primary">Register</button>
    <a href="login.php" class="btn btn-link">I already have an account</a>
  </div>
</form>
<?php include __DIR__ . '/../templates/footer.php'; ?>
