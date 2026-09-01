<?php
include('db.php');
// avoid leaking PHP warnings into AJAX JSON responses; log instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

$errors = [];
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $security_question = trim($_POST['security_question'] ?? '');
    $security_answer = trim($_POST['security_answer'] ?? '');
    $pin_code = trim($_POST['pin_code'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني صحيح مطلوب';
    }
    if(empty($security_question)) {
        $errors[] = 'سؤال الأمان مطلوب';
    }
    if(empty($security_answer)) {
        $errors[] = 'جواب سؤال الأمان مطلوب';
    }
    if(empty($pin_code) || !preg_match('/^[0-9]{4}$/', $pin_code)) {
        $errors[] = 'الرمز PIN يجب أن يكون 4 أرقام';
    }
    if(empty($new_password)) {
        $errors[] = 'كلمة المرور الجديدة مطلوبة';
    }
    if($new_password !== $confirm_password) {
        $errors[] = 'كلمتا المرور غير متطابقتين';
    }
    if(!preg_match('/^[0-9]{6,}$/', $new_password)) {
        $errors[] = 'كلمة المرور يجب أن تكون 6 أرقام على الأقل';
    }

    if(empty($errors)) {
        $sql = "SELECT id, security_question, security_answer, pin_code FROM students WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if(mysqli_stmt_num_rows($stmt) === 1) {
                mysqli_stmt_bind_result($stmt, $sid, $db_question, $db_answer, $db_pin);
                mysqli_stmt_fetch($stmt);
                if($db_question !== $security_question) {
                    $errors[] = 'سؤال الأمان غير مطابق';
                } elseif(!password_verify($security_answer, $db_answer)) {
                    $errors[] = 'جواب سؤال الأمان غير صحيح';
                } elseif(!password_verify($pin_code, $db_pin)) {
                    $errors[] = 'رمز PIN غير صحيح';
                } else {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = "UPDATE students SET password = ? WHERE id = ?";
                    $stmt_update = mysqli_prepare($conn, $update);
                    if($stmt_update) {
                        mysqli_stmt_bind_param($stmt_update, 'si', $new_hash, $sid);
                        if(mysqli_stmt_execute($stmt_update)) {
                            $success = 'تم تحديث كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.';
                        } else {
                            $errors[] = 'حدث خطأ أثناء تحديث كلمة المرور.';
                        }
                        mysqli_stmt_close($stmt_update);
                    } else {
                        $errors[] = 'خطأ في قاعدة البيانات. يرجى المحاولة لاحقاً.';
                    }
                }
            } else {
                $errors[] = 'المستخدم غير موجود';
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'خطأ في قاعدة البيانات. يرجى المحاولة لاحقاً.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>استعادة كلمة المرور</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: 'Cairo', sans-serif; display: flex; justify-content: center; align-items: center; }
        .recover-card { max-width: 520px; width: 100%; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="card recover-card">
        <div class="card-header bg-primary text-white text-center">
            <h4>استعادة كلمة المرور</h4>
        </div>
        <div class="card-body">
            <?php if(!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">سؤال الأمان</label>
                    <select name="security_question" class="form-select" required>
                        <option value="">اختر سؤال الأمان</option>
                        <option value="ما اسم المدرسة الابتدائية؟" <?php if(($_POST['security_question'] ?? '') == 'ما اسم المدرسة الابتدائية؟') echo 'selected'; ?>>ما اسم المدرسة الابتدائية؟</option>
                        <option value="ما اسم والدتك؟" <?php if(($_POST['security_question'] ?? '') == 'ما اسم والدتك؟') echo 'selected'; ?>>ما اسم والدتك؟</option>
                        <option value="ما اسم أول حيوان أليف؟" <?php if(($_POST['security_question'] ?? '') == 'ما اسم أول حيوان أليف؟') echo 'selected'; ?>>ما اسم أول حيوان أليف؟</option>
                        <option value="ما اسم المدينة التي ولدت فيها؟" <?php if(($_POST['security_question'] ?? '') == 'ما اسم المدينة التي ولدت فيها؟') echo 'selected'; ?>>ما اسم المدينة التي ولدت فيها؟</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">جواب سؤال الأمان</label>
                    <input type="text" name="security_answer" class="form-control" value="<?php echo htmlspecialchars($_POST['security_answer'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">رمز PIN الرباعي</label>
                    <input type="text" name="pin_code" class="form-control" value="<?php echo htmlspecialchars($_POST['pin_code'] ?? ''); ?>" maxlength="4" pattern="[0-9]{4}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">تحديث كلمة المرور</button>
            </form>
            <div class="text-center mt-3">
                <a href="login.php" class="btn btn-outline-primary w-100">العودة لتسجيل الدخول</a>
            </div>
        </div>
    </div>
</body>
</html>
