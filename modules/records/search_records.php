<?php
// ============================================================
//  modules/records/search_records.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('health_records', 'read');

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$page_title = 'Search Health Records';

$search    = htmlspecialchars(trim($_GET['q'] ?? ''));
$icd_filter= htmlspecialchars(trim($_GET['icd'] ?? ''));
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? '';
$per_page  = 10;
$page_num  = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page_num - 1) * $per_page;

// Build query based on role
$where_parts = ['1=1'];
$params      = [];
$types       = '';

if ($role === 'patient') {
    $pid_r = $conn->query("SELECT patient_id FROM patients WHERE user_id = $uid LIMIT 1");
    $pid   = (int)$pid_r->fetch_assoc()['patient_id'];
    $where_parts[] = "hr.patient_id = ?";
    $params[] = $pid; $types .= 'i';
}

if (!empty($search)) {
    $where_parts[] = "(u_p.full_name LIKE ? OR hr.diagnosis LIKE ? OR hr.title LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if (!empty($icd_filter)) {
    $where_parts[] = "hr.icd_code LIKE ?";
    $params[] = "%$icd_filter%"; $types .= 's';
}
if (!empty($date_from)) {
    $where_parts[] = "DATE(hr.created_at) >= ?";
    $params[] = $date_from; $types .= 's';
}
if (!empty($date_to)) {
    $where_parts[] = "DATE(hr.created_at) <= ?";
    $params[] = $date_to; $types .= 's';
}

$where = implode(' AND ', $where_parts);

// Count
$count_sql = "SELECT COUNT(*) AS c FROM health_records hr JOIN patients p ON hr.patient_id = p.patient_id JOIN users u_p ON p.user_id = u_p.user_id WHERE $where";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();
$pages = ceil($total / $per_page);

// Fetch
$sql = "SELECT hr.*, u_p.full_name AS patient_name, u_c.full_name AS clinician_name
        FROM health_records hr
        JOIN patients p ON hr.patient_id = p.patient_id
        JOIN users u_p ON p.user_id = u_p.user_id
        JOIN users u_c ON hr.clinician_id = u_c.user_id
        WHERE $where
        ORDER BY hr.created_at DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$records_result = $stmt->get_result();
$records = [];
while ($r = $records_result->fetch_assoc()) $records[] = $r;
$stmt->close();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-search text-primary me-2"></i>Health Records</h2>
        <p class="text-muted mb-0"><?= $total ?> record<?= $total != 1 ? 's' : '' ?> found</p>
    </div>
    <?php if (in_array($role, ['clinician','admin'])): ?>
    <a href="<?= BASE_URL ?>/modules/records/create_record.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>New Record</a>
    <?php endif; ?>
</div>
<?= render_flash() ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Patient name, diagnosis, title..." value="<?= $search ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">ICD Code</label>
                <input type="text" name="icd" class="form-control" placeholder="e.g. E11" value="<?= $icd_filter ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i></button>
                <a href="?" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Patient</th><th>Type</th><th>Title</th><th>Diagnosis</th><th>ICD</th><th>Clinician</th><th>Sensitive</th><th>Created</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="10" class="text-center py-4 text-muted">No records found.</td></tr>
            <?php else: ?>
            <?php foreach ($records as $rec): ?>
            <tr>
                <td class="text-muted">#<?= $rec['record_id'] ?></td>
                <td><?= htmlspecialchars($rec['patient_name']) ?></td>
                <td><span class="badge bg-info text-dark"><?= ucfirst(str_replace('_',' ',$rec['record_type'])) ?></span></td>
                <td class="text-truncate" style="max-width:160px"><?= htmlspecialchars($rec['title']) ?></td>
                <td class="text-truncate" style="max-width:130px"><?= htmlspecialchars($rec['diagnosis'] ?? '—') ?></td>
                <td><code><?= htmlspecialchars($rec['icd_code'] ?? '—') ?></code></td>
                <td><?= htmlspecialchars($rec['clinician_name']) ?></td>
                <td><?= $rec['is_sensitive'] ? '<span class="badge bg-danger"><i class="bi bi-lock-fill"></i></span>' : '<span class="text-muted">—</span>' ?></td>
                <td><small><?= date('d M Y', strtotime($rec['created_at'])) ?></small></td>
                <td>
                    <a href="<?= BASE_URL ?>/modules/records/view_record.php?id=<?= $rec['record_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                    <?php if (in_array($role, ['clinician','admin'])): ?>
                    <a href="<?= BASE_URL ?>/modules/records/update_record.php?id=<?= $rec['record_id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <?php endif; ?>
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
$qs = http_build_query(array_filter(['q'=>$search,'icd'=>$icd_filter,'date_from'=>$date_from,'date_to'=>$date_to]));
for ($i = 1; $i <= $pages; $i++):
?>
<li class="page-item <?= $i == $page_num ? 'active' : '' ?>"><a class="page-link" href="?<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a></li>
<?php endfor; ?>
</ul></nav>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
