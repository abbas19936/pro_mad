<?php
require_once 'security.php';
secure_session_start();
include('db.php');
$error = '';
$success = '';
$security_question = '';
$email = '';
$step = 'email';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'يرجى إدخال بريد إلكتروني صحيح';
    } elseif(!preg_match('/@uowasit\.edu\.iq$/i', $email)) {
        $error = 'يجب أن ينتهي البريد بـ @uowasit.edu.iq';
    } else {
        if ($stmt = mysqli_prepare($conn, "SELECT id, security_question, security_answer FROM students WHERE email = ?")) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $student_id, $student_question, $student_answer_hash);
            if (mysqli_stmt_fetch($stmt)) {
                mysqli_stmt_close($stmt);
                $student = [
                    'id' => $student_id,
                    'security_question' => $student_question,
                    'security_answer' => $student_answer_hash,
                ];

                if(isset($_POST['security_answer'])) {
                    $security_answer = trim($_POST['security_answer']);
                    if(empty($security_answer)) {
                        $error = 'الرجاء إدخال الإجابة على سؤال التذكر';
                        $security_question = $student['security_question'];
                        $step = 'answer';
                    } elseif(!password_verify($security_answer, $student['security_answer'])) {
                        $error = 'الإجابة غير صحيحة';
                        $security_question = $student['security_question'];
                        $step = 'answer';
                    } else {
                        try {
                            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        } catch (Exception $e) {
                            $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        }
                        $new_password_hash = password_hash($code, PASSWORD_DEFAULT);
                        if ($updateStmt = mysqli_prepare($conn, "UPDATE students SET password = ? WHERE id = ?")) {
                            mysqli_stmt_bind_param($updateStmt, 'si', $new_password_hash, $student['id']);
                            mysqli_stmt_execute($updateStmt);
                            mysqli_stmt_close($updateStmt);
                        }

                        $subject = 'رمز إعادة تعيين كلمة المرور';
                        $message = "رمز إعادة تعيين كلمة المرور الخاص بك هو: $code\n\nيمكنك استخدام هذا الرمز لتسجيل الدخول وتغيير كلمة المرور لاحقاً.";
                        $headers = 'From: noreply@uowasit.edu.iq' . "\r\n" . 'Content-Type: text/plain; charset=utf-8';
                        @mail($email, $subject, $message, $headers);

                        $success = 'تم إرسال رمز مؤقت إلى بريدك الجامعي. يمكنك الآن تسجيل الدخول به.';
                        $step = 'done';
                    }
                } else {
                    $security_question = $student['security_question'];
                    $step = 'answer';
                }
            } else {
                mysqli_stmt_close($stmt);
                $error = 'لم يتم العثور على حساب لهذا البريد الإلكتروني';
            }
        } else {
            $error = 'حدث خطأ في النظام. حاول مرة أخرى لاحقاً.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>نسيت كلمة المرور</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="purple-bg forgot-page">
    <div class="card">
        <div class="card-header bg-primary text-white text-center">
            <h4>استعادة كلمة المرور</h4>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if($step !== 'done'): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">البريد الإلكتروني الجامعي</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <?php if($step === 'answer' && $security_question): ?>
                        <div class="mb-3">
                            <label class="form-label">سؤال التذكر</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($security_question); ?>" disabled>
                            <input type="hidden" name="security_question" value="<?php echo htmlspecialchars($security_question); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الإجابة</label>
                            <input type="text" name="security_answer" class="form-control" required>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary w-100"><?php echo $step === 'answer' ? 'إرسال الرمز' : 'متابعة'; ?></button>
                </form>
            <?php endif; ?>
            <div class="text-center mt-3">
                <a href="login.php" class="btn btn-outline-secondary w-100 mb-2">العودة لتسجيل الدخول</a>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js" defer></script>
</body>
</html>