<?php
// ============================================================
//  dashboards/admin_dashboard.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rbac.php';
require_login();
if ($_SESSION['role'] !== 'admin') { include __DIR__ . '/../includes/403.php'; exit; }

$page_title = 'Admin Dashboard';

// Stats
$stats = [];
foreach ([
    'users'               => 'Total Users',
    'patients'            => 'Patients',
    'health_records'      => 'Health Records',
    'appointments'        => 'Appointments',
    'prescriptions'       => 'Prescriptions',
    'insurance_claims'    => 'Claims',
    'blockchain_audit_log'=> 'Audit Entries',
    'system_logs'         => 'System Logs',
] as $table => $label) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM $table");
    $stats[$label] = (int)$r->fetch_assoc()['c'];
}

// Recent system logs
$sys_logs = [];
$r = $conn->query(
    "SELECT sl.*, u.full_name FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.user_id
     ORDER BY sl.created_at DESC LIMIT 10");
while ($row = $r->fetch_assoc()) $sys_logs[] = $row;

// Recent users
$recent_users = [];
$r = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 8");
while ($row = $r->fetch_assoc()) $recent_users[] = $row;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="page-wrapper">
<?= render_flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-shield-lock text-danger me-2"></i>Admin Dashboard</h2>
        <p class="text-muted mb-0">System overview — <?= date('l, d F Y') ?></p>
    </div>
    <a href="<?= BASE_URL ?>/admin/manage_users.php" class="btn btn-danger"><i class="bi bi-people me-2"></i>Manage Users</a>
</div>

<!-- System Stats Grid -->
<div class="row g-3 mb-4">
    <?php
    $icons = ['Total Users'=>'people-fill','Patients'=>'person-heart','Health Records'=>'file-medical','Appointments'=>'calendar3','Prescriptions'=>'capsule','Claims'=>'receipt','Audit Entries'=>'link-45deg','System Logs'=>'journal-text'];
    $colors = ['Total Users'=>'primary','Patients'=>'success','Health Records'=>'info','Appointments'=>'warning','Prescriptions'=>'success','Claims'=>'danger','Audit Entries'=>'purple','System Logs'=>'secondary'];
    foreach ($stats as $label => $count):
        $icon  = $icons[$label] ?? 'grid';
        $color = $colors[$label] ?? 'primary';
    ?>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-gradient-<?= $color ?>">
            <div class="stat-icon"><i class="bi bi-<?= $icon ?>"></i></div>
            <div><div class="stat-value"><?= $count ?></div><div class="stat-label"><?= $label ?></div></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Quick Links -->
<div class="row g-3 mb-4">
    <?php
    $quick = [
        ['Manage Users',       'people',      'admin/manage_users.php',             'primary'],
        ['Role Permissions',   'shield-check', 'admin/manage_roles.php',            'info'],
        ['System Logs',        'journal-text', 'admin/system_logs.php',             'secondary'],
        ['Blockchain Audit',   'link-45deg',   'modules/blockchain/audit_viewer.php','danger'],
        ['All Health Records', 'file-medical', 'modules/records/search_records.php','success'],
        ['AI Predictions',     'robot',        'modules/records/search_records.php','warning'],
    ];
    foreach ($quick as [$label, $icon, $path, $color]):
    ?>
    <div class="col-6 col-md-2">
        <a href="<?= BASE_URL ?>/<?= $path ?>" class="btn btn-outline-<?= $color ?> w-100 py-3 d-flex flex-column align-items-center gap-1 text-decoration-none">
            <i class="bi bi-<?= $icon ?> fs-3"></i>
            <small class="fw-semibold"><?= $label ?></small>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Recent Users -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="section-title mb-0"><i class="bi bi-person-plus me-2"></i>Recent Users</span>
                <a href="<?= BASE_URL ?>/admin/manage_users.php" class="btn btn-sm btn-outline-primary">Manage All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Name</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_users as $u): ?>
                    <?php
                    $rbadges = ['patient'=>'primary','clinician'=>'success','pharmacist'=>'info','insurer'=>'warning','admin'=>'danger'];
                    $rb = $rbadges[$u['role']] ?? 'secondary';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($u['full_name']) ?><br><small class="text-muted"><?= htmlspecialchars($u['email']) ?></small></td>
                        <td><span class="badge bg-<?= $rb ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td><?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        <td><small><?= date('d M Y', strtotime($u['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent System Logs -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="section-title mb-0"><i class="bi bi-journal-text me-2"></i>Recent System Logs</span>
                <a href="<?= BASE_URL ?>/admin/system_logs.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>User</th><th>Action</th><th>Module</th><th>Status</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($sys_logs as $log): ?>
                    <?php $sc = ['success'=>'success','failure'=>'danger','warning'=>'warning'][$log['status']] ?? 'secondary'; ?>
                    <tr>
                        <td><?= htmlspecialchars($log['full_name'] ?? 'System') ?></td>
                        <td class="text-truncate" style="max-width:160px"><?= htmlspecialchars($log['action']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['module'] ?? '—') ?></span></td>
                        <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($log['status']) ?></span></td>
                        <td><small><?= date('d M H:i', strtotime($log['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
