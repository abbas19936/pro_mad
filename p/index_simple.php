<?php
session_start();
date_default_timezone_set('Asia/Baghdad');
include('db.php');
require_once('language.php');

// Simple test
$projects = [];
$projectsQuery = mysqli_query($conn, "SELECT * FROM books WHERE status = 'approved' ORDER BY id DESC LIMIT 5");
if($projectsQuery) {
    while($row = mysqli_fetch_assoc($projectsQuery)) {
        $projects[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $html_lang; ?>" dir="<?php echo $html_dir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('college_title'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="default-bg">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <?php echo __('college_title'); ?>
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <?php echo __('language'); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo lang_url('ar'); ?>"><?php echo __('arabic'); ?></a></li>
                        <li><a class="dropdown-item" href="<?php echo lang_url('en'); ?>"><?php echo __('english'); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <h2><?php echo __('search'); ?></h2>
        <input type="text" id="searchInput" class="form-control mb-3" placeholder="<?php echo __('search_placeholder'); ?>">

        <div class="row" id="projectsContainer">
            <?php foreach($projects as $project): ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5><?php echo htmlspecialchars($project['title']); ?></h5>
                        <p><?php echo htmlspecialchars($project['author']); ?></p>
                        <p><?php echo htmlspecialchars($project['study_shift'] == 'morning' ? __('morning') : __('evening')); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js" defer></script>
</body>
</html>