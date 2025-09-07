<?php
require_once __DIR__ . '/../app/mail.php';

// 🔁 EDIT these two lines to your own address & name
$toEmail = 'afratul2002@gmail.com';
$toName  = 'Ahnaf Faiyaj Ratul';

$result = send_mail($toEmail, $toName, 'Test email from Car Service Tracker',
    '<p>Hello! If you can read this, your SMTP is working ✅</p>');

if ($result === true) {
    echo "✅ Sent test email to $toEmail";
} else {
    echo "❌ $result";
}
