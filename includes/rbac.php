<?php
// ============================================================
//  includes/rbac.php  —  Role-Based Access Control
// ============================================================

require_once __DIR__ . '/../config/db.php';

/**
 * Check if the current session user has a given permission on a module.
 * Redirects to 403 page if permission is denied.
 *
 * @param string $module   e.g. 'health_records'
 * @param string $action   'read' | 'write' | 'delete' | 'approve'
 * @param bool   $redirect Whether to redirect on failure (default true)
 * @return bool
 */
function check_permission(string $module, string $action = 'read', bool $redirect = true): bool {
    global $conn;

    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        if ($redirect) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
        return false;
    }

    $role = $_SESSION['role'];

    // Admin always has all permissions
    if ($role === 'admin') {
        return true;
    }

    $column_map = [
        'read'    => 'can_read',
        'write'   => 'can_write',
        'delete'  => 'can_delete',
        'approve' => 'can_approve',
    ];

    $col = $column_map[$action] ?? 'can_read';

    $stmt = $conn->prepare("SELECT $col FROM role_permissions WHERE role = ? AND module = ? LIMIT 1");
    $stmt->bind_param('ss', $role, $module);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $has_permission = ($row && (int)$row[$col] === 1);

    if (!$has_permission && $redirect) {
        http_response_code(403);
        include __DIR__ . '/../includes/403.php';
        exit;
    }

    return $has_permission;
}

/**
 * Require the user to be logged in; redirect to login otherwise.
 */
function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php?msg=session_expired');
        exit;
    }

    // Session timeout: 30 minutes of inactivity
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?msg=session_expired');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Check if patient has consented access to their records for a given user.
 */
function has_consent(int $patient_id, int $accessor_id, string $record_type = 'all'): bool {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT consent_id FROM consents
         WHERE patient_id = ? AND granted_to_user_id = ?
           AND is_active = 1
           AND (record_type = 'all' OR record_type = ?)
           AND (expires_at IS NULL OR expires_at > NOW())
         LIMIT 1"
    );
    $stmt->bind_param('iis', $patient_id, $accessor_id, $record_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $found = ($result->num_rows > 0);
    $stmt->close();
    return $found;
}

/**
 * Generate a CSRF token and store it in the session.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the submitted CSRF token.
 */
function csrf_validate(): bool {
    $submitted = $_POST['csrf_token'] ?? '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $submitted);
}

/**
 * Set a flash message.
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Render and clear the flash message.
 */
function render_flash(): string {
    if (!isset($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type_map = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $bs = $type_map[$f['type']] ?? 'info';
    return '<div class="alert alert-' . $bs . ' alert-dismissible fade show" role="alert">'
         . htmlspecialchars($f['message'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
