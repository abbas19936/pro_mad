<?php
session_start();
date_default_timezone_set('Asia/Baghdad');
include('db.php');
require_once('language.php');

function translate_specialty($specialty) {
    switch($specialty) {
        case 'قسم علوم الحاسوب':
            return __('computer_science');
        case 'قسم هندسة البرمجيات':
            return __('software_engineering');
        default:
            return htmlspecialchars($specialty);
    }
}

function translate_study_shift($shift) {
    switch($shift) {
        case 'صباحي':
            return __('morning');
        case 'مسائي':
            return __('evening');
        default:
            return __('not_available');
    }
}

// التأكد من وجود حقل status في جدول books
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'status'");
if(mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved'");
    // تحديث المشاريع القديمة إلى approved
    mysqli_query($conn, "UPDATE books SET status = 'approved' WHERE status IS NULL OR status = ''");
}

// تأكد من وجود حقول ملف الطالب الشخصي
$studentColumns = ['personal_email' => "VARCHAR(255)", 'phone' => "VARCHAR(50)", 'address' => "TEXT", 'additional_info' => "TEXT"];
foreach($studentColumns as $column => $definition) {
    $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE '$column'");
    if(mysqli_num_rows($checkColumn) == 0) {
        mysqli_query($conn, "ALTER TABLE students ADD COLUMN $column $definition");
    }
}

// حدد الزائر بواسطة كوكيز ثابتة
if(empty($_COOKIE['visitor_token'])) {
    $visitor_token = bin2hex(random_bytes(16));
    setcookie('visitor_token', $visitor_token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
} else {
    $visitor_token = $_COOKIE['visitor_token'];
}

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$ip_address = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR'] ?? '');
$user_agent = mysqli_real_escape_string($conn, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255));
$visitor_token_db = mysqli_real_escape_string($conn, $visitor_token);

$visitorQuery = mysqli_query($conn, "SELECT id, visit_date FROM page_visitors WHERE visitor_token = '$visitor_token_db' LIMIT 1");
if($visitorQuery && mysqli_num_rows($visitorQuery) > 0) {
    $visitor = mysqli_fetch_assoc($visitorQuery);
    if($visitor['visit_date'] !== $today) {
        mysqli_query($conn, "UPDATE page_visitors SET last_activity = '$now', visit_date = '$today' WHERE id = {$visitor['id']}");
        mysqli_query($conn, "INSERT INTO page_views (id, count) VALUES (1, 1) ON DUPLICATE KEY UPDATE count = count + 1");
    } else {
        mysqli_query($conn, "UPDATE page_visitors SET last_activity = '$now' WHERE id = {$visitor['id']}");
    }
} else {
    mysqli_query($conn, "INSERT INTO page_visitors (visitor_token, ip_address, user_agent, first_visit, last_activity, visit_date) VALUES ('$visitor_token_db', '$ip_address', '$user_agent', '$now', '$now', '$today')");
    mysqli_query($conn, "INSERT INTO page_views (id, count) VALUES (1, 1) ON DUPLICATE KEY UPDATE count = count + 1");
}

// عدد المشاهدات اليوم (كل جهاز مرة واحدة في اليوم)
$viewsResult = mysqli_query($conn, "SELECT COUNT(*) AS count FROM page_visitors WHERE visit_date = '$today'");
$views = 0;
if($viewsResult && $row = mysqli_fetch_assoc($viewsResult)) {
    $views = $row['count'];
}

// عدد المتواجدين الآن خلال آخر 5 دقائق
$active_since = date('Y-m-d H:i:s', time() - 60 * 5);
$onlineResult = mysqli_query($conn, "SELECT COUNT(*) AS online FROM page_visitors WHERE last_activity >= '$active_since'");
$online = 0;
if($onlineResult && $row = mysqli_fetch_assoc($onlineResult)) {
    $online = $row['online'];
}

// إجمالي زوار الموقع من التأسيس إلى اليوم
$totalVisitorsResult = mysqli_query($conn, "SELECT count FROM page_views WHERE id = 1");
$totalVisitors = 0;
if($totalVisitorsResult && $row = mysqli_fetch_assoc($totalVisitorsResult)) {
    $totalVisitors = intval($row['count']);
}

$specialty = $_GET['specialty'] ?? '';
$errors = [];
$showProfileModal = false;
$profileComplete = true;
$studentProfile = [];
if(isset($_SESSION['loggedin']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student') {
    $student_id = intval($_SESSION['user_id']);
    $studentResult = mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id LIMIT 1");
    if($studentResult && mysqli_num_rows($studentResult) > 0) {
        $studentProfile = mysqli_fetch_assoc($studentResult);
        if(empty($studentProfile['personal_email']) || empty($studentProfile['phone']) || empty($studentProfile['address'])) {
            $showProfileModal = true;
            $profileComplete = false;
        }
    }
}

if(isset($_POST['save_profile'])) {
    if(!isset($_SESSION['loggedin']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
        $errors[] = __('login_required');
    } else {
        $student_id = intval($_SESSION['user_id']);
        $name = trim($_POST['name']);
        $personal_email = trim($_POST['personal_email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $additional_info = trim($_POST['additional_info']);

        if(empty($name)) $errors[] = __('full_name_required');
        if(empty($personal_email) || !filter_var($personal_email, FILTER_VALIDATE_EMAIL)) $errors[] = __('personal_email_required');
        if(empty($phone)) $errors[] = __('phone_required');
        if(empty($address)) $errors[] = __('address_required');

        if(empty($errors)) {
            $name = mysqli_real_escape_string($conn, $name);
            $personal_email = mysqli_real_escape_string($conn, $personal_email);
            $phone = mysqli_real_escape_string($conn, $phone);
            $address = mysqli_real_escape_string($conn, $address);
            $additional_info = mysqli_real_escape_string($conn, $additional_info);
            mysqli_query($conn, "UPDATE students SET name = '$name', personal_email = '$personal_email', phone = '$phone', address = '$address', additional_info = '$additional_info' WHERE id = $student_id");
            $studentProfile = mysqli_query($conn, "SELECT * FROM students WHERE id = $student_id LIMIT 1");
            $studentProfile = mysqli_fetch_assoc($studentProfile);
            $showProfileModal = false;
            echo "<script>alert(" . json_encode(__('profile_saved')) . ");</script>";
        } else {
            $showProfileModal = true;
        }
    }
}

if(isset($_POST['add_project'])) {
    if(!isset($_SESSION['loggedin']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
        $errors[] = __('login_required');
    } else {
        $title = trim($_POST['title']);
        $title_en = trim($_POST['title_en']);
        $author1 = trim($_POST['author1']);
        $author2 = trim($_POST['author2'] ?? '');
        $author3 = trim($_POST['author3'] ?? '');
        $author = $author1;
        if(!empty($author2)) $author .= '، ' . $author2;
        if(!empty($author3)) $author .= '، ' . $author3;
        $supervisor_name = trim($_POST['supervisor_name']);
        $specialty_field = $_POST['specialty_field'];
        $study_shift = $_POST['study_shift'];
        $beneficiary = trim($_POST['beneficiary']);
        $summary_ar = trim($_POST['summary_ar']);
        $summary_en = trim($_POST['summary_en']);
        $academic_year = trim($_POST['academic_year']);
        
        if(empty($title)) $errors[] = __('title_required');
        if(empty($author1)) $errors[] = __('first_student_required');
        if(empty($supervisor_name)) $errors[] = __('supervisor_required');
        if(empty($specialty_field)) $errors[] = __('department_required');
        if(empty($study_shift)) $errors[] = __('study_type_required');
        if(empty($beneficiary)) $errors[] = __('beneficiary_required');
        if(empty($academic_year)) $errors[] = __('graduation_year_required');
        if(empty($studentProfile['personal_email']) || empty($studentProfile['phone']) || empty($studentProfile['address'])) {
            $errors[] = 'يجب تحديث ملفك الشخصي قبل إرسال المشروع.';
            $showProfileModal = true;
        }
        
        $pdf_file_name = '';
        if(isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['pdf_file']['tmp_name'];
            $file_name = $_FILES['pdf_file']['name'];
            $file_size = $_FILES['pdf_file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if($file_ext !== 'pdf') {
                $errors[] = __('pdf_only');
            } elseif($file_size > 10 * 1024 * 1024) { // 10MB
                $errors[] = __('file_too_large');
            } else {
                $new_file_name = uniqid('project_', true) . '.pdf';
                $upload_path = 'uploads/' . $new_file_name;
                if(move_uploaded_file($file_tmp, $upload_path)) {
                    $pdf_file_name = $new_file_name;
                } else {
                    $errors[] = __('upload_failed');
                }
            }
        }
        
        if(empty($errors)) {
            $title = mysqli_real_escape_string($conn, $title);
            $title_en = mysqli_real_escape_string($conn, $title_en);
            $author = mysqli_real_escape_string($conn, $author);
            $supervisor_name = mysqli_real_escape_string($conn, $supervisor_name);
            $specialty_field = mysqli_real_escape_string($conn, $specialty_field);
            $study_shift = mysqli_real_escape_string($conn, $study_shift);
            $beneficiary = mysqli_real_escape_string($conn, $beneficiary);
            $summary_ar = mysqli_real_escape_string($conn, $summary_ar);
            $summary_en = mysqli_real_escape_string($conn, $summary_en);
            $academic_year = mysqli_real_escape_string($conn, $academic_year);
            $student_id = $_SESSION['user_id'];
            $sql = "INSERT INTO books (title, title_en, author, supervisor_name, specialty, study_shift, beneficiary, summary_ar, summary_en, academic_year, pdf_file, added_by, status) 
                    VALUES ('$title', '$title_en', '$author', '$supervisor_name', '$specialty_field', '$study_shift', '$beneficiary', '$summary_ar', '$summary_en', '$academic_year', '$pdf_file_name', $student_id, 'pending')";
            if(mysqli_query($conn, $sql)) {
                // إرسال إشعار للطالب
                $notification_message = mysqli_real_escape_string($conn, __('project_submitted'));
                // تحقق من وجود الطالب قبل إدراج الإشعار
                $check_student = mysqli_query($conn, "SELECT id FROM students WHERE id = $student_id");
                if($check_student && mysqli_num_rows($check_student) > 0) {
                    mysqli_query($conn, "INSERT INTO notifications (student_id, message, type) VALUES ($student_id, '$notification_message', 'info')");
                }
                echo "<script>alert(" . json_encode(__('project_submitted')) . ");</script>";
            } else {
                $errors[] = __('database_error') . ': ' . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $html_lang; ?>" dir="<?php echo $html_dir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('college_title'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="default-bg">

<div class="college-header animate__animated animate__fadeInDown">
    <img src="logo.png" alt="شعار الكلية" class="college-logo animate__animated animate__bounceIn">
    <h2 class="mt-2 animate__animated animate__fadeIn"><?php echo __('college_title'); ?></h2>
    <div class="datetime animate__animated animate__fadeIn animate__delay-1s">
        <i class="fas fa-calendar-alt"></i> <span id="dateText"></span> | <i class="fas fa-clock"></i> <span id="timeText"></span>
     
    </div>
    <div class="datetime animate__animated animate__fadeIn animate__delay-1s mt-2">
        <strong><?php echo __('visitors_today'); ?>:</strong> <?php echo number_format($views); ?>
        &nbsp;|&nbsp;
        <strong><?php echo __('online_now'); ?>:</strong> <?php echo number_format($online); ?>
    </div>
    <div class="datetime animate__animated animate__fadeIn animate__delay-1s mt-2">
        <strong><?php echo __('total_visitors'); ?>:</strong> <span style="color: #007bff; font-weight: bold;"><?php echo number_format($totalVisitors); ?></span>
    </div>
       <div class="float-end">
            <a href="<?php echo lang_url('ar'); ?>" class="btn btn-sm btn-outline-primary <?php echo $lang_code == 'ar' ? 'active' : ''; ?>"><?php echo __('arabic'); ?></a>
            <a href="<?php echo lang_url('en'); ?>" class="btn btn-sm btn-outline-primary <?php echo $lang_code == 'en' ? 'active' : ''; ?>"><?php echo __('english'); ?></a>
        </div>
</div>

<div class="container mt-4 animate__animated animate__fadeInUp animate__delay-0.5s">
    <div class="row mb-4">
        <div class="col-md-8">
            <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center flex-grow-1">
                    <label class="form-label me-2 mb-0"><?php echo __('search'); ?>:</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="<?php echo __('search_placeholder'); ?>">
                </div>
                <div class="d-flex align-items-center">
                    <label class="form-label me-2 mb-0"><?php echo __('department'); ?>:</label>
                    <select name="specialty" class="form-select w-auto" onchange="this.form.submit()">
                        <option value="" <?php if($specialty == '') echo 'selected'; ?>><?php echo __('all_departments'); ?></option>
                        <option value="قسم علوم الحاسوب" <?php if($specialty == 'قسم علوم الحاسوب') echo 'selected'; ?>><?php echo __('computer_science'); ?></option>
                        <option value="قسم هندسة البرمجيات" <?php if($specialty == 'قسم هندسة البرمجيات') echo 'selected'; ?>><?php echo __('software_engineering'); ?></option>
                    </select>
                </div>
            </form>
        </div>
        <div class="col-md-4 text-end">
            <div class="action-tabs">
                <?php if(isset($_SESSION['loggedin']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student'): ?>
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#profileModal"><?php echo __('complete_profile'); ?></button>
                    <button type="button" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#requestModal" <?php if(!$profileComplete) echo 'disabled title="' . __('profile_required') . '"'; ?>><?php echo __('add_project'); ?></button>
                    <a href="notifications.php" class="btn btn-outline-info"><?php echo __('notifications'); ?></a>
                    <a href="logout.php" class="btn btn-outline-secondary"><?php echo __('logout'); ?></a>
                    <?php if(!$profileComplete): ?>
                        <div class="mt-2 text-danger"><?php echo __('profile_required'); ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-primary"><?php echo __('login_register'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
        $totalProjectsResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM books WHERE status = 'approved'");
        $totalProjects = 0;
        if($totalProjectsResult && $totalRow = mysqli_fetch_assoc($totalProjectsResult)) {
            $totalProjects = intval($totalRow['total']);
        }
        $groupResult = mysqli_query($conn, "SELECT specialty, COUNT(*) AS count FROM books WHERE status = 'approved' GROUP BY specialty ORDER BY count DESC");
    ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-white rounded-4 p-3 shadow-sm">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <strong class="me-2"><?php echo __('project_groups'); ?>:</strong>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary<?php echo $specialty == '' ? ' active' : ''; ?>"><?php echo __('all_projects'); ?> (<?php echo $totalProjects; ?>)</a>
                    <?php
                        if($groupResult) {
                            while($groupRow = mysqli_fetch_assoc($groupResult)) {
                                $groupSpecialty = translate_specialty($groupRow['specialty']);
                                $groupCount = intval($groupRow['count']);
                                $activeClass = $specialty == $groupRow['specialty'] ? ' active' : '';
                                echo "<a href=\"?specialty=" . urlencode($groupRow['specialty']) . "\" class=\"btn btn-sm btn-outline-primary{$activeClass}\">{$groupSpecialty} ({$groupCount})</a>";
                            }
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="projects-grid animate__animated animate__fadeIn animate__delay-1s">
        <?php
        $query = "SELECT * FROM books WHERE status = 'approved'";
        if($specialty) $query .= " AND specialty = '$specialty'";
        $query .= " ORDER BY specialty, title";
        
        $result = mysqli_query($conn, $query);
        if(!$result) {
            echo "<div class='no-projects'>" . __('database_error') . ": " . mysqli_error($conn) . "</div>";
        } else {
        while($row = mysqli_fetch_assoc($result)) {
            $title = htmlspecialchars($row['title']);
            $author = htmlspecialchars($row['author']);
            $studentName = '';
            $studentEmail = '';
            $studentPhone = '';
            $studentAddress = '';
            $studentAdditional = '';
            if(!empty($row['added_by'])) {
                $studentQuery = mysqli_query($conn, "SELECT name, personal_email, phone, address, additional_info FROM students WHERE id = " . intval($row['added_by']) . " LIMIT 1");
                if($studentQuery && mysqli_num_rows($studentQuery) > 0) {
                    $studentData = mysqli_fetch_assoc($studentQuery);
                    $studentName = $studentData['name'];
                    $studentEmail = $studentData['personal_email'];
                    $studentPhone = $studentData['phone'];
                    $studentAddress = $studentData['address'];
                    $studentAdditional = $studentData['additional_info'];
                }
            }
            ?>
            <div class="project-card" data-title="<?php echo $title; ?>" data-author="<?php echo $author; ?>" data-supervisor="<?php echo htmlspecialchars($row['supervisor_name']); ?>">
                <div class="project-card-header">
                    <?php echo htmlspecialchars($row['title']); ?>
                </div>
                <div class="project-card-body">
                    <div class="project-details">
                        <div class="project-info-item">
                            <strong><?php echo __('student_team'); ?></strong>
                            <span><?php 
                                $authors = explode('، ', $row['author']);
                                $numbered_authors = [];
                                foreach($authors as $index => $auth) {
                                    $numbered_authors[] = ($index + 1) . '. ' . trim($auth);
                                }
                                echo htmlspecialchars(implode(', ', $numbered_authors));
                            ?></span>
                        </div>
                        <div class="project-info-item">
                            <strong><?php echo __('department'); ?></strong>
                            <span><?php echo htmlspecialchars(translate_specialty($row['specialty'])); ?></span>
                        </div>
                        <div class="project-info-item">
                            <strong><?php echo __('study_type'); ?></strong>
                            <span><?php echo htmlspecialchars(translate_study_shift($row['study_shift'] ?? '')); ?></span>
                        </div>
                        <div class="project-info-item">
                            <strong><?php echo __('supervisor'); ?></strong>
                            <span><?php echo htmlspecialchars($row['supervisor_name'] ?? __('not_available')); ?></span>
                        </div>
                        <div class="project-info-item">
                            <strong><?php echo __('graduation_year_label'); ?></strong>
                            <span><?php echo htmlspecialchars($row['academic_year'] ?? __('not_available')); ?></span>
                        </div>
                        <div class="project-info-item">
                            <strong><?php echo __('beneficiary'); ?></strong>
                            <span><?php echo nl2br(htmlspecialchars($row['beneficiary'] ?? __('not_available'))); ?></span>
                        </div>
                    </div>
                    <div class="project-preview">
                        <?php if(!empty($row['pdf_file'])): ?>
                            <iframe src="view_pdf.php?id=<?php echo intval($row['id']); ?>#page=1" width="100%" height="320px" style="border: 1px solid rgba(0,0,0,0.08); border-radius: 16px; background: #fff;"></iframe>
                        <?php else: ?>
                            <div class="no-cover"><?php echo __('cover_not_available'); ?></div>
                        <?php endif; ?>
                        <div class="project-actions">
                            <?php if(!empty($row['pdf_file'])): ?>
                                <a href='../p/view_pdf.php?id=<?php echo intval($row['id']); ?>' class='btn btn-primary'><?php echo __('view_on_site'); ?></a>
                                <a href='download.php?file=<?php echo urlencode($row['pdf_file']); ?>' class='btn btn-danger'><?php echo __('download'); ?></a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-info" onclick="showSummary('<?php echo addslashes($row['summary_ar'] ?? ''); ?>','<?php echo addslashes($row['summary_en'] ?? ''); ?>')"><?php echo __('show_summary'); ?></button>
                            <button type="button" class="btn btn-secondary" onclick="showStudentInfo(this)" 
                                data-student-name="<?php echo htmlspecialchars($studentName); ?>" 
                                data-student-email="<?php echo htmlspecialchars($studentEmail); ?>" 
                                data-student-phone="<?php echo htmlspecialchars($studentPhone); ?>" 
                                data-student-address="<?php echo htmlspecialchars($studentAddress); ?>" 
                                data-student-additional="<?php echo htmlspecialchars($studentAdditional); ?>">
                                <?php echo __('view_student_info'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        }
        if(mysqli_num_rows($result) == 0) {
            echo "<div class='no-projects'>" . __('no_projects_available') . "</div>";
        }
        }
        ?>
    </div>
</div>

<!-- Modal لإضافة مشروع -->
<div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestModalLabel"><?php echo __('add_project'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <?php if(!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('project_title'); ?></label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('project_title_en'); ?></label>
                                <input type="text" name="title_en" class="form-control" value="<?php echo htmlspecialchars($_POST['title_en'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('first_student'); ?></label>
                                <input type="text" name="author1" class="form-control" value="<?php echo htmlspecialchars($_POST['author1'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <input type="checkbox" id="add_author2" onchange="toggleAuthor2()"> <?php echo __('add_second_student'); ?>
                                </label>
                                <input type="text" name="author2" id="author2" class="form-control" value="<?php echo htmlspecialchars($_POST['author2'] ?? ''); ?>" style="display: none;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <input type="checkbox" id="add_author3" onchange="toggleAuthor3()"> <?php echo __('add_third_student'); ?>
                                </label>
                                <input type="text" name="author3" id="author3" class="form-control" value="<?php echo htmlspecialchars($_POST['author3'] ?? ''); ?>" style="display: none;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('supervisor_name'); ?></label>
                                <input type="text" name="supervisor_name" class="form-control" value="<?php echo htmlspecialchars($_POST['supervisor_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('department'); ?></label>
                                <select name="specialty_field" class="form-select" required>
                                    <option value=""><?php echo __('choose_department'); ?></option>
                                    <option value="قسم علوم الحاسوب" <?php if(($_POST['specialty_field'] ?? '') == 'قسم علوم الحاسوب') echo 'selected'; ?>><?php echo __('computer_science'); ?></option>
                                    <option value="قسم هندسة البرمجيات" <?php if(($_POST['specialty_field'] ?? '') == 'قسم هندسة البرمجيات') echo 'selected'; ?>><?php echo __('software_engineering'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('study_type'); ?></label>
                                <select name="study_shift" class="form-select" required>
                                    <option value=""><?php echo __('choose_study_type'); ?></option>
                                    <option value="صباحي" <?php if(($_POST['study_shift'] ?? '') == 'صباحي') echo 'selected'; ?>><?php echo __('morning'); ?></option>
                                    <option value="مسائي" <?php if(($_POST['study_shift'] ?? '') == 'مسائي') echo 'selected'; ?>><?php echo __('evening'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('graduation_year'); ?></label>
                                <input type="text" name="academic_year" class="form-control" value="<?php echo htmlspecialchars($_POST['academic_year'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('upload_pdf'); ?></label>
                                <input type="file" name="pdf_file" class="form-control" accept=".pdf">
                                <small class="form-text text-muted"><?php echo __('pdf_note'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('beneficiary'); ?></label>
                                <textarea name="beneficiary" class="form-control" rows="2" required><?php echo htmlspecialchars($_POST['beneficiary'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('arabic_summary'); ?></label>
                                <textarea name="summary_ar" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['summary_ar'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo __('english_summary'); ?></label>
                                <textarea name="summary_en" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['summary_en'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" name="add_project" class="btn btn-custom"><?php echo __('submit_project'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.SiteConfig = window.SiteConfig || {};
window.SiteConfig.notAvailable = <?php echo json_encode(__('not_available')); ?>;
window.SiteConfig.locale = <?php echo json_encode($html_lang === 'ar' ? 'ar-EG' : 'en-US'); ?>;
window.SiteConfig.noResultsText = <?php echo json_encode(__('no_results')); ?>;
window.SiteConfig.showProfileModal = <?php echo $showProfileModal ? 'true' : 'false'; ?>;
window.SiteConfig.showRequestModal = <?php echo !empty($errors) ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/main.js" defer></script>

