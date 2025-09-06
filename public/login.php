<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/config.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    if (login($email, $pass)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
include __DIR__ . '/../templates/header.php';
?>
<h2>Login</h2>
<?php if (isset($_GET['registered'])): ?>
<div class="alert alert-success">Registration successful. Please log in.</div>
<?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>
<form method="post" class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Email</label>
    <input type="email" class="form-control" name="email" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Password</label>
    <input type="password" class="form-control" name="password" required>
  </div>
  <div class="col-12">
    <button class="btn btn-primary">Login</button>
    <a class="btn btn-link" href="register.php">Create account</a>
  </div>
</form>
<?php include __DIR__ . '/../templates/footer.php'; ?>
