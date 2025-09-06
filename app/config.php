<?php
declare(strict_types=1);

$DB_HOST = '127.0.0.1';
$DB_NAME = 'car_tracker';
$DB_USER = 'root';     // default for XAMPP
$DB_PASS = '';         // default is empty for XAMPP

$BASE_URL = '/car-service-tracker/public'; // adjust if different

// File uploads
$UPLOAD_DIR = __DIR__ . '/../public/uploads';
$MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2MB

// Start session (secure-ish defaults for local dev)
ini_set('session.cookie_httponly', '1');
session_name('CSTSESSID');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
