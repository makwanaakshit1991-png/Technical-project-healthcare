<?php
// ============================================================
//  modules/prescriptions/view_prescriptions.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('prescriptions', 'read');

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$page_title = 'Prescriptions';

$per_page = 10; $page_num = max(1,(int)($_GET['page']??1)); $offset = ($page_num-1)*$per_page;

$where = '1=1';
if ($role === 'patient') {
    $r = $conn->query("SELECT patient_id FROM patients WHERE user_id=$uid LIMIT 1");
    $pid = (int)$r->fetch_assoc()['patient_id'];
    $where .= " AND pr.patient_id=$pid";
} elseif ($role === 'clinician') {
    $where .= " AND pr.prescribing_clinician_id=$uid";
}

// Auto-expire old prescriptions
$conn->query("UPDATE prescriptions SET status='expired' WHERE status='active' AND DATEDIFF(NOW(), created_at) > duration_days");

$total = (int)$conn->query("SELECT COUNT(*) AS c FROM prescriptions pr WHERE $where")->fetch_assoc()['c'];
$pages = ceil($total / $per_page);

$rxs = [];
$r = $conn->query(
    "SELECT pr.*, u_p.full_name AS patient_name, u_c.full_name AS clinician_name,
            u_d.full_name AS dispenser_name
     FROM prescriptions pr
     JOIN patients p ON pr.patient_id = p.patient_id
     JOIN users u_p ON p.user_id = u_p.user_id
     JOIN users u_c ON pr.prescribing_clinician_id = u_c.user_id
     LEFT JOIN users u_d ON pr.dispensed_by = u_d.user_id
     WHERE $where ORDER BY pr.created_at DESC LIMIT $per_page OFFSET $offset");
while ($row = $r->fetch_assoc()) $rxs[] = $row;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-capsule text-primary me-2"></i>Prescriptions</h2>
        <p class="text-muted mb-0"><?= $total ?> prescription<?= $total!=1?'s':'' ?></p>
    </div>
    <?php if (in_array($role, ['clinician','admin'])): ?>
    <a href="<?= BASE_URL ?>/modules/prescriptions/add_prescription.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Add Prescription</a>
    <?php endif; ?>
</div>
<?= render_flash() ?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Patient</th><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Days</th><th>Prescribed By</th><th>Status</th><th>Dispensed</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php if (empty($rxs)): ?>
            <tr><td colspan="10" class="text-center py-4 text-muted">No prescriptions found.</td></tr>
            <?php else: ?>
            <?php foreach ($rxs as $rx): ?>
            <?php
            $sbadge = ['active'=>'primary','dispensed'=>'success','cancelled'=>'danger','expired'=>'secondary'];
            $sc = $sbadge[$rx['status']] ?? 'secondary';
            ?>
            <tr>
                <td>#<?= $rx['prescription_id'] ?></td>
                <td><?= htmlspecialchars($rx['patient_name']) ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($rx['medication_name']) ?></td>
                <td><?= htmlspecialchars($rx['dosage']) ?></td>
                <td><?= htmlspecialchars($rx['frequency']) ?></td>
                <td><?= $rx['duration_days'] ?></td>
                <td><?= htmlspecialchars($rx['clinician_name']) ?></td>
                <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($rx['status']) ?></span></td>
                <td>
                    <?php if ($rx['dispensed_by']): ?>
                    <span class="text-success small"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($rx['dispenser_name']) ?></span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td><small><?= date('d M Y', strtotime($rx['created_at'])) ?></small></td>
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
