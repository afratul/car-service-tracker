<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/mail.php';


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
        // 1) Create the user
        $stmt = db()->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)");
        $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);

        // 2) Generate a 6-digit OTP and expiry (15 minutes)
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

        // 3) Store OTP on the user
        db()->prepare("UPDATE users SET email_verification_code=?, email_verification_expires=? WHERE email=?")
           ->execute([$otp, $expires, $email]);

        // 4) Send the verification email
        $html = "<p>Hi " . htmlspecialchars($name) . ",</p>
                 <p>Your verification code is <strong style='font-size:18px;'>{$otp}</strong>.</p>
                 <p>It expires in 15 minutes.</p>
                 <p>Verify here: <a href='verify.php?email=" . urlencode($email) . "'>Verify Email</a></p>";

        $sent = send_mail($email, $name, 'Verify your email (Car Service Tracker)', $html);

        // 5) Redirect to verification page if email sent, otherwise show error
        if ($sent === true) {
            header('Location: verify.php?email=' . urlencode($email));
            exit;
        } else {
            $errors[] = 'Could not send verification email. ' . $sent;
        }

    } catch (PDOException $e) {
        if (!empty($e->errorInfo[1]) && $e->errorInfo[1] === 1062) {
            $errors[] = 'Email already exists';
        } else {
            throw $e;
        }
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
