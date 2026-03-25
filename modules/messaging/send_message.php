<?php
// ============================================================
//  modules/messaging/send_message.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('messages', 'write');

$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/modules/messaging/inbox.php'); exit;
}

if (!csrf_validate()) { flash('error', 'Invalid CSRF token.'); header('Location: ' . BASE_URL . '/modules/messaging/inbox.php'); exit; }

$receiver_id = (int)$_POST['receiver_id'];
$subject     = htmlspecialchars(trim($_POST['subject'] ?? ''));
$body        = htmlspecialchars(trim($_POST['body'] ?? ''));

if (!$receiver_id || empty($body)) {
    flash('error', 'Recipient and message body are required.');
    header('Location: ' . BASE_URL . '/modules/messaging/inbox.php'); exit;
}

$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?,?,?,?)");
$stmt->bind_param('iiss', $uid, $receiver_id, $subject, $body);
$stmt->execute(); $stmt->close();

flash('success', 'Message sent successfully.');
header('Location: ' . BASE_URL . '/modules/messaging/inbox.php?tab=sent');
exit;
