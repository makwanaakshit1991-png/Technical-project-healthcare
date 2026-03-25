<?php
// ============================================================
//  admin/manage_users.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rbac.php';
require_login();
if ($_SESSION['role'] !== 'admin') { include __DIR__ . '/../includes/403.php'; exit; }

$page_title = 'Manage Users';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) { flash('error', 'Invalid CSRF token.'); header('Location: ' . BASE_URL . '/admin/manage_users.php'); exit; }

    $action = $_POST['action'] ?? '';
    $target_id = (int)$_POST['target_user_id'];

    if ($action === 'toggle_active' && $target_id) {
        $r = $conn->query("SELECT is_active FROM users WHERE user_id=$target_id LIMIT 1");
        $cur = (int)$r->fetch_assoc()['is_active'];
        $new = $cur ? 0 : 1;
        $stmt = $conn->prepare("UPDATE users SET is_active=? WHERE user_id=?");
        $stmt->bind_param('ii', $new, $target_id); $stmt->execute(); $stmt->close();
        flash('success', 'User status updated.');
    } elseif ($action === 'delete' && $target_id && $target_id !== (int)$_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
        $stmt->bind_param('i', $target_id); $stmt->execute(); $stmt->close();
        flash('success', 'User deleted.');
    } elseif ($action === 'change_role' && $target_id) {
        $new_role = $_POST['new_role'] ?? '';
        $valid_roles = ['patient','clinician','pharmacist','insurer','admin'];
        if (in_array($new_role, $valid_roles)) {
            $stmt = $conn->prepare("UPDATE users SET role=? WHERE user_id=?");
            $stmt->bind_param('si', $new_role, $target_id); $stmt->execute(); $stmt->close();
            flash('success', 'User role updated.');
        }
    } elseif ($action === 'reset_password' && $target_id) {
        $new_pass = $_POST['new_password'] ?? '';
        if (strlen($new_pass) >= 8) {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE user_id=?");
            $stmt->bind_param('si', $hash, $target_id); $stmt->execute(); $stmt->close();
            flash('success', 'Password reset successfully.');
        } else {
            flash('error', 'Password must be at least 8 characters.');
        }
    }
    header('Location: ' . BASE_URL . '/admin/manage_users.php'); exit;
}

// Search & filter
$search = htmlspecialchars(trim($_GET['q'] ?? ''));
$role_filter = $_GET['role'] ?? 'all';
$per_page = 10; $page_num = max(1,(int)($_GET['page']??1)); $offset = ($page_num-1)*$per_page;

$where_parts = ['1=1'];
$params = []; $types = '';
if (!empty($search)) {
    $where_parts[] = "(full_name LIKE ? OR email LIKE ?)";
    $like = "%$search%"; $params[] = $like; $params[] = $like; $types .= 'ss';
}
if ($role_filter !== 'all') {
    $where_parts[] = "role = ?";
    $params[] = $role_filter; $types .= 's';
}
$where = implode(' AND ', $where_parts);

$count_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();
$pages = ceil($total / $per_page);

$users_stmt = $conn->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
if (!empty($params)) $users_stmt->bind_param($types, ...$params);
$users_stmt->execute();
$users_result = $users_stmt->get_result();
$users = [];
while ($row = $users_result->fetch_assoc()) $users[] = $row;
$users_stmt->close();

// Role counts
$role_counts = [];
$rc = $conn->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role");
while ($r = $rc->fetch_assoc()) $role_counts[$r['role']] = $r['c'];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-people text-danger me-2"></i>Manage Users</h2>
        <p class="text-muted mb-0"><?= $total ?> user<?= $total!=1?'s':'' ?> found</p>
    </div>
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary" target="_blank"><i class="bi bi-person-plus me-2"></i>Add User</a>
</div>
<?= render_flash() ?>

<!-- Role filter pills -->
<div class="mb-3 d-flex gap-2 flex-wrap">
    <a href="?role=all<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $role_filter==='all'?'btn-primary':'btn-outline-secondary' ?>">All (<?= array_sum($role_counts) ?>)</a>
    <?php foreach (['patient','clinician','pharmacist','insurer','admin'] as $r): ?>
    <a href="?role=<?= $r ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $role_filter===$r?'btn-primary':'btn-outline-secondary' ?>">
        <?= ucfirst($r) ?> (<?= $role_counts[$r] ?? 0 ?>)
    </a>
    <?php endforeach; ?>
</div>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="role" value="<?= $role_filter ?>">
            <input type="text" name="q" class="form-control" placeholder="Search by name or email..." value="<?= $search ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
            <?php if ($search): ?><a href="?role=<?= $role_filter ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a><?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Institution</th><th>Phone</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
            <tr><td colspan="9" class="text-center py-4 text-muted">No users found.</td></tr>
            <?php else: ?>
            <?php foreach ($users as $u): ?>
            <?php
            $rbadges = ['patient'=>'primary','clinician'=>'success','pharmacist'=>'info','insurer'=>'warning','admin'=>'danger'];
            $rb = $rbadges[$u['role']] ?? 'secondary';
            $is_self = (int)$u['user_id'] === (int)$_SESSION['user_id'];
            ?>
            <tr>
                <td class="text-muted">#<?= $u['user_id'] ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($u['full_name']) ?></td>
                <td><small><?= htmlspecialchars($u['email']) ?></small></td>
                <td><span class="badge bg-<?= $rb ?>"><?= ucfirst($u['role']) ?></span></td>
                <td><small><?= htmlspecialchars($u['institution'] ?? '—') ?></small></td>
                <td><small><?= htmlspecialchars($u['phone'] ?? '—') ?></small></td>
                <td>
                    <?= $u['is_active']
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>' ?>
                </td>
                <td><small><?= date('d M Y', strtotime($u['created_at'])) ?></small></td>
                <td>
                    <div class="d-flex gap-1">
                        <!-- Toggle Active -->
                        <?php if (!$is_self): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>"
                                    data-bs-toggle="tooltip" title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>-circle"></i>
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- Edit Role / Reset Password -->
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                                onclick="setEditUser(<?= $u['user_id'] ?>,'<?= $u['role'] ?>','<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <!-- Delete -->
                        <?php if (!$is_self): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    data-confirm="Permanently delete <?= htmlspecialchars($u['full_name']) ?>?">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3"><ul class="pagination justify-content-center">
<?php
$qs = http_build_query(array_filter(['q'=>$search,'role'=>$role_filter!=='all'?$role_filter:'']));
for ($i=1;$i<=$pages;$i++):
?>
<li class="page-item <?= $i==$page_num?'active':'' ?>"><a class="page-link" href="?<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a></li>
<?php endfor; ?>
</ul></nav>
<?php endif; ?>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalTitle"><i class="bi bi-pencil-square me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Change Role -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="target_user_id" id="editUserId">
                    <label class="form-label fw-semibold">Change Role</label>
                    <div class="input-group">
                        <select name="new_role" id="editUserRole" class="form-select">
                            <?php foreach (['patient','clinician','pharmacist','insurer','admin'] as $r): ?>
                            <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Save Role</button>
                    </div>
                </form>
                <hr>
                <!-- Reset Password -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="target_user_id" id="editUserId2">
                    <label class="form-label fw-semibold">Reset Password</label>
                    <div class="input-group">
                        <input type="password" name="new_password" class="form-control" placeholder="New password (min 8 chars)" minlength="8">
                        <button type="submit" class="btn btn-warning">Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function setEditUser(id, role, name) {
    document.getElementById('editModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit: ' + name;
    document.getElementById('editUserId').value  = id;
    document.getElementById('editUserId2').value = id;
    document.getElementById('editUserRole').value = role;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
