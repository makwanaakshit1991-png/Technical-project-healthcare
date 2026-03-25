<?php
// ============================================================
//  modules/lab_results/view_lab_results.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('lab_results', 'read');

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$page_title = 'Lab Results';
$per_page = 10; $page_num = max(1,(int)($_GET['page']??1)); $offset = ($page_num-1)*$per_page;

$where = '1=1';
if ($role === 'patient') {
    $r = $conn->query("SELECT patient_id FROM patients WHERE user_id=$uid LIMIT 1");
    $pid = (int)$r->fetch_assoc()['patient_id'];
    $where .= " AND lr.patient_id=$pid";
} elseif ($role === 'clinician') {
    $where .= " AND lr.ordered_by=$uid";
} elseif ($role === 'insurer') {
    // Only patients who have consented to insurer
    $where .= " AND EXISTS (SELECT 1 FROM consents c WHERE c.patient_id=lr.patient_id AND c.granted_to_user_id=$uid AND c.is_active=1 AND (c.expires_at IS NULL OR c.expires_at > NOW()) AND (c.record_type='all' OR c.record_type='lab_result'))";
}

$total = (int)$conn->query("SELECT COUNT(*) AS c FROM lab_results lr WHERE $where")->fetch_assoc()['c'];
$pages = ceil($total / $per_page);

$labs = [];
$r = $conn->query(
    "SELECT lr.*, u_p.full_name AS patient_name, u_c.full_name AS clinician_name
     FROM lab_results lr
     JOIN patients p ON lr.patient_id = p.patient_id
     JOIN users u_p ON p.user_id = u_p.user_id
     JOIN users u_c ON lr.ordered_by = u_c.user_id
     WHERE $where ORDER BY lr.result_date DESC, lr.created_at DESC LIMIT $per_page OFFSET $offset");
while ($row = $r->fetch_assoc()) $labs[] = $row;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-flask text-primary me-2"></i>Lab Results</h2>
        <p class="text-muted mb-0"><?= $total ?> result<?= $total!=1?'s':'' ?></p>
    </div>
    <?php if (in_array($role, ['clinician','admin'])): ?>
    <a href="<?= BASE_URL ?>/modules/lab_results/upload_lab_result.php" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Upload Result</a>
    <?php endif; ?>
</div>
<?= render_flash() ?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Patient</th><th>Test</th><th>Result</th><th>Unit</th><th>Normal Range</th><th>Lab</th><th>Date</th><th>Flag</th><th>Ordered By</th></tr>
            </thead>
            <tbody>
            <?php if (empty($labs)): ?>
            <tr><td colspan="10" class="text-center py-4 text-muted">No lab results found.</td></tr>
            <?php else: ?>
            <?php foreach ($labs as $lab): ?>
            <tr class="<?= $lab['is_abnormal'] ? 'table-danger' : '' ?>">
                <td>#<?= $lab['lab_id'] ?></td>
                <td><?= htmlspecialchars($lab['patient_name']) ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($lab['test_name']) ?></td>
                <td class="fw-bold <?= $lab['is_abnormal'] ? 'text-danger' : 'text-success' ?>"><?= htmlspecialchars($lab['result_value']) ?></td>
                <td><?= htmlspecialchars($lab['unit'] ?? '—') ?></td>
                <td><small><?= htmlspecialchars($lab['normal_range'] ?? '—') ?></small></td>
                <td><small><?= htmlspecialchars($lab['lab_name'] ?? '—') ?></small></td>
                <td><small><?= $lab['result_date'] ? date('d M Y', strtotime($lab['result_date'])) : '—' ?></small></td>
                <td>
                    <?= $lab['is_abnormal']
                        ? '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Abnormal</span>'
                        : '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Normal</span>' ?>
                </td>
                <td><small><?= htmlspecialchars($lab['clinician_name']) ?></small></td>
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
<?php for ($i=1;$i<=$pages;$i++): ?>
<li class="page-item <?= $i==$page_num?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
<?php endfor; ?>
</ul></nav>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
