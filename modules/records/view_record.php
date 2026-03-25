<?php
// ============================================================
//  modules/records/view_record.php
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
$rid  = (int)($_GET['id'] ?? 0);

if (!$rid) { flash('error', 'Invalid record ID.'); header('Location: ' . BASE_URL . '/modules/records/search_records.php'); exit; }

$stmt = $conn->prepare(
    "SELECT hr.*, u_c.full_name AS clinician_name, u_p.full_name AS patient_name, u_p.email AS patient_email
     FROM health_records hr
     JOIN users u_c ON hr.clinician_id = u_c.user_id
     JOIN patients p ON hr.patient_id = p.patient_id
     JOIN users u_p ON p.user_id = u_p.user_id
     WHERE hr.record_id = ? LIMIT 1");
$stmt->bind_param('i', $rid); $stmt->execute();
$record = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (!$record) { flash('error', 'Record not found.'); header('Location: ' . BASE_URL . '/modules/records/search_records.php'); exit; }

// Access control
if ($role === 'patient') {
    // Patient can only see their own records
    $my_pid_r = $conn->query("SELECT patient_id FROM patients WHERE user_id = $uid LIMIT 1");
    $my_pid   = (int)$my_pid_r->fetch_assoc()['patient_id'];
    if ($record['patient_id'] !== $my_pid) { include __DIR__ . '/../../includes/403.php'; exit; }
}

// Sensitive record check for non-patient, non-admin, non-clinician-owner
if ($record['is_sensitive'] && !in_array($role, ['admin'])) {
    $is_owner = ($role === 'clinician' && (int)$record['clinician_id'] === $uid);
    if (!$is_owner) {
        $patient_uid_r = $conn->query("SELECT user_id FROM patients WHERE patient_id = {$record['patient_id']} LIMIT 1");
        $patient_uid   = (int)$patient_uid_r->fetch_assoc()['user_id'];
        $is_patient    = ($role === 'patient' && $uid === $patient_uid);
        if (!$is_patient && !has_consent($record['patient_id'], $uid)) {
            http_response_code(403);
            include __DIR__ . '/../../includes/header.php';
            include __DIR__ . '/../../includes/navbar.php';
            echo '<div class="page-wrapper"><div class="alert alert-danger text-center p-5"><i class="bi bi-shield-x fs-1 d-block mb-3"></i><h4>Access Denied — Consent Required</h4><p>This is a sensitive record. Patient consent is required to view it.</p><a href="javascript:history.back()" class="btn btn-primary">Go Back</a></div></div>';
            include __DIR__ . '/../../includes/footer.php';
            exit;
        }
    }
}

// Insurer needs consent
if ($role === 'insurer' && !has_consent($record['patient_id'], $uid)) {
    http_response_code(403);
    include __DIR__ . '/../../includes/header.php';
    include __DIR__ . '/../../includes/navbar.php';
    echo '<div class="page-wrapper"><div class="alert alert-warning text-center p-5"><i class="bi bi-shield-exclamation fs-1 d-block mb-3"></i><h4>Consent Required</h4><p>The patient has not granted you access to this record.</p><a href="javascript:history.back()" class="btn btn-primary">Go Back</a></div></div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// Log access to blockchain
require_once __DIR__ . '/../../modules/blockchain/audit_logger.php';
log_transaction('AccessRequest', $uid, $rid, ['action' => 'record_viewed', 'record_id' => $rid, 'accessor_role' => $role], $_SERVER['REMOTE_ADDR'] ?? '');

$page_title = 'Health Record #' . $rid;
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<?= render_flash() ?>
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <div class="flex-grow-1">
        <h2 class="fw-bold mb-0"><?= htmlspecialchars($record['title']) ?></h2>
        <p class="text-muted mb-0">Health Record #<?= $rid ?></p>
    </div>
    <?php if (in_array($role, ['clinician','admin']) && ((int)$record['clinician_id'] === $uid || $role === 'admin')): ?>
    <a href="<?= BASE_URL ?>/modules/records/update_record.php?id=<?= $rid ?>" class="btn btn-outline-primary">
        <i class="bi bi-pencil me-2"></i>Edit
    </a>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><strong><i class="bi bi-file-medical-fill me-2 text-primary"></i>Record Details</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Patient</label>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($record['patient_name']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Clinician</label>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($record['clinician_name']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Record Type</label>
                        <p class="mb-0"><span class="badge bg-info text-dark"><?= ucfirst(str_replace('_',' ',$record['record_type'])) ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">ICD-10 Code</label>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($record['icd_code'] ?? '—') ?></p>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small">Diagnosis</label>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($record['diagnosis'] ?? '—') ?></p>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small">Description / Notes</label>
                        <div class="bg-light rounded p-3"><?= nl2br(htmlspecialchars($record['description'] ?? 'No description.')) ?></div>
                    </div>
                    <?php if ($record['is_sensitive']): ?>
                    <div class="col-12">
                        <span class="badge bg-danger"><i class="bi bi-lock-fill me-1"></i>Sensitive Record</span>
                        <small class="text-muted ms-2">This record has enhanced privacy protection.</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong><i class="bi bi-link-45deg me-2 text-primary"></i>FHIR Resource Hash (Blockchain)</strong></div>
            <div class="card-body">
                <code class="small text-break"><?= htmlspecialchars($record['fhir_resource_hash'] ?? 'N/A') ?></code>
                <p class="text-muted small mt-2 mb-0">SHA-256 hash of the FHIR-normalised record payload. Any modification to this record will invalidate this hash.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><strong>Timestamps</strong></div>
            <div class="card-body">
                <p class="mb-1 small"><span class="text-muted">Created:</span> <?= date('d M Y H:i', strtotime($record['created_at'])) ?></p>
                <p class="mb-0 small"><span class="text-muted">Updated:</span> <?= date('d M Y H:i', strtotime($record['updated_at'])) ?></p>
            </div>
        </div>
        <div class="card">
            <div class="card-body text-center p-4">
                <i class="bi bi-shield-check fs-1 text-success d-block mb-2"></i>
                <div class="fw-bold">Record Integrity</div>
                <small class="text-muted">This access has been logged to the blockchain audit trail</small>
            </div>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
