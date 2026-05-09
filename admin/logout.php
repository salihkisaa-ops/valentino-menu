<?php
require_once '../includes/config.php';

// Oturumu sonlandır
$_SESSION = array();

// Cookie'leri temizle
if (isset($_COOKIE['admin_remember'])) {
    setcookie('admin_remember', '', time() - 3600, '/');
}

// Session'ı yok et
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Login sayfasına yönlendir
header('Location: login.php');
exit;
