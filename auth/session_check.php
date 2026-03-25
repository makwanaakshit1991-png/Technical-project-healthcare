<?php
// ============================================================
//  auth/session_check.php  —  Include at top of every page
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', rtrim(
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
    '://' . $_SERVER['HTTP_HOST'] .
    dirname(str_replace('\\', '/', substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])))),
    '/shrs'
) . '/shrs');

require_once __DIR__ . '/../includes/rbac.php';
require_login();
