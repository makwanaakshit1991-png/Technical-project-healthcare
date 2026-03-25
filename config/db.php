<?php
// ============================================================
//  config/db.php  —  Database connection (MySQLi)
//  Edit DB_HOST, DB_USER, DB_PASS to match your environment.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'shrs_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log('SHRS DB Connection failed: ' . $conn->connect_error);
    die('<div style="font-family:sans-serif;padding:40px;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:8px;max-width:600px;margin:60px auto;">
        <h2>⚠️ Database Connection Error</h2>
        <p>Could not connect to the database. Please check your credentials in <code>config/db.php</code>.</p>
        <p><small>' . htmlspecialchars($conn->connect_error) . '</small></p>
    </div>');
}

$conn->set_charset('utf8mb4');
