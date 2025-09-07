<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/mail.php';

$email = strtolower(trim($_GET['email'] ?? ''));
$notice = 'If the email exists and is unverified, a new code was sent.';

if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Look up the user
    $stmt = db()->prepare("SELECT id, name, email_verified FROM users WHERE email=?");
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if ($u && (int)$u['email_verified'] === 0) {
        // Generate a fresh code
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

        db()->prepare("UPDATE users SET email_verification_code=?, email_verification_expires=? WHERE id=?")
           ->execute([$otp, $expires, $u['id']]);

        // Send email
        $html = "<p>Hi " . htmlspecialchars($u['name']) . ",</p>
                 <p>Your new verification code is <strong style='font-size:18px;'>{$otp}</strong>.</p>
                 <p>It expires in 15 minutes.</p>
                 <p>Verify here: <a href='verify.php?email=" . urlencode($email) . "'>Verify Email</a></p>";
        send_mail($email, $u['name'], 'Your new verification code', $html);
    }
}

include __DIR__ . '/../templates/header.php';
?>
<div class="alert alert-info"><?=htmlspecialchars($notice)?></div>
<p><a class="btn btn-primary" href="verify.php?email=<?=urlencode($email)?>">Back to verification</a></p>
<?php include __DIR__ . '/../templates/footer.php'; ?>
