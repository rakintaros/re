<?php
session_start();

// ทำลาย session ทั้งหมด
session_destroy();

// ลบ session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect ไปหน้า login
header("Location: login.php");
exit();
?>