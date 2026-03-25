<?php
// ============================================================
//  dashboards/insurer_dashboard.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rbac.php';
require_login();
if ($_SESSION['role'] !== 'insurer' && $_SESSION['role'] !== 'admin') {
    include __DIR__ . '/../includes/403.php'; exit;
}

$uid = (int)$_SESSION['user_id'];
$page_title = 'Insurer Dashboard';

// Handle claim review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_action'])) {
    if (!csrf_validate()) { flash('error', 'Invalid CSRF token.'); }
    else {
        $claim_id = (int)$_POST['claim_id'];
        $action   = $_POST['claim_action'] === 'approve' ? 'approved' : 'rejected';
        $remarks  = htmlspecialchars($_POST['remarks'] ?? '');
        $stmt = $conn->prepare("UPDATE insurance_claims SET status=?, remarks=?, reviewed_at=NOW() WHERE claim_id=? AND insurer_id=?");
        $stmt->bind_param('ssii', $action, $remarks, $claim_id, $uid);
        $stmt->execute(); $stmt->close();
        flash('success', "Claim #$claim_id has been $action.");
    }
    header('Location: ' . BASE_URL . '/dashboards/insurer_dashboard.php');
    exit;
}

// Claims filter
$filter_status = $_GET['status'] ?? 'all';
$where_status  = ($filter_status !== 'all') ? "AND ic.status = '$filter_status'" : '';

$claims = [];
$stmt = $conn->query(
    "SELECT ic.*, u_p.full_name AS patient_name, hr.title AS record_title, hr.record_type
     FROM insurance_claims ic
     JOIN patients p ON ic.patient_id = p.patient_id
     JOIN users u_p ON p.user_id = u_p.user_id
     LEFT JOIN health_records hr ON ic.record_id = hr.record_id
     WHERE ic.insurer_id = $uid $where_status
     ORDER BY ic.submitted_at DESC LIMIT 30"
);
while ($r = $stmt->fetch_assoc()) {
    // Check consent
    $has = has_consent($r['patient_id'], $uid, 'all') || has_consent($r['patient_id'], $uid, 'insurance_claims');
    $r['has_consent'] = $has;
    $claims[] = $r;
}

// Counts by status
$count_q = $conn->query("SELECT status, COUNT(*) AS c FROM insurance_claims WHERE insurer_id=$uid GROUP BY status");
$counts  = ['submitted'=>0,'under_review'=>0,'approved'=>0,'rejected'=>0];
while ($r = $count_q->fetch_assoc()) $counts[$r['status']] = $r['c'];
$total = array_sum($counts);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="page-wrapper">
<?= render_flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Insurer Dashboard</h2>
        <p class="text-muted mb-0"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card bg-gradient-primary">
            <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            <div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Claims</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-gradient-warning">
            <div class="stat-icon"><i class="bi bi-hourglass"></i></div>
            <div><div class="stat-value"><?= $counts['submitted'] + $counts['under_review'] ?></div><div class="stat-label">Pending Review</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-gradient-success">
            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
            <div><div class="stat-value"><?= $counts['approved'] ?></div><div class="stat-label">Approved</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-gradient-danger">
            <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
            <div><div class="stat-value"><?= $counts['rejected'] ?></div><div class="stat-label">Rejected</div></div>
        </div>
    </div>
</div>

<!-- Filter tabs -->
<div class="card">
    <div class="card-header">
        <ul class="nav nav-pills gap-1">
            <?php foreach (['all'=>'All','submitted'=>'Submitted','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected'] as $val => $lbl): ?>
            <li class="nav-item">
                <a class="nav-link <?= $filter_status === $val ? 'active' : '' ?>" href="?status=<?= $val ?>">
                    <?= $lbl ?>
                    <?php if ($val !== 'all' && isset($counts[$val])): ?>
                    <span class="badge bg-secondary ms-1"><?= $counts[$val] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Patient</th><th>Record</th><th>Amount</th><th>Description</th><th>Status</th><th>Submitted</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if (empty($claims)): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">No claims found.</td></tr>
            <?php else: ?>
            <?php foreach ($claims as $c): ?>
            <?php
            $sbadge = ['submitted'=>'secondary','under_review'=>'warning','approved'=>'success','rejected'=>'danger'];
            $sc = $sbadge[$c['status']] ?? 'secondary';
            ?>
            <tr>
                <td>#<?= $c['claim_id'] ?></td>
                <td>
                    <?= htmlspecialchars($c['patient_name']) ?>
                    <?php if (!$c['has_consent']): ?>
                    <br><span class="badge bg-warning text-dark"><i class="bi bi-shield-x me-1"></i>No Consent</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['record_id'] && $c['has_consent']): ?>
                    <a href="<?= BASE_URL ?>/modules/records/view_record.php?id=<?= $c['record_id'] ?>" class="text-decoration-none">
                        <?= htmlspecialchars($c['record_title'] ?? 'Record #'.$c['record_id']) ?>
                    </a>
                    <?php elseif (!$c['has_consent']): ?>
                    <span class="text-muted small">Access Denied — Consent Required</span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="fw-bold">₹<?= number_format($c['claim_amount'], 2) ?></td>
                <td class="text-truncate" style="max-width:180px"><?= htmlspecialchars($c['description'] ?? '') ?></td>
                <td><span class="badge bg-<?= $sc ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span></td>
                <td><small><?= date('d M Y', strtotime($c['submitted_at'])) ?></small></td>
                <td>
                    <?php if (in_array($c['status'], ['submitted','under_review']) && $c['has_consent']): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reviewModal"
                            onclick="setClaimReview(<?= $c['claim_id'] ?>,'<?= htmlspecialchars($c['patient_name']) ?>')">
                        <i class="bi bi-eye me-1"></i>Review
                    </button>
                    <?php elseif (!$c['has_consent']): ?>
                    <span class="text-muted small">Consent required</span>
                    <?php else: ?>
                    <span class="text-muted small"><?= ucfirst($c['status']) ?></span>
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
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="claim_id" id="modalClaimId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Review Claim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Reviewing claim for: <strong id="modalPatientName"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Add review notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="claim_action" value="reject" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Reject</button>
                <button type="submit" name="claim_action" value="approve" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Approve</button>
            </div>
        </form>
    </div>
</div>
<script>
function setClaimReview(id, name) {
    document.getElementById('modalClaimId').value = id;
    document.getElementById('modalPatientName').textContent = name;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
