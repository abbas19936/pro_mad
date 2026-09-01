<?php
session_start();
// clear session safely
$_SESSION = [];
if (ini_get("session.use_cookies")) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000,
		$params["path"], $params["domain"],
		$params["secure"], $params["httponly"]
	);
}
session_unset();
session_destroy();

// Redirect to home with a message
header('Location: index.php?msg=logged_out');
exit;
?>