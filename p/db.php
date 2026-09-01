<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = mysqli_connect("localhost", "root", "", "dental_library");
if (!$conn) {
    die("الاتصال فشل: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
?>