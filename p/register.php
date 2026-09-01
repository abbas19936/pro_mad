<?php
include('db.php');
$errors = [];
$success = '';
if(isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $specialty = $_POST['specialty'];
    $university = $_POST['university'] ?? '';
    $other_university = trim($_POST['university_other'] ?? '');
    $faculty = trim($_POST['faculty']);
    $security_question = trim($_POST['security_question'] ?? '');
    $security_answer = trim($_POST['security_answer'] ?? '');
    
    if(empty($name)) $errors[] = 'الاسم مطلوب';
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'بريد إلكتروني صحيح مطلوب';
    if(!preg_match('/@uowasit\.edu\.iq$/i', $email)) $errors[] = 'يجب أن ينتهي البريد بـ @uowasit.edu.iq';
    if(empty($password)) $errors[] = 'كلمة المرور مطلوبة';
    if($password !== $confirm_password) $errors[] = 'كلمات المرور غير متطابقة';
    if(strlen($password) < 6) $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    if(empty($university)) $errors[] = 'الجامعة مطلوبة';
    if($university === 'other' && empty($other_university)) $errors[] = 'يرجى كتابة اسم الجامعة إذا اخترت أخرى';
    if(empty($faculty)) $errors[] = 'اسم الكلية مطلوب';
    if(empty($security_question)) $errors[] = 'اختر سؤال التذكر';
    if(empty($security_answer)) $errors[] = 'أجب عن سؤال التذكر';
    
    $email_check = mysqli_real_escape_string($conn, $email);
    $result = mysqli_query($conn, "SELECT id FROM students WHERE email = '$email_check'");
    if(mysqli_num_rows($result) > 0) $errors[] = 'البريد الإلكتروني مسجل بالفعل';
    
    if(empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $security_answer_hash = password_hash($security_answer, PASSWORD_DEFAULT);
        if($university === 'other') {
            $university = $other_university;
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO students (name, email, password, specialty, university, faculty, security_question, security_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssssss', $name, $email, $password_hash, $specialty, $university, $faculty, $security_question, $security_answer_hash);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'تم التسجيل بنجاح! يمكنك الآن تسجيل الدخول.';
            } else {
                $errors[] = 'خطأ في قاعدة البيانات.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'خطأ في تهيئة قاعدة البيانات.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل طالب جديد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="purple-bg register-page">
    <div class="card register-card">
        <div class="card-header bg-primary text-white text-center">
            <h4>تسجيل طالب جديد</h4>
        </div>
        <div class="card-body">
            <?php if(!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">الاسم الكامل</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني الجامعي</label>
                    <input type="email" name="email" class="form-control" placeholder="example@university.edu.iq" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
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
                    <label class="form-label">سؤال التذكر</label>
                    <select name="security_question" class="form-select" required>
                        <option value="">اختر سؤالاً</option>
                        <option value="شنو أول سيارة اشتريتها؟" <?php if(($_POST['security_question'] ?? '') == 'شنو أول سيارة اشتريتها؟') echo 'selected'; ?>>شنو أول سيارة اشتريتها؟</option>
                        <option value="شنو اسم مدرستك الابتدائية؟" <?php if(($_POST['security_question'] ?? '') == 'شنو اسم مدرستك الابتدائية؟') echo 'selected'; ?>>شنو اسم مدرستك الابتدائية؟</option>
                        <option value="شنو اسم أفضل صديق؟" <?php if(($_POST['security_question'] ?? '') == 'شنو اسم أفضل صديق؟') echo 'selected'; ?>>شنو اسم أفضل صديق؟</option>
                        <option value="آخر 4 أرقام من هاتفك؟" <?php if(($_POST['security_question'] ?? '') == 'آخر 4 أرقام من هاتفك؟') echo 'selected'; ?>>آخر 4 أرقام من هاتفك؟</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">الإجابة على سؤال التذكر</label>
                    <input type="text" name="security_answer" class="form-control" value="<?php echo htmlspecialchars($_POST['security_answer'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">التخصص</label>
                    <select name="specialty" class="form-select">
                        <option value="">اختر التخصص</option>
                                        <option value="هندسة برمجيات">هندسة برمجيات</option>
                                        <option value="ذكاء اصطناعي">ذكاء اصطناعي</option>
                                        <option value="أمن سيبراني">أمن سيبراني</option>
                                        <option value="شبكات الحاسوب">شبكات الحاسوب</option>
                                        <option value="علوم البيانات">علوم البيانات</option>
                                        <option value="الحوسبة السحابية">الحوسبة السحابية</option>
                                        <option value="واجهات المستخدم">واجهات المستخدم</option>
                                        <option value="غير ذلك">غير ذلك</option>
                                   
                    </select>
                </div>
                <button type="submit" name="register" class="btn btn-primary w-100">تسجيل</button>
            </form>
            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-outline-primary w-100 mb-2">العودة للمكتبة</a>
                <p class="mt-3">
                    <a href="login.php">لديك حساب؟ سجل الدخول</a>
                </p>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 جميع الحقوق محفوظة - منصة مشاريع التخرج</p>
        <p><strong>تمت البرمجة بواسطة:</strong> عباس خضير عباس</p>
    </footer>
    <script src="assets/js/main.js" defer></script>
</body>
</html>