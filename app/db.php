<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    if ($pdo === null) {
        $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
