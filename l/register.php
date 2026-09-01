<?php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
include('db.php');
// avoid leaking PHP warnings into AJAX JSON responses; log instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

$requiredStudentColumns = [
    'university' => 'VARCHAR(255)',
    'faculty' => 'VARCHAR(255)',
    'birth_date' => 'DATE',
    'address' => 'VARCHAR(255)',
    'phone' => 'VARCHAR(50)',
    'official_email' => 'VARCHAR(255)',
    'security_question' => 'VARCHAR(255)',
    'security_answer' => 'VARCHAR(255)',
    'pin_code' => 'VARCHAR(255)'
];
foreach ($requiredStudentColumns as $column => $definition) {
    $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE '$column'");
    if ($checkColumn && mysqli_num_rows($checkColumn) === 0) {
        mysqli_query($conn, "ALTER TABLE students ADD COLUMN $column $definition");
    }
}

$errors = [];
$success = '';
if (!isset($_SESSION['registration_code'])) {
    $_SESSION['registration_code'] = (string) random_int(1000, 9999);
}

// Detect AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

if(isset($_POST['register']) || $isAjax) {
    if(!isValidCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'طلب غير صالح. أعد تحميل الصفحة.';
    }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    // accept either confirm_password or password_confirm from different forms
    $confirm_password = $_POST['confirm_password'] ?? ($_POST['password_confirm'] ?? '');
    $specialty = $_POST['specialty'] ?? '';
    $university = $_POST['university'] ?? '';
    $other_university = trim($_POST['university_other'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $registration_code = trim($_POST['registration_code'] ?? '');

    if(empty($name)) $errors[] = 'الاسم مطلوب';
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'بريد إلكتروني صحيح مطلوب';
    if(empty($password)) $errors[] = 'كلمة المرور مطلوبة';
    if($password !== $confirm_password) $errors[] = 'كلمات المرور غير متطابقة';
    if(!preg_match('/^[0-9]{6,}$/', $password)) $errors[] = 'كلمة المرور يجب أن تكون 6 أرقام على الأقل';
    if(empty($university)) $errors[] = 'الجامعة مطلوبة';
    if($university === 'other' && empty($other_university)) $errors[] = 'يرجى كتابة اسم الجامعة إذا اخترت أخرى';
    if(empty($faculty)) $errors[] = 'اسم الكلية مطلوب';
    if(empty($phone) || !preg_match('/^[0-9\+\-\s]{7,20}$/', $phone)) $errors[] = 'رقم هاتف صحيح مطلوب';
    if($registration_code !== ($_SESSION['registration_code'] ?? '')) $errors[] = 'رمز التحقق غير صحيح';

    $sql_check = "SELECT id FROM students WHERE email = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    if($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "s", $email);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if(mysqli_stmt_num_rows($stmt_check) > 0) $errors[] = 'البريد الإلكتروني مسجل بالفعل';
        mysqli_stmt_close($stmt_check);
    } else {
        $errors[] = 'خطأ في قاعدة البيانات. يرجى المحاولة لاحقاً.';
    }

    if(empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        if($university === 'other') {
            $university = $other_university;
        }

        $sql = "INSERT INTO students (name, email, password, specialty, university, faculty, birth_date, address, phone, official_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssssssss", $name, $email, $password_hash, $specialty, $university, $faculty, $birth_date, $address, $phone, $email);
            if(mysqli_stmt_execute($stmt)) {
                $success = 'تم التسجيل بنجاح! يمكنك الآن تسجيل الدخول.';
                unset($_SESSION['registration_code']);
            } else {
                $errors[] = 'حدث خطأ عند التسجيل. يرجى المحاولة لاحقاً.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'خطأ في قاعدة البيانات. يرجى المحاولة لاحقاً.';
        }
    }
}

// If AJAX, return JSON response and exit
if($isAjax && ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    header('Content-Type: application/json; charset=utf-8');
    if(!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
    } else {
        echo json_encode(['success' => true, 'message' => $success]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل طالب جديد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: 'Cairo', sans-serif; display: flex; justify-content: center; align-items: center; }
        .register-card { max-width: 500px; width: 100%; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="card register-card">
        <div class="card-header bg-primary text-white text-center">
            <h4>تسجيل طالب جديد</h4>
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="mb-3">
                    <label class="form-label">الاسم الكامل</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">الإيميل الرسمي</label>
                    <input type="email" name="email" class="form-control" placeholder="example@university.edu.iq" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">تاريخ الميلاد</label>
                            <input type="date" name="birth_date" class="form-control" value="<?php echo htmlspecialchars($_POST['birth_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="text" name="phone" class="form-control" placeholder="0770xxxxxxx" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                            <div class="alert alert-secondary text-center fw-bold fs-4 mt-3 mb-2"><?php echo htmlspecialchars($_SESSION['registration_code']); ?></div>
                            <label class="form-label">اكتب رمز التحقق الظاهر</label>
                            <input type="text" name="registration_code" class="form-control text-center" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">العنوان الكامل</label>
                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">الجامعة</label>
                    <select name="university" id="universitySelect" class="form-select" onchange="toggleOtherUniversity(this)" required>
                        <option value="">اختر الجامعة</option>
                        <optgroup label="الجامعات الحكومية العراقية">
                            <option value="جامعة بغداد" <?php if(($_POST['university'] ?? '') == 'جامعة بغداد') echo 'selected'; ?>>جامعة بغداد</option>
                            <option value="الجامعة المستنصرية" <?php if(($_POST['university'] ?? '') == 'الجامعة المستنصرية') echo 'selected'; ?>>الجامعة المستنصرية</option>
                            <option value="جامعة الموصل" <?php if(($_POST['university'] ?? '') == 'جامعة الموصل') echo 'selected'; ?>>جامعة الموصل</option>
                            <option value="جامعة تكريت" <?php if(($_POST['university'] ?? '') == 'جامعة تكريت') echo 'selected'; ?>>جامعة تكريت</option>
                            <option value="جامعة بابل" <?php if(($_POST['university'] ?? '') == 'جامعة بابل') echo 'selected'; ?>>جامعة بابل</option>
                            <option value="جامعة البصرة" <?php if(($_POST['university'] ?? '') == 'جامعة البصرة') echo 'selected'; ?>>جامعة البصرة</option>
                            <option value="جامعة كربلاء" <?php if(($_POST['university'] ?? '') == 'جامعة كربلاء') echo 'selected'; ?>>جامعة كربلاء</option>
                            <option value="جامعة ديالى" <?php if(($_POST['university'] ?? '') == 'جامعة ديالى') echo 'selected'; ?>>جامعة ديالى</option>
                            <option value="جامعة ذي قار" <?php if(($_POST['university'] ?? '') == 'جامعة ذي قار') echo 'selected'; ?>>جامعة ذي قار</option>
                            <option value="جامعة الانبار" <?php if(($_POST['university'] ?? '') == 'جامعة الانبار') echo 'selected'; ?>>جامعة الأنبار</option>
                            <option value="جامعة صلاح الدين" <?php if(($_POST['university'] ?? '') == 'جامعة صلاح الدين') echo 'selected'; ?>>جامعة صلاح الدين</option>
                            <option value="جامعة واسط" <?php if(($_POST['university'] ?? '') == 'جامعة واسط') echo 'selected'; ?>>جامعة واسط</option>
                            <option value="جامعة كركوك" <?php if(($_POST['university'] ?? '') == 'جامعة كركوك') echo 'selected'; ?>>جامعة كركوك</option>
                        </optgroup>
                        <optgroup label="الجامعات الأهلية العراقية">
                            <option value="الجامعة العراقية الخاصة" <?php if(($_POST['university'] ?? '') == 'الجامعة العراقية الخاصة') echo 'selected'; ?>>الجامعة العراقية الخاصة</option>
                            <option value="جامعة الزهراء الأهلية" <?php if(($_POST['university'] ?? '') == 'جامعة الزهراء الأهلية') echo 'selected'; ?>>جامعة الزهراء الأهلية</option>
                            <option value="جامعة الشرق الأوسط" <?php if(($_POST['university'] ?? '') == 'جامعة الشرق الأوسط') echo 'selected'; ?>>جامعة الشرق الأوسط</option>
                            <option value="الجامعة المستنصرية الأهلية" <?php if(($_POST['university'] ?? '') == 'الجامعة المستنصرية الأهلية') echo 'selected'; ?>>الجامعة المستنصرية الأهلية</option>
                            <option value="جامعة عمار بن ياسر" <?php if(($_POST['university'] ?? '') == 'جامعة عمار بن ياسر') echo 'selected'; ?>>جامعة عمار بن ياسر</option>
                            <option value="الجامعة العراقية" <?php if(($_POST['university'] ?? '') == 'الجامعة العراقية') echo 'selected'; ?>>الجامعة العراقية</option>
                        </optgroup>
                        <option value="other" <?php if(($_POST['university'] ?? '') == 'other') echo 'selected'; ?>>أخرى</option>
                    </select>
                </div>
                <div class="mb-3" id="otherUniversityGroup" style="display: <?php echo (($_POST['university'] ?? '') === 'other') ? 'block' : 'none'; ?>;">
                    <label class="form-label">اكتب اسم الجامعة</label>
                    <input type="text" name="university_other" class="form-control" value="<?php echo htmlspecialchars($_POST['university_other'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">الكلية</label>
                    <input type="text" name="faculty" class="form-control" value="<?php echo htmlspecialchars($_POST['faculty'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">التخصص</label>
                    <select name="specialty" class="form-select">
                        <option value="">اختر التخصص</option>
                        <option value="جراحة الفم والوجه والفكين" <?php if(($_POST['specialty'] ?? '') == 'جراحة الفم والوجه والفكين') echo 'selected'; ?>>جراحة الفم والوجه والفكين</option>
                        <option value="تقويم الأسنان" <?php if(($_POST['specialty'] ?? '') == 'تقويم الأسنان') echo 'selected'; ?>>تقويم الأسنان</option>
                        <option value="امراض اللثة" <?php if(($_POST['specialty'] ?? '') == 'امراض اللثة') echo 'selected'; ?>>امراض اللثة</option>
                        <option value="طب أسنان الأطفال" <?php if(($_POST['specialty'] ?? '') == 'طب أسنان الأطفال') echo 'selected'; ?>>طب أسنان الأطفال</option>
                        <option value="تجميل الوجه والفكين" <?php if(($_POST['specialty'] ?? '') == 'تجميل الوجه والفكين') echo 'selected'; ?>>تجميل الوجه والفكين</option>
                        <option value="الأشعة" <?php if(($_POST['specialty'] ?? '') == 'الأشعة') echo 'selected'; ?>>الأشعة</option>
                        <option value="زراعة الأسنان" <?php if(($_POST['specialty'] ?? '') == 'زراعة الأسنان') echo 'selected'; ?>>زراعة الأسنان</option>
                        <option value="صناعة أسنان" <?php if(($_POST['specialty'] ?? '') == 'صناعة أسنان') echo 'selected'; ?>>صناعة أسنان</option>
                        <option value="غير ذلك" <?php if(($_POST['specialty'] ?? '') == 'غير ذلك') echo 'selected'; ?>>غير ذلك</option>
                    </select>
                </div>
                <button type="submit" name="register" class="btn btn-primary w-100">تسجيل</button>
            </form>
            <script>
                function toggleOtherUniversity(select) {
                    document.getElementById('otherUniversityGroup').style.display = select.value === 'other' ? 'block' : 'none';
                }
                document.addEventListener('DOMContentLoaded', function() {
                    var select = document.getElementById('universitySelect');
                    if(select) toggleOtherUniversity(select);
                });
            </script>
            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-outline-primary w-100 mb-2">العودة للمكتبة</a>
                <p class="mt-3">
                    <a href="login.php">لديك حساب؟ سجل الدخول</a>
                </p>
            </div>
        </div>
    </div>
    <footer class="text-center text-muted py-3">المبرمج: عباس خضير</footer>
</body>
</html>