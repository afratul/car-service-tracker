<?php
declare(strict_types=1);

$DB_HOST = '127.0.0.1';
$DB_NAME = 'car_tracker';
$DB_USER = 'root';     // default for XAMPP
$DB_PASS = '';         // default is empty for XAMPP

// Base URL of your app (adjust if your folder is different)
define('BASE_URL', 'http://localhost/car-service-tracker');

// --- SMS via BulkSMSBD ---
define('BULKSMSBD_API_KEY',   getenv('BULKSMSBD_API_KEY')   ?: '4coS9g4aVf3ZVe4aCBny');
define('BULKSMSBD_SENDER_ID', getenv('BULKSMSBD_SENDER_ID') ?: '8809617629068');
define('BULKSMSBD_ENDPOINT',  'http://bulksmsbd.net/api/smsapi');


$BASE_URL = '/car-service-tracker/public'; // adjust if different

// File uploads
$UPLOAD_DIR = __DIR__ . '/../public/uploads';
$MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2MB

// === Uploads (profile photos) ===
define('UPLOAD_BASE', __DIR__ . '/../public/uploads');
define('PROFILE_UPLOAD_DIR', UPLOAD_BASE . '/profile');
define('PROFILE_MAX_BYTES', 2 * 1024 * 1024); // 2 MB
$PROFILE_ALLOWED_MIME = ['image/jpeg','image/png','image/webp'];

// Ensure folder exists (safe if already exists)
if (!is_dir(PROFILE_UPLOAD_DIR)) {
    @mkdir(PROFILE_UPLOAD_DIR, 0777, true);
}


// Start session (secure-ish defaults for local dev)
ini_set('session.cookie_httponly', '1');
session_name('CSTSESSID');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
