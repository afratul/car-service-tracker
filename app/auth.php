<?php
require_once __DIR__ . '/db.php';

function current_user() {
    return $_SESSION['user'] ?? null;
}
function require_login() {
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}
function login(string $email, string $password): bool {
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
        return true;
    }
    return false;
}
function logout() {
    $_SESSION = [];
    session_destroy();
}
