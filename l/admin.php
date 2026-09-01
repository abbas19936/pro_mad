<?php 
session_start();
if(!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}
// التحقق من أن المستخدم أدمن فقط
if($_SESSION['user_type'] !== 'admin') {
    header('Location: professor.php');
    exit;
}
include('db.php');

$statusColumnResult = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'status'");
$hasStatusColumn = $statusColumnResult && mysqli_num_rows($statusColumnResult) > 0;

$totalBooksQuery = "SELECT COUNT(*) AS count FROM books";
if ($hasStatusColumn) {
    $totalBooksQuery .= " WHERE status = 'approved'";
}
$totalBooksResult = mysqli_query($conn, $totalBooksQuery);
$totalBooks = 0;
if($totalBooksResult && $row = mysqli_fetch_assoc($totalBooksResult)) {
    $totalBooks = intval($row['count']);
}
$pendingRequestsResult = mysqli_query($conn, "SELECT COUNT(*) AS count FROM requests WHERE status = 'pending'");
$pendingRequests = 0;
if($pendingRequestsResult && $row = mysqli_fetch_assoc($pendingRequestsResult)) {
    $pendingRequests = intval($row['count']);
}

$hasViewCount = false;
$hasDownloadCount = false;
$viewCountColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'view_count'");
$downloadCountColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'download_count'");
if($viewCountColumn && mysqli_num_rows($viewCountColumn) > 0) {
    $hasViewCount = true;
}
if($downloadCountColumn && mysqli_num_rows($downloadCountColumn) > 0) {
    $hasDownloadCount = true;
}

$totalViews = 0;
if ($hasViewCount) {
    $totalViewsResult = mysqli_query($conn, "SELECT SUM(view_count) AS count FROM books");
    if($totalViewsResult && $row = mysqli_fetch_assoc($totalViewsResult)) {
        $totalViews = intval($row['count']);
    }
}

$totalDownloads = 0;
if ($hasDownloadCount) {
    $totalDownloadsResult = mysqli_query($conn, "SELECT SUM(download_count) AS count FROM books");
    if($totalDownloadsResult && $row = mysqli_fetch_assoc($totalDownloadsResult)) {
        $totalDownloads = intval($row['count']);
    }
}
$totalLecturesResult = mysqli_query($conn, "SELECT COUNT(*) AS count FROM lectures");
$totalLectures = 0;
if($totalLecturesResult && $row = mysqli_fetch_assoc($totalLecturesResult)) {
    $totalLectures = intval($row['count']);
}

// دالة لإرسال إشعار
function send_notification($student_identifier, $message, $type = 'info') {
    global $conn;
    $student_id = null;
    if(is_numeric($student_identifier) && intval($student_identifier) > 0) {
        $student_id = intval($student_identifier);
    } else {
        $sql = "SELECT id FROM students WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $student_identifier);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if(mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_assoc($result);
            $student_id = $row['id'];
        }
    }
    
    if($student_id) {
        $sql_notif = "INSERT INTO notifications (student_id, message, type) VALUES (?, ?, ?)";
        $stmt_notif = mysqli_prepare($conn, $sql_notif);
        mysqli_stmt_bind_param($stmt_notif, "iss", $student_id, $message, $type);
        mysqli_stmt_execute($stmt_notif);
    }
}

if(isset($_POST['approve_request'])) {
    $id = intval($_POST['approve_request']);
    $sql = "SELECT * FROM requests WHERE id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_assoc($result)) {
        $author = 'غير محدد';
        $empty_string = '';
        $publisher_name = $row['name'] ?: 'طالب';
        $publisher_role = 'student';
        $sql_insert = "INSERT INTO books (title, author, specialty, pdf_file, external_link, publication_date, added_by, publisher_name, publisher_role, status) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, 'approved')";
        $stmt_insert = mysqli_prepare($conn, $sql_insert);
        mysqli_stmt_bind_param($stmt_insert, "ssssssss", $row['book_title'], $author, $row['specialty'], $empty_string, $empty_string, $row['publication_date'], $publisher_name, $publisher_role);
        mysqli_stmt_execute($stmt_insert);
        
        $notification_msg = "تمت الموافقة على طلبك للكتاب: {$row['book_title']}. الكتاب متاح الآن.";
        send_notification($row['student_id'] ?: $row['email'], $notification_msg, 'success');
        
        $sql_update = "UPDATE requests SET status = 'approved', admin_reason = ?, processed_by = ?, processed_at = NOW() WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        $reason = 'تمت الموافقة من قبل الإدارة';
        $processed_by = intval($_SESSION['user_id']);
        mysqli_stmt_bind_param($stmt_update, "sii", $reason, $processed_by, $id);
        mysqli_stmt_execute($stmt_update);
        
        echo "<script>alert('تمت الموافقة على الطلب وإضافة الكتاب.');</script>";
    }
}

if(isset($_POST['reject_request'])) {
    $id = intval($_POST['reject_request']);
    $reject_reason = trim($_POST['reject_reason'] ?? '');
    if($reject_reason === '') {
        $reject_reason = 'تم رفض الطلب بدون سبب إضافي.';
    }
    
    $sql = "SELECT * FROM requests WHERE id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_assoc($result)) {
        $notification_msg = "تم رفض طلبك للكتاب: {$row['book_title']}. السبب: {$reject_reason}";
        send_notification($row['student_id'] ?: $row['email'], $notification_msg, 'warning');
        
        $sql_update = "UPDATE requests SET status = 'rejected', admin_reason = ?, processed_by = ?, processed_at = NOW() WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        $processed_by = intval($_SESSION['user_id']);
        mysqli_stmt_bind_param($stmt_update, "sii", $reject_reason, $processed_by, $id);
        mysqli_stmt_execute($stmt_update);
        
        echo "<script>alert('تم رفض الطلب وإرسال إشعار للطالب.');</script>";
    }
}

if(isset($_POST['submit'])) {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $spec = trim($_POST['specialty'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $admin_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;
    $pub_date = !empty($_POST['publication_date']) ? $_POST['publication_date'] : null;
    $file_name = '';
    
    if(isset($_FILES['pdf']) && $_FILES['pdf']['error'] == 0) {
        $allowed_types = ['application/pdf', 'application/x-pdf'];
        $max_size = 50 * 1024 * 1024;
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['pdf']['tmp_name']);
        finfo_close($finfo);
        
        if(in_array($mime, $allowed_types) && $_FILES['pdf']['size'] <= $max_size) {
            $file_name = 'book_' . time() . '_' . md5($_FILES['pdf']['name']) . '.pdf';
            if(!is_dir('uploads')) {
                mkdir('uploads', 0750, true);
            }
            if(move_uploaded_file($_FILES['pdf']['tmp_name'], "uploads/".$file_name)) {
                // Success
            } else {
                $file_name = '';
                echo "<script>alert('خطأ في رفع الملف.');</script>";
            }
        } else {
            echo "<script>alert('ملف غير صحيح. يرجى رفع ملف PDF يحتوي على 50 MB أو أقل.');</script>";
        }
    }
    
    if(!empty($title) && !empty($spec)) {
        $publisher_name = htmlspecialchars($_SESSION['user_name'] ?? 'الإدارة');
        $publisher_role = htmlspecialchars($_SESSION['user_type'] ?? 'admin');
        $book_link = !empty($file_name) ? 'uploads/' . $file_name : '';
        $sql = "INSERT INTO books (title, author, specialty, pdf_file, external_link, link, publication_date, added_by, publisher_name, publisher_role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssisss", $title, $author, $spec, $file_name, $link, $book_link, $pub_date, $admin_user_id, $publisher_name, $publisher_role);
        
        if(mysqli_stmt_execute($stmt)) {
            echo "<script>alert('تم إضافة الكتاب بنجاح');</script>";
        } else {
            echo "<script>alert('خطأ عند إضافة الكتاب.');</script>";
        }
    } else {
        echo "<script>alert('العنوان والتخصص مطلوبان.');</script>";
    }
}

if(isset($_GET['delete_book'])) {
    $id = intval($_GET['delete_book']);
    $sql = "SELECT pdf_file FROM books WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $book = mysqli_fetch_assoc($result);
    if($book && !empty($book['pdf_file'])) {
        $file_path = __DIR__ . '/uploads/' . basename($book['pdf_file']);
        if(file_exists($file_path)) {
            unlink($file_path);
        }
    }
    $sql = "DELETE FROM books WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    echo "<script>alert('تم حذف الكتاب');</script>";
}

if(isset($_POST['upload_admin_lecture'])) {
    $title = trim($_POST['lecture_title'] ?? '');
    $subject = trim($_POST['lecture_subject'] ?? '');
    $stage = intval($_POST['lecture_stage'] ?? 0);
    if(empty($title) || empty($subject) || $stage <= 0) {
        echo "<script>alert('جميع الحقول المطلوبة يجب أن تكون مملوءة.');</script>";
    } else {
        $upload_dir = __DIR__ . '/uploads/';
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0750, true);
        }
        if(isset($_FILES['lecture_file']) && $_FILES['lecture_file']['error'] == 0) {
            $allowed_types = ['application/pdf', 'application/x-pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
            $max_size = 100 * 1024 * 1024;
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['lecture_file']['tmp_name']);
            finfo_close($finfo);
            if(!in_array($mime, $allowed_types)) {
                echo "<script>alert('نوع ملف غير مسموح. يرجى رفع ملف PDF أو Word أو PowerPoint.');</script>";
            } elseif($_FILES['lecture_file']['size'] > $max_size) {
                echo "<script>alert('حجم الملف كبير جداً. الحد الأقصى هو 100 MB.');</script>";
            } else {
                $file_ext = pathinfo($_FILES['lecture_file']['name'], PATHINFO_EXTENSION);
                $file_name = 'lecture_admin_' . time() . '_' . md5($_FILES['lecture_file']['name']) . '.' . strtolower($file_ext);
                $upload_path = $upload_dir . $file_name;
                if(move_uploaded_file($_FILES['lecture_file']['tmp_name'], $upload_path)) {
                    $admin_user_id = intval($_SESSION['user_id']);
                    $sql = "INSERT INTO lectures (title, subject, stage, file_path, added_by, uploaded_by_role) VALUES (?, ?, ?, ?, ?, 'admin')";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssisi", $title, $subject, $stage, $file_name, $admin_user_id);
                    if(mysqli_stmt_execute($stmt)) {
                        echo "<script>alert('تم رفع المحاضرة بنجاح');</script>";
                    } else {
                        unlink($upload_path);
                        echo "<script>alert('خطأ في حفظ المحاضرة.');</script>";
                    }
                } else {
                    echo "<script>alert('حدث خطأ أثناء رفع الملف.');</script>";
                }
            }
        } else {
            echo "<script>alert('يرجى اختيار ملف للرفع.');</script>";
        }
    }
}

if(isset($_POST['edit_book'])) {
    $id = intval($_POST['edit_id']);
    $title = trim($_POST['edit_title'] ?? '');
    $author = trim($_POST['edit_author'] ?? '');
    $specialty = trim($_POST['edit_specialty'] ?? '');
    $pub_date = $_POST['edit_pub_date'] ?? null;
    $link = trim($_POST['edit_link'] ?? '');
    
    $sql = "UPDATE books SET title = ?, author = ?, specialty = ?, publication_date = ?, external_link = ?, link = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssi", $title, $author, $specialty, $pub_date, $link, $link, $id);
    mysqli_stmt_execute($stmt);
    echo "<script>alert('تم تحديث الكتاب');</script>";
}

if(isset($_GET['delete_lecture'])) {
    $id = intval($_GET['delete_lecture']);
    $sql = "SELECT file_path FROM lectures WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    if($row && !empty($row['file_path'])) {
        $file_path = __DIR__ . '/uploads/' . basename($row['file_path']);
        if(file_exists($file_path)) {
            unlink($file_path);
        }
    }
    $sql_delete = "DELETE FROM lectures WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $id);
    mysqli_stmt_execute($stmt_delete);
    echo "<script>alert('تم حذف المحاضرة');</script>";
}

if(isset($_POST['add_user'])) {
    $name = trim($_POST['user_name'] ?? '');
    $email = trim($_POST['user_email'] ?? '');
    $role = $_POST['user_role'] ?? 'professor';
    $password = !empty($_POST['user_password']) ? password_hash($_POST['user_password'], PASSWORD_DEFAULT) : password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    
    if(!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $password, $role);
        mysqli_stmt_execute($stmt);
        echo "<script>alert('تم إضافة المستخدم');</script>";
    } else {
        echo "<script>alert('بيانات غير صحيحة.');</script>";
    }
}

if(isset($_POST['add_student'])) {
    $name = trim($_POST['student_name'] ?? '');
    $email = trim($_POST['student_email'] ?? '');
    $password = $_POST['student_password'] ?? '';
    $specialty = trim($_POST['student_specialty'] ?? '');
    $university = trim($_POST['student_university'] ?? '');
    $faculty = trim($_POST['student_faculty'] ?? '');

    if(empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password) || empty($specialty) || empty($university) || empty($faculty)) {
        echo "<script>alert('يرجى ملء جميع بيانات الطالب بشكل صحيح.');</script>";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql_check = "SELECT id FROM students WHERE email = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "s", $email);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        if(mysqli_num_rows($result_check) > 0) {
            echo "<script>alert('البريد الإلكتروني مستخدم بالفعل لطالب.');</script>";
        } else {
            $officialEmailColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'official_email'");
            if ($officialEmailColumn && mysqli_num_rows($officialEmailColumn) > 0) {
                $sql = "INSERT INTO students (name, email, password, specialty, university, faculty, official_email) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssssss", $name, $email, $password_hash, $specialty, $university, $faculty, $email);
            } else {
                $sql = "INSERT INTO students (name, email, password, specialty, university, faculty) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssss", $name, $email, $password_hash, $specialty, $university, $faculty);
            }
            if(mysqli_stmt_execute($stmt)) {
                echo "<script>alert('تم إضافة الطالب بنجاح.');</script>";
            } else {
                echo "<script>alert('حدث خطأ أثناء إضافة الطالب.');</script>";
            }
        }
    }
}

if(isset($_POST['edit_password'])) {
    $user_id = intval($_POST['edit_user_id']);
    $new_password = $_POST['new_password'] ?? '';
    
    if(strlen($new_password) >= 8) {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $new_password_hash, $user_id);
        mysqli_stmt_execute($stmt);
        echo "<script>alert('تم تحديث كلمة المرور');</script>";
    } else {
        echo "<script>alert('كلمة المرور يجب أن تكون 8 أحرف على الأقل.');</script>";
    }
}

if(isset($_GET['delete_student'])) {
    $id = intval($_GET['delete_student']);
    $sql = "DELETE FROM students WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    echo "<script>alert('تم حذف الطالب بنجاح');</script>";
}

if(isset($_GET['delete_user'])) {
    $id = intval($_GET['delete_user']);
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    echo "<script>alert('تم حذف المستخدم بنجاح');</script>";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة التحكم - الإدارة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            font-family: 'Cairo', sans-serif;
        }
        .college-header { 
            background: rgba(255,255,255,0.95); 
            padding: 30px; 
            text-align: center; 
            border-bottom: 5px solid #007bff; 
            border-radius: 15px; 
            margin-bottom: 30px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: fadeInDown 1s ease-out;
        }
        .college-logo { 
            max-height: 120px; 
            animation: bounceIn 1.5s ease-out;
        }
        .datetime { 
            font-size: 1.2em; 
            color: #333; 
            margin-top: 15px; 
            animation: fadeIn 2s ease-out;
        }
        .container { 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
            animation: fadeInUp 1s ease-out;
        }
        .btn-custom { 
            background: linear-gradient(45deg, #007bff, #0056b3); 
            border: none; 
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        .btn-custom:hover { 
            background: linear-gradient(45deg, #0056b3, #004085); 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }
        .table tr { 
            transition: all 0.3s ease; 
        }
        .table tr:hover { 
            background-color: rgba(0,123,255,0.1) !important; 
        }
        .table td { 
            padding: 12px; 
            vertical-align: middle; 
        }
        .table { 
            animation: fadeIn 1.5s ease-out; 
        }
        .badge { 
            animation: pulse 2s infinite; 
        }
        .nav-tabs {
            display: flex;
            justify-content: flex-start;
            flex-wrap: nowrap;
            border-bottom: 3px solid #007bff;
            overflow-x: auto;
            margin-bottom: 20px;
            gap: 0.5rem;
        }
        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            padding: 12px 20px;
            transition: all 0.3s ease;
            white-space: nowrap;
            flex: 0 0 auto;
        }
        .nav-tabs .nav-link:hover {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        .nav-tabs .nav-link.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background-color: transparent;
        }
        /* تحويل الجدول إلى بطاقات - المحمول */
        @media (max-width: 768px) {
            .table, .table thead, .table tbody, .table th, .table td, .table tr {
                display: block;
                width: 100%;
            }
            .table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .table tbody {
                display: grid;
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .table tbody tr {
                display: block;
                border: 2px solid #e0e0e0;
                border-radius: 12px;
                background: white;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            /* تحسينات البطاقة - توسيع وتوسيط */
            .table tbody > tr > td {
                min-height: 500px; /* ارتفاع أدنى للبطاقة */
                display: flex;
                flex-direction: column;
            }
            .table tbody > tr > td > div:first-child {
                /* عنوان البطاقة */
                text-align: center;
                padding: 20px 15px;
            }
            .table tbody > tr > td > div:last-child {
                /* محتوى البطاقة */
                flex: 1;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0;
                width: 100%;
                min-height: 400px;
            }
            .table tbody > tr > td > div:last-child > div {
                padding: 20px 15px !important; /* زيادة الحشو */
                text-align: center; /* توسيط النص */
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                border-bottom: 1px solid #f0f0f0;
                border-left: 1px solid #f0f0f0;
            }
            .table tbody > tr > td > div:last-child > div:nth-child(odd) {
                border-left: none;
            }
            .table tbody > tr > td > div:last-child > div:last-child,
            .table tbody > tr > td > div:last-child > div:nth-last-child(2) {
                border-bottom: none;
            }
            .table td {
                display: block;
                border: none;
                padding: 12px !important;
                text-align: right;
                position: relative;
                border-bottom: 1px solid #f0f0f0;
            }
            .table tbody tr td:last-child {
                border-bottom: none;
            }
            .table td:before {
                content: attr(data-label);
                display: block;
                font-weight: bold;
                color: #28a745;
                font-size: 0.85rem;
                margin-bottom: 8px;
            }
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes bounceIn {
            0% { opacity: 0; transform: scale(0.3); }
            50% { opacity: 1; transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>

<div class="college-header animate__animated animate__fadeInDown">
    <img src="logo.png" alt="شعار الكلية" class="college-logo animate__animated animate__bounceIn">
    <h2 class="mt-2 animate__animated animate__fadeIn">لوحة التحكم - الإدارة</h2>
    <div class="datetime animate__animated animate__fadeIn animate__delay-1s">
        <i class="fas fa-calendar-alt"></i> <span id="dateText"></span> | <i class="fas fa-clock"></i> <span id="timeText"></span>
    </div>
</div>

<div class="container mt-4 animate__animated animate__fadeInUp animate__delay-0.5s">
    <div class="row mb-4">
        <div class="col-md-12 text-end">
            <a href="index.php" class="btn btn-outline-primary me-2">العودة للمكتبة</a>
            <a href="logout.php" class="btn btn-outline-secondary">تسجيل الخروج</a>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="p-3 bg-light rounded-4 shadow-sm text-center">
                <h6>الكتب المعتمدة</h6>
                <p class="display-6 mb-0"><?php echo number_format($totalBooks); ?></p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="p-3 bg-light rounded-4 shadow-sm text-center">
                <h6>طلبات بانتظار الموافقة</h6>
                <p class="display-6 mb-0"><?php echo number_format($pendingRequests); ?></p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="p-3 bg-light rounded-4 shadow-sm text-center">
                <h6>إجمالي المشاهدات</h6>
                <p class="display-6 mb-0"><?php echo number_format($totalViews); ?></p>
            </div>
        </div>
        <div class="col-md-2">
            <div class="p-3 bg-light rounded-4 shadow-sm text-center">
                <h6>إجمالي التنزيلات</h6>
                <p class="display-6 mb-0"><?php echo number_format($totalDownloads); ?></p>
            </div>
        </div>
   
        <div class="col-md-2">
            <div class="p-3 bg-light rounded-4 shadow-sm text-center">
                <h6>المحاضرات المرفوعة</h6>
                <p class="display-6 mb-0"><?php echo number_format($totalLectures); ?></p>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="books-tab" data-bs-toggle="tab" data-bs-target="#books" type="button" role="tab" aria-controls="books" aria-selected="true">الكتب</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="lectures-tab" data-bs-toggle="tab" data-bs-target="#lectures" type="button" role="tab" aria-controls="lectures" aria-selected="false">المحاضرات</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab" aria-controls="students" aria-selected="false">الطلاب</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="professors-tab" data-bs-toggle="tab" data-bs-target="#professors" type="button" role="tab" aria-controls="professors" aria-selected="false">إدارة الحسابات</button>
        </li>
    </ul>
    <div class="tab-content" id="adminTabsContent">
                <!-- تبويب الكتب -->
                <div class="tab-pane fade show active" id="books" role="tabpanel" aria-labelledby="books-tab">
                    <div class="mt-4">
                        <h5 class="bg-primary text-white p-2 rounded">إضافة كتاب جديد</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <input type="text" name="title" class="form-control" placeholder="عنوان الكتاب" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="author" class="form-control" placeholder="المؤلف">
                                </div>
                                <div class="col-md-2">
                                    <select name="specialty" class="form-select" required>
                                        <option value="جراحة الفم والوجه والفكين">جراحة الفم والوجه والفكين</option>
                                        <option value="تقويم الأسنان">تقويم الأسنان</option>
                                        <option value="امراض اللثة">امراض اللثة</option>
                                        <option value="طب أسنان الأطفال">طب أسنان الأطفال</option>
                                        <option value="تجميل الوجه والفكين">تجميل الوجه والفكين</option>
                                        <option value="الأشعة">الأشعة</option>
                                        <option value="زراعة الأسنان">زراعة الأسنان</option>
                                        <option value="صناعة أسنان">صناعة أسنان</option>
                                        <option value="غير ذلك">غير ذلك</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="date" name="publication_date" class="form-control" placeholder="تاريخ النشر">
                                </div>
                                <div class="col-md-2">
                                    <input type="url" name="link" class="form-control" placeholder="رابط خارجي">
                                </div>
                                <div class="col-md-3">
                                    <input type="file" name="pdf" class="form-control" accept=".pdf">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="submit" class="btn btn-success w-100">حفظ الكتاب</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="mt-4">
                        <h5 class="bg-success text-white p-2 rounded">قائمة الكتب</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped bg-white">
                                <thead class="table-dark">
                                    <tr>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result = mysqli_query($conn, "SELECT b.*, COALESCE(b.link, IF(b.pdf_file != '', CONCAT('uploads/', b.pdf_file), '')) AS book_link FROM books b ORDER BY b.created_at DESC");
                                    while($row = mysqli_fetch_assoc($result)) {
                                        $pdfLink = !empty($row['book_link']) ? htmlspecialchars($row['book_link']) : '';
                                        ?>
                                        <tr style="display: contents;">
                                            <td style="display: block; border: 4px solid #28a745; border-radius: 12px; background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 15px;">
                                                <!-- عنوان البطاقة -->
                                                <div style="background: #f8f9ff; border-bottom: 2px solid #28a745; padding: 20px 15px; font-size: 1.2rem; color: #28a745; font-weight: bold; text-align: center;">
                                                    <?php echo htmlspecialchars($row['title']); ?>
                                                </div>
                                                <!-- تفاصيل البطاقة في شبكة عمودية -->
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0; width: 100%; flex: 1;">
                                                    <!-- الصف 1: المؤلف -->
                                                    <div style="border-bottom: 1px solid #f0f0f0; border-left: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #28a745; font-size: 0.9rem; margin-bottom: 10px;">المؤلف</div>
                                                        <div><strong><?php echo htmlspecialchars($row['author']); ?></strong></div>
                                                    </div>
                                                    <div style="border-bottom: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #28a745; font-size: 0.9rem; margin-bottom: 10px;">التخصص</div>
                                                        <div><span class='badge bg-info'><?php echo htmlspecialchars($row['specialty']); ?></span></div>
                                                    </div>
                                                    
                                                    <!-- الصف 2: تاريخ النشر -->
                                                    <div style="border-bottom: 1px solid #f0f0f0; border-left: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #28a745; font-size: 0.9rem; margin-bottom: 10px;">تاريخ النشر</div>
                                                        <div><?php echo htmlspecialchars($row['publication_date']); ?></div>
                                                    </div>
                                                    <div style="border-bottom: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #28a745; font-size: 0.9rem; margin-bottom: 10px;">رابط خارجي</div>
                                                        <div><a href='<?php echo htmlspecialchars($row['external_link']); ?>' target='_blank'>زيارة</a></div>
                                                    </div>
                                                    
                                                    <!-- الصف 3: تحميل PDF -->
                                                    <div style="border-bottom: 1px solid #f0f0f0; border-left: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #28a745; font-size: 0.9rem; margin-bottom: 10px;">رابط</div>
                                                        <div>
                                                            <?php if(!empty($pdfLink)): ?>
                                                                <a href='<?php echo $pdfLink; ?>' target='_blank'>PDF</a>
                                                            <?php else: ?>
                                                                <span>لا يوجد</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div style="border-bottom: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #28a745; font-size: 0.9rem; margin-bottom: 10px;">تحميل</div>
                                                        <div>
                                                            <?php if(!empty($pdfLink)): ?>
                                                                <a href='<?php echo $pdfLink; ?>' target='_blank' download>تحميل</a>
                                                            <?php else: ?>
                                                                <span>لا يوجد</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div style="border-bottom: 1px solid #f0f0f0; border-left: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #28a745; font-size: 0.9rem; margin-bottom: 10px;">منشور بواسطة</div>
                                                        <?php $publisherName = htmlspecialchars(($row['publisher_name'] ?? '') ?: 'الإدارة'); ?>
                                                        <?php $publisherRole = $row['publisher_role'] ?? 'admin'; ?>
                                                        <div><?php echo $publisherName; ?> (<?php echo htmlspecialchars($publisherRole === 'admin' ? 'الإدارة' : ($publisherRole === 'professor' ? 'أستاذ' : 'طالب')); ?>)</div>
                                                    </div>
                                                    <div style="border-bottom: 1px solid #f0f0f0; border-left: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #28a745; font-size: 0.9rem; margin-bottom: 10px;">إحصائيات</div>
                                                        <div>مشاهدات: <?php echo intval($row['view_count'] ?? 0); ?></div>
                                                        <div>تنزيلات: <?php echo intval($row['download_count'] ?? 0); ?></div>
                                                    </div>
                                                    <div style="border-bottom: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #28a745; font-size: 0.9rem; margin-bottom: 10px;">الإجراءات</div>
                                                        <div>
                                                            <a href='view_pdf.php?id=<?php echo $row['id']; ?>' target='_blank' class='btn btn-sm btn-info'>رابط</a>
                                                            <button class='btn btn-sm btn-warning' data-bs-toggle='modal' data-bs-target='#editModal' onclick='editBook(<?php echo $row['id']; ?>, "<?php echo addslashes($row['title']); ?>", "<?php echo addslashes($row['author']); ?>", "<?php echo $row['specialty']; ?>", "<?php echo $row['publication_date']; ?>", "<?php echo addslashes($row['external_link']); ?>")'>تعديل</button>
                                                            <a href='?delete_book=<?php echo $row['id']; ?>' class='btn btn-sm btn-danger' onclick='return confirm("حذف؟")'>حذف</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                    <div class="tab-pane fade" id="lectures" role="tabpanel" aria-labelledby="lectures-tab">
                        <div class="mt-4">
                            <h5 class="bg-warning text-white p-2 rounded">رفع محاضرة جديدة</h5>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <input type="text" name="lecture_title" class="form-control" placeholder="عنوان المحاضرة" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="lecture_subject" class="form-control" placeholder="اسم المادة" required>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="lecture_stage" class="form-select" required>
                                            <option value="1">المرحلة 1</option>
                                            <option value="2">المرحلة 2</option>
                                            <option value="3">المرحلة 3</option>
                                            <option value="4">المرحلة 4</option>
                                            <option value="5">المرحلة 5</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="file" name="lecture_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx" required>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="submit" name="upload_admin_lecture" class="btn btn-success w-100">رفع</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="mt-4">
                            <h5 class="bg-info text-white p-2 rounded">قائمة المحاضرات</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped bg-white">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>العنوان</th>
                                            <th>المادة</th>
                                            <th>المرحلة</th>
                                            <th>الرفع بواسطة</th>
                                            <th>تاريخ الرفع</th>
                                            <th>تحميل</th>
                                            <th>إجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $lectureResult = mysqli_query($conn, "SELECT l.*, u.name AS uploader FROM lectures l LEFT JOIN users u ON l.added_by = u.id ORDER BY upload_date DESC");
                                        while($row = mysqli_fetch_assoc($lectureResult)) {
                                            echo "<tr>
                                                <td>" . htmlspecialchars($row['title']) . "</td>
                                                <td>" . htmlspecialchars($row['subject']) . "</td>
                                                <td>" . htmlspecialchars($row['stage']) . "</td>
                                                <td>" . htmlspecialchars($row['uploader'] ?: 'الإدارة') . " (" . htmlspecialchars($row['uploaded_by_role']) . ")</td>
                                                <td>" . htmlspecialchars($row['upload_date']) . "</td>
                                                <td><a href='uploads/" . htmlspecialchars($row['file_path']) . "' target='_blank' class='btn btn-sm btn-primary'>تحميل</a></td>
                                                <td><a href='?delete_lecture=" . $row['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"حذف؟\")'>حذف</a></td>
                                            </tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- تبويب الطلاب -->
                <div class="tab-pane fade" id="students" role="tabpanel" aria-labelledby="students-tab">
                    <div class="mt-4">
                        <h5 class="bg-warning text-white p-2 rounded">إضافة طالب جديد</h5>
                        <form method="POST" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="student_name" class="form-control" placeholder="اسم الطالب" required>
                            </div>
                            <div class="col-md-3">
                                <input type="email" name="student_email" class="form-control" placeholder="البريد الإلكتروني" required>
                            </div>
                            <div class="col-md-2">
                                <input type="password" name="student_password" class="form-control" placeholder="كلمة المرور" required>
                            </div>
                            <div class="col-md-2">
                                <select name="student_specialty" class="form-select" required>
                                    <option value="">التخصص</option>
                                    <option value="جراحة الفم والوجه والفكين">جراحة الفم والوجه والفكين</option>
                                    <option value="تقويم الأسنان">تقويم الأسنان</option>
                                    <option value="امراض اللثة">امراض اللثة</option>
                                    <option value="طب أسنان الأطفال">طب أسنان الأطفال</option>
                                    <option value="تجميل الوجه والفكين">تجميل الوجه والفكين</option>
                                    <option value="الأشعة">الأشعة</option>
                                    <option value="زراعة الأسنان">زراعة الأسنان</option>
                                    <option value="صناعة أسنان">صناعة أسنان</option>
                                    <option value="غير ذلك">غير ذلك</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="text" name="student_university" class="form-control" placeholder="الجامعة" required>
                            </div>
                            <div class="col-md-2">
                                <input type="text" name="student_faculty" class="form-control" placeholder="الكلية" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="add_student" class="btn btn-success w-100">إضافة طالب</button>
                            </div>
                        </form>
                    </div>
                    <div class="mt-4">
                        <h5 class="bg-danger text-white p-2 rounded">قائمة الطلاب المسجلين</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped bg-white">
                                <thead class="table-dark">
                                    <tr>
                                        <th>الاسم</th>
                                        <th>البريد</th>
                                        <th>التخصص</th>
                                        <th>الجامعة</th>
                                        <th>الكلية</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result = mysqli_query($conn, "SELECT * FROM students ORDER BY created_at DESC");
                                    while($row = mysqli_fetch_assoc($result)) {
                                        echo "<tr>
                                            <td>" . htmlspecialchars($row['name']) . "</td>
                                            <td>" . htmlspecialchars($row['email']) . "</td>
                                            <td>" . htmlspecialchars($row['specialty']) . "</td>
                                            <td>" . htmlspecialchars($row['university']) . "</td>
                                            <td>" . htmlspecialchars($row['faculty']) . "</td>
                                            <td>" . htmlspecialchars($row['created_at']) . "</td>
                                            <td><a href='?delete_student=" . $row['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"حذف؟\")'>حذف</a></td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h5 class="bg-warning text-white p-2 rounded">طلبات الكتب</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped bg-white">
                                <thead class="table-dark">
                                    <tr>
                                        <th>الاسم</th>
                                        <th>البريد</th>
                                        <th>عنوان الكتاب</th>
                                        <th>التخصص</th>
                                        <th>الرسالة</th>
                                        <th>تاريخ الطلب</th>
                                        <th>الحالة</th>
                                        <th>سبب الرفض / ملاحظات الإدارة</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result = mysqli_query($conn, "SELECT * FROM requests ORDER BY request_date DESC");
                                    while($row = mysqli_fetch_assoc($result)) {
                                        $status = htmlspecialchars($row['status'] ?? 'pending');
                                        $reason = htmlspecialchars($row['admin_reason'] ?? '');
                                        if(($row['status'] ?? 'pending') === 'approved') {
                                            $status = '<span class="badge bg-success">موافق عليه</span>';
                                        } elseif(($row['status'] ?? 'pending') === 'rejected') {
                                            $status = '<span class="badge bg-danger">مرفوض</span>';
                                        } else {
                                            $status = '<span class="badge bg-warning text-dark">قيد الانتظار</span>';
                                        }
                                        $actions = "<button type='button' class='btn btn-sm btn-info me-1' onclick='openRequestPreview(" . intval($row['id']) . ", \"" . addslashes($row['book_title']) . "\", \"" . addslashes($row['name']) . "\", \"" . addslashes($row['email']) . "\", \"" . addslashes($row['specialty']) . "\", \"" . addslashes($row['message']) . "\", \"" . addslashes($row['request_date']) . "\", \"" . addslashes($row['publication_date'] ?: 'غير محدد') . "\")'>رابط</button>";
                                        if(($row['status'] ?? 'pending') === 'pending') {
                                            $actions .= "<form method='POST' class='d-inline me-1'><input type='hidden' name='approve_request' value='" . intval($row['id']) . "'><button type='submit' class='btn btn-sm btn-success'>موافقة</button></form>";
                                            $actions .= "<button type='button' class='btn btn-sm btn-danger' onclick='openRejectModal(" . intval($row['id']) . ", \"" . addslashes($row['book_title']) . "\")'>رفض</button>";
                                        } else {
                                            $actions .= "<span class='badge bg-secondary'>تمت المعالجة</span>";
                                        }
                                        echo "<tr>
                                            <td>" . htmlspecialchars($row['name']) . "</td>
                                            <td>" . htmlspecialchars($row['email']) . "</td>
                                            <td>" . htmlspecialchars($row['book_title']) . "</td>
                                            <td>" . htmlspecialchars($row['specialty']) . "</td>
                                            <td>" . htmlspecialchars($row['message']) . "</td>
                                            <td>" . htmlspecialchars($row['request_date']) . "</td>
                                            <td>" . $status . "</td>
                                            <td>" . $reason . "</td>
                                            <td>" . $actions . "</td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- تبويب الأساتذة -->
                <div class="tab-pane fade" id="professors" role="tabpanel" aria-labelledby="professors-tab">
                    <div class="mt-4">
                        <h5 class="bg-secondary text-white p-2 rounded">إضافة حساب جديد</h5>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <input type="text" name="user_name" class="form-control" placeholder="الاسم" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="email" name="user_email" class="form-control" placeholder="البريد" required>
                                </div>
                                <div class="col-md-2">
                                    <select name="user_role" class="form-select">
                                        <option value="professor">أستاذ</option>
                                        <option value="admin">إدارة</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="password" name="user_password" class="form-control" placeholder="كلمة المرور (اختياري)">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="add_user" class="btn btn-success w-100">إضافة</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="mt-4">
                        <h5 class="bg-info text-white p-2 rounded">قائمة الحسابات</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped bg-white">
                                <thead class="table-dark">
                                    <tr>
                                        <th>الاسم</th>
                                        <th>البريد</th>
                                        <th>الدور</th>
                                        <th>تاريخ الإنشاء</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
                                    while($row = mysqli_fetch_assoc($result)) {
                                        echo "<tr>
                                            <td>" . htmlspecialchars($row['name']) . "</td>
                                            <td>" . htmlspecialchars($row['email']) . "</td>
                                            <td>" . htmlspecialchars($row['role']) . "</td>
                                            <td>" . htmlspecialchars($row['created_at']) . "</td>
                                            <td>
                                                <button class='btn btn-sm btn-warning' data-bs-toggle='modal' data-bs-target='#editPasswordModal' onclick='editPassword(" . $row['id'] . ", \"" . addslashes($row['name']) . "\")'>تعديل كلمة المرور</button>
                                                <a href='?delete_user=" . $row['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"حذف؟\")'>حذف</a>
                                            </td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h5 class="bg-danger text-white p-2 rounded">قائمة الطلاب المسجلين</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped bg-white">
                                <thead class="table-dark">
                                    <tr>
                                        <th>الاسم</th>
                                        <th>البريد</th>
                                        <th>التخصص</th>
                                        <th>الجامعة</th>
                                        <th>الكلية</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result_students = mysqli_query($conn, "SELECT * FROM students ORDER BY created_at DESC");
                                    while($row = mysqli_fetch_assoc($result_students)) {
                                        echo "<tr>
                                            <td>" . htmlspecialchars($row['name']) . "</td>
                                            <td>" . htmlspecialchars($row['email']) . "</td>
                                            <td>" . htmlspecialchars($row['specialty']) . "</td>
                                            <td>" . htmlspecialchars($row['university']) . "</td>
                                            <td>" . htmlspecialchars($row['faculty']) . "</td>
                                            <td>" . htmlspecialchars($row['created_at']) . "</td>
                                            <td><a href='?delete_student=" . $row['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"حذف؟\")'>حذف</a></td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal للتعديل -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">تعديل الكتاب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">العنوان</label>
                            <input type="text" name="edit_title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">المؤلف</label>
                            <input type="text" name="edit_author" id="edit_author" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">التخصص</label>
                            <select name="edit_specialty" id="edit_specialty" class="form-select" required>
                                <option value="جراحة الفم والوجه والفكين">جراحة الفم والوجه والفكين</option>
                                <option value="تقويم الأسنان">تقويم الأسنان</option>
                                <option value="امراض اللثة">امراض اللثة</option>
                                <option value="طب أسنان الأطفال">طب أسنان الأطفال</option>
                                <option value="تجميل الوجه والفكين">تجميل الوجه والفكين</option>
                                <option value="الأشعة">الأشعة</option>
                                <option value="زراعة الأسنان">زراعة الأسنان</option>
                                <option value="صناعة أسنان">صناعة أسنان</option>
                                <option value="غير ذلك">غير ذلك</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تاريخ النشر</label>
                            <input type="date" name="edit_pub_date" id="edit_pub_date" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الرابط الخارجي</label>
                            <input type="url" name="edit_link" id="edit_link" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="edit_book" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal لتعديل كلمة المرور -->
    <div class="modal fade" id="editPasswordModal" tabindex="-1" aria-labelledby="editPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPasswordModalLabel">تعديل كلمة المرور</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">الأستاذ: <span id="edit_user_name"></span></label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="edit_password" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal لرفض طلب الكتاب -->
    <div class="modal fade" id="rejectRequestModal" tabindex="-1" aria-labelledby="rejectRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectRequestModalLabel">رفض طلب الكتاب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="reject_request" id="reject_request_id">
                        <div class="mb-3">
                            <label class="form-label">سبب الرفض</label>
                            <textarea name="reject_reason" id="reject_reason" class="form-control" rows="4" placeholder="أدخل سبب الرفض..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger">رفض الطلب</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="previewRequestModal" tabindex="-1" aria-labelledby="previewRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewRequestModalLabel">معاينة طلب الكتاب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold mb-3" id="previewRequestTitle"></h6>
                    <div id="previewRequestContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editBook(id, title, author, specialty, pub_date, link) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_author').value = author;
            document.getElementById('edit_specialty').value = specialty;
            document.getElementById('edit_pub_date').value = pub_date;
            document.getElementById('edit_link').value = link;
        }
        function editPassword(id, name) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_user_name').textContent = name;
        }
        function openRejectModal(requestId, title) {
            document.getElementById('reject_request_id').value = requestId;
            document.getElementById('rejectRequestModalLabel').textContent = 'رفض طلب الكتاب: ' + title;
            document.getElementById('reject_reason').value = '';
            var modal = new bootstrap.Modal(document.getElementById('rejectRequestModal'));
            modal.show();
        }
        function openRequestPreview(requestId, title, name, email, specialty, message, requestDate, publicationDate) {
            document.getElementById('previewRequestTitle').textContent = title;
            const messageHtml = (message || '').replace(/\n/g, '<br>');
            document.getElementById('previewRequestContent').innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <div class="text-muted small">الاسم</div>
                            <div class="fw-bold">${name}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <div class="text-muted small">البريد</div>
                            <div class="fw-bold">${email}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <div class="text-muted small">التخصص</div>
                            <div class="fw-bold">${specialty}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <div class="text-muted small">تاريخ الطلب</div>
                            <div class="fw-bold">${requestDate}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="bg-light p-3 rounded">
                            <div class="text-muted small">تاريخ النشر</div>
                            <div class="fw-bold">${publicationDate}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="bg-light p-3 rounded">
                            <div class="text-muted small">الرسالة / الملاحظات</div>
                            <div class="fw-bold">${messageHtml}</div>
                        </div>
                    </div>
                </div>
            `;
            var modal = new bootstrap.Modal(document.getElementById('previewRequestModal'));
            modal.show();
        }
        function updateDateTime() {
            var now = new Date();
            var dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('dateText').textContent = now.toLocaleDateString('ar-EG', dateOptions);
            document.getElementById('timeText').textContent = now.toLocaleTimeString('ar-EG', { hour12: false });
        }
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 1000);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>