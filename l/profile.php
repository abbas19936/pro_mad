<?php
session_start();
if(!isset($_SESSION['loggedin']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit;
}
include('db.php');
$user_id = intval($_SESSION['user_id']);

$success = '';
$errors = [];

$sql = "SELECT * FROM students WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

if(!$student) {
    header('Location: logout.php');
    exit;
}

if(isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $university = trim($_POST['university'] ?? '');
    $other_university = trim($_POST['university_other'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');

    if(empty($name)) $errors[] = 'الاسم مطلوب';
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'بريد إلكتروني رسمي صحيح مطلوب';
    if(empty($birth_date)) $errors[] = 'تاريخ الميلاد مطلوب';
    if(empty($address)) $errors[] = 'العنوان مطلوب';
    if(empty($phone) || !preg_match('/^[0-9\+\-\s]{7,20}$/', $phone)) $errors[] = 'رقم هاتف صحيح مطلوب';
    if(empty($university)) $errors[] = 'الجامعة مطلوبة';
    if($university === 'other' && empty($other_university)) $errors[] = 'يرجى كتابة اسم الجامعة إذا اخترت أخرى';
    if(empty($faculty)) $errors[] = 'اسم الكلية مطلوب';

    if(empty($errors)) {
        $sql_check = "SELECT id FROM students WHERE email = ? AND id != ?";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "si", $email, $user_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        if(mysqli_num_rows($result_check) > 0) {
            $errors[] = 'هذا البريد الإلكتروني مستخدم بالفعل';
        }
    }

    if(empty($errors)) {
        if($university === 'other') {
            $university = $other_university;
        }
        $sql_update = "UPDATE students SET name = ?, email = ?, official_email = ?, birth_date = ?, address = ?, phone = ?, specialty = ?, university = ?, faculty = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "sssssssssi", $name, $email, $email, $birth_date, $address, $phone, $specialty, $university, $faculty, $user_id);
        if(mysqli_stmt_execute($stmt_update)) {
            $success = 'تم تحديث البيانات بنجاح.';
            $_SESSION['user_name'] = $name;
            $student = array_merge($student, [
                'name' => $name,
                'email' => $email,
                'official_email' => $email,
                'birth_date' => $birth_date,
                'address' => $address,
                'phone' => $phone,
                'specialty' => $specialty,
                'university' => $university,
                'faculty' => $faculty,
            ]);
        } else {
            $errors[] = 'حدث خطأ أثناء حفظ البيانات. يرجى المحاولة لاحقاً.';
        }
    }
}

function isSelected($value, $current) {
    return ($value === $current) ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-family: 'Cairo', sans-serif; min-height: 100vh; }
        .profile-card { max-width: 900px; margin: 40px auto; padding: 30px; background: white; border-radius: 20px; box-shadow: 0 20px 45px rgba(0,0,0,0.15); }
    </style>
</head>
<body>
    <div class="profile-card">
        <div class="text-center mb-4">
            <h2>بياناتي الشخصية</h2>
            <p>يمكنك تعديل بياناتك الرسمية للتواصل المستقبلي.</p>
        </div>
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
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">الاسم</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($student['name']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">الإيميل الرسمي</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">تاريخ الميلاد</label>
                <input type="date" name="birth_date" class="form-control" value="<?php echo htmlspecialchars($student['birth_date']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">رقم الهاتف</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($student['phone']); ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">العنوان</label>
                <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($student['address']); ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">الجامعة</label>
                <select name="university" id="universitySelect" class="form-select" onchange="toggleOtherUniversity(this)" required>
                    <option value="">اختر الجامعة</option>
                    <option value="جامعة واسط" <?php echo isSelected('جامعة واسط', $student['university']); ?>>جامعة واسط</option>
                    <option value="جامعة بغداد" <?php echo isSelected('جامعة بغداد', $student['university']); ?>>جامعة بغداد</option>
                    <option value="الجامعة المستنصرية" <?php echo isSelected('الجامعة المستنصرية', $student['university']); ?>>الجامعة المستنصرية</option>
                    <option value="other" <?php echo isSelected('other', $student['university']); ?>>أخرى</option>
                </select>
            </div>
            <div class="col-md-8" id="otherUniversityGroup" style="display: <?php echo ($student['university'] === 'other') ? 'block' : 'none'; ?>;">
                <label class="form-label">اكتب اسم الجامعة</label>
                <input type="text" name="university_other" class="form-control" value="<?php echo htmlspecialchars($student['university'] === 'other' ? $student['university'] : ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">الكلية</label>
                <input type="text" name="faculty" class="form-control" value="<?php echo htmlspecialchars($student['faculty']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">التخصص</label>
                <select name="specialty" class="form-select" required>
                    <option value="">اختر التخصص</option>
                    <option value="جراحة الفم والوجه والفكين" <?php echo isSelected('جراحة الفم والوجه والفكين', $student['specialty']); ?>>جراحة الفم والوجه والفكين</option>
                    <option value="تقويم الأسنان" <?php echo isSelected('تقويم الأسنان', $student['specialty']); ?>>تقويم الأسنان</option>
                    <option value="امراض اللثة" <?php echo isSelected('امراض اللثة', $student['specialty']); ?>>امراض اللثة</option>
                    <option value="طب أسنان الأطفال" <?php echo isSelected('طب أسنان الأطفال', $student['specialty']); ?>>طب أسنان الأطفال</option>
                    <option value="تجميل الوجه والفكين" <?php echo isSelected('تجميل الوجه والفكين', $student['specialty']); ?>>تجميل الوجه والفكين</option>
                    <option value="الأشعة" <?php echo isSelected('الأشعة', $student['specialty']); ?>>الأشعة</option>
                    <option value="زراعة الأسنان" <?php echo isSelected('زراعة الأسنان', $student['specialty']); ?>>زراعة الأسنان</option>
                    <option value="صناعة أسنان" <?php echo isSelected('صناعة أسنان', $student['specialty']); ?>>صناعة أسنان</option>
                    <option value="غير ذلك" <?php echo isSelected('غير ذلك', $student['specialty']); ?>>غير ذلك</option>
                </select>
            </div>
            <div class="col-12 text-end">
                <button type="submit" name="update_profile" class="btn btn-primary">حفظ التحديثات</button>
                <a href="index.php" class="btn btn-secondary">العودة للمكتبة</a>
            </div>
        </form>
    </div>
    <script>
        function toggleOtherUniversity(select) {
            document.getElementById('otherUniversityGroup').style.display = select.value === 'other' ? 'block' : 'none';
        }
        document.addEventListener('DOMContentLoaded', function() {
            var select = document.getElementById('universitySelect');
            if(select) toggleOtherUniversity(select);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
