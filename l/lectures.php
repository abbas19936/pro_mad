<?php include('db.php'); 
$stage = intval($_GET['stage'] ?? 0);
if($stage <= 0) {
    $stage = '';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>المحاضرات</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            font-family: 'Cairo', sans-serif;
        }
        .container { 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
            animation: fadeInUp 1s ease-out;
            margin-top: 20px;
        }
        .btn-custom { 
            background: linear-gradient(45deg, #28a745, #20c997); 
            border: none; 
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40,167,69,0.3);
        }
        .table tr { 
            transition: all 0.3s ease; 
        }
        .table tr:hover { 
            background-color: rgba(40,167,69,0.1) !important; 
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
    </style>
</head>
<body>

<div class="container animate__animated animate__fadeInUp">
    <h2 class="text-center mb-4 animate__animated animate__fadeIn">المحاضرات</h2>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <form method="GET" class="d-flex align-items-center">
                <label class="form-label me-2 mb-0">اختر المرحلة:</label>
                <select name="stage" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="">كل المراحل</option>
                    <option value="1" <?php if($stage == '1') echo 'selected'; ?>>المرحلة 1</option>
                    <option value="2" <?php if($stage == '2') echo 'selected'; ?>>المرحلة 2</option>
                    <option value="3" <?php if($stage == '3') echo 'selected'; ?>>المرحلة 3</option>
                    <option value="4" <?php if($stage == '4') echo 'selected'; ?>>المرحلة 4</option>
                    <option value="5" <?php if($stage == '5') echo 'selected'; ?>>المرحلة 5</option>
                </select>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-outline-secondary">العودة للواجهة الرئيسية</a>
        </div>
    </div>

    <div class="table-responsive animate__animated animate__fadeIn animate__delay-1s">
        <table class="table table-bordered table-striped bg-white">
            <thead class="table-dark">
                <tr>
                    <th>عنوان المحاضرة</th>
                    <th>المرحلة</th>
                    <th>تحميل</th>
                    <th>تاريخ الرفع</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if($stage > 0) {
                    $sql = "SELECT * FROM lectures WHERE stage = ? ORDER BY upload_date DESC";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $stage);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                } else {
                    $sql = "SELECT * FROM lectures ORDER BY upload_date DESC";
                    $result = mysqli_query($conn, $sql);
                }
                
                if(!$result) {
                    echo "<tr><td colspan='4'>خطأ في جلب البيانات.</td></tr>";
                } else {
                    while($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['title']) . "</td>
                            <td><span class='badge bg-success'>المرحلة {$row['stage']}</span></td>
                            <td><a href='uploads/{$row['file_path']}' class='btn btn-sm btn-danger' target='_blank'>تحميل 📥</a></td>
                            <td>{$row['upload_date']}</td>
                        </tr>";
                    }
                    if(mysqli_num_rows($result) == 0) {
                        echo "<tr><td colspan='4'>لا توجد محاضرات متاحة لهذه المرحلة.</td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>