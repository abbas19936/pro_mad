<?php
require_once 'security.php';
secure_session_start();
include('db.php');
if(isset($_POST['admin_login'])) {
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
    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>دخول لوحة التحكم</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="purple-bg login-page">

<div class="college-header animate__animated animate__fadeInDown">
    <img src="logo.png" alt="شعار الكلية" class="college-logo animate__animated animate__bounceIn">
    <h2 class="mt-2 animate__animated animate__fadeIn">دخول لوحة التحكم</h2>
    <p class="animate__animated animate__fadeIn animate__delay-1s">للإدارة والأساتذة فقط</p>
</div>

<div class="container animate__animated animate__fadeInUp animate__delay-0.5s">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="admin_login" class="btn btn-custom w-100">دخول</button>
            </form>
            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-outline-primary w-100 mb-2">العودة للمكتبة</a>
                <p class="mt-3">
                    أنت طالب؟ <a href="login.php">تسجيل الدخول كطالب</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js" defer></script>
</body>
</html>