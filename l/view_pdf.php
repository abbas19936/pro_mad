<?php
include('db.php');
// log errors instead of echoing them (prevents breaking iframe responses)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
$id = intval($_GET['id'] ?? 0);
if($id <= 0) {
    die('معرّف الكتاب غير صالح.');
}
$sql = "SELECT title, pdf_file FROM books WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) == 0) {
    die('الكتاب غير موجود أو لا يحتوي على ملف PDF.');
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
// زيادة عدد المشاهدات إذا كان العمود موجودًا
$viewCountColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'view_count'");
if ($viewCountColumn && mysqli_num_rows($viewCountColumn) > 0) {
    $updateView = "UPDATE books SET view_count = view_count + 1 WHERE id = ?";
    $stmt_view = mysqli_prepare($conn, $updateView);
    if($stmt_view) {
        mysqli_stmt_bind_param($stmt_view, "i", $id);
        if(!mysqli_stmt_execute($stmt_view)) {
            error_log('view_pdf: failed to update view_count for id=' . $id . ' - ' . mysqli_error($conn));
        }
        mysqli_stmt_close($stmt_view);
    } else {
        error_log('view_pdf: failed to prepare statement for view_count update - ' . mysqli_error($conn));
    }
}

$pdfUrl = 'uploads/' . rawurlencode($pdfName);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض PDF - <?php echo htmlspecialchars($row['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background: #f4f6fb; }
        .pdf-viewer { min-height: 80vh; border: 1px solid #ccc; background: white; }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="pdf-viewer">
        <object data="<?php echo $pdfUrl; ?>" type="application/pdf" width="100%" height="800">
            <p>تعذر عرض الملف في المتصفح. يمكنك تحميله من هنا: <a href="download_pdf.php?id=<?php echo $id; ?>">تحميل PDF</a></p>
        </object>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>