<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/sms.php';

$user = [
  'id'           => 1,
  'phone_number' => '01711055829', // try your number
  'sms_opt_in'   => 1,
];

[$ok, $resp] = send_sms_bulksmsbd($user['phone_number'], 'Hello from BulkSMSBD integration!');
echo "OK? " . ($ok ? "yes" : "no") . PHP_EOL;
echo $resp . PHP_EOL;
