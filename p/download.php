<?php
require_once 'security.php';
secure_session_start();
include('db.php');
require_student();

$file = basename($_GET['file'] ?? '');
if(empty($file) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') {
    header('Location: index.php');
    exit;
}

if ($stmt = mysqli_prepare($conn, "SELECT id FROM books WHERE pdf_file = ? LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, 's', $file);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) === 0) {
        mysqli_stmt_close($stmt);
        header('Location: index.php');
        exit;
    }
    mysqli_stmt_close($stmt);
} else {
    header('Location: index.php');
    exit;
}

$filePath = __DIR__ . '/uploads/' . $file;
if(!file_exists($filePath)) {
    header('Location: index.php');
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
