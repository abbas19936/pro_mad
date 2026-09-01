<?php
session_start();
// prevent PHP warnings breaking AJAX JSON responses; log to file instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
include('db.php');

// Detect AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

function sendJson($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// دالة لتحديد نوع الجهاز
function getDeviceType($userAgent) {
    if (preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone|BlackBerry|Opera Mini|webOS/', $userAgent)) {
        return 'Mobile';
    }
    return 'Desktop';
}

// Initialize login attempts tracking
if(!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if(isset($_POST['login']) && !isValidCsrfToken($_POST['csrf_token'] ?? '')) {
    if($isAjax) sendJson(['success' => false, 'message' => 'طلب غير صالح. أعد تحميل الصفحة.']);
    $error = 'طلب غير صالح. أعد تحميل الصفحة.';
}

if(isset($_POST['login']) && isValidCsrfToken($_POST['csrf_token'] ?? '')) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if(empty($username) || empty($password)) {
        $error = 'البريد الإلكتروني وكلمة المرور مطلوبة';
    } else {
        $deviceType = getDeviceType($_SERVER['HTTP_USER_AGENT']);
        $found_user = false;
        
        // تحقق من الإدارة أو الأساتذة باستخدام prepared statement
        $sql = "SELECT id, name, password, role FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if(mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $uid, $uname, $uhash, $urole);
                mysqli_stmt_fetch($stmt);
                if(password_verify($password, $uhash)) {
                    session_regenerate_id(true);
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $uid;
                    $_SESSION['user_name'] = $uname;
                    $_SESSION['user_type'] = $urole;
                    $_SESSION['login_time'] = time();
                    $_SESSION['login_attempts'] = 0;
                    $found_user = true;

                    // تسجيل الدخول في الإحصائيات باستخدام prepared statement
                    $sql_log = "INSERT INTO login_statistics (user_id, device_type) VALUES (?, ?)";
                    $stmt_log = mysqli_prepare($conn, $sql_log);
                    if($stmt_log) {
                        mysqli_stmt_bind_param($stmt_log, "is", $uid, $deviceType);
                        mysqli_stmt_execute($stmt_log);
                        mysqli_stmt_close($stmt_log);
                    }

                    if($urole == 'admin') {
                            if ($isAjax) {
                                sendJson(["success" => true, "message" => 'تم تسجيل الدخول بنجاح', "user_name" => $uname, "user_type" => $urole, "redirect" => 'admin.php']);
                            }
                            header('Location: admin.php');
                    } else {
                            if ($isAjax) {
                                sendJson(["success" => true, "message" => 'تم تسجيل الدخول بنجاح', "user_name" => $uname, "user_type" => $urole, "redirect" => 'professor.php']);
                            }
                            header('Location: professor.php');
                    }
                    exit;
                }
            }
            mysqli_stmt_close($stmt);
        }

        // تحقق من الطلاب باستخدام prepared statement
        $sql_student = "SELECT id, name, password FROM students WHERE email = ?";
        $stmt_student = mysqli_prepare($conn, $sql_student);
        if($stmt_student) {
            mysqli_stmt_bind_param($stmt_student, "s", $username);
            mysqli_stmt_execute($stmt_student);
            mysqli_stmt_store_result($stmt_student);
            if(mysqli_stmt_num_rows($stmt_student) == 1) {
                mysqli_stmt_bind_result($stmt_student, $sid, $sname, $shash);
                mysqli_stmt_fetch($stmt_student);
                if(password_verify($password, $shash)) {
                    session_regenerate_id(true);
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $sid;
                    $_SESSION['user_name'] = $sname;
                    $_SESSION['user_type'] = 'student';
                    $_SESSION['login_time'] = time();
                    $_SESSION['login_attempts'] = 0;
                    $found_user = true;

                    // تسجيل الدخول في الإحصائيات
                    $sql_log = "INSERT INTO login_statistics (user_id, device_type) VALUES (?, ?)";
                    $stmt_log = mysqli_prepare($conn, $sql_log);
                    if($stmt_log) {
                        mysqli_stmt_bind_param($stmt_log, "is", $sid, $deviceType);
                        mysqli_stmt_execute($stmt_log);
                        mysqli_stmt_close($stmt_log);
                    }

                    if ($isAjax) {
                        sendJson(["success" => true, "message" => 'تم تسجيل الدخول بنجاح', "user_name" => $sname, "user_type" => 'student', "redirect" => 'index.php']);
                    }
                    header('Location: index.php');
                    exit;
                }
            }
            mysqli_stmt_close($stmt_student);
        }
        
        if(!$found_user) {
            $_SESSION['login_attempts']++;
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
            if ($isAjax) {
                sendJson(["success" => false, "errors" => [$error]]);
            }
        }
    }
}

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    sendJson(["success" => false, "errors" => [$error ?? 'حدث خطأ أثناء تسجيل الدخول.']]);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول</title>
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
    <h2 class="mt-2 animate__animated animate__fadeIn">تسجيل الدخول</h2>
    <p class="animate__animated animate__fadeIn animate__delay-1s">ادخل بياناتك للوصول إلى النظام</p>
</div>

<div class="container animate__animated animate__fadeInUp animate__delay-0.5s">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php if(isset($error)) echo "<div class='alert alert-danger'>" . htmlspecialchars($error) . "</div>"; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="login" class="btn btn-custom w-100">دخول</button>
            </form>
                <div class="text-center mt-3">
                    <a href="recover.php" class="d-block mb-2">نسيت كلمة المرور؟ استرجاع عبر سؤال الأمان</a>
                    <a href="index.php" class="btn btn-outline-primary w-100 mb-2">العودة للمكتبة</a>
                    <p class="mt-3">
                        <a href="register.php">تسجيل طالب جديد</a>
                    </p>
                </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
</body>
</html>