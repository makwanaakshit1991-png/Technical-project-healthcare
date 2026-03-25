<?php
// ============================================================
//  modules/insurance/view_claims.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('insurance_claims', 'read');

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$page_title = 'Insurance Claims';
$per_page = 10; $page_num = max(1,(int)($_GET['page']??1)); $offset = ($page_num-1)*$per_page;

$where = '1=1';
if ($role === 'patient') {
    $r = $conn->query("SELECT patient_id FROM patients WHERE user_id=$uid LIMIT 1");
    $pid = (int)$r->fetch_assoc()['patient_id'];
    $where .= " AND ic.patient_id=$pid";
} elseif ($role === 'insurer') {
    $where .= " AND ic.insurer_id=$uid";
}

$total = (int)$conn->query("SELECT COUNT(*) AS c FROM insurance_claims ic WHERE $where")->fetch_assoc()['c'];
$pages = ceil($total / $per_page);

$claims = [];
$r = $conn->query(
    "SELECT ic.*, u_p.full_name AS patient_name, u_i.full_name AS insurer_name, hr.title AS record_title
     FROM insurance_claims ic
     JOIN patients p ON ic.patient_id = p.patient_id
     JOIN users u_p ON p.user_id = u_p.user_id
     JOIN users u_i ON ic.insurer_id = u_i.user_id
     LEFT JOIN health_records hr ON ic.record_id = hr.record_id
     WHERE $where ORDER BY ic.submitted_at DESC LIMIT $per_page OFFSET $offset");
while ($row = $r->fetch_assoc()) $claims[] = $row;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-receipt text-primary me-2"></i>Insurance Claims</h2>
        <p class="text-muted mb-0"><?= $total ?> claim<?= $total!=1?'s':'' ?></p>
    </div>
    <?php if (in_array($role, ['patient','clinician','admin'])): ?>
    <a href="<?= BASE_URL ?>/modules/insurance/submit_claim.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Submit Claim</a>
    <?php endif; ?>
</div>
<?= render_flash() ?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Patient</th><th>Insurer</th><th>Record</th><th>Amount</th><th>Description</th><th>Status</th><th>Submitted</th><th>Remarks</th></tr>
            </thead>
            <tbody>
            <?php if (empty($claims)): ?>
            <tr><td colspan="9" class="text-center py-4 text-muted">No claims found.</td></tr>
            <?php else: ?>
            <?php foreach ($claims as $c): ?>
            <?php $sbadge=['submitted'=>'secondary','under_review'=>'warning','approved'=>'success','rejected'=>'danger']; $sc=$sbadge[$c['status']]??'secondary'; ?>
            <tr>
                <td>#<?= $c['claim_id'] ?></td>
                <td><?= htmlspecialchars($c['patient_name']) ?></td>
                <td><?= htmlspecialchars($c['insurer_name']) ?></td>
                <td>
                    <?php if ($c['record_id']): ?>
                    <a href="<?= BASE_URL ?>/modules/records/view_record.php?id=<?= $c['record_id'] ?>" class="text-decoration-none small">
                        <?= htmlspecialchars($c['record_title'] ?? 'Record #'.$c['record_id']) ?>
                    </a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="fw-bold">₹<?= number_format($c['claim_amount'], 2) ?></td>
                <td class="text-truncate" style="max-width:160px"><small><?= htmlspecialchars($c['description'] ?? '—') ?></small></td>
                <td><span class="badge bg-<?= $sc ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span></td>
                <td><small><?= date('d M Y', strtotime($c['submitted_at'])) ?></small></td>
                <td class="text-truncate" style="max-width:120px"><small><?= htmlspecialchars($c['remarks'] ?? '—') ?></small></td>
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
