<?php
date_default_timezone_set('Asia/Baghdad');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
$conn = mysqli_connect("localhost", "root", "");
if (!$conn) { die("الاتصال فشل: " . mysqli_connect_error()); }
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS dental_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
mysqli_select_db($conn, "dental_library");

if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function isValidCsrfToken($token) {
    return session_status() === PHP_SESSION_ACTIVE
        && !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function ensureDatabaseColumn($conn, $table, $column, $definition) {
    $tableExists = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$tableExists || mysqli_num_rows($tableExists) == 0) {
        return;
    }
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($check && mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

ensureDatabaseColumn($conn, 'books', 'view_count', 'view_count INT DEFAULT 0');
ensureDatabaseColumn($conn, 'books', 'download_count', 'download_count INT DEFAULT 0');
ensureDatabaseColumn($conn, 'books', 'link', 'link VARCHAR(255)');
ensureDatabaseColumn($conn, 'students', 'security_question', 'security_question VARCHAR(255)');
ensureDatabaseColumn($conn, 'students', 'security_answer', 'security_answer VARCHAR(255)');
ensureDatabaseColumn($conn, 'students', 'pin_code', 'pin_code VARCHAR(255)');
?>