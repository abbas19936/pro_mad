<?php
function secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $cookieParams['lifetime'],
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

function validate_csrf_token($token) {
    return !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_admin() {
    if (empty($_SESSION['loggedin']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
        header('Location: login.php');
        exit;
    }
}

function require_student() {
    if (empty($_SESSION['loggedin']) || ($_SESSION['user_type'] ?? '') !== 'student') {
        header('Location: login.php');
        exit;
    }
}
