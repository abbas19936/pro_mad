<?php
require_once 'security.php';
secure_session_start();
include('db.php');
if(isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($stmt = mysqli_prepare($conn, "SELECT id, name, password, role FROM users WHERE email = ?")) {
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $id, $name, $hash, $role);
        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            if (password_verify($password, $hash)) {
                session_regenerate_id(true);
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_type'] = $role;
                header('Location: ' . ($role === 'admin' ? 'admin.php' : 'professor.php'));
                exit;
            }
        } else {
            mysqli_stmt_close($stmt);
        }
    }

    if ($stmt = mysqli_prepare($conn, "SELECT id, name, password FROM students WHERE email = ?")) {
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $student_id, $student_name, $student_hash);
        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            if (password_verify($password, $student_hash)) {
                session_regenerate_id(true);
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $student_id;
                $_SESSION['user_name'] = $student_name;
                $_SESSION['user_type'] = 'student';
                header('Location: index.php');
                exit;
            }
        } else {
            mysqli_stmt_close($stmt);
        }
    }

    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="purple-bg login-page">

<div class="college-header animate__animated animate__fadeInDown">
    <img src="logo.png" alt="شعار الكلية" class="college-logo animate__animated animate__bounceIn">
    <h2 class="mt-2 animate__animated animate__fadeIn">تسجيل الدخول</h2>
    <p class="animate__animated animate__fadeIn animate__delay-1s">ادخل بياناتك للوصول إلى النظام</p>
</div>

<div class="container animate__animated animate__fadeInUp animate__delay-0.5s">
    <div class="row justify-content-center">
        <div class="col-md-6">

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
            <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني/email</label>
                    <input type="email" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">كلمة المرور/password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="login" class="btn btn-custom w-100">دخول</button>
            </form>
            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-outline-primary w-100 mb-2">العودة للمكتبة</a>
                <p class="mt-3">
                    <a href="register.php">تسجيل طالب جديد</a>
                </p>
                <p>
                    <a href="forgot_password.php">نسيت كلمة المرور؟</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js" defer></script>
</body>
</html>
</body>
</html>