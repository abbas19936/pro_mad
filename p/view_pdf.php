<?php
include('db.php');
$id = intval($_GET['id'] ?? 0);
if($id <= 0) {
    die('معرّف الكتاب غير صالح.');
}
$result = mysqli_query($conn, "SELECT title, pdf_file FROM books WHERE id = $id LIMIT 1");
if(!$result || mysqli_num_rows($result) == 0) {
    die('الكتاب غير موجود.');
}
$row = mysqli_fetch_assoc($result);
$pdfFile = trim($row['pdf_file']);
if(empty($pdfFile)) {
    die('لا يوجد ملف PDF لهذا الكتاب.');
}
$pdfName = basename($pdfFile);
$pdfPath = __DIR__ . '/uploads/' . $pdfName;
if(!file_exists($pdfPath)) {
    die('ملف PDF غير موجود على الخادم.');
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $pdfName . '"');
readfile($pdfPath);
?>