<?php
// ============================================================
//  admin/manage_roles.php  —  Role Permission Editor
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rbac.php';
require_login();
if ($_SESSION['role'] !== 'admin') { include __DIR__ . '/../includes/403.php'; exit; }

$page_title = 'Role Permissions';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) { flash('error', 'Invalid CSRF token.'); header('Location: ' . BASE_URL . '/admin/manage_roles.php'); exit; }

    $roles   = ['patient','clinician','pharmacist','insurer','admin'];
    $modules = ['health_records','prescriptions','lab_results','appointments','consents','insurance_claims','messages','ai_predictions','blockchain_audit','users','vital_signs'];
    $cols    = ['can_read','can_write','can_delete','can_approve'];

    // Delete all and re-insert from form
    $conn->query("DELETE FROM role_permissions");

    foreach ($roles as $role) {
        foreach ($modules as $module) {
            $read    = isset($_POST["perm_{$role}_{$module}_read"])    ? 1 : 0;
            $write   = isset($_POST["perm_{$role}_{$module}_write"])   ? 1 : 0;
            $delete  = isset($_POST["perm_{$role}_{$module}_delete"])  ? 1 : 0;
            $approve = isset($_POST["perm_{$role}_{$module}_approve"]) ? 1 : 0;

            $stmt = $conn->prepare("INSERT INTO role_permissions (role, module, can_read, can_write, can_delete, can_approve) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('ssiiii', $role, $module, $read, $write, $delete, $approve);
            $stmt->execute(); $stmt->close();
        }
    }
    flash('success', 'Permissions saved successfully.');
    header('Location: ' . BASE_URL . '/admin/manage_roles.php'); exit;
}

// Load current permissions
$perms = [];
$r = $conn->query("SELECT * FROM role_permissions");
while ($row = $r->fetch_assoc()) {
    $perms[$row['role']][$row['module']] = $row;
}

$roles   = ['patient','clinician','pharmacist','insurer','admin'];
$modules = ['health_records','prescriptions','lab_results','appointments','consents','insurance_claims','messages','ai_predictions','blockchain_audit','users','vital_signs'];
$cols    = ['read','write','delete','approve'];
$role_colors = ['patient'=>'primary','clinician'=>'success','pharmacist'=>'info','insurer'=>'warning','admin'=>'danger'];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-shield-lock text-danger me-2"></i>Role Permission Matrix</h2>
        <p class="text-muted mb-0">Configure what each role can do in each module</p>
    </div>
</div>
<?= render_flash() ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <!-- Nav tabs per role -->
    <ul class="nav nav-tabs mb-0" id="roleTabs">
        <?php foreach ($roles as $i => $role): ?>
        <li class="nav-item">
            <a class="nav-link <?= $i===0?'active':'' ?>" data-bs-toggle="tab" href="#tab_<?= $role ?>">
                <span class="badge bg-<?= $role_colors[$role] ?> me-1"><?= ucfirst($role) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach ($roles as $i => $role): ?>
        <div class="tab-pane fade <?= $i===0?'show active':'' ?>" id="tab_<?= $role ?>">
            <div class="card" style="border-top-left-radius:0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:200px">Module</th>
                                <th class="text-center">Read</th>
                                <th class="text-center">Write</th>
                                <th class="text-center">Delete</th>
                                <th class="text-center">Approve</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($modules as $module): ?>
                        <?php
                        $p = $perms[$role][$module] ?? [];
                        $is_admin = ($role === 'admin');
                        ?>
                        <tr>
                            <td class="fw-semibold">
                                <code><?= str_replace('_',' ', $module) ?></code>
                            </td>
                            <?php foreach ($cols as $col): ?>
                            <td class="text-center">
                                <div class="form-check d-flex justify-content-center">
                                    <input type="checkbox"
                                           name="perm_<?= $role ?>_<?= $module ?>_<?= $col ?>"
                                           class="form-check-input"
                                           <?= !empty($p['can_'.$col]) ? 'checked' : '' ?>
                                           <?= $is_admin ? 'checked disabled' : '' ?>>
                                    <?php if ($is_admin): ?>
                                    <input type="hidden" name="perm_<?= $role ?>_<?= $module ?>_<?= $col ?>" value="on">
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-danger px-4">
            <i class="bi bi-save me-2"></i>Save All Permissions
        </button>
        <small class="text-muted ms-3"><i class="bi bi-info-circle me-1"></i>Admin always retains full access regardless of settings.</small>
    </div>
</form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
