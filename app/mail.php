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
