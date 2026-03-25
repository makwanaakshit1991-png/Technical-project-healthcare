<?php
// ============================================================
//  logout.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);

session_unset();
session_destroy();

header('Location: ' . BASE_URL . '/index.php?msg=logged_out');
exit;
