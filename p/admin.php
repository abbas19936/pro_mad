<?php 
require_once 'security.php';
secure_session_start();
if(!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}
// التحقق من أن المستخدم أدمن فقط
if($_SESSION['user_type'] !== 'admin') {
    header('Location: professor.php');
    exit;
}
include('db.php');
include('language.php');

// التأكد من وجود حقل status في جدول books
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'status'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved'");
    mysqli_query($conn, "UPDATE books SET status = 'approved' WHERE status IS NULL OR status = ''");
}

// دالة لإرسال إشعار
function send_notification($student_email, $message, $type = 'info') {
    global $conn;
    if ($stmt = mysqli_prepare($conn, "SELECT id FROM students WHERE email = ?")) {
        mysqli_stmt_bind_param($stmt, 's', $student_email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $student_id);
        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            if ($insertStmt = mysqli_prepare($conn, "INSERT INTO notifications (student_id, message, type) VALUES (?, ?, ?)") ) {
                mysqli_stmt_bind_param($insertStmt, 'iss', $student_id, $message, $type);
                mysqli_stmt_execute($insertStmt);
                mysqli_stmt_close($insertStmt);
            }
        } else {
            mysqli_stmt_close($stmt);
        }
    }
}

if(isset($_GET['approve_request'])) {
    $id = intval($_GET['approve_request']);
    if ($stmt = mysqli_prepare($conn, "SELECT book_title, specialty, email FROM requests WHERE id = ?")) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $book_title, $specialty, $student_email);
        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            $author = __('not_available');
            if ($insertStmt = mysqli_prepare($conn, "INSERT INTO books (title, author, specialty, pdf_file, external_link) VALUES (?, ?, ?, '', '')")) {
                mysqli_stmt_bind_param($insertStmt, 'sss', $book_title, $author, $specialty);
                mysqli_stmt_execute($insertStmt);
                mysqli_stmt_close($insertStmt);
            }
            send_notification($student_email, sprintf(__('notification_request_approved'), $book_title), 'success');
            if ($deleteStmt = mysqli_prepare($conn, "DELETE FROM requests WHERE id = ?")) {
                mysqli_stmt_bind_param($deleteStmt, 'i', $id);
                mysqli_stmt_execute($deleteStmt);
                mysqli_stmt_close($deleteStmt);
            }
            echo "<script>alert(" . json_encode(__('request_approved')) . ");</script>";
        } else {
            mysqli_stmt_close($stmt);
        }
    }
}

if(isset($_GET['reject_request'])) {

    $id = intval($_GET['reject_request']);
    if ($stmt = mysqli_prepare($conn, "SELECT book_title, email FROM requests WHERE id = ?")) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $book_title, $student_email);
        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            send_notification($student_email, sprintf(__('notification_request_rejected'), $book_title), 'warning');
            if ($deleteStmt = mysqli_prepare($conn, "DELETE FROM requests WHERE id = ?")) {
                mysqli_stmt_bind_param($deleteStmt, 'i', $id);
                mysqli_stmt_execute($deleteStmt);
                mysqli_stmt_close($deleteStmt);
            }
            echo "<script>alert(" . json_encode(__('request_rejected')) . ");</script>";
        } else {
            mysqli_stmt_close($stmt);
        }
    }
}
if(isset($_POST['submit'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $author1 = trim($_POST['author1']);
    $author2 = trim($_POST['author2'] ?? '');
    $author3 = trim($_POST['author3'] ?? '');
    $author = $author1;
    if(!empty($author2)) $author .= '، ' . $author2;
    if(!empty($author3)) $author .= '، ' . $author3;
    $author = mysqli_real_escape_string($conn, $author);
    $supervisor_name = mysqli_real_escape_string($conn, $_POST['supervisor_name']);
    $spec = mysqli_real_escape_string($conn, $_POST['specialty']);
    $study_shift = mysqli_real_escape_string($conn, $_POST['study_shift']);
    $beneficiary = mysqli_real_escape_string($conn, $_POST['beneficiary']);
    $summary_ar = mysqli_real_escape_string($conn, $_POST['summary_ar']);
    $summary_en = mysqli_real_escape_string($conn, $_POST['summary_en']);
    $pub_date = $_POST['publication_date'] ?? '';
    $academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);
    
    $file_name = '';
    if(isset($_FILES['pdf']) && $_FILES['pdf']['error'] == 0) {
        $tmp_name = $_FILES['pdf']['tmp_name'];
        $original_name = basename($_FILES['pdf']['name']);
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            $file_name = uniqid('book_', true) . '.pdf';
            move_uploaded_file($tmp_name, __DIR__ . '/uploads/' . $file_name);
        }
    }
    
    if ($stmt = mysqli_prepare($conn, "INSERT INTO books (title, author, supervisor_name, specialty, study_shift, beneficiary, summary_ar, summary_en, pdf_file, publication_date, academic_year, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)") ) {
        $added_by = 1;
        mysqli_stmt_bind_param($stmt, 'ssssssssssis', $title, $author, $supervisor_name, $spec, $study_shift, $beneficiary, $summary_ar, $summary_en, $file_name, $pub_date, $academic_year, $added_by);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo "<script>alert(" . json_encode(__('project_added')) . ");</script>";
}

if(isset($_GET['delete_book'])) {
    $id = intval($_GET['delete_book']);
    if ($stmt = mysqli_prepare($conn, "DELETE FROM books WHERE id = ?")) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo "<script>alert(" . json_encode(__('project_deleted')) . ");</script>";
}

if(isset($_POST['edit_book'])) {
    $id = intval($_POST['edit_id']);
    $title = trim($_POST['edit_title']);
    $author1 = trim($_POST['edit_author1']);
    $author2 = trim($_POST['edit_author2'] ?? '');
    $author3 = trim($_POST['edit_author3'] ?? '');
    $author = $author1;
    if(!empty($author2)) $author .= '، ' . $author2;
    if(!empty($author3)) $author .= '، ' . $author3;
    $supervisor_name = trim($_POST['edit_supervisor_name']);
    $specialty = trim($_POST['edit_specialty']);
    $study_shift = trim($_POST['edit_study_shift']);
    $beneficiary = trim($_POST['edit_beneficiary']);
    $summary_ar = trim($_POST['edit_summary_ar']);
    $summary_en = trim($_POST['edit_summary_en']);
    $pub_date = $_POST['edit_pub_date'];
    $academic_year = trim($_POST['edit_academic_year']);

    if ($stmt = mysqli_prepare($conn, "UPDATE books SET title = ?, author = ?, supervisor_name = ?, specialty = ?, study_shift = ?, beneficiary = ?, summary_ar = ?, summary_en = ?, publication_date = ?, academic_year = ? WHERE id = ?")) {
        mysqli_stmt_bind_param($stmt, 'ssssssssssi', $title, $author, $supervisor_name, $specialty, $study_shift, $beneficiary, $summary_ar, $summary_en, $pub_date, $academic_year, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo "<script>alert(" . json_encode(__('project_updated')) . ");</script>";
}

if(isset($_POST['add_user'])) {
    $name = trim($_POST['user_name']);
    $email = trim($_POST['user_email']);
    $role = trim($_POST['user_role']);
    $password = !empty($_POST['user_password']) ? password_hash($_POST['user_password'], PASSWORD_DEFAULT) : password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

    if ($stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)")) {
        mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $password, $role);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }  
    echo "<script>alert('تم إضافة المستخدم');</script>";
}

if(isset($_POST['edit_password'])) {
    $user_id = intval($_POST['edit_user_id']);
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    if ($stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?")) {
        mysqli_stmt_bind_param($stmt, 'si', $new_password, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo "<script>alert(" . json_encode(__('password_updated')) . ");</script>";
}

if(isset($_POST['add_student'])) {
    $name = trim($_POST['student_name']);
    $email = trim($_POST['student_email']);
    $password = $_POST['student_password'];
    $confirm_password = $_POST['student_confirm_password'];
    $specialty = trim($_POST['student_specialty']);
    $university = $_POST['student_university'] ?? '';
    $other_university = trim($_POST['student_university_other'] ?? '');
    $faculty = trim($_POST['student_faculty']);
    $security_question = trim($_POST['student_security_question']);
    $security_answer = trim($_POST['student_security_answer'] ?? '');

    if(!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@uowasit\.edu\.iq$/i', $email)) {
        echo "<script>alert(" . json_encode(__('invalid_email')) . ");</script>";
    } elseif($password !== $confirm_password) {
        echo "<script>alert(" . json_encode(__('passwords_not_match')) . ");</script>";
    } elseif(strlen($password) < 6) {
        echo "<script>alert('كلمة المرور يجب أن تكون 6 أحرف على الأقل');</script>";
    } else {
        if ($stmt = mysqli_prepare($conn, "SELECT id FROM students WHERE email = ?")) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                mysqli_stmt_close($stmt);
                echo "<script>alert(" . json_encode(__('email_exists')) . ");</script>";
            } else {
                mysqli_stmt_close($stmt);
                if($university === 'other') {
                    $university = $other_university;
                }
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $security_answer_hash = password_hash($security_answer, PASSWORD_DEFAULT);
                if ($insertStmt = mysqli_prepare($conn, "INSERT INTO students (name, email, password, specialty, university, faculty, security_question, security_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)") ) {
                    mysqli_stmt_bind_param($insertStmt, 'ssssssss', $name, $email, $password_hash, $specialty, $university, $faculty, $security_question, $security_answer_hash);
                    mysqli_stmt_execute($insertStmt);
                    mysqli_stmt_close($insertStmt);
                }
                echo "<script>alert(" . json_encode(__('student_added')) . ");</script>";
            }
        }
    }
}


 

if(isset($_GET['delete_student'])) {
    $id = intval($_GET['delete_student']);
    if ($stmt = mysqli_prepare($conn, "DELETE FROM students WHERE id = ?")) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo "<script>alert(" . json_encode(__('student_deleted')) . ");</script>";
}

if(isset($_GET['approve_project'])) {
    $id = intval($_GET['approve_project']);
    $student_id = intval($_GET['student_id']);
    if ($stmt = mysqli_prepare($conn, "UPDATE books SET status = 'approved' WHERE id = ?")) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    if ($titleStmt = mysqli_prepare($conn, "SELECT title FROM books WHERE id = ?")) {
        mysqli_stmt_bind_param($titleStmt, 'i', $id);
        mysqli_stmt_execute($titleStmt);
        mysqli_stmt_bind_result($titleStmt, $project_title);
        mysqli_stmt_fetch($titleStmt);
        mysqli_stmt_close($titleStmt);
    }
    $message = sprintf(__('notification_project_approved'), $project_title);
    if ($checkStmt = mysqli_prepare($conn, "SELECT id FROM students WHERE id = ?")) {
        mysqli_stmt_bind_param($checkStmt, 'i', $student_id);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            mysqli_stmt_close($checkStmt);
            if ($insertStmt = mysqli_prepare($conn, "INSERT INTO notifications (student_id, message, type) VALUES (?, ?, 'success')")) {
                mysqli_stmt_bind_param($insertStmt, 'is', $student_id, $message);
                mysqli_stmt_execute($insertStmt);
                mysqli_stmt_close($insertStmt);
            }
        } else {
            mysqli_stmt_close($checkStmt);
        }
    }
    echo "<script>alert('تمت الموافقة على المشروع');</script>";
}

if(isset($_GET['reject_project'])) {
    $id = intval($_GET['reject_project']);
    $student_id = intval($_GET['student_id']);
    if ($stmt = mysqli_prepare($conn, "UPDATE books SET status = 'rejected' WHERE id = ?")) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    if ($titleStmt = mysqli_prepare($conn, "SELECT title FROM books WHERE id = ?")) {
        mysqli_stmt_bind_param($titleStmt, 'i', $id);
        mysqli_stmt_execute($titleStmt);
        mysqli_stmt_bind_result($titleStmt, $project_title);
        mysqli_stmt_fetch($titleStmt);
        mysqli_stmt_close($titleStmt);
    }
    $message = "تم رفض مشروعك: $project_title. يرجى مراجعة المعلومات وإعادة الإرسال.";
    if ($checkStmt = mysqli_prepare($conn, "SELECT id FROM students WHERE id = ?")) {
        mysqli_stmt_bind_param($checkStmt, 'i', $student_id);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            mysqli_stmt_close($checkStmt);
            if ($insertStmt = mysqli_prepare($conn, "INSERT INTO notifications (student_id, message, type) VALUES (?, ?, 'warning')")) {
                mysqli_stmt_bind_param($insertStmt, 'is', $student_id, $message);
                mysqli_stmt_execute($insertStmt);
                mysqli_stmt_close($insertStmt);
            }
        } else {
            mysqli_stmt_close($checkStmt);
        }
    }
    echo "<script>alert('تم رفض المشروع');</script>";
}

if(isset($_GET['delete_user'])) {
    $id = intval($_GET['delete_user']);
    if ($stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?")) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo "<script>alert(" . json_encode(__('user_deleted')) . ");</script>";
}
?>

<!DOCTYPE html>
<html lang="<?php echo $html_lang; ?>" dir="<?php echo $html_dir; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo __('admin_panel'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="purple-bg admin-page">

<div class="college-header animate__animated animate__fadeInDown">
    <img src="logo.png" alt="<?php echo __('college_logo_alt'); ?>" class="college-logo animate__animated animate__bounceIn">
    <h2 class="mt-2 animate__animated animate__fadeIn"><?php echo __('admin_panel'); ?></h2>
    <div class="datetime animate__animated animate__fadeIn animate__delay-1s">
        <i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?> | <i class="fas fa-clock"></i> <?php echo date('H:i:s'); ?>
    </div>
</div>

<div class="container mt-4 animate__animated animate__fadeIn">
    <div class="row mb-4">
        <div class="col-md-12 text-end">
            <a href="index.php" class="btn btn-outline-primary me-2"><?php echo __('back_to_main'); ?></a>
            <a href="logout.php" class="btn btn-outline-secondary me-2"><?php echo __('logout'); ?></a>
            <div class="btn-group" role="group" aria-label="<?php echo __('language'); ?>">
                <a href="<?php echo lang_url('ar'); ?>" class="btn btn-outline-dark <?php echo $lang_code === 'ar' ? 'active' : ''; ?>"><?php echo __('arabic'); ?></a>
                <a href="<?php echo lang_url('en'); ?>" class="btn btn-outline-dark <?php echo $lang_code === 'en' ? 'active' : ''; ?>"><?php echo __('english'); ?></a>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="projects-tab" data-bs-toggle="tab" data-bs-target="#projects" type="button" role="tab" aria-controls="projects" aria-selected="true"><?php echo __('projects'); ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="false"><?php echo __('pending_projects'); ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab" aria-controls="students" aria-selected="false"><?php echo __('student_management'); ?></button>
        </li>
    </ul>
    <div class="tab-content" id="adminTabsContent">
                <!-- تبويب المشروعات -->
                <div class="tab-pane fade show active" id="projects" role="tabpanel" aria-labelledby="projects-tab">
                    <div class="mt-4">
                        <h5 class="bg-primary text-white p-2 rounded"><?php echo __('add_new_project'); ?></h5>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <input type="text" name="title" class="form-control" placeholder="<?php echo __('project_title'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="author1" class="form-control" placeholder="<?php echo __('first_student'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <input type="checkbox" id="add_author2" onchange="toggleAuthor2()" class="me-2">
                                        <label for="add_author2" class="form-label mb-0"><?php echo __('add_second_student'); ?></label>
                                    </div>
                                    <input type="text" name="author2" id="author2" class="form-control mt-2" placeholder="<?php echo __('second_student'); ?>" style="display: none;">
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <input type="checkbox" id="add_author3" onchange="toggleAuthor3()" class="me-2">
                                        <label for="add_author3" class="form-label mb-0"><?php echo __('add_third_student'); ?></label>
                                    </div>
                                    <input type="text" name="author3" id="author3" class="form-control mt-2" placeholder="<?php echo __('third_student'); ?>" style="display: none;">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="supervisor_name" class="form-control" placeholder="<?php echo __('supervisor_name'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <select name="specialty" class="form-select" required>
                                        <option value=""><?php echo __('choose_department'); ?></option>
                                        <option value="قسم علوم الحاسوب"><?php echo __('computer_science'); ?></option>
                                        <option value="قسم هندسة البرمجيات"><?php echo __('software_engineering'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select name="study_shift" class="form-select" required>
                                        <option value=""><?php echo __('choose_study_type'); ?></option>
                                        <option value="صباحي"><?php echo __('morning'); ?></option>
                                        <option value="مسائي"><?php echo __('evening'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="academic_year" class="form-control" placeholder="2023-2024" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="beneficiary" class="form-control" placeholder="<?php echo __('beneficiary'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <textarea name="summary_ar" class="form-control" rows="2" placeholder="<?php echo __('arabic_summary'); ?>"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <textarea name="summary_en" class="form-control" rows="2" placeholder="<?php echo __('english_summary'); ?>"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <input type="file" name="pdf" class="form-control" accept=".pdf">
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" name="submit" class="btn btn-success w-100"><?php echo __('submit_project'); ?></button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="mt-4">
                        <h5 class="bg-success text-white p-2 rounded"><?php echo __('projects_list'); ?></h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped bg-white">
                                <thead class="table-dark">
                                    <tr>
                                       
                                </thead>
                                <tbody>
                                    <?php
                                    $result = mysqli_query($conn, "SELECT * FROM books ORDER BY created_at DESC");
                                    while($row = mysqli_fetch_assoc($result)) {
                                        ?>
                                        <tr style="display: contents;">
                                            <td style="display: block; border: 4px solid #007bff; border-radius: 12px; background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 15px;">
                                                <!-- عنوان البطاقة -->
                                                <div style="background: #f8f9ff; border-bottom: 2px solid #007bff; padding: 20px 15px; font-size: 1.2rem; color: #007bff; font-weight: bold; text-align: center;">
                                                    <?php echo htmlspecialchars($row['title']); ?>
                                                </div>
                                                <!-- تفاصيل البطاقة في شبكة عمودية -->
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0; width: 100%; flex: 1;">
                                                    <!-- الصف 1: الطالب / الفريق -->
                                                    <div style="border-bottom: 1px solid #f0f0f0; border-left: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #007bff; font-size: 0.9rem; margin-bottom: 10px;"><?php echo __('student_team'); ?></div>
                                                        <div><strong><?php echo htmlspecialchars($row['author']); ?></strong></div>
                                                    </div>
                                                    <div style="border-bottom: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #007bff; font-size: 0.9rem; margin-bottom: 10px;"><?php echo __('supervisor'); ?></div>
                                                        <div><?php echo htmlspecialchars($row['supervisor_name'] ?? ''); ?></div>
                                                    </div>
                                                    
                                                    <!-- الصف 2: مجال المشروع -->
                                                    <div style="border-bottom: 1px solid #f0f0f0; border-left: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #007bff; font-size: 0.9rem; margin-bottom: 10px;"><?php echo __('department'); ?></div>
                                                        <div><span class='badge bg-info'><?php echo htmlspecialchars($row['specialty']); ?></span></div>
                                                    </div>
                                                    <div style="border-bottom: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #007bff; font-size: 0.9rem; margin-bottom: 10px;"><?php echo __('beneficiary'); ?></div>
                                                        <div><?php echo htmlspecialchars($row['beneficiary'] ?? ''); ?></div>
                                                    </div>
                                                    
                                                    <!-- الصف 3: الملخص -->
                                                    <div style="border-bottom: 1px solid #f0f0f0; border-left: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #007bff; font-size: 0.9rem; margin-bottom: 10px;"><?php echo __('summary'); ?></div>
                                                        <div><button class='btn btn-sm btn-info' onclick='showSummary("<?php echo addslashes($row['summary_ar'] ?? ''); ?>", "<?php echo addslashes($row['summary_en'] ?? ''); ?>")'><?php echo __('view_summary'); ?></button></div>
                                                    </div>
                                                    <!-- الصف 4: سنة التخرج -->
                                                    <div style="border-bottom: 1px solid #f0f0f0; border-left: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #007bff; font-size: 0.9rem; margin-bottom: 10px;"><?php echo __('graduation_year_label'); ?></div>
                                                        <div><?php echo htmlspecialchars($row['academic_year'] ?? $row['publication_date']); ?></div>
                                                    </div>
                                                    <div style="border-bottom: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #007bff; font-size: 0.9rem; margin-bottom: 10px;"><?php echo __('report'); ?></div>
                                                        <div><a href='uploads/<?php echo htmlspecialchars($row['pdf_file']); ?>' target='_blank'><?php echo __('pdf'); ?></a></div>
                                                    </div>
                                                    
                                                    <!-- الصف 5: الإجراءات -->
                                                    <div style="border-left: 1px solid #f0f0f0; padding: 20px 15px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                        <div style="font-weight: bold; color: #007bff; font-size: 0.9rem; margin-bottom: 10px;"><?php echo __('actions'); ?></div>
                                                        <div>
                                                            <button class='btn btn-sm btn-warning' data-bs-toggle='modal' data-bs-target='#editModal' onclick='editBook(<?php echo $row['id']; ?>, "<?php echo addslashes($row['title']); ?>", "<?php echo addslashes($row['author']); ?>", "<?php echo addslashes($row['supervisor_name'] ?? ''); ?>", "<?php echo $row['specialty']; ?>", "<?php echo $row['study_shift']; ?>", "<?php echo $row['publication_date']; ?>", "<?php echo addslashes($row['academic_year'] ?? ''); ?>", "<?php echo addslashes($row['beneficiary'] ?? ''); ?>", "<?php echo addslashes($row['summary_ar'] ?? ''); ?>", "<?php echo addslashes($row['summary_en'] ?? ''); ?>")'><?php echo __('edit'); ?></button>
                                                            <a href='?delete_book=<?php echo $row['id']; ?>' class='btn btn-sm btn-danger' onclick='return confirm("<?php echo __('confirm_delete'); ?>")'><?php echo __('delete'); ?></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <div class="mt-4">
                        <h5 class="bg-warning text-white p-2 rounded"><?php echo __('pending_projects'); ?></h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped bg-white">
                                <thead class="table-dark">
                                    <tr>
                                        <th><?php echo __('project_title'); ?></th>
                                        <th><?php echo __('student_team'); ?></th>
                                        <th><?php echo __('supervisor'); ?></th>
                                        <th><?php echo __('department'); ?></th>
                                        <th><?php echo __('graduation_year_label'); ?></th>
                                        <th><?php echo __('report'); ?></th>
                                        <th><?php echo __('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $pendingResult = mysqli_query($conn, "SELECT * FROM books WHERE status = 'pending' ORDER BY created_at DESC");
                                    while($row = mysqli_fetch_assoc($pendingResult)) {
                                        $pdf_link = '';
                                        if(!empty($row['pdf_file'])) {
                                            $pdf_link = "<a href='uploads/" . htmlspecialchars($row['pdf_file']) . "' target='_blank' class='btn btn-sm btn-info'>" . __('report') . "</a>";
                                        }
                                        echo "<tr>
                                            <td>" . htmlspecialchars($row['title']) . "</td>
                                            <td>" . htmlspecialchars($row['author']) . "</td>
                                            <td>" . htmlspecialchars($row['supervisor_name']) . "</td>
                                            <td>" . htmlspecialchars($row['specialty']) . "</td>
                                            <td>" . htmlspecialchars($row['academic_year']) . "</td>
                                            <td>$pdf_link</td>
                                            <td>
                                                <button class='btn btn-sm btn-success' onclick='approveProject(" . $row['id'] . ", " . $row['added_by'] . ")'>" . __('approve') . "</button>
                                                <button class='btn btn-sm btn-danger' onclick='rejectProject(" . $row['id'] . ", " . $row['added_by'] . ")'>" . __('reject') . "</button>
                                            </td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="students" role="tabpanel" aria-labelledby="students-tab">
                    <div class="mt-4">
                        <h5 class="bg-primary text-white p-2 rounded"><?php echo __('اضافة طالب'); ?></h5>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" name="student_name" class="form-control" placeholder="<?php echo __('full_name'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="email" name="student_email" class="form-control" placeholder="example@uowasit.edu.iq" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="password" name="student_password" class="form-control" placeholder="<?php echo __('password'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="password" name="student_confirm_password" class="form-control" placeholder="<?php echo __('confirm_password'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?php echo __('university'); ?></label>
                                    <select name="student_university" id="studentUniversitySelect" class="form-select" onchange="toggleStudentOtherUniversity(this)">
                                        <option value=""><?php echo __('choose_university'); ?></option>
                                        <option value="جامعة واسط"><?php echo __('uowasit_university'); ?></option>
                                        <option value="جامعة بغداد"><?php echo __('baghdad_university'); ?></option>
                                        <option value="الجامعة المستنصرية"><?php echo __('al_mustansiriyah_university'); ?></option>
                                        <option value="جامعة الموصل"><?php echo __('mosul_university'); ?></option>
                                        <option value="جامعة تكريت"><?php echo __('tikrit_university'); ?></option>
                                        <option value="other"><?php echo __('other'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="studentOtherUniversityGroup" style="display:none;">
                                    <input type="text" name="student_university_other" class="form-control" placeholder="<?php echo __('other_university'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="student_faculty" class="form-control" placeholder="<?php echo __(' الكلية '); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <select name="student_specialty" class="form-select" required>
                                        <option value=""><?php echo __('choose_department'); ?></option>
                                        <option value="قسم علوم الحاسوب"><?php echo __('computer_science'); ?></option>
                                        <option value="قسم هندسة البرمجيات"><?php echo __('software_engineering'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select name="student_security_question" class="form-select" required>
                                        <option value=""><?php echo __('choose_security_question'); ?></option>
                                        <option value="شنو أول سيارة اشتريتها؟"><?php echo __('security_question_1'); ?></option>
                                        <option value="شنو اسم مدرستك الابتدائية؟"><?php echo __('security_question_2'); ?></option>
                                        <option value="شنو اسم أفضل صديق؟"><?php echo __('security_question_3'); ?></option>
                                        <option value="آخر 4 أرقام من هاتفك؟"><?php echo __('security_question_4'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="student_security_answer" class="form-control" placeholder="<?php echo __('security_answer'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" name="add_student" class="btn btn-success w-100"><?php echo __('اضافة طالب'); ?></button>
                                </div>
                              
                            </div>
                        </form>
                    </div>
                    <div class="mt-4">
                        <h5 class="bg-success text-white p-2 rounded"><?php echo __('registered_students'); ?></h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped bg-white">
                                <thead class="table-dark">
                                    <tr>
                                        <th><?php echo __('full_name'); ?></th>
                                        <th><?php echo __('email'); ?></th>
                                        <th><?php echo __('university'); ?></th>
                                        <th><?php echo __('faculty'); ?></th>
                                        <th><?php echo __('department'); ?></th>
                                        <th><?php echo __('security_question'); ?></th>
                                        <th><?php echo __('registration_date'); ?></th>
                                        <th><?php echo __('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $students_result = mysqli_query($conn, "SELECT * FROM students ORDER BY created_at DESC");
                                    while($student = mysqli_fetch_assoc($students_result)) {
                                        echo "<tr>
                                            <td>" . htmlspecialchars($student['name']) . "</td>
                                            <td>" . htmlspecialchars($student['email']) . "</td>
                                            <td>" . htmlspecialchars($student['university'] ?? '') . "</td>
                                            <td>" . htmlspecialchars($student['faculty'] ?? '') . "</td>
                                            <td>" . htmlspecialchars($student['specialty'] ?? '') . "</td>
                                            <td>" . htmlspecialchars($student['security_question'] ?? '') . "</td>
                                            <td>" . htmlspecialchars($student['created_at']) . "</td>
                                            <td>
                                                <button class='btn btn-sm btn-info' onclick='showStudentInfo(" . json_encode($student['name']) . ", " . json_encode($student['email']) . ", " . json_encode($student['personal_email'] ?? '') . ", " . json_encode($student['phone'] ?? '') . ", " . json_encode($student['address'] ?? '') . ", " . json_encode($student['additional_info'] ?? '') . ", " . json_encode($student['specialty'] ?? '') . ", " . json_encode($student['university'] ?? '') . ", " . json_encode($student['faculty'] ?? '') . ", " . json_encode($student['created_at']) . ")'>" . __('view') . "</button>
                                                <a href='?delete_student=" . $student['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"" . __('confirm_delete_student') . "\")'>" . __('delete') . "</a>
                                            </td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Info Modal -->
    <div class="modal fade" id="studentInfoModal" tabindex="-1" aria-labelledby="studentInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="studentInfoModalLabel"><?php echo __('student_info_title'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('full_name'); ?>:</strong> <span id="student_name"></span></p>
                            <p><strong><?php echo __('email'); ?>:</strong> <span id="student_email"></span></p>
                            <p><strong><?php echo __('personal_email'); ?>:</strong> <span id="student_personal_email"></span></p>
                            <p><strong><?php echo __('phone_number'); ?>:</strong> <span id="student_phone"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('full_address'); ?>:</strong> <span id="student_address"></span></p>
                            <p><strong><?php echo __('department'); ?>:</strong> <span id="student_specialty"></span></p>
                            <p><strong><?php echo __('university'); ?>:</strong> <span id="student_university"></span></p>
                            <p><strong><?php echo __('faculty'); ?>:</strong> <span id="student_faculty"></span></p>
                        </div>
                        <div class="col-md-12">
                            <p><strong><?php echo __('additional_info'); ?>:</strong> <span id="student_additional_info"></span></p>
                            <p><strong><?php echo __('registration_date'); ?>:</strong> <span id="student_created_at"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('close'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal تعديل -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel"><?php echo __('edit_project'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('project_title'); ?></label>
                            <input type="text" name="edit_title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('first_student'); ?></label>
                            <input type="text" name="edit_author1" id="edit_author1" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('second_student'); ?></label>
                            <input type="text" name="edit_author2" id="edit_author2" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('third_student'); ?></label>
                            <input type="text" name="edit_author3" id="edit_author3" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('supervisor_name'); ?></label>
                            <input type="text" name="edit_supervisor_name" id="edit_supervisor_name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('department'); ?></label>
                            <select name="edit_specialty" id="edit_specialty" class="form-select">
                                <option value="قسم علوم الحاسوب"><?php echo __('computer_science'); ?></option>
                                <option value="قسم هندسة البرمجيات"><?php echo __('software_engineering'); ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('study_type'); ?></label>
                            <select name="edit_study_shift" id="edit_study_shift" class="form-select">
                                <option value="صباحي"><?php echo __('morning'); ?></option>
                                <option value="مسائي"><?php echo __('evening'); ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('study_type'); ?></label>
                            <select name="edit_study_shift" id="edit_study_shift" class="form-select">
                                <option value="صباحي"><?php echo __('morning'); ?></option>
                                <option value="مسائي"><?php echo __('evening'); ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('graduation_year'); ?></label>
                            <input type="text" name="edit_academic_year" id="edit_academic_year" class="form-control" placeholder="2023-2024">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('beneficiary'); ?></label>
                            <input type="text" name="edit_beneficiary" id="edit_beneficiary" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('arabic_summary'); ?></label>
                            <textarea name="edit_summary_ar" id="edit_summary_ar" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('english_summary'); ?></label>
                            <textarea name="edit_summary_en" id="edit_summary_en" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                        <button type="submit" name="edit_book" class="btn btn-primary"><?php echo __('save_profile'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Edit Modal -->
    <div class="modal fade" id="editPasswordModal" tabindex="-1" aria-labelledby="editPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPasswordModalLabel"><?php echo __('edit_password'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('professor'); ?>: <span id="edit_user_name"></span></label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('new_password'); ?></label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                        <button type="submit" name="edit_password" class="btn btn-primary"><?php echo __('save_profile'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary Modal -->
    <div class="modal fade" id="summaryModal" tabindex="-1" aria-labelledby="summaryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="summaryModalLabel"><?php echo __('project_summary'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6><?php echo __('arabic_summary_label'); ?>:</h6>
                    <p id="summary_ar_content"></p>
                    <hr>
                    <h6><?php echo __('english_summary_label'); ?>:</h6>
                    <p id="summary_en_content"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('close'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
window.SiteConfig = window.SiteConfig || {};
window.SiteConfig.notAvailable = <?php echo json_encode(__('not_available')); ?>;
window.SiteConfig.confirmApproveProject = <?php echo json_encode(__('confirm_approve_project')); ?>;
window.SiteConfig.confirmRejectProject = <?php echo json_encode(__('confirm_reject_project')); ?>;
</script>
    <script src="assets/js/main.js" defer></script>
</body>
</html>




