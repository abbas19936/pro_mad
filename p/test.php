<?php
session_start();
date_default_timezone_set('Asia/Baghdad');
include('db.php');
require_once('language.php');

// Simple test
echo "Language: " . $lang_code . "<br>";
echo "Direction: " . $html_dir . "<br>";
echo "Test translation: " . __('college_title') . "<br>";
?>