<?php
session_start();
if(!isset($_SESSION['loggedin']) || $_SESSION['user_type'] != 'student') {
    header('Location: login.php');
    exit;
}
include('db.php');
$user_id = $_SESSION['user_id'];

// تحديث الإشعارات كمقروءة عند الزيارة
mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE student_id = $user_id AND is_read = 0");

// جلب الإشعارات
$result = mysqli_query($conn, "SELECT * FROM notifications WHERE student_id = $user_id ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>الإشعارات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="purple-bg notifications-page">
    <div class="container">
        <h2 class="text-center text-white mb-4">الإشعارات</h2>
        <?php if(mysqli_num_rows($result) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
                <div class="card notification-card <?php 
                    if($row['type'] == 'success') echo 'border-success';
                    elseif($row['type'] == 'warning') echo 'border-warning';
                    elseif($row['type'] == 'error') echo 'border-danger';
                    else echo 'border-info';
                ?>">
                    <div class="card-body">
                        <p class="mb-1"><?php echo $row['message']; ?></p>
                        <small class="text-muted"><?php echo $row['created_at']; ?></small>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info text-center">لا توجد إشعارات جديدة.</div>
        <?php endif; ?>
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-primary">العودة للرئيسية</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js" defer></script>
</body>
</html>