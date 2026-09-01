<?php
// Language system
// session_start(); // Removed because it's already called in index.php

// Default language
$lang_code = 'ar';

// Check if language is set in session or GET parameter
if(isset($_GET['lang'])) {
    $lang_code = $_GET['lang'];
    $_SESSION['lang'] = $lang_code;
} elseif(isset($_SESSION['lang'])) {
    $lang_code = $_SESSION['lang'];
}

// Validate language code
if(!in_array($lang_code, ['ar', 'en'])) {
    $lang_code = 'ar';
}

// Load language file
$lang_file = __DIR__ . '/languages/' . $lang_code . '.php';
if(file_exists($lang_file)) {
    include_once $lang_file;
} else {
    // Fallback to Arabic if file doesn't exist
    include_once __DIR__ . '/languages/ar.php';
}

// Set HTML direction and language
$html_dir = ($lang_code == 'ar') ? 'rtl' : 'ltr';
$html_lang = ($lang_code == 'ar') ? 'ar' : 'en';

// Translation function
function __($key) {
    global $lang;
    return isset($lang[$key]) ? $lang[$key] : $key;
}

// Language switcher URL
function lang_url($new_lang) {
    $params = $_GET;
    $params['lang'] = $new_lang;
    return '?' . http_build_query($params);
}
?>