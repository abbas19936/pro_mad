<?php
// هذا الملف يمسح البيانات القديمة من المشاريع والطلاب ويضيف بيانات نموذجية جديدة لعام 2020-2021
include('db.php');

$tables = ['registrations', 'notifications', 'books', 'students'];
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
foreach ($tables as $table) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if ($check && mysqli_num_rows($check) > 0) {
        $result = mysqli_query($conn, "TRUNCATE TABLE $table");
        if (!$result) {
            echo "خطأ في مسح جدول $table: " . mysqli_error($conn) . "<br>";
        } else {
            echo "تم مسح جدول $table بنجاح.<br>";
        }
    } else {
        echo "جدول $table غير موجود.<br>";
    }
}
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

// تأكد من وجود أعمدة الأمن في جدول الطلاب
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'security_question'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN security_question VARCHAR(255)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'security_answer'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN security_answer VARCHAR(255)");
}

// إضافة الأدمن الجديد
$admin_password = password_hash('admin', PASSWORD_DEFAULT);
$result = mysqli_query($conn, "INSERT IGNORE INTO users (name, email, password, role) VALUES ('Admin CIT', 'admin@cit.com', '$admin_password', 'admin')");
if (!$result) {
    echo "خطأ في إضافة الأدمن: " . mysqli_error($conn) . "<br>";
} else {
    echo "تم إضافة الأدمن بنجاح.<br>";
}

// الحصول على معرف الأدمن الفعلي
$admin_id = 1;
$admin_result = mysqli_query($conn, "SELECT id FROM users WHERE email='admin@cit.com' LIMIT 1");
if ($admin_result && mysqli_num_rows($admin_result) > 0) {
    $admin_row = mysqli_fetch_assoc($admin_result);
    $admin_id = intval($admin_row['id']);
}

// تأكد من وجود أعمدة السنة وحقول المشروع في جدول الكتب
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'academic_year'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN academic_year VARCHAR(20)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'publication_date'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN publication_date DATE");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'supervisor_name'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN supervisor_name VARCHAR(255)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'project_idea'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN project_idea TEXT");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'beneficiary'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN beneficiary VARCHAR(255)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'status'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
}

$studentCount = 0;
for ($i = 1; $i <= 100; $i++) {
    $name = "user{$i}";
    $email = "user{$i}@uowasit.edu.iq";
    $password = '0000';
    $specialty = $i <= 50 ? 'قسم هندسة البرمجيات' : 'قسم علوم الحاسوب';
    $university = 'جامعة واسط';
    $faculty = 'كلية علوم الحاسوب وتكنلوجيا المعلومات - جامعة واسط';
    $question = 'آخر 4 أرقام من هاتفك؟';
    $answer = '0000';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $answer_hash = password_hash($answer, PASSWORD_DEFAULT);
    $name = mysqli_real_escape_string($conn, $name);
    $email = mysqli_real_escape_string($conn, $email);
    $specialty = mysqli_real_escape_string($conn, $specialty);
    $university = mysqli_real_escape_string($conn, $university);
    $faculty = mysqli_real_escape_string($conn, $faculty);
    $question = mysqli_real_escape_string($conn, $question);
    $result = mysqli_query($conn, "INSERT INTO students (name, email, password, specialty, university, faculty, security_question, security_answer) VALUES ('$name', '$email', '$password_hash', '$specialty', '$university', '$faculty', '$question', '$answer_hash')");
    if (!$result) {
        echo "خطأ في إضافة الطالب $name: " . mysqli_error($conn) . "<br>";
    } else {
        $studentCount++;
    }
}
echo "تم إنشاء $studentCount حسابات طلابية جديدة بكلمة المرور 0000 (50 قسم هندسة برمجيات، 50 قسم علوم الحاسوب).<br>";

$books = [
    [
        'نظام إدارة مشاريع التخرج',
        'علي حسن، سارة خالد، محمد جاسم',
        'د. كمال عبد الله',
        'هندسة برمجيات',
        'منصة ويب لإدارة مشاريع التخرج وتسجيل الطلاب والمشرفين.',
        'نظام إدارة مشاريع التخرج يساعد الطلاب على تنظيم المشروعات وحفظ التفاصيل والملخصات.',
        'Graduation project management system for student and supervisor tracking.',
        'كلية الهندسة',
        '',
        '2021-06-10',
        '2020-2021',
        1
    ],
    [
        'تطبيق التذاكر الذكية',
        'ريم علي، ياسر فاضل',
        'د. سارة كريم',
        'ذكاء اصطناعي',
        'تطبيق يساعد الطلاب والموظفين على إدارة تذاكر الدعم الفني الذكي.',
        'التطبيق يستخدم الذكاء الاصطناعي لتصنيف التذاكر وتوجيهها للأقسام المناسبة.',
        'Smart ticketing app for support requests and classification.',
        'كلية الهندسة',
        '',
        '2021-07-05',
        '2020-2021',
        1
    ],
    [
        'نظام تأمين اختبارات الكترونية',
        'محمد جاسم، علي حسن، ريم علي',
        'د. جاسم حسين',
        'أمن سيبراني',
        'نظام إلكتروني لحماية الامتحانات عبر الشبكة وضمان الهوية.',
        'يحمي النظام الامتحانات من الغش باستخدام التحقق الثنائي والمراقبة.',
        'Secure electronic exam system with identity verification.',
        'كلية الهندسة',
        '',
        '2021-05-18',
        '2020-2021',
        1
    ],
    [
        'بوابة تعليم البيانات',
        'سارة خالد، ياسر فاضل',
        'د. علي محمد',
        'علوم البيانات',
        'بوابة لتعلم تحليل البيانات وتصور النتائج التعليمية.',
        'تتيح البوابة الوصول إلى ملخصات ونماذج بيانية وتحليلات الطلاب.',
        'Data learning portal for analytics and visualization.',
        'كلية الهندسة',
        '',
        '2021-04-12',
        '2020-2021',
        1
    ],
    [
        'موقع التدريب السحابي',
        'ندى حسين، علي حسن',
        'د. نهى عبد الرحمن',
        'الحوسبة السحابية',
        'موقع لتنسيق طلبات التدريب العملي وإدارة الموارد السحابية.',
        'يسمح الموقع بإدارة التدريبات والملفات والسحابة ضمن بيئة واحدة.',
        'Cloud training coordination platform for internships.',
        'كلية الهندسة',
        '',
        '2021-08-01',
        '2020-2021',
        1
    ],
    [
        'تطبيق التعلم الإلكتروني',
        'أحمد سالم، فاطمة عمر، حسن علي',
        'د. ريم سالم',
        'هندسة برمجيات',
        'منصة تعليمية إلكترونية للطلاب مع ميزات التفاعل والتقييم.',
        'التطبيق يوفر دروساً تفاعلية واختبارات ومتابعة التقدم الدراسي.',
        'E-learning platform with interactive lessons and assessments.',
        'كلية الهندسة',
        '',
        '2021-09-15',
        '2020-2021',
        1
    ],
    [
        'نظام التعرف على الوجوه',
        'لينا محمد، كريم يوسف',
        'د. محمد علي',
        'ذكاء اصطناعي',
        'نظام يستخدم الذكاء الاصطناعي للتعرف على الوجوه في الصور.',
        'يستخدم خوارزميات التعلم الآلي لتحليل الصور وتحديد الأشخاص.',
        'Facial recognition system using AI algorithms.',
        'كلية الهندسة',
        '',
        '2021-10-20',
        '2020-2021',
        1
    ],
    [
        'أداة فحص الأمان السيبراني',
        'مريم أحمد، عمر خالد',
        'د. سعد حسن',
        'أمن سيبراني',
        'أداة لفحص الثغرات الأمنية في المواقع والتطبيقات.',
        'تقوم الأداة بفحص الشبكات والتطبيقات للكشف عن الثغرات الأمنية.',
        'Cybersecurity vulnerability scanner for websites and apps.',
        'كلية الهندسة',
        '',
        '2021-11-05',
        '2020-2021',
        1
    ],
    [
        'شبكة اجتماعية للطلاب',
        'زينب حسن، سعد عبد الله',
        'د. فاطمة يوسف',
        'شبكات الحاسوب',
        'منصة اجتماعية للطلاب لمشاركة المواد والمناقشات.',
        'توفر المنصة غرف دراسية ومنتديات ومشاركة الملفات.',
        'Student social network for sharing materials and discussions.',
        'كلية الهندسة',
        '',
        '2021-12-10',
        '2020-2021',
        1
    ],
    [
        'تحليل البيانات الطبية',
        'أحمد سالم، لينا محمد، كريم يوسف',
        'د. علي سالم',
        'علوم البيانات',
        'أداة لتحليل البيانات الطبية واستخراج الإحصائيات.',
        'تساعد في تحليل السجلات الطبية واستخراج الأنماط والتنبؤات.',
        'Medical data analytics tool for statistics and predictions.',
        'كلية الهندسة',
        '',
        '2022-01-15',
        '2020-2021',
        1
    ],
    [
        'نظام إدارة السحابة',
        'فاطمة عمر، مريم أحمد',
        'د. حسن علي',
        'الحوسبة السحابية',
        'نظام لإدارة الموارد السحابية وتوزيع الحمل.',
        'يوفر واجهة لإدارة الخوادم السحابية والتطبيقات.',
        'Cloud resource management system with load balancing.',
        'كلية الهندسة',
        '',
        '2022-02-20',
        '2020-2021',
        1
    ],
    [
        'تطبيق الواقع المعزز',
        'حسن علي، عمر خالد، زينب حسن',
        'د. لينا محمد',
        'واجهات المستخدم',
        'تطبيق يستخدم الواقع المعزز للتعليم والترفيه.',
        'يوفر تجارب تفاعلية باستخدام الكاميرا والشاشة.',
        'Augmented reality app for education and entertainment.',
        'كلية الهندسة',
        '',
        '2022-03-10',
        '2020-2021',
        1
    ]
];

foreach ($books as $book) {
    [$title, $author, $supervisor, $specialty, $idea, $summary_ar, $summary_en, $beneficiary, $pdf, $pub_date, $academic_year, $added_by] = $book;
    $added_by = $admin_id;
    $title = mysqli_real_escape_string($conn, $title);
    $author = mysqli_real_escape_string($conn, $author);
    $supervisor = mysqli_real_escape_string($conn, $supervisor);
    $specialty = mysqli_real_escape_string($conn, $specialty);
    $idea = mysqli_real_escape_string($conn, $idea);
    $summary_ar = mysqli_real_escape_string($conn, $summary_ar);
    $summary_en = mysqli_real_escape_string($conn, $summary_en);
    $beneficiary = mysqli_real_escape_string($conn, $beneficiary);
    $academic_year = mysqli_real_escape_string($conn, $academic_year);
    $result = mysqli_query($conn, "INSERT INTO books (title, author, supervisor_name, specialty, project_idea, summary_ar, summary_en, beneficiary, pdf_file, publication_date, academic_year, added_by) VALUES ('$title', '$author', '$supervisor', '$specialty', '$idea', '$summary_ar', '$summary_en', '$beneficiary', '$pdf', '$pub_date', '$academic_year', $added_by)");
    if (!$result) {
        echo "خطأ في إضافة المشروع $title: " . mysqli_error($conn) . "<br>";
    }
}
echo "تم إضافة " . count($books) . " مشروع بنجاح.<br>";

$success = true;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تهيئة البيانات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-body text-center">
            <h3 class="card-title mb-3">تم مسح البيانات القديمة وإضافة بيانات جديدة</h3>
            <p class="card-text">الآن يوجد مجموعة طلابية جديدة وبيانات مشاريع بعام الدراسة 2020-2021.</p>
            <a href="index.php" class="btn btn-primary mt-3">العودة للرئيسية</a>
        </div>
    </div>
</div>
</body>
</html>