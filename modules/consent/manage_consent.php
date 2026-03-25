<?php
// ============================================================
//  modules/consent/manage_consent.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../modules/blockchain/audit_logger.php';
require_login();
check_permission('consents', 'read');

$uid = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$page_title = 'Consent Management';

// Get patient_id for current user (if patient)
$pid = 0;
if ($role === 'patient') {
    $r = $conn->query("SELECT patient_id FROM patients WHERE user_id = $uid LIMIT 1");
    $pid = (int)$r->fetch_assoc()['patient_id'];
}

// Handle revoke
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_id'])) {
    if (!csrf_validate()) { flash('error', 'Invalid CSRF token.'); }
    else {
        $cid = (int)$_POST['revoke_id'];
        $stmt = $conn->prepare("UPDATE consents SET is_active=0, revoked_at=NOW() WHERE consent_id=? AND patient_id=?");
        $stmt->bind_param('ii', $cid, $pid); $stmt->execute(); $stmt->close();
        log_transaction('ConsentUpdate', $uid, null, ['action'=>'consent_revoked','consent_id'=>$cid], $_SERVER['REMOTE_ADDR'] ?? '');
        flash('success', 'Consent revoked.');
    }
    header('Location: ' . BASE_URL . '/modules/consent/manage_consent.php'); exit;
}

// Handle grant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grant_consent'])) {
    if (!csrf_validate()) { flash('error', 'Invalid CSRF token.'); }
    else {
        $granted_to = (int)$_POST['granted_to_user_id'];
        $record_type = htmlspecialchars($_POST['record_type'] ?? 'all');
        $institution = htmlspecialchars($_POST['institution'] ?? '');
        $purpose     = htmlspecialchars($_POST['purpose'] ?? '');
        $is_sensitive = isset($_POST['is_sensitive_consent']) ? 1 : 0;
        $expires_at  = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        if ($granted_to && $pid) {
            $stmt = $conn->prepare("INSERT INTO consents (patient_id, granted_to_user_id, record_type, institution, purpose, is_sensitive_consent, expires_at) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('iisssisi', $pid, $granted_to, $record_type, $institution, $purpose, $is_sensitive, $expires_at);
            $stmt->execute(); $cid_new = $conn->insert_id; $stmt->close();
            log_transaction('ConsentUpdate', $uid, null, ['action'=>'consent_granted','consent_id'=>$cid_new,'granted_to'=>$granted_to], $_SERVER['REMOTE_ADDR'] ?? '');
            flash('success', 'Consent granted successfully.');
        } else {
            flash('error', 'Invalid data.');
        }
    }
    header('Location: ' . BASE_URL . '/modules/consent/manage_consent.php'); exit;
}

// Get existing consents
$consents = [];
if ($pid) {
    $r = $conn->query(
        "SELECT c.*, u.full_name AS grantee_name, u.role AS grantee_role, u.institution AS grantee_institution
         FROM consents c JOIN users u ON c.granted_to_user_id = u.user_id
         WHERE c.patient_id = $pid ORDER BY c.granted_at DESC");
    while ($row = $r->fetch_assoc()) $consents[] = $row;
}

// Get users to grant consent to (clinicians, insurers, pharmacists)
$grantees = [];
$r = $conn->query("SELECT user_id, full_name, role, institution FROM users WHERE role IN ('clinician','insurer','pharmacist') AND is_active=1 ORDER BY role, full_name");
while ($row = $r->fetch_assoc()) $grantees[] = $row;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<?= render_flash() ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-shield-check text-primary me-2"></i>Consent Management</h2>
        <p class="text-muted mb-0">Control who can access your health records</p>
    </div>
    <?php if ($role === 'patient'): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#grantModal"><i class="bi bi-plus-circle me-2"></i>Grant New Consent</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><strong><i class="bi bi-list-check me-2"></i>Active & Past Consents</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Granted To</th><th>Role</th><th>Institution</th><th>Record Type</th><th>Purpose</th><th>Sensitive</th><th>Expires</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($consents)): ?>
            <tr><td colspan="9" class="text-center py-4 text-muted">No consents found.</td></tr>
            <?php else: ?>
            <?php foreach ($consents as $c): ?>
            <?php
            $is_active = $c['is_active'] && (!$c['expires_at'] || strtotime($c['expires_at']) > time());
            $rbadges = ['clinician'=>'success','pharmacist'=>'info','insurer'=>'warning'];
            $rb = $rbadges[$c['grantee_role']] ?? 'secondary';
            ?>
            <tr class="<?= !$is_active ? 'table-secondary opacity-75' : '' ?>">
                <td class="fw-semibold"><?= htmlspecialchars($c['grantee_name']) ?></td>
                <td><span class="badge bg-<?= $rb ?>"><?= ucfirst($c['grantee_role']) ?></span></td>
                <td><?= htmlspecialchars($c['grantee_institution'] ?? '—') ?></td>
                <td><code><?= htmlspecialchars($c['record_type'] ?? 'all') ?></code></td>
                <td class="text-truncate" style="max-width:160px"><?= htmlspecialchars($c['purpose'] ?? '—') ?></td>
                <td><?= $c['is_sensitive_consent'] ? '<span class="badge bg-danger"><i class="bi bi-lock-fill"></i> Yes</span>' : '<span class="text-muted">No</span>' ?></td>
                <td><small><?= $c['expires_at'] ? date('d M Y', strtotime($c['expires_at'])) : 'Never' ?></small></td>
                <td>
                    <?php if ($is_active): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                    <?php elseif ($c['revoked_at']): ?>
                    <span class="badge bg-secondary">Revoked</span>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark">Expired</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_active && $role === 'patient'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="revoke_id" value="<?= $c['consent_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Revoke this consent?">
                            <i class="bi bi-x-circle me-1"></i>Revoke
                        </button>
                    </form>
                    <?php else: ?>—<?php endif; ?>
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

<!-- Grant Consent Modal -->
<div class="modal fade" id="grantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="grant_consent" value="1">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield-plus me-2"></i>Grant Data Access Consent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Grant Access To *</label>
                        <select name="granted_to_user_id" class="form-select" required>
                            <option value="">— Select User —</option>
                            <?php foreach ($grantees as $g): ?>
                            <option value="<?= $g['user_id'] ?>"><?= htmlspecialchars($g['full_name']) ?> (<?= ucfirst($g['role']) ?><?= $g['institution'] ? ' — '.$g['institution'] : '' ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Record Type</label>
                        <select name="record_type" class="form-select">
                            <option value="all">All Records</option>
                            <?php foreach (['consultation','lab_result','prescription','imaging','discharge_summary','immunization'] as $t): ?>
                            <option value="<?= $t ?>"><?= ucfirst(str_replace('_',' ',$t)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Institution</label>
                        <input type="text" name="institution" class="form-control" placeholder="e.g. City General Hospital">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expires</label>
                        <input type="date" name="expires_at" class="form-control" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Purpose</label>
                        <input type="text" name="purpose" class="form-control" placeholder="e.g. Primary care treatment, insurance claim">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_sensitive_consent" class="form-check-input" id="sensitiveConsent">
                            <label class="form-check-label" for="sensitiveConsent">Include sensitive records (psychiatric, HIV, substance use)</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-warning mb-0 py-2">
                            <i class="bi bi-info-circle me-2"></i>By granting consent you authorise the selected user to access your specified health records.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-shield-check me-2"></i>Grant Consent</button>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
