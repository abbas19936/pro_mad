<?php
session_start();
include('db.php');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

$id = intval($_GET['id'] ?? 0);
if($id <= 0) {
    http_response_code(400);
    die('معرّف الكتاب غير صالح.');
}

$sql = "SELECT title, pdf_file FROM books WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) == 0) {
    http_response_code(404);
    die('الكتاب غير موجود.');
}

$row = mysqli_fetch_assoc($result);
$pdfFile = trim($row['pdf_file']);
if(empty($pdfFile)) {
    http_response_code(404);
    die('لا يوجد ملف PDF لهذا الكتاب.');
}

$pdfName = basename($pdfFile);
$filePath = __DIR__ . '/uploads/' . $pdfName;

if(!file_exists($filePath)) {
    http_response_code(404);
    die('ملف PDF غير موجود.');
}

// زيادة عدد التنزيلات إذا كان العمود موجودًا
$downloadCountColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'download_count'");
if ($downloadCountColumn && mysqli_num_rows($downloadCountColumn) > 0) {
    $updateDownload = "UPDATE books SET download_count = download_count + 1 WHERE id = ?";
    $stmt_download = mysqli_prepare($conn, $updateDownload);
    if($stmt_download) {
        mysqli_stmt_bind_param($stmt_download, "i", $id);
        mysqli_stmt_execute($stmt_download);
        mysqli_stmt_close($stmt_download);
    }
}

$downloadName = preg_replace('/[^A-Za-z0-9\-_.\u0600-\u06FF ]/u', '', $row['title']);
$downloadName = trim($downloadName);
if($downloadName === '') {
    $downloadName = 'book';
}
$downloadName .= '.pdf';

// Clear any previous output
if (ob_get_level()) ob_end_clean();

header('Content-Type: application/pdf');
header("Content-Disposition: attachment; filename=\"$downloadName\"");
header('Content-Description: File Transfer');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;

