<?php
session_start();
if(!isset($_SESSION['loggedin']) || $_SESSION['user_type'] != 'student') {
    header('Location: login.php');
    exit;
}
include('db.php');
$user_id = intval($_SESSION['user_id']);

// تحديث الإشعارات كمقروءة عند الزيارة باستخدام prepared statement
$sql_update = "UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0";
$stmt_update = mysqli_prepare($conn, $sql_update);
mysqli_stmt_bind_param($stmt_update, "i", $user_id);
mysqli_stmt_execute($stmt_update);

// جلب الإشعارات باستخدام prepared statement
$sql = "SELECT * FROM notifications WHERE student_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>الإشعارات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: 'Cairo', sans-serif; padding: 20px; }
        .notification-card { margin-bottom: 15px; border-radius: 10px; }
    </style>
</head>
<body>
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
                        <p class="mb-1"><?php echo htmlspecialchars($row['message']); ?></p>
                        <small class="text-muted"><?php echo htmlspecialchars($row['created_at']); ?></small>
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
</body>
</html>