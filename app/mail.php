<?php
declare(strict_types=1);

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Edit these with your Gmail (or SMTP provider) settings
$SMTP_HOST = 'smtp.gmail.com';
$SMTP_USERNAME = 'afratul2002@gmail.com';      // your Gmail address
$SMTP_PASSWORD = 'vuowxgnikmoqixqe';     // Gmail App Password (not your normal password!)
$SMTP_PORT = 587;
$SMTP_SECURE = PHPMailer::ENCRYPTION_STARTTLS;
$SMTP_FROM_EMAIL = 'afratul2002@gmail.com';
$SMTP_FROM_NAME  = 'Car Service Tracker';


function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody) {
    global $SMTP_HOST, $SMTP_USERNAME, $SMTP_PASSWORD, $SMTP_PORT, $SMTP_SECURE, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USERNAME;
        $mail->Password   = $SMTP_PASSWORD;
        $mail->SMTPSecure = $SMTP_SECURE;
        $mail->Port       = $SMTP_PORT;

        // Recipients
        $mail->setFrom($SMTP_FROM_EMAIL, $SMTP_FROM_NAME);
        $mail->addReplyTo($SMTP_FROM_EMAIL, $SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: " . $mail->ErrorInfo;
    }
}

// === Password reset helper ===
// Builds the reset link and uses send_mail(...) to deliver it.
function send_password_reset(string $toEmail, string $toName, string $token) {
    // Build a link the user can click (BASE_URL must be reachable from the inbox!)
    $base = rtrim(BASE_URL, '/'); // BASE_URL comes from config.php
    $link = $base . '/public/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($toEmail);

    $subject = 'Reset your password';
    $html = '
      <p>Hi ' . htmlspecialchars($toName) . ',</p>
      <p>We received a request to reset your password. Click the button below to set a new one:</p>
      <p><a href="' . htmlspecialchars($link) . '" style="display:inline-block;padding:10px 16px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:6px;">Reset password</a></p>
      <p>If the button doesn’t work, copy and paste this link into your browser:<br>
      <a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>
      <p>This link expires in 60 minutes. If you didn’t request this, you can safely ignore this email.</p>
    ';

    return send_mail($toEmail, $toName, $subject, $html);
}

// === Password-changed notice ===
function send_password_changed(string $toEmail, string $toName) {
    $when = (new DateTime())->format('Y-m-d H:i:s');
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $subject = 'Your password was changed';
    $html = '
      <p>Hi ' . htmlspecialchars($toName) . ',</p>
      <p>This is a confirmation that your password was changed on '.$when.' (server time).</p>
      <p>If you did not perform this change, please reset your password immediately from the "Forgot password" page.</p>
      <p><small>Request IP (if available): '.htmlspecialchars($ip).'</small></p>
    ';
    return send_mail($toEmail, $toName, $subject, $html);
}

