<?php
session_start();
include('db.php');

// Initialize login attempts tracking
if(!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempt_time'] = time();
}

// Rate limiting: max 5 attempts per 15 minutes
if($_SESSION['login_attempts'] >= 5) {
    if(time() - $_SESSION['login_attempt_time'] < 900) { // 15 minutes
        $error = 'تم تجاوز عدد محاولات الدخول. يرجى المحاولة لاحقاً.';
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

if(isset($_POST['admin_login']) && !isValidCsrfToken($_POST['csrf_token'] ?? '')) {
    $error = 'طلب غير صالح. أعد تحميل الصفحة.';
}

if(isset($_POST['admin_login']) && isValidCsrfToken($_POST['csrf_token'] ?? '') && !isset($error)) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if(empty($username) || empty($password)) {
        $error = 'البريد الإلكتروني وكلمة المرور مطلوبة';
    } else {
        // تحقق من الإدارة أو الأساتذة باستخدام prepared statement
        $sql = "SELECT id, name, password, role FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_assoc($result);
            if(password_verify($password, $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['name'];
                $_SESSION['user_type'] = $row['role'];
                $_SESSION['login_time'] = time();
                $_SESSION['login_attempts'] = 0;
                
                if($row['role'] == 'admin') {
                    header('Location: admin.php');
                } else {
                    header('Location: professor.php');
                }
                exit;
            } else {
                $_SESSION['login_attempts']++;
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
            }
        } else {
            $_SESSION['login_attempts']++;
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        }
    }
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
    <h2 class="mt-2 animate__animated animate__fadeIn">دخول لوحة التحكم</h2>
    <p class="animate__animated animate__fadeIn animate__delay-1s">للإدارة والأساتذة فقط</p>
</div>

<div class="container animate__animated animate__fadeInUp animate__delay-0.5s">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php if(isset($error)) echo "<div class='alert alert-danger'>" . htmlspecialchars($error) . "</div>"; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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
</body>
</html>