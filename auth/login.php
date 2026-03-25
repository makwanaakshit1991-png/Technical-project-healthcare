<?php
// ============================================================
//  auth/login.php  —  Process login form submission
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// CSRF check
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid request. Please try again.'];
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL) ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = 'Please enter both email and password.';
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Fetch user
$stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

if (!$user || !password_verify($password, $user['password_hash'])) {
    // Log failed attempt
    $fail_uid = $user['user_id'] ?? null;
    if ($fail_uid) {
        $ls = $conn->prepare("INSERT INTO system_logs (user_id, action, module, status, ip_address, user_agent) VALUES (?, 'Failed login attempt', 'auth', 'failure', ?, ?)");
        $ls->bind_param('iss', $fail_uid, $ip, $ua);
        $ls->execute();
        $ls->close();
    }

    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if (!(int)$user['is_active']) {
    $_SESSION['login_error'] = 'Your account is deactivated. Please contact the administrator.';
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Successful login — regenerate session
session_regenerate_id(true);
$_SESSION['user_id']       = (int)$user['user_id'];
$_SESSION['full_name']     = $user['full_name'];
$_SESSION['email']         = $user['email'];
$_SESSION['role']          = $user['role'];
$_SESSION['last_activity'] = time();
unset($_SESSION['csrf_token']); // Regenerate on next page

// Log successful login
$ls = $conn->prepare("INSERT INTO system_logs (user_id, action, module, status, ip_address, user_agent) VALUES (?, 'User login', 'auth', 'success', ?, ?)");
$ls->bind_param('iss', $_SESSION['user_id'], $ip, $ua);
$ls->execute();
$ls->close();

// Blockchain audit
require_once __DIR__ . '/../modules/blockchain/audit_logger.php';
log_transaction('LoginEvent', $_SESSION['user_id'], null, [
    'action' => 'login',
    'email'  => $email,
    'ts'     => date('Y-m-d H:i:s'),
], $ip);

// Redirect to role dashboard
$dashboards = [
    'patient'    => BASE_URL . '/dashboards/patient_dashboard.php',
    'clinician'  => BASE_URL . '/dashboards/clinician_dashboard.php',
    'pharmacist' => BASE_URL . '/dashboards/pharmacist_dashboard.php',
    'insurer'    => BASE_URL . '/dashboards/insurer_dashboard.php',
    'admin'      => BASE_URL . '/dashboards/admin_dashboard.php',
];

header('Location: ' . ($dashboards[$user['role']] ?? BASE_URL . '/index.php'));
exit;
