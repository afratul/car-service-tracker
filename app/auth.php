<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function current_user() {
    if (empty($_SESSION['user']['id'])) return null;

    // Always fetch fresh from DB (keeps name/photo/etc. in sync)
    $stmt = db()->prepare("SELECT id, name, email, role, profile_photo FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $row = $stmt->fetch();

    if (!$row) {
        // user deleted or not found → log out
        $_SESSION = [];
        session_destroy();
        return null;
    }

    // Refresh session snapshot (safe & cheap)
    $_SESSION['user'] = [
        'id'            => (int)$row['id'],
        'name'          => $row['name'],
        'email'         => $row['email'],
        'role'          => $row['role'],
        'profile_photo' => $row['profile_photo'] ?? null,
    ];

    return $_SESSION['user'];
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
            'id'            => (int)$user['id'],
            'name'          => $user['name'],
            'email'         => $user['email'],
            'role'          => $user['role'],
            'profile_photo' => $user['profile_photo'] ?? null,
        ];
        return true;
    }
    return false;
}

function logout() {
    $_SESSION = [];
    session_destroy();
}
