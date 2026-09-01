<?php 
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
include('db.php');
if (!isset($_SESSION['registration_code'])) {
    $_SESSION['registration_code'] = (string) random_int(1000, 9999);
}

// حدد الزائر بواسطة كوكيز ثابتة
if(empty($_COOKIE['visitor_token'])) {
    $visitor_token = bin2hex(random_bytes(16));
    setcookie('visitor_token', $visitor_token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
} else {
    $visitor_token = $_COOKIE['visitor_token'];
}

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$visitor_token_db = $visitor_token;

$sql = "SELECT id, visit_date FROM page_visitors WHERE visitor_token = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $visitor_token_db);
mysqli_stmt_execute($stmt);
$visitorQuery = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($visitorQuery) > 0) {
    $visitor = mysqli_fetch_assoc($visitorQuery);
    if($visitor['visit_date'] !== $today) {
        $sql_update = "UPDATE page_visitors SET last_activity = ?, visit_date = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "ssi", $now, $today, $visitor['id']);
        mysqli_stmt_execute($stmt_update);
        
        $sql_view = "INSERT INTO page_views (id, count) VALUES (1, 1) ON DUPLICATE KEY UPDATE count = count + 1";
        mysqli_query($conn, $sql_view);
    } else {
        $sql_update = "UPDATE page_visitors SET last_activity = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "si", $now, $visitor['id']);
        mysqli_stmt_execute($stmt_update);
    }
} else {
    $sql_insert = "INSERT INTO page_visitors (visitor_token, ip_address, user_agent, first_visit, last_activity, visit_date) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "ssssss", $visitor_token_db, $ip_address, $user_agent, $now, $now, $today);
    mysqli_stmt_execute($stmt_insert);
    
    $sql_view = "INSERT INTO page_views (id, count) VALUES (1, 1) ON DUPLICATE KEY UPDATE count = count + 1";
    mysqli_query($conn, $sql_view);
}

// عدد المشاهدات اليوم (كل جهاز مرة واحدة في اليوم)
$viewsResult = mysqli_query($conn, "SELECT COUNT(*) AS count FROM page_visitors WHERE visit_date = '$today'");
$views = 0;
if($viewsResult && $row = mysqli_fetch_assoc($viewsResult)) {
    $views = $row['count'];
}

// عدد المتواجدين الآن خلال آخر 5 دقائق
$active_since = date('Y-m-d H:i:s', time() - 60 * 5);
$onlineResult = mysqli_query($conn, "SELECT COUNT(*) AS online FROM page_visitors WHERE last_activity >= '$active_since'");
$online = 0;
if($onlineResult && $row = mysqli_fetch_assoc($onlineResult)) {
    $online = $row['online'];
}

// إحصائيات الزوار المسجلين (من أول جلسة إلى الآن)
$desktopLoginsResult = mysqli_query($conn, "SELECT COUNT(*) AS count FROM login_statistics WHERE device_type = 'Desktop'");
$desktopLogins = 0;
if($desktopLoginsResult && $row = mysqli_fetch_assoc($desktopLoginsResult)) {
    $desktopLogins = $row['count'];
}

$mobileLoginsResult = mysqli_query($conn, "SELECT COUNT(*) AS count FROM login_statistics WHERE device_type = 'Mobile'");
$mobileLogins = 0;
if($mobileLoginsResult && $row = mysqli_fetch_assoc($mobileLoginsResult)) {
    $mobileLogins = $row['count'];
}

$totalLogins = $desktopLogins + $mobileLogins;

// إجمالي زوار الموقع من التأسيس إلى اليوم
$totalVisitorsResult = mysqli_query($conn, "SELECT COUNT(*) AS count FROM page_visitors");
$totalVisitors = 0;
if($totalVisitorsResult && $row = mysqli_fetch_assoc($totalVisitorsResult)) {
    $totalVisitors = $row['count'];
}

$specialty = '';
if(!empty($_GET['specialty'])) {
    $specialty = mysqli_real_escape_string($conn, trim($_GET['specialty']));
}
$errors = [];
if(isset($_POST['request_book'])) {
    if(!isset($_SESSION['loggedin']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
        $errors[] = 'يجب تسجيل الدخول كطالب لإرسال طلب كتاب';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $book_title = trim($_POST['book_title']);
        $publication_date = trim($_POST['publication_date']);
        $req_specialty = $_POST['req_specialty'];
        $message = trim($_POST['message']);
        
        if(empty($name)) $errors[] = 'الاسم مطلوب';
        if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'بريد إلكتروني صحيح مطلوب';
        if(empty($book_title)) $errors[] = 'عنوان الكتاب مطلوب';
        if(empty($req_specialty)) $errors[] = 'التخصص مطلوب';
        
        if(empty($errors)) {
            $student_id = intval($_SESSION['user_id']);
            $sql = "INSERT INTO requests (student_id, name, email, book_title, specialty, message, request_date, publication_date) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "issssss", $student_id, $name, $email, $book_title, $req_specialty, $message, $publication_date);
            if(mysqli_stmt_execute($stmt)) {
                echo "<script>alert('تم إرسال طلبك بنجاح!');</script>";
            } else {
                $errors[] = 'حدث خطأ في قاعدة البيانات. يرجى المحاولة لاحقاً.';
            }
        }
    }
}
// جلب الإشعارات لعرضها في اللوحة العلوية
$notifications = [];
if(isset($_SESSION['loggedin'])) {
    if(($_SESSION['user_type'] ?? '') === 'admin') {
        $notifRes = mysqli_query($conn, "SELECT n.*, s.name AS student_name FROM notifications n LEFT JOIN students s ON n.student_id = s.id ORDER BY n.created_at DESC LIMIT 20");
    } else {
        $uid = intval($_SESSION['user_id'] ?? 0);
        $notifRes = mysqli_query($conn, "SELECT * FROM notifications WHERE student_id = $uid ORDER BY created_at DESC LIMIT 50");
    }
    if($notifRes) {
        while($nr = mysqli_fetch_assoc($notifRes)) {
            $notifications[] = $nr;
        }
    }
}

// flash messages (from redirect after actions like logout)
$flashMsg = '';
if(!empty($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $flashMsg = 'تم تسجيل الخروج بنجاح.';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مكتبة طب الأسنان</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #e8efff;
            --surface: #ffffff;
            --surface-strong: #f8fbff;
            --text: #1f2937;
            --muted: #5f6574;
            --border: #d6dee9;
            --primary: #0d47a1;
            --primary-soft: #1976d2;
            --success: #249a5b;
            --danger: #d6333e;
            --accent: #ffca28;
            --shadow: rgba(13, 71, 161, 0.12);
            --glass: rgba(255,255,255,0.6);
            --card-grad-1: rgba(13,71,161,0.06);
            --card-grad-2: rgba(35,161,106,0.04);
        }
        body {
            background: linear-gradient(135deg, #dfe8ff 0%, #eef4ff 100%);
            min-height: 100vh;
            font-family: 'Cairo', sans-serif;
            color: var(--text);
        }
        .college-header {
            background: linear-gradient(90deg, rgba(13,71,161,0.06), rgba(35,161,106,0.03));
            padding: 28px 22px;
            text-align: center;
            border-bottom: 4px solid rgba(13,71,161,0.08);
            border-radius: 14px;
            margin-bottom: 26px;
            box-shadow: 0 18px 40px rgba(6,24,55,0.08);
            animation: fadeInDown 0.9s ease-out;
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 18px;
            align-items: center;
        }
        .college-logo {
            max-height: 110px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(13,71,161,0.08);
            animation: bounceIn 1.2s ease-out;
        }
        .header-body {
            text-align: right;
        }
        .datetime {
            font-size: 0.98rem;
            color: var(--muted);
            margin-top: 6px;
            animation: fadeIn 1.5s ease-out;
        }
        .stats-badges { display:flex; gap:10px; justify-content:flex-end; margin-top:6px; }
        .stats-badges .badge { background: var(--glass); color: var(--text); font-weight:700; padding:8px 12px; border-radius:999px; border:1px solid var(--border); }
        .container {
            background: var(--surface);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 15px 35px var(--shadow);
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
        .btn-toggle-mode {
            border: 2px solid var(--border);
            background: transparent;
            color: var(--text);
            font-weight: 600;
            transition: all 0.25s ease;
        }
        .btn-toggle-mode:hover {
            background: rgba(13,71,161,0.08);
            border-color: var(--primary);
            color: var(--primary);
        }
        .action-tabs {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            overflow-x: auto;
            padding-top: 5px;
            width: 100%;
        }
        .student-action-card {
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            color: white;
            border-radius: 18px;
            padding: 18px 22px;
            box-shadow: 0 18px 60px rgba(13, 71, 161, 0.25);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            position: relative;
            overflow: hidden;
        }
        .student-action-card::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
        }
        .student-action-card h3 {
            font-size: 1.25rem;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .student-action-card p {
            opacity: 0.95;
            margin-bottom: 14px;
            max-width: 560px;
        }
        .student-action-card .btn-request {
            background: #ffca28;
            color: #0d47a1;
            border: none;
            font-weight: 700;
            padding: 10px 20px;
            box-shadow: 0 10px 25px rgba(255,202,40,0.25);
        }
        .student-action-card .btn-request:hover {
            background: #ffc107;
        }
        .request-highlight {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.22);
            color: #fff;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        .student-action-icon {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.18);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }
        .student-action-card { transition: transform 0.28s ease, box-shadow 0.28s ease; }
        .student-action-card:hover { transform: translateY(-6px); box-shadow: 0 28px 80px rgba(8,30,78,0.18); }
        .top-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px 14px;
            gap: 18px;
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 0.95rem;
            flex-wrap: wrap;
            background: linear-gradient(90deg, rgba(255,255,255,0.5), transparent 20%, transparent 80%, rgba(255,255,255,0.5));
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(13,71,161,0.08);
            backdrop-filter: blur(10px);
            animation: slideInDown 0.8s ease-out;
            transition: all 0.3s ease;
        }
        .top-bar:hover {
            box-shadow: 0 16px 45px rgba(13,71,161,0.12);
            transform: translateY(-2px);
        }
        .top-bar .bar-left,
        .top-bar .bar-right {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .top-action-group {
            position: relative;
        }
        .top-action-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            background: rgba(255,255,255,0.92);
            border: 1px solid var(--border);
            box-shadow: 0 8px 18px rgba(31, 41, 55, 0.08);
            color: var(--text);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-decoration: none;
            animation: slideInUp 0.5s ease-out;
        }
        .top-action-button:nth-child(1) { animation-delay: 0.05s; }
        .top-action-button:nth-child(2) { animation-delay: 0.1s; }
        .top-action-button:nth-child(3) { animation-delay: 0.15s; }
        .top-action-button:nth-child(4) { animation-delay: 0.2s; }
        .top-action-button:nth-child(5) { animation-delay: 0.25s; }
        .top-action-button:hover,
        .top-action-button:focus {
            background: var(--surface-strong);
            transform: translateY(-4px) scale(1.05);
            border-color: rgba(13,71,161,0.3);
            box-shadow: 0 14px 32px rgba(13,71,161,0.15);
            outline: none;
        }
        .top-action-button:active {
            transform: translateY(-2px) scale(1.03);
        }
        .top-action-button-link {
            text-decoration: none;
        }
        /* Ensure top bar action buttons remain readable in dark mode */
        body.dark-mode .top-action-button {
            background: rgba(255,255,255,0.04);
            color: #e8ecff;
            border-color: rgba(255,255,255,0.06);
        }
        body.dark-mode .top-action-button:hover {
            background: rgba(65,150,255,0.15);
            border-color: rgba(65,150,255,0.3);
            box-shadow: 0 14px 32px rgba(65,150,255,0.15);
        }
        .top-action-button-link { color: inherit; }
        .dropdown-panel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 250px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(31, 41, 55, 0.12);
            padding: 10px 0;
            display: none;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .dropdown-panel.show {
            display: block;
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        .dropdown-title {
            padding: 10px 16px;
            font-size: 0.85rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .02em;
            font-weight: 700;
        }
        .dropdown-item,
        .dropdown-panel a {
            display: block;
            padding: 12px 16px;
            color: var(--text);
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .dropdown-item:hover,
        .dropdown-panel a:hover {
            background: rgba(13,71,161,0.08);
            padding-right: 18px;
        }
        .bar-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.85);
            box-shadow: 0 8px 20px rgba(31, 41, 55, 0.08);
            color: var(--text);
            font-weight: 600;
            transition: all 0.3s ease;
            animation: slideInUp 0.6s ease-out 0.35s both;
        }
        .bar-item:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 28px rgba(13,71,161,0.12);
        }
        .bar-item span {
            font-size: 1.05rem;
        }
        .bar-item small {
            color: var(--muted);
            font-size: 0.82rem;
        }
        #themeToggle {
            font-size: 1.2rem;
            padding: 10px 14px;
            min-width: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            animation: slideInUp 0.5s ease-out;
        }
        #themeToggle:hover {
            transform: scale(1.12) rotate(20deg);
            filter: drop-shadow(0 6px 14px rgba(13,71,161,0.2));
        }
        #themeToggle:active {
            transform: scale(1.08) rotate(10deg);
        }
        body.dark-mode .top-bar {
            background: linear-gradient(90deg, rgba(13,23,45,0.6), transparent 20%, transparent 80%, rgba(13,23,45,0.6));
            box-shadow: 0 12px 35px rgba(0,0,0,0.2);
        }
        body.dark-mode .top-bar:hover {
            box-shadow: 0 16px 45px rgba(65,150,255,0.12);
        }
        body.dark-mode .bar-item {
            background: rgba(13,23,45,0.7);
            color: #cbd6ef;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        body.dark-mode .bar-item:hover {
            box-shadow: 0 12px 28px rgba(65,150,255,0.12);
        }
        body.dark-mode {
            background: linear-gradient(135deg, #0a1929 0%, #1a2942 100%);
            color: #e8edf8;
        }
        body.dark-mode .college-header {
            background: linear-gradient(90deg, rgba(15,23,45,0.9), rgba(24,26,50,0.9));
            border-color: #2d5a8c;
            box-shadow: 0 18px 50px rgba(0,0,0,0.6);
        }
        body.dark-mode .container {
            background: linear-gradient(180deg, #111d35 0%, #0d1622 100%);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        body.dark-mode .datetime {
            color: #b8d0f0;
        }
        body.dark-mode .book-card {
            background: linear-gradient(180deg, #182845 0%, #0d1520 100%);
            border-color: rgba(65,150,255,0.12);
        }
        body.dark-mode .book-card:hover {
            box-shadow: 0 30px 80px rgba(65,150,255,0.15);
            border-color: rgba(65,150,255,0.2);
        }
        body.dark-mode .book-cover {
            background: linear-gradient(180deg, rgba(13,23,45,0.6), rgba(8,14,28,0.8));
            border-color: rgba(65,150,255,0.06);
        }
        body.dark-mode .book-header {
            color: #4da6ff;
            border-color: rgba(65,150,255,0.08);
        }
        body.dark-mode .book-details p {
            color: #cbd6ef;
        }
        body.dark-mode .college-header h2,
        body.dark-mode .dateText {
            color: #e6ecff;
        }
        body.dark-mode .btn-custom {
            color: #fff;
            background: linear-gradient(45deg, #0d47a1, #1976d2);
        }
        body.dark-mode .btn-custom:hover {
            background: linear-gradient(45deg, #1976d2, #1565c0);
        }
        body.dark-mode .btn-toggle-mode {
            color: #e8edf8;
            border-color: rgba(65,150,255,0.3);
        }
        body.dark-mode .btn-toggle-mode:hover {
            background: rgba(65,150,255,0.08);
        }
        body.dark-mode .top-action-button {
            background: rgba(13,23,45,0.7);
            color: #cbd6ef;
            border-color: rgba(65,150,255,0.1);
        }
        body.dark-mode .top-action-button:hover {
            background: rgba(65,150,255,0.12);
            border-color: rgba(65,150,255,0.2);
        }
        body.dark-mode .dropdown-panel {
            background: #0f1a32;
            border-color: rgba(65,150,255,0.1);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        body.dark-mode .dropdown-item,
        body.dark-mode .dropdown-panel a {
            color: #cbd6ef;
        }
        body.dark-mode .dropdown-item:hover,
        body.dark-mode .dropdown-panel a:hover {
            background: rgba(65,150,255,0.08);
        }
        body.dark-mode .stats-badges .badge {
            background: rgba(13,23,45,0.7);
            border-color: rgba(65,150,255,0.15);
            color: #cbd6ef;
        }
        body.dark-mode .table tbody tr {
            background: #152040;
            border-color: #22355d;
        }
        body.dark-mode .table thead {
            background: #172a4f;
        }
        body.dark-mode .table th,
        body.dark-mode .table td {
            color: #dbe3ff;
        }
        body.dark-mode .modal-content {
            background: #0f1a32;
            color: #e8ecff;
        }
        /* make iframe/pdf container match dark mode (PDF content may still be white) */
        body.dark-mode .modal-body,
        body.dark-mode .pdf-viewer,
        body.dark-mode .modal-body iframe,
        body.dark-mode .modal-body object {
            background: #0f1a32 !important;
            color: #e8ecff !important;
        }
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background: #152040;
            border-color: #22355d;
            color: #e8ecff;
        }
        body.dark-mode .form-control::placeholder,
        body.dark-mode .form-select option {
            color: #cbd6ef;
        }
        body.dark-mode .btn-outline-primary,
        body.dark-mode .btn-outline-secondary,
        body.dark-mode .btn-outline-success,
        body.dark-mode .btn-outline-info {
            color: #cbd6ef;
            border-color: #375bb6;
        }
        body.dark-mode .btn-outline-primary:hover,
        body.dark-mode .btn-outline-secondary:hover,
        body.dark-mode .btn-outline-success:hover,
        body.dark-mode .btn-outline-info:hover {
            background: rgba(255,255,255,0.06);
        }
        @media (max-width: 768px) {
            .student-action-card {
                flex-direction: column;
                align-items: stretch;
                text-align: right;
            }
            .student-action-icon {
                margin: 0 auto;
            }
        }
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .action-tabs .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            min-width: 110px;
            padding: 8px 14px;
            font-size: 0.95rem;
        }
        @media (max-width: 992px) {
            .action-tabs {
                justify-content: flex-start;
            }
        }
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 24px;
            margin-top: 24px;
            padding: 12px;
            background: linear-gradient(180deg, rgba(255,255,255,0.3), transparent);
            border-radius: 16px;
        }
        body.dark-mode .books-grid {
            background: linear-gradient(180deg, rgba(65,150,255,0.02), transparent);
        }
        .book-card {
            background: linear-gradient(180deg,var(--card-grad-1),var(--card-grad-2));
            border: 1px solid rgba(13,71,161,0.06);
            border-radius: 16px;
            box-shadow: 0 18px 46px rgba(6,24,55,0.08);
            overflow: hidden;
            transition: transform 0.34s cubic-bezier(.2,.9,.2,1), box-shadow 0.34s ease;
            animation: fadeInUp 0.6s ease-out;
            display: flex;
            flex-wrap: nowrap;
            min-height: 280px;
            position: relative;
        }
        .book-card:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 30px 80px rgba(6,24,55,0.15);
            border-color: rgba(13,71,161,0.14);
        }
        .book-cover {
            flex: 0 0 40%;
            width: 40%;
            max-width: 40%;
            min-width: 40%;
            border-right: 1px solid rgba(13,71,161,0.04);
            background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
            position: relative;
            min-height: 100%;
        }
        .book-cover iframe, .book-cover img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            background: var(--surface);
        }
        .cover-overlay {
            position: absolute;
            left: 12px;
            bottom: 12px;
            display:flex; gap:8px;
        }
        .cover-overlay .btn { padding:8px 10px; border-radius:10px; font-size:0.9rem; }
        .no-cover {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--muted);
            font-size: 1rem;
            padding: 15px;
            text-align: center;
        }
        .book-info {
            flex: 0 0 55%;
            width: 55%;
            min-width: 55%;
            display: flex;
            flex-direction: column;
            padding: 16px 20px;
        }
        .book-header {
            padding: 12px 14px;
            text-align: right;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--primary);
            background: transparent;
            border-bottom: 1px dashed rgba(13,71,161,0.04);
        }
        .book-details {
            flex: 1;
            padding: 16px 0 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .book-details p {
            margin: 0;
            font-size: 0.95rem;
            color: var(--muted);
        }
        .stats-small {
            display: flex;
            gap: 16px;
            margin-top: 10px !important;
            font-size: 0.9rem;
        }
        .stats-small span {
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
            background: rgba(13,71,161,0.04);
        }
        body.dark-mode .stats-small span {
            background: rgba(65,150,255,0.08);
            color: #4da6ff;
        }
        #searchInput {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--surface);
            color: var(--text);
        }
        #searchInput:focus {
            border-color: var(--primary);
            box-shadow: 0 0 12px rgba(13,71,161,0.18);
            outline: none;
        }
        #searchInput:focus {
            border-color: #0056b3;
            box-shadow: 0 0 8px rgba(0, 86, 179, 0.2);
            outline: none;
        }
        /* Ensure form controls and search input always have readable contrast */
        input.form-control, textarea.form-control, .form-select, #searchInput {
            background: var(--surface);
            color: var(--text);
            border-color: var(--border);
        }

        /* Dark mode overrides for inputs and select to avoid white-on-white */
        body.dark-mode input.form-control,
        body.dark-mode textarea.form-control,
        body.dark-mode .form-select,
        body.dark-mode #searchInput {
            background: #152040;
            color: #e8ecff;
            border-color: #22355d;
        }

        body.dark-mode input::placeholder,
        body.dark-mode textarea::placeholder {
            color: #9fb0d6;
            opacity: 1;
        }
        #noResults {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 1.2rem;
        }
        .no-books {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 1.2rem;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 768px) {
            .books-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .book-card {
                min-height: 280px;
            }
            .book-cover {
                flex: 0 0 50%;
                width: 50%;
                min-width: 50%;
                max-width: 50%;
                min-height: 240px;
            }
            .book-info {
                flex: 0 0 50%;
                width: 50%;
                min-width: 50%;
                padding: 8px;
            }
            .book-header {
                font-size: 1rem;
                padding: 10px 8px;
            }
            .book-details p {
                font-size: 0.82rem;
            }
        }
        @media (max-width: 480px) {
            .books-grid {
                gap: 10px;
            }
            .book-card {
                border-radius: 10px;
                min-height: 240px;
            }
            .book-cover {
                flex: 0 0 50%;
                width: 50%;
                min-width: 50%;
                max-width: 50%;
                min-height: 200px;
            }
            .book-info {
                flex: 0 0 50%;
                width: 50%;
                min-width: 50%;
                padding: 6px;
            }
            .book-header {
                font-size: 0.95rem;
                padding: 8px 6px;
            }
            .book-details p {
                font-size: 0.72rem;
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
        /* Premium animations */
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes pulseGlow {
            0% { box-shadow: 0 18px 46px rgba(6,24,55,0.08); }
            50% { box-shadow: 0 28px 60px rgba(13,71,161,0.12); }
            100% { box-shadow: 0 18px 46px rgba(6,24,55,0.08); }
        }
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
        .book-card {
            animation: fadeInUp 0.6s ease-out;
        }
        .book-card:nth-child(1) { animation-delay: 0s; }
        .book-card:nth-child(2) { animation-delay: 0.1s; }
        .book-card:nth-child(3) { animation-delay: 0.2s; }
        .book-card:nth-child(4) { animation-delay: 0.3s; }
        .book-card:nth-child(n+5) { animation-delay: 0.4s; }
        .cover-overlay .btn {
            transition: all 0.25s ease;
        }
        .cover-overlay .btn:hover {
            transform: scale(1.08);
        }
        .btn-hero {
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .btn-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 40px rgba(13,71,161,0.28) !important;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }
            .college-header {
                padding: 15px;
            }
            .college-logo {
                max-height: 80px;
            }
            .college-header h2 {
                font-size: 1.2rem;
            }
            .datetime {
                font-size: 1rem;
            }
            .container {
                padding: 15px;
                margin: 10px;
            }
            .action-tabs {
                flex-direction: column;
                align-items: stretch;
                gap: 5px;
            }
            .action-tabs .btn {
                min-width: auto;
                width: 100%;
            }
            /* تحويل الجدول إلى بطاقات - المحمول فقط */
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
                width: 100%;
            }
            .table tbody tr {
                display: block;
                border: 2px solid #e0e0e0;
                border-radius: 12px;
                background: white;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .table tbody tr td:first-child {
                background: #f8f9ff;
                border-bottom: 2px solid #28a745;
                font-size: 1.1rem;
                color: #28a745;
                font-weight: bold;
                padding: 15px !important;
                display: block;
                border: none !important;
            }
            .table tbody tr > td {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0;
                width: 100%;
                border: none !important;
                padding: 0 !important;
            }
            .table tbody tr > td > * {
                border-bottom: 1px solid #f0f0f0;
                border-left: 1px solid #f0f0f0;
                padding: 12px;
                text-align: right;
            }
            .table td {
                display: block;
                border: none;
                padding: 12px !important;
                text-align: right;
                position: relative;
            }
            .table td:before {
                content: attr(data-label);
                display: block;
                font-weight: bold;
                color: #28a745;
                font-size: 0.85rem;
                margin-bottom: 8px;
            }
            .modal-dialog {
                margin: 10px;
            }
            .modal-body {
                padding: 15px;
            }
        }

        @media (max-width: 576px) {
            .college-header h2 {
                font-size: 1rem;
            }
            .table {
                font-size: 0.8rem;
            }
            .btn {
                font-size: 0.85rem;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>

<div class="college-header animate__animated animate__fadeInDown">
    <img src="logo.png" alt="شعار الكلية" class="college-logo animate__animated animate__bounceIn">
    <div class="header-body">
        <h2 class="mt-0 animate__animated animate__fadeIn">المكتبة الإلكترونية لكلية طب الأسنان</h2>
        <div class="datetime animate__animated animate__fadeIn animate__delay-1s">منصة موثوقة لمشاركة الكتب والمحاضرات الأكاديمية</div>
        <div class="stats-badges animate__animated animate__fadeIn animate__delay-1s">
            <div class="badge">المشاهدات اليوم: <strong><?php echo number_format($views); ?></strong></div>
            <div class="badge">المتواجدون الآن: <strong><?php echo number_format($online); ?></strong></div>
            <div class="badge">إجمالي الزوار: <strong><?php echo number_format($totalVisitors); ?></strong></div>
        </div>
    </div>
</div>

<?php if(!empty($flashMsg)): ?>
    <div class="container mt-3">
        <div class="alert alert-success text-center"><?php echo htmlspecialchars($flashMsg); ?></div>
    </div>
<?php endif; ?>

    <div class="top-bar">
    <div class="bar-left">
        <?php if(isset($_SESSION['loggedin']) && isset($_SESSION['user_type'])): ?>
            <?php if(($_SESSION['user_type'] ?? '') === 'admin'): ?>
                <span class="top-action-button" style="cursor:default;"><span>🛠️</span> مرحبا بك، <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'الإدارة'); ?></span>
                <a href="admin.php" class="top-action-button top-action-button-link" style="margin-left:8px;">لوحة التحكم</a>
            <?php endif; ?>
          
        <?php else: ?>
            <button id="headerLoginButton" type="button" class="top-action-button" data-bs-toggle="modal" data-bs-target="#loginModal"><span>🔑</span> تسجيل الدخول</button>
        <?php endif; ?>
        <?php if(isset($_SESSION['loggedin']) && ($_SESSION['user_type'] ?? '') === 'student'): ?>
            <button type="button" class="top-action-button" data-bs-toggle="modal" data-bs-target="#requestModal"><span>📚</span> طلب كتاب</button>
        <?php else: ?>
            <button type="button" class="top-action-button" id="requestLoginButton" data-bs-toggle="modal" data-bs-target="#loginModal"><span>📚</span> طلب كتاب</button>
        <?php endif; ?>
        <div class="top-action-group">
            <a href="lectures.php" class="top-action-button top-action-button-link"><span>🎓</span> المحاضرات</a>
            <div class="dropdown-panel" id="stagesPanel">
                <div class="dropdown-title">اختر المرحلة</div>
                <a href="lectures.php?stage=1">المرحلة الأولى</a>
                <a href="lectures.php?stage=2">المرحلة الثانية</a>
                <a href="lectures.php?stage=3">المرحلة الثالثة</a>
                <a href="lectures.php?stage=4">المرحلة الرابعة</a>
                <a href="lectures.php?stage=5">المرحلة الخامسة</a>
            </div>
        </div>
        <div class="top-action-group">
            <button id="notifButton" class="top-action-button" type="button"><span>🔔</span> الإشعارات</button>
            <div class="dropdown-panel" id="notifPanel">
                <div class="dropdown-title">آخر الإشعارات</div>
                <?php if(empty($notifications)): ?>
                    <div class="dropdown-item">لا توجد إشعارات جديدة</div>
                <?php else: ?>
                    <?php foreach($notifications as $n): ?>
                        <div class="dropdown-item">
                            <div class="small text-muted"><?php echo htmlspecialchars($n['created_at'] ?? ''); ?></div>
                            <div><?php echo htmlspecialchars($n['message'] ?? ''); ?></div>
                            <?php if(!empty($n['student_name'])): ?>
                                <div class="small text-muted">من: <?php echo htmlspecialchars($n['student_name']); ?></div>
                            <?php elseif(!empty($n['student_id'])): ?>
                                <div class="small text-muted">من رقم: <?php echo intval($n['student_id']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="bar-right">
        <?php if(isset($_SESSION['loggedin']) && isset($_SESSION['user_type'])): ?>
            <a href="logout.php" class="top-action-button top-action-button-link"><span>🚪</span> خروج</a>
        <?php endif; ?>
    </div>
</div>

<div class="container mt-4 animate__animated animate__fadeInUp animate__delay-0.5s">
    <div class="row mb-4">
        <div class="col-md-8">
            <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center flex-grow-1">
                    <label class="form-label me-2 mb-0">🔍 بحث:</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="ابحث عن اسم الكتاب أو المؤلف...">
                </div>
                <div class="d-flex align-items-center">
                    <label class="form-label me-2 mb-0">📚 التخصص:</label>
                    <select name="specialty" class="form-select w-auto" onchange="this.form.submit()">
                        <option value="" <?php if($specialty == '') echo 'selected'; ?>>كل التخصصات</option>
                        <option value="جراحة الفم والوجه والفكين" <?php if($specialty == 'جراحة الفم والوجه والفكين') echo 'selected'; ?>>جراحة الفم والوجه والفكين</option>
                        <option value="تقويم الأسنان" <?php if($specialty == 'تقويم الأسنان') echo 'selected'; ?>>تقويم الأسنان</option>
                        <option value="امراض اللثة" <?php if($specialty == 'امراض اللثة') echo 'selected'; ?>>امراض اللثة</option>
                        <option value="طب أسنان الأطفال" <?php if($specialty == 'طب أسنان الأطفال') echo 'selected'; ?>>طب أسنان الأطفال</option>
                        <option value="تجميل الوجه والفكين" <?php if($specialty == 'تجميل الوجه والفكين') echo 'selected'; ?>>تجميل الوجه والفكين</option>
                        <option value="الأشعة" <?php if($specialty == 'الأشعة') echo 'selected'; ?>>الأشعة</option>
                        <option value="زراعة الأسنان" <?php if($specialty == 'زراعة الأسنان') echo 'selected'; ?>>زراعة الأسنان</option>
                        <option value="صناعة أسنان" <?php if($specialty == 'صناعة أسنان') echo 'selected'; ?>>صناعة أسنان</option>
                        <option value="غير ذلك" <?php if($specialty == 'غير ذلك') echo 'selected'; ?>>غير ذلك</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" id="themeToggle" class="btn btn-toggle-mode" title="تبديل الوضع الداكن">🌙</button>
        </div>
    </div>

    <?php if(!isset($_SESSION['loggedin'])): ?>
    <div class="alert alert-info">يمكنك تسجيل الدخول للاستفادة من طلب الكتب والملف الشخصي والإشعارات مباشرة من الشريط العلوي.</div>
    <?php endif; ?>

    <?php
        $totalBooksResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM books");
        $totalBooks = 0;
        if($totalBooksResult && $totalRow = mysqli_fetch_assoc($totalBooksResult)) {
            $totalBooks = intval($totalRow['total']);
        }
        $groupResult = mysqli_query($conn, "SELECT specialty, COUNT(*) AS count FROM books GROUP BY specialty ORDER BY count DESC");
    ?>





    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-white rounded-4 p-3 shadow-sm">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <strong class="me-2">مجموعات الكتب:</strong>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary<?php echo $specialty == '' ? ' active' : ''; ?>">الكل (<?php echo $totalBooks; ?>)</a>
                    <?php
                        if($groupResult) {
                            while($groupRow = mysqli_fetch_assoc($groupResult)) {
                                $groupSpecialty = htmlspecialchars($groupRow['specialty']);
                                $groupCount = intval($groupRow['count']);
                                $activeClass = $specialty == $groupRow['specialty'] ? ' active' : '';
                                echo "<a href=\"?specialty=" . urlencode($groupRow['specialty']) . "\" class=\"btn btn-sm btn-outline-primary{$activeClass}\">{$groupSpecialty} ({$groupCount})</a>";
                            }
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="books-grid animate__animated animate__fadeIn animate__delay-1s">
        <?php
        $statusColumnResult = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'status'");
        $hasStatusColumn = $statusColumnResult && mysqli_num_rows($statusColumnResult) > 0;

        $query = "SELECT * FROM books";
        if ($hasStatusColumn) {
            $query .= " WHERE status = 'approved'";
        }
        if($specialty) {
            $specialtyEscaped = mysqli_real_escape_string($conn, $specialty);
            if ($hasStatusColumn) {
                $query .= " AND specialty = '$specialtyEscaped'";
            } else {
                $query .= " WHERE specialty = '$specialtyEscaped'";
            }
        }
        $query .= " ORDER BY specialty, title";
        
        $result = mysqli_query($conn, $query);
        if(!$result) {
            echo "<div class='no-books'>خطأ في قاعدة البيانات: " . mysqli_error($conn) . "</div>";
        } else {
        while($row = mysqli_fetch_assoc($result)) {
            $title = htmlspecialchars($row['title']);
            $author = htmlspecialchars($row['author']);
            $publisher = htmlspecialchars(($row['publisher_name'] ?? '') ?: 'الإدارة');
            $publisher_role = $row['publisher_role'] ?? 'admin';
            $publisher_label = htmlspecialchars($publisher_role === 'admin' ? 'الإدارة' : ($publisher_role === 'professor' ? 'الأستاذ' : 'الطالب'));
            $viewCount = intval($row['view_count'] ?? 0);
            $downloadCount = intval($row['download_count'] ?? 0);
            ?>
            <div class="book-card" data-title="<?php echo $title; ?>" data-author="<?php echo $author; ?>">
                <!-- غلاف الكتاب -->
                <div class="book-cover">
                    <?php if(!empty($row['pdf_file'])): ?>
                        <iframe src="uploads/<?php echo rawurlencode(basename($row['pdf_file'])); ?>#page=1" width="100%" height="100%" style="border: none;"></iframe>
                    <?php else: ?>
                        <div class="no-cover">غلاف غير متوفر</div>
                    <?php endif; ?>
                    <div class="cover-overlay">
                        <?php if(!empty($row['pdf_file'])): ?>
                            <a href="view_pdf.php?id=<?php echo intval($row['id']); ?>" class="btn btn-sm btn-primary">تصفح</a>
                            <a href="download_pdf.php?id=<?php echo intval($row['id']); ?>" class="btn btn-sm btn-danger" download>تحميل</a>
                        <?php else: ?>
                            <a href="lectures.php" class="btn btn-sm btn-outline-secondary">المزيد</a>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- معلومات الكتاب -->
                <div class="book-info">
                    <!-- عنوان البطاقة -->
                    <div class="book-header">
                        <?php echo htmlspecialchars($row['title']); ?>
                    </div>
                    <!-- تفاصيل الكتاب المختصرة -->
                    <div class="book-details">
                        <p><strong>المؤلف:</strong> <?php echo htmlspecialchars($row['author']); ?></p>
                        <p><strong>التخصص:</strong> <?php echo htmlspecialchars($row['specialty']); ?></p>
                        <p><strong>النشر:</strong> <?php echo htmlspecialchars($row['publication_date']); ?></p>
                        <p><strong>الناشر:</strong> <?php echo htmlspecialchars($publisher); ?></p>
                        <?php if(!empty($row['pdf_file'])): ?>
                            <p class="stats-small"><span>👁️ <?php echo $viewCount; ?></span> <span>⬇️ <?php echo $downloadCount; ?></span></p>
                        <?php endif; ?>
                        <?php if(!empty($row['external_link'])): ?>
                            <p><a href='<?php echo htmlspecialchars($row['external_link']); ?>' class='link-secondary' target='_blank'>رابط إضافي ↗</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php
        }
        if(mysqli_num_rows($result) == 0) {
            echo "<div class='no-books'>لا توجد كتب متاحة لهذا التخصص.</div>";
        }
        }
        ?>
    </div>
</div>

<!-- Modal لطلب كتاب -->
<div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestModalLabel">طلب كتاب جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php if(!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">الاسم</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">عنوان الكتاب</label>
                        <input type="text" name="book_title" class="form-control" value="<?php echo htmlspecialchars($_POST['book_title'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">تاريخ النشر (اختياري)</label>
                        <input type="text" name="publication_date" class="form-control" value="<?php echo htmlspecialchars($_POST['publication_date'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">التخصص</label>
                        <select name="req_specialty" class="form-select" required>
                            <option value="">اختر التخصص</option>
                            <option value="جراحة الفم والوجه والفكين" <?php if(($_POST['req_specialty'] ?? '') == 'جراحة الفم والوجه والفكين') echo 'selected'; ?>>جراحة الفم والوجه والفكين</option>
                            <option value="تقويم الأسنان" <?php if(($_POST['req_specialty'] ?? '') == 'تقويم الأسنان') echo 'selected'; ?>>تقويم الأسنان</option>
                            <option value="امراض اللثة" <?php if(($_POST['req_specialty'] ?? '') == 'امراض اللثة') echo 'selected'; ?>>امراض اللثة</option>
                            <option value="طب أسنان الأطفال" <?php if(($_POST['req_specialty'] ?? '') == 'طب أسنان الأطفال') echo 'selected'; ?>>طب أسنان الأطفال</option>
                            <option value="تجميل الوجه والفكين" <?php if(($_POST['req_specialty'] ?? '') == 'تجميل الوجه والفكين') echo 'selected'; ?>>تجميل الوجه والفكين</option>
                            <option value="الأشعة" <?php if(($_POST['req_specialty'] ?? '') == 'الأشعة') echo 'selected'; ?>>الأشعة</option>
                            <option value="زراعة الأسنان" <?php if(($_POST['req_specialty'] ?? '') == 'زراعة الأسنان') echo 'selected'; ?>>زراعة الأسنان</option>
                            <option value="صناعة أسنان" <?php if(($_POST['req_specialty'] ?? '') == 'صناعة أسنان') echo 'selected'; ?>>صناعة أسنان</option>
                            <option value="غير ذلك" <?php if(($_POST['req_specialty'] ?? '') == 'غير ذلك') echo 'selected'; ?>>غير ذلك</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">رسالة إضافية</label>
                        <textarea name="message" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="request_book" class="btn btn-custom">إرسال الطلب</button>
                </div>
            </form>
        </div>
    </div>
</div>

        <!-- Modal تسجيل دخول (منبثق، بنفس ستايل الصفحة) -->
        <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="loginModalLabel">تسجيل الدخول</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="loginForm" method="POST" action="login.php">
                        <input type="hidden" name="login" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="modal-body">
                            <div class="alert-area"></div>
                            <div class="mb-3">
                                <label class="form-label">البريد الإلكتروني أو اسم المستخدم</label>
                                <input type="text" name="username" class="form-control" placeholder="البريد أو اسم المستخدم" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">كلمة المرور</label>
                                <input type="password" name="password" class="form-control" placeholder="كلمة المرور" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                                <label class="form-check-label" for="rememberCheck">تذكرني</label>
                            </div>
                            <div class="text-muted small">يمكنك استخدام حسابك للدخول وطلب الكتب والإشعارات.</div>
                            <div class="mt-3">
                                <a href="#" id="openRegister" class="link-primary">إنشاء حساب جديد كطالب</a>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-custom">تسجيل الدخول</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal تسجيل طالب -->
        <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="registerModalLabel">تسجيل حساب طالب</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="register.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">الاسم الكامل</label>
                                <input type="text" name="name" class="form-control" placeholder="الاسم الكامل" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">البريد الإلكتروني</label>
                                <input type="email" name="email" class="form-control" placeholder="example@domain.com" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">كلمة المرور</label>
                                <input type="password" name="password" class="form-control" placeholder="كلمة المرور" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">تأكيد كلمة المرور</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="تأكيد كلمة المرور" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">الجامعة</label>
                                <input type="text" name="university" class="form-control" placeholder="اسم الجامعة" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">الكلية</label>
                                <input type="text" name="faculty" class="form-control" placeholder="اسم الكلية" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">رقم الهاتف</label>
                                <input type="text" name="phone" class="form-control" placeholder="0770xxxxxxx" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">اكتب رمز التحقق الظاهر</label>
                                <div class="alert alert-secondary text-center fw-bold fs-4 mb-2"><?php echo htmlspecialchars($_SESSION['registration_code']); ?></div>
                                <input type="text" name="registration_code" class="form-control text-center" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required>
                            </div>
                            <input type="hidden" name="user_type" value="student">
                            <div class="text-muted small">بإنشاء حساب ستتمكن من طلب الكتب وتلقي الإشعارات والوصول للموارد.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-custom">إنشاء الحساب</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

<?php if(!empty($errors)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var myModal = new bootstrap.Modal(document.getElementById('requestModal'));
        myModal.show();
    });
</script>
<?php endif; ?>
<script>
    function updateDateTime() {
        var now = new Date();
        var dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('dateText').textContent = now.toLocaleDateString('ar-EG', dateOptions);
        document.getElementById('timeText').textContent = now.toLocaleTimeString('ar-EG', { hour12: false });
    }
    
    // البحث الفوري عن الكتب
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.trim().toLowerCase();
        const bookCards = document.querySelectorAll('.book-card');
        let visibleCount = 0;
        
        bookCards.forEach(card => {
            const title = card.getAttribute('data-title').toLowerCase();
            const author = card.getAttribute('data-author').toLowerCase();
            
            if (title.includes(searchTerm) || author.includes(searchTerm) || searchTerm === '') {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // إذا لم توجد نتائج
        let noResultsDiv = document.getElementById('noResults');
        if (visibleCount === 0 && searchTerm !== '') {
            if (!noResultsDiv) {
                noResultsDiv = document.createElement('div');
                noResultsDiv.id = 'noResults';
                noResultsDiv.className = 'no-books';
                noResultsDiv.textContent = 'لم يتم العثور على نتائج للبحث';
                document.querySelector('.books-grid').appendChild(noResultsDiv);
            }
        } else if (noResultsDiv) {
            noResultsDiv.remove();
        }
    });
    
    function setTheme(mode) {
        var isDark = mode === 'dark';
        document.body.classList.toggle('dark-mode', isDark);
        var toggleButton = document.getElementById('themeToggle');
        if (toggleButton) {
            toggleButton.textContent = isDark ? '☀️' : '🌙';
        }
        localStorage.setItem('dentalTheme', isDark ? 'dark' : 'light');
    }

    function toggleTheme() {
        var currentMode = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
        setTheme(currentMode === 'dark' ? 'light' : 'dark');
    }

    function closeDropdowns() {
        document.querySelectorAll('.dropdown-panel.show').forEach(panel => panel.classList.remove('show'));
    }

    document.addEventListener('click', function(event) {
        var notifButton = document.getElementById('notifButton');
        var notifPanel = document.getElementById('notifPanel');
        var stagesPanel = document.getElementById('stagesPanel');
        var lecturesLink = document.querySelector('a[href="lectures.php"]');
        
        if (notifButton && notifPanel && notifButton.contains(event.target)) return;
        if (notifPanel && notifPanel.contains(event.target)) return;
        if (lecturesLink && stagesPanel && lecturesLink.contains(event.target)) return;
        if (stagesPanel && stagesPanel.contains(event.target)) return;
        
        closeDropdowns();
    });

    var lecturesLink = document.querySelector('a[href="lectures.php"]');
    if (lecturesLink) {
        lecturesLink.addEventListener('click', function(event) {
            event.preventDefault();
            var stagesPanel = document.getElementById('stagesPanel');
            if (stagesPanel) {
                stagesPanel.classList.toggle('show');
            }
        });
    }

    document.getElementById('themeToggle')?.addEventListener('click', toggleTheme);
    document.getElementById('notifButton')?.addEventListener('click', function(event) {
        event.stopPropagation();
        var notifPanel = document.getElementById('notifPanel');
        if (!notifPanel) return;
        notifPanel.classList.toggle('show');
    });

    // Open register modal from login modal
    document.getElementById('openRegister')?.addEventListener('click', function(e) {
        e.preventDefault();
        var loginModalEl = document.getElementById('loginModal');
        var registerModalEl = document.getElementById('registerModal');
        if (loginModalEl) {
            var loginModal = bootstrap.Modal.getInstance(loginModalEl) || new bootstrap.Modal(loginModalEl);
            loginModal.hide();
        }
        if (registerModalEl) {
            var regModal = new bootstrap.Modal(registerModalEl);
            regModal.show();
        }
    });

    // AJAX register: submit register form without leaving page
    var registerForm = document.querySelector('#registerModal form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(registerForm);
            // include the register button name so server recognizes submission
            formData.append('register', '1');
            var submitBtn = registerForm.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            fetch('register.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).then(function(res) {
                var contentType = (res.headers.get('content-type') || '').toLowerCase();
                if (contentType.indexOf('application/json') !== -1) {
                    return res.json();
                }
                return res.text().then(function(text){ throw new Error('non-json:' + text.slice(0,800)); });
            }).then(function(data) {
                if (submitBtn) submitBtn.disabled = false;
                var alertArea = registerForm.querySelector('.alert-area');
                if (!alertArea) {
                    alertArea = document.createElement('div');
                    alertArea.className = 'alert-area';
                    registerForm.insertBefore(alertArea, registerForm.firstChild);
                }
                alertArea.innerHTML = '';
                if (!data) {
                    alertArea.innerHTML = '<div class="alert alert-danger">تعذر الاتصال بالخادم.</div>';
                    return;
                }
                if (data.success) {
                    alertArea.innerHTML = '<div class="alert alert-success">' + (data.message || 'تم التسجيل بنجاح') + '</div>';
                    // close modal after short delay and open login modal
                    setTimeout(function() {
                        var regModalEl = document.getElementById('registerModal');
                        var regModal = bootstrap.Modal.getInstance(regModalEl) || new bootstrap.Modal(regModalEl);
                        regModal.hide();
                        var loginModalEl = document.getElementById('loginModal');
                        if (loginModalEl) {
                            var loginModal = new bootstrap.Modal(loginModalEl);
                            loginModal.show();
                        }
                    }, 1200);
                } else {
                    var html = '<div class="alert alert-danger"><ul class="mb-0">';
                    (data.errors || ['حدث خطأ']).forEach(function(err) { html += '<li>' + err + '</li>'; });
                    html += '</ul></div>';
                    alertArea.innerHTML = html;
                }
            }).catch(function(err) {
                if (submitBtn) submitBtn.disabled = false;
                var alertArea = registerForm.querySelector('.alert-area');
                if (!alertArea) {
                    alertArea = document.createElement('div');
                    alertArea.className = 'alert-area';
                    registerForm.insertBefore(alertArea, registerForm.firstChild);
                }
                if (err && err.message && err.message.indexOf('non-json:') === 0) {
                    var serverHtmlSnippet = err.message.replace('non-json:', '');
                    alertArea.innerHTML = '<div class="alert alert-danger">خطأ من الخادم: ' + serverHtmlSnippet.substring(0,200) + '...</div>';
                } else {
                    alertArea.innerHTML = '<div class="alert alert-danger">خطأ في الشبكة. يرجى المحاولة لاحقاً.</div>';
                }
            });
        });
    }

    // AJAX login: submit login form without leaving page
    var loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(loginForm);
            formData.set('login', '1');
            var submitBtn = loginForm.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            fetch('login.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).then(function(res) {
                return res.json().then(function(data) {
                    return { ok: res.ok, data: data };
                }).catch(function() {
                    throw new Error('invalid-json');
                });
            }).then(function(result) {
                if (submitBtn) submitBtn.disabled = false;
                var data = result.data;
                var alertArea = loginForm.querySelector('.alert-area');
                if (!alertArea) {
                    alertArea = document.createElement('div');
                    alertArea.className = 'alert-area';
                    loginForm.insertBefore(alertArea, loginForm.firstChild);
                }
                alertArea.innerHTML = '';
                if (result.ok && data.success) {
                    alertArea.innerHTML = '<div class="alert alert-success">' + (data.message || 'تم تسجيل الدخول بنجاح') + '</div>';
                    if (data.redirect) {
                        setTimeout(function() {
                            window.location.href = data.redirect;
                        }, 700);
                        return;
                    }
                    if (data.user_name) {
                        updateHeaderAfterLogin(data.user_name, data.user_type || 'student');
                    }
                    setTimeout(function() {
                        var loginModalEl = document.getElementById('loginModal');
                        var loginModal = bootstrap.Modal.getInstance(loginModalEl) || new bootstrap.Modal(loginModalEl);
                        loginModal.hide();
                    }, 800);
                } else {
                    var errors = (data.errors && data.errors.length) ? data.errors : [data.message || 'اسم المستخدم أو كلمة المرور غير صحيحة'];
                    var html = '<div class="alert alert-danger"><ul class="mb-0">';
                    errors.forEach(function(err) { html += '<li>' + err + '</li>'; });
                    html += '</ul></div>';
                    alertArea.innerHTML = html;
                }
            }).catch(function(err) {
                if (submitBtn) submitBtn.disabled = false;
                var alertArea = loginForm.querySelector('.alert-area');
                if (!alertArea) {
                    alertArea = document.createElement('div');
                    alertArea.className = 'alert-area';
                    loginForm.insertBefore(alertArea, loginForm.firstChild);
                }
                alertArea.innerHTML = '<div class="alert alert-danger">خطأ في الشبكة. يرجى المحاولة لاحقاً.</div>';
            });
        });
    }

    function updateHeaderAfterLogin(name, userType) {
        var barLeft = document.querySelector('.top-bar .bar-left');
        var barRight = document.querySelector('.top-bar .bar-right');
        if (barLeft) {
            var loginButton = document.getElementById('headerLoginButton');
            var profileLink = document.createElement('a');
            profileLink.href = 'profile.php';
            profileLink.className = 'top-action-button top-action-button-link';
            var icon = document.createElement('span');
            icon.textContent = '👤';
            profileLink.appendChild(icon);
            profileLink.appendChild(document.createTextNode(' ' + name));
            if (loginButton) {
                barLeft.replaceChild(profileLink, loginButton);
            } else {
                barLeft.insertBefore(profileLink, barLeft.firstChild);
            }
        }
        if (barRight && !barRight.querySelector('a[href="logout.php"]')) {
            var logoutLink = document.createElement('a');
            logoutLink.href = 'logout.php';
            logoutLink.className = 'top-action-button top-action-button-link';
            var icon = document.createElement('span');
            icon.textContent = '🚪';
            logoutLink.appendChild(icon);
            logoutLink.appendChild(document.createTextNode(' خروج'));
            barRight.appendChild(logoutLink);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var savedTheme = localStorage.getItem('dentalTheme') || 'light';
        setTheme(savedTheme);
        updateDateTime();
        setInterval(updateDateTime, 1000);
    });

    // Download a book via direct browser navigation to avoid fetch issues
    async function downloadBook(id, title) {
        try {
            var downloadUrl = 'download_pdf.php?id=' + encodeURIComponent(id);
            var a = document.createElement('a');
            a.href = downloadUrl;
            a.target = '_blank';
            a.rel = 'noopener';
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        } catch (err) {
            alert('تعذر تحميل الملف. حاول مرة أخرى.');
            console.error(err);
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<footer class="text-center text-muted py-3">المبرمج: عباس خضير</footer>
</body>
</html>
