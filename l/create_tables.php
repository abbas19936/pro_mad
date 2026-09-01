<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

include('db.php');

// إنشاء جدول الطلبة
$sql_students = "CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    specialty VARCHAR(255),
    university VARCHAR(255),
    faculty VARCHAR(255),
    birth_date DATE,
    address VARCHAR(255),
    phone VARCHAR(50),
    official_email VARCHAR(255),
    security_question VARCHAR(255),
    security_answer VARCHAR(255),
    pin_code VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// إنشاء جدول المستخدمين (الأساتذة والإدارة)
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'professor') DEFAULT 'professor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// إنشاء جدول الكتب مع حقول إضافية
$sql_books = "CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    specialty VARCHAR(255),
    pdf_file VARCHAR(255),
    external_link VARCHAR(255),
    link VARCHAR(255),
    publication_date DATE,
    added_by INT,
    publisher_name VARCHAR(255) DEFAULT 'الإدارة',
    publisher_role VARCHAR(50) DEFAULT 'admin',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    view_count INT DEFAULT 0,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// إنشاء جدول طلبات الكتب
$sql_requests = "CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    book_title VARCHAR(255) NOT NULL,
    specialty VARCHAR(255),
    message TEXT,
    request_date DATETIME NOT NULL,
    publication_date DATE,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_reason TEXT,
    processed_by INT,
    processed_at DATETIME,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// إنشاء جدول المحاضرات مع ربط بالمستخدم
$sql_lectures = "CREATE TABLE IF NOT EXISTS lectures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    stage INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    added_by INT,
    uploaded_by_role VARCHAR(50) DEFAULT 'professor',
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql_students)) {
    echo "تم إنشاء جدول الطلبة بنجاح.<br>";
} else {
    echo "خطأ في إنشاء جدول الطلبة: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $sql_users)) {
    echo "تم إنشاء جدول المستخدمين بنجاح.<br>";
} else {
    echo "خطأ في إنشاء جدول المستخدمين: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $sql_books)) {
    echo "تم إنشاء جدول الكتب بنجاح.<br>";
} else {
    echo "خطأ في إنشاء جدول الكتب: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $sql_requests)) {
    echo "تم إنشاء جدول طلبات الكتب بنجاح.<br>";
} else {
    echo "خطأ في إنشاء جدول طلبات الكتب: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $sql_lectures)) {
    // إضافة عمود subject إذا لم يكن موجودًا
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM lectures LIKE 'subject'");
    if(mysqli_num_rows($check_column) == 0) {
        mysqli_query($conn, "ALTER TABLE lectures ADD COLUMN subject VARCHAR(255) AFTER title");
    }
    echo "تم إنشاء جدول المحاضرات بنجاح.<br>";
} else {
    echo "خطأ في إنشاء جدول المحاضرات: " . mysqli_error($conn) . "<br>";
}

// إنشاء جدول التسجيلات (في حالة طالب يريد التسجيل)
$sql_registrations = "CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    book_id INT,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
)";

// إنشاء جدول الإشعارات
$sql_notifications = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
)";

// إنشاء جدول زوار الصفحة لتتبع الزيارات اليومية
$sql_page_visitors = "CREATE TABLE IF NOT EXISTS page_visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_token VARCHAR(64) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    first_visit DATETIME NOT NULL,
    last_activity DATETIME NOT NULL,
    visit_date DATE NOT NULL
)";

// إنشاء جدول عداد المشاهدات
$sql_page_views = "CREATE TABLE IF NOT EXISTS page_views (
    id INT PRIMARY KEY,
    count INT DEFAULT 0
)";

// إنشاء جدول إحصائيات تسجيل الدخول
$sql_login_stats = "CREATE TABLE IF NOT EXISTS login_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    device_type VARCHAR(50),
    login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(login_time)
)";

if (mysqli_query($conn, $sql_registrations)) {
    echo "تم إنشاء جدول التسجيلات بنجاح.<br>";
} else {
    echo "خطأ في إنشاء جدول التسجيلات: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $sql_login_stats)) {
    echo "تم إنشاء جدول إحصائيات الدخول بنجاح.<br>";
} else {
    echo "خطأ في إنشاء جدول إحصائيات الدخول: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $sql_notifications)) {
    echo "تم إنشاء جدول الإشعارات بنجاح.<br>";
} else {
    echo "خطأ في إنشاء جدول الإشعارات: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $sql_page_visitors)) {
    echo "تم إنشاء جدول زوار الصفحة بنجاح.<br>";
} else {
    echo "خطأ في إنشاء جدول زوار الصفحة: " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $sql_page_views)) {
    mysqli_query($conn, "INSERT IGNORE INTO page_views (id, count) VALUES (1, 0)");
    echo "تم إنشاء جدول عداد المشاهدات بنجاح.<br>";
} else {
    echo "خطأ في إنشاء جدول عداد المشاهدات: " . mysqli_error($conn) . "<br>";
}

// تأكد من وجود أعمدة الجامعة والكلية إذا كانت الجداول موجودة مسبقاً
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'university'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN university VARCHAR(255)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'faculty'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN faculty VARCHAR(255)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'birth_date'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN birth_date DATE");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'address'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN address VARCHAR(255)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'phone'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN phone VARCHAR(50)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'official_email'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN official_email VARCHAR(255)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'security_question'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN security_question VARCHAR(255)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'security_answer'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN security_answer VARCHAR(255)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'pin_code'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN pin_code VARCHAR(255)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'updated_at'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

// إضافة حقول جديدة للكتب إذا لم تكن موجودة
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'publication_date'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN publication_date DATE");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'added_by'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN added_by INT, ADD FOREIGN KEY (added_by) REFERENCES users(id)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'publisher_name'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN publisher_name VARCHAR(255) DEFAULT 'الإدارة'");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'publisher_role'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN publisher_role VARCHAR(50) DEFAULT 'admin'");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'status'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'approved'");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'view_count'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN view_count INT DEFAULT 0");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'download_count'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN download_count INT DEFAULT 0");
}

// إضافة حقول جديدة لطلبات الكتب إذا لم تكن موجودة
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM requests LIKE 'student_id'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE requests ADD COLUMN student_id INT, ADD FOREIGN KEY (student_id) REFERENCES students(id)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM requests LIKE 'status'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE requests ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending'");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM requests LIKE 'admin_reason'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE requests ADD COLUMN admin_reason TEXT");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM requests LIKE 'processed_by'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE requests ADD COLUMN processed_by INT, ADD FOREIGN KEY (processed_by) REFERENCES users(id)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM requests LIKE 'processed_at'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE requests ADD COLUMN processed_at DATETIME");
}

// إضافة حقول للمحاضرات إذا لم تكن موجودة
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM lectures LIKE 'added_by'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE lectures ADD COLUMN added_by INT, ADD FOREIGN KEY (added_by) REFERENCES users(id)");
}
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM lectures LIKE 'uploaded_by_role'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE lectures ADD COLUMN uploaded_by_role VARCHAR(50) DEFAULT 'professor'");
}
?>