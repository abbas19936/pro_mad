<?php
session_start();
if(!isset($_SESSION['loggedin']) || $_SESSION['user_type'] != 'professor') {
    header('Location: login.php');
    exit;
}
include('db.php');
$user_id = $_SESSION['user_id'];

if(isset($_POST['upload_lecture'])) {
    $title = trim($_POST['lecture_title'] ?? '');
    $subject = trim($_POST['lecture_subject'] ?? '');
    $stage = intval($_POST['lecture_stage'] ?? 0);
    
    if(empty($title) || empty($subject) || $stage <= 0) {
        echo "<script>alert('جميع الحقول المطلوبة يجب أن تكون مملوءة.');</script>";
    } else {
        $upload_dir = __DIR__ . '/uploads/';
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0750, true);
        }

        if(isset($_FILES['lecture_file']) && $_FILES['lecture_file']['error'] == 0) {
            $allowed_types = ['application/pdf', 'application/x-pdf', 'application/msword', 
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
            $max_size = 100 * 1024 * 1024; // 100MB
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['lecture_file']['tmp_name']);
            finfo_close($finfo);
            
            if(!in_array($mime, $allowed_types)) {
                echo "<script>alert('نوع ملف غير مسموح. يرجى رفع ملف PDF أو Word أو PowerPoint.');</script>";
            } elseif($_FILES['lecture_file']['size'] > $max_size) {
                echo "<script>alert('حجم الملف كبير جداً. الحد الأقصى هو 100 MB.');</script>";
            } else {
                $file_ext = pathinfo($_FILES['lecture_file']['name'], PATHINFO_EXTENSION);
                $file_name = 'lecture_' . $user_id . '_' . time() . '_' . md5($_FILES['lecture_file']['name']) . '.' . strtolower($file_ext);
                $upload_path = $upload_dir . $file_name;
                
                    if(move_uploaded_file($_FILES['lecture_file']['tmp_name'], $upload_path)) {
                    // ensure lectures.status column exists
                    $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM lectures LIKE 'status'");
                    if(mysqli_num_rows($checkCol) == 0) {
                        mysqli_query($conn, "ALTER TABLE lectures ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'approved'");
                    }
                    $sql = "INSERT INTO lectures (title, subject, stage, file_path, added_by, uploaded_by_role, status) VALUES (?, ?, ?, ?, ?, 'professor', 'pending')";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssisi", $title, $subject, $stage, $file_name, $user_id);
                    if(mysqli_stmt_execute($stmt)) {
                        echo "<script>alert('تم رفع المحاضرة بنجاح (قيد المراجعة).');</script>";
                    } else {
                        unlink($upload_path);
                        echo "<script>alert('خطأ في حفظ المحاضرة.');</script>";
                    }
                } else {
                    echo "<script>alert('حدث خطأ أثناء رفع الملف.');</script>";
                }
            }
        } else {
            echo "<script>alert('يرجى اختيار ملف للرفع.');</script>";
        }
    }
}

if(isset($_POST['upload_book'])) {
    $title = trim($_POST['book_title'] ?? '');
    $author = trim($_POST['book_author'] ?? '');
    $spec = trim($_POST['book_specialty'] ?? '');
    $link = trim($_POST['book_link'] ?? '');
    $pub_date = !empty($_POST['book_publication_date']) ? $_POST['book_publication_date'] : null;
    $file_name = '';

    if(isset($_FILES['book_pdf']) && $_FILES['book_pdf']['error'] == 0) {
        $allowed_types = ['application/pdf', 'application/x-pdf'];
        $max_size = 50 * 1024 * 1024;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['book_pdf']['tmp_name']);
        finfo_close($finfo);
        if(!in_array($mime, $allowed_types) || $_FILES['book_pdf']['size'] > $max_size) {
            echo "<script>alert('يرجى رفع ملف PDF صحيح بحجم 50MB أو أقل.');</script>";
        } else {
            $file_name = 'book_' . time() . '_' . md5($_FILES['book_pdf']['name']) . '.pdf';
            if(!is_dir('uploads')) {
                mkdir('uploads', 0750, true);
            }
            if(!move_uploaded_file($_FILES['book_pdf']['tmp_name'], __DIR__ . '/uploads/' . $file_name)) {
                $file_name = '';
                echo "<script>alert('حدث خطأ في رفع الملف.');</script>";
            }
        }
    }

    if(!empty($title) && !empty($spec)) {
        $publisher_name = htmlspecialchars($_SESSION['user_name'] ?? 'الأستاذ');
        $publisher_role = 'professor';
        // ensure books.status exists
        $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'status'");
        if(mysqli_num_rows($checkCol) == 0) {
            mysqli_query($conn, "ALTER TABLE books ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'approved'");
        }
        $book_link = !empty($file_name) ? 'uploads/' . $file_name : $link;
        $sql = "INSERT INTO books (title, author, specialty, pdf_file, external_link, link, publication_date, added_by, publisher_name, publisher_role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssisss", $title, $author, $spec, $file_name, $link, $book_link, $pub_date, $user_id, $publisher_name, $publisher_role);
        if(mysqli_stmt_execute($stmt)) {
            echo "<script>alert('تم إضافة الكتاب بنجاح (قيد المراجعة)');</script>";
        } else {
            echo "<script>alert('خطأ عند إضافة الكتاب.');</script>";
        }
    } else {
        echo "<script>alert('العنوان والتخصص مطلوبان.');</script>";
    }
}

if(isset($_GET['delete_lecture'])) {
    $id = intval($_GET['delete_lecture']);
    $sql = "SELECT added_by, file_path FROM lectures WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    if($row && $row['added_by'] == $user_id) {
        // Delete file
        $file_path = __DIR__ . '/uploads/' . basename($row['file_path']);
        if(file_exists($file_path)) {
            unlink($file_path);
        }
        
        $sql_delete = "DELETE FROM lectures WHERE id = ?";
        $stmt_delete = mysqli_prepare($conn, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $id);
        mysqli_stmt_execute($stmt_delete);
        echo "<script>alert('تم حذف المحاضرة');</script>";
    } else {
        echo "<script>alert('غير مصرح لك بحذف هذه المحاضرة.');</script>";
    }
}

if(isset($_GET['delete_book'])) {
    $id = intval($_GET['delete_book']);
    $sql = "SELECT added_by, pdf_file FROM books WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if($row && $row['added_by'] == $user_id) {
        if(!empty($row['pdf_file'])) {
            $file_path = __DIR__ . '/uploads/' . basename($row['pdf_file']);
            if(file_exists($file_path)) {
                unlink($file_path);
            }
        }
        $sql_delete = "DELETE FROM books WHERE id = ?";
        $stmt_delete = mysqli_prepare($conn, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $id);
        mysqli_stmt_execute($stmt_delete);
        echo "<script>alert('تم حذف الكتاب');</script>";
    } else {
        echo "<script>alert('غير مصرح لك بحذف هذا الكتاب.');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة الأستاذ</title>
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
        .table tr { 
            transition: all 0.3s ease; 
        }
        .table tr:hover { 
            background-color: rgba(0,123,255,0.1) !important; 
            transform: scale(1.02); 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .table td { 
            padding: 12px; 
            vertical-align: middle; 
        }
        .table { 
            animation: fadeIn 1.5s ease-out; 
        }
        .badge { 
            animation: pulse 2s infinite; 
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
    <h2 class="mt-2 animate__animated animate__fadeIn">لوحة الأستاذ</h2>
    <p class="animate__animated animate__fadeIn animate__delay-1s">مرحباً <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
</div>

<div class="container animate__animated animate__fadeInUp animate__delay-0.5s">
    <div class="row mb-4">
        <div class="col-md-12 text-end">
            <a href="index.php" class="btn btn-outline-primary me-2">العودة للمكتبة</a>
            <a href="logout.php" class="btn btn-outline-secondary">تسجيل الخروج</a>
        </div>
    </div>
            <div class="mb-4">
                <h5 class="bg-warning text-white p-2 rounded">رفع محاضرة جديدة</h5>
                <form method="POST" enctype="multipart/form-data" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="lecture_title" class="form-control" placeholder="عنوان المحاضرة" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="lecture_subject" class="form-control" placeholder="اسم المادة" required>
                    </div>
                    <div class="col-md-2">
                        <select name="lecture_stage" class="form-select" required>
                            <option value="1">المرحلة 1</option>
                            <option value="2">المرحلة 2</option>
                            <option value="3">المرحلة 3</option>
                            <option value="4">المرحلة 4</option>
                            <option value="5">المرحلة 5</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="file" name="lecture_file" class="form-control" accept=".pdf" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="upload_lecture" class="btn btn-success w-100">رفع المحاضرة</button>
                    </div>
                </form>
            </div>
            <div class="mb-4">
                <h5 class="bg-success text-white p-2 rounded">إضافة كتاب جديد</h5>
                <form method="POST" enctype="multipart/form-data" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="book_title" class="form-control" placeholder="عنوان الكتاب" required>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="book_author" class="form-control" placeholder="المؤلف">
                    </div>
                    <div class="col-md-2">
                        <select name="book_specialty" class="form-select" required>
                            <option value="جراحة الفم والوجه والفكين">جراحة الفم والوجه والفكين</option>
                            <option value="تقويم الأسنان">تقويم الأسنان</option>
                            <option value="امراض اللثة">امراض اللثة</option>
                            <option value="طب أسنان الأطفال">طب أسنان الأطفال</option>
                            <option value="تجميل الوجه والفكين">تجميل الوجه والفكين</option>
                            <option value="الأشعة">الأشعة</option>
                            <option value="زراعة الأسنان">زراعة الأسنان</option>
                            <option value="صناعة أسنان">صناعة أسنان</option>
                            <option value="غير ذلك">غير ذلك</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="book_publication_date" class="form-control" placeholder="تاريخ النشر">
                    </div>
                    <div class="col-md-2">
                        <input type="url" name="book_link" class="form-control" placeholder="رابط خارجي">
                    </div>
                    <div class="col-md-3">
                        <input type="file" name="book_pdf" class="form-control" accept=".pdf">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="upload_book" class="btn btn-success w-100">إضافة كتاب</button>
                    </div>
                </form>
            </div>
            <div>
                <h5 class="bg-info text-white p-2 rounded">محاضراتي</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped bg-white">
                        <thead class="table-dark">
                            <tr>
                                <th>العنوان</th>
                                <th>المادة</th>
                                <th>المرحلة</th>
                                <th>تاريخ الرفع</th>
                                <th>تحميل</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = mysqli_query($conn, "SELECT * FROM lectures WHERE added_by = $user_id ORDER BY upload_date DESC");
                            while($row = mysqli_fetch_assoc($result)) {
                                echo "<tr>
                                    <td>" . htmlspecialchars($row['title']) . "</td>
                                    <td>" . htmlspecialchars($row['subject']) . "</td>
                                    <td>" . htmlspecialchars($row['stage']) . "</td>
                                    <td>" . htmlspecialchars($row['upload_date']) . "</td>
                                    <td><a href='uploads/" . htmlspecialchars($row['file_path']) . "' target='_blank' class='btn btn-sm btn-primary'>تحميل</a></td>
                                    <td><a href='?delete_lecture=" . $row['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"حذف؟\")'>حذف</a></td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">
                <h5 class="bg-secondary text-white p-2 rounded">الكتب التي نشرتها</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped bg-white">
                        <thead class="table-dark">
                            <tr>
                                <th>العنوان</th>
                                <th>المؤلف</th>
                                <th>التخصص</th>
                                <th>المشاهدات</th>
                                <th>التنزيلات</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $booksResult = mysqli_query($conn, "SELECT * FROM books WHERE added_by = $user_id ORDER BY created_at DESC");
                            while($book = mysqli_fetch_assoc($booksResult)) {
                                echo "<tr>
                                    <td>" . htmlspecialchars($book['title']) . "</td>
                                    <td>" . htmlspecialchars($book['author']) . "</td>
                                    <td>" . htmlspecialchars($book['specialty']) . "</td>
                                    <td>" . intval($book['view_count']) . "</td>
                                    <td>" . intval($book['download_count']) . "</td>
                                    <td><a href='?delete_book=" . $book['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"حذف؟\")'>حذف</a></td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>