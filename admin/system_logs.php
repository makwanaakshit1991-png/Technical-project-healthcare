<?php
// ============================================================
//  admin/system_logs.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rbac.php';
require_login();
if ($_SESSION['role'] !== 'admin') { include __DIR__ . '/../includes/403.php'; exit; }

$page_title = 'System Logs';

$status_filter = $_GET['status'] ?? 'all';
$module_filter = htmlspecialchars($_GET['module'] ?? 'all');
$search        = htmlspecialchars(trim($_GET['q'] ?? ''));
$per_page      = 15;
$page_num      = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($page_num - 1) * $per_page;

$where_parts = ['1=1'];
$params = []; $types = '';

if ($status_filter !== 'all') {
    $where_parts[] = "sl.status = ?";
    $params[] = $status_filter; $types .= 's';
}
if ($module_filter !== 'all' && !empty($module_filter)) {
    $where_parts[] = "sl.module = ?";
    $params[] = $module_filter; $types .= 's';
}
if (!empty($search)) {
    $where_parts[] = "(sl.action LIKE ? OR u.full_name LIKE ?)";
    $like = "%$search%"; $params[] = $like; $params[] = $like; $types .= 'ss';
}
$where = implode(' AND ', $where_parts);

$count_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM system_logs sl LEFT JOIN users u ON sl.user_id=u.user_id WHERE $where");
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();
$pages = ceil($total / $per_page);

$logs_stmt = $conn->prepare("SELECT sl.*, u.full_name, u.role FROM system_logs sl LEFT JOIN users u ON sl.user_id=u.user_id WHERE $where ORDER BY sl.created_at DESC LIMIT $per_page OFFSET $offset");
if (!empty($params)) $logs_stmt->bind_param($types, ...$params);
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();
$logs = []; while ($row = $logs_result->fetch_assoc()) $logs[] = $row;
$logs_stmt->close();

// Unique modules for filter
$modules_r = $conn->query("SELECT DISTINCT module FROM system_logs WHERE module IS NOT NULL ORDER BY module");
$modules = []; while ($m = $modules_r->fetch_assoc()) $modules[] = $m['module'];

// Summary counts
$counts = [];
$rc = $conn->query("SELECT status, COUNT(*) AS c FROM system_logs GROUP BY status");
while ($r = $rc->fetch_assoc()) $counts[$r['status']] = $r['c'];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-journal-text text-primary me-2"></i>System Logs</h2>
        <p class="text-muted mb-0"><?= number_format($total) ?> log entries</p>
    </div>
    <a href="<?= BASE_URL ?>/modules/blockchain/audit_viewer.php" class="btn btn-outline-danger">
        <i class="bi bi-link-45deg me-2"></i>Blockchain Audit Trail
    </a>
</div>
<?= render_flash() ?>

<!-- Summary stat cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card bg-gradient-success">
            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
            <div><div class="stat-value"><?= number_format($counts['success'] ?? 0) ?></div><div class="stat-label">Success</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card bg-gradient-danger">
            <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
            <div><div class="stat-value"><?= number_format($counts['failure'] ?? 0) ?></div><div class="stat-label">Failures</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card bg-gradient-warning">
            <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div><div class="stat-value"><?= number_format($counts['warning'] ?? 0) ?></div><div class="stat-label">Warnings</div></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control" placeholder="Search action or user..." value="<?= $search ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="all" <?= $status_filter==='all'?'selected':'' ?>>All Statuses</option>
                    <option value="success"  <?= $status_filter==='success'?'selected':'' ?>>Success</option>
                    <option value="failure"  <?= $status_filter==='failure'?'selected':'' ?>>Failure</option>
                    <option value="warning"  <?= $status_filter==='warning'?'selected':'' ?>>Warning</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="module" class="form-select">
                    <option value="all">All Modules</option>
                    <?php foreach ($modules as $m): ?>
                    <option value="<?= $m ?>" <?= $module_filter===$m?'selected':'' ?>><?= ucfirst($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <a href="?" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Status</th><th>IP</th><th>Timestamp</th></tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">No logs found.</td></tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <?php
            $sc = ['success'=>'success','failure'=>'danger','warning'=>'warning'][$log['status']] ?? 'secondary';
            $rbadges = ['patient'=>'primary','clinician'=>'success','pharmacist'=>'info','insurer'=>'warning','admin'=>'danger'];
            $rb = $rbadges[$log['role'] ?? ''] ?? 'secondary';
            ?>
            <tr class="<?= $log['status']==='failure'?'table-danger opacity-75':($log['status']==='warning'?'table-warning opacity-75':'') ?>">
                <td class="text-muted"><?= $log['log_id'] ?></td>
                <td><?= htmlspecialchars($log['full_name'] ?? 'System') ?></td>
                <td><?php if ($log['role']): ?><span class="badge bg-<?= $rb ?>"><?= ucfirst($log['role']) ?></span><?php else: ?>—<?php endif; ?></td>
                <td class="text-truncate" style="max-width:220px"><?= htmlspecialchars($log['action']) ?></td>
                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['module'] ?? '—') ?></span></td>
                <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($log['status']) ?></span></td>
                <td><small class="font-monospace"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></small></td>
                <td><small><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></small></td>
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
$qs = http_build_query(array_filter(['q'=>$search,'status'=>$status_filter!=='all'?$status_filter:'','module'=>$module_filter!=='all'?$module_filter:'']));
for ($i=1;$i<=$pages;$i++):
?>
<li class="page-item <?= $i==$page_num?'active':'' ?>"><a class="page-link" href="?<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a></li>
<?php endfor; ?>
</ul></nav>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
