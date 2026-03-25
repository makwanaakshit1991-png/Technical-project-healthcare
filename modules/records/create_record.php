<?php
// ============================================================
//  modules/records/create_record.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('health_records', 'write');

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$page_title = 'Create Health Record';

// Handle quick vitals from clinician dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_vitals'])) {
    if (!csrf_validate()) { flash('error', 'Invalid token.'); header('Location: ' . BASE_URL . '/dashboards/clinician_dashboard.php'); exit; }
    $pid  = (int)($_POST['qv_patient_id'] ?? 0);
    $hr   = (int)($_POST['qv_hr'] ?? 0) ?: null;
    $spo2 = (float)($_POST['qv_spo2'] ?? 0) ?: null;
    $map  = (float)($_POST['qv_map'] ?? 0) ?: null;
    $temp = (float)($_POST['qv_temp'] ?? 0) ?: null;
    if ($pid) {
        $stmt = $conn->prepare("INSERT INTO vital_signs (patient_id, recorded_by, heart_rate, spo2, mean_arterial_pressure, temperature) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('iiiddd', $pid, $uid, $hr, $spo2, $map, $temp);
        $stmt->execute(); $stmt->close();
        flash('success', 'Vital signs recorded.');
    }
    header('Location: ' . BASE_URL . '/dashboards/clinician_dashboard.php'); exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['quick_vitals'])) {
    if (!csrf_validate()) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $patient_id  = (int)($_POST['patient_id'] ?? 0);
        $record_type = $_POST['record_type'] ?? '';
        $title       = htmlspecialchars(trim($_POST['title'] ?? ''));
        $description = htmlspecialchars(trim($_POST['description'] ?? ''));
        $diagnosis   = htmlspecialchars(trim($_POST['diagnosis'] ?? ''));
        $icd_code    = htmlspecialchars(trim($_POST['icd_code'] ?? ''));
        $is_sensitive = isset($_POST['is_sensitive']) ? 1 : 0;

        $valid_types = ['consultation','lab_result','prescription','imaging','discharge_summary','immunization'];
        if (!$patient_id)                      $errors[] = 'Patient is required.';
        if (!in_array($record_type, $valid_types)) $errors[] = 'Invalid record type.';
        if (empty($title))                     $errors[] = 'Title is required.';

        if (empty($errors)) {
            $fhir_hash = hash('sha256', $patient_id . $record_type . $diagnosis . date('Y-m-d H:i:s'));
            $stmt = $conn->prepare(
                "INSERT INTO health_records (patient_id, clinician_id, record_type, title, description, diagnosis, icd_code, fhir_resource_hash, is_sensitive)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('iisssssi', $patient_id, $uid, $record_type, $title, $description, $diagnosis, $icd_code, $fhir_hash, $is_sensitive);
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                $stmt->close();

                // Blockchain log
                require_once __DIR__ . '/../../modules/blockchain/audit_logger.php';
                log_transaction('RecordCreate', $uid, $new_id, [
                    'action' => 'record_create', 'patient_id' => $patient_id,
                    'record_type' => $record_type, 'fhir_hash' => $fhir_hash,
                ], $_SERVER['REMOTE_ADDR'] ?? '');

                // System log
                $act = "Created health record #$new_id";
                $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
                $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $ls = $conn->prepare("INSERT INTO system_logs (user_id, action, module, status, ip_address, user_agent) VALUES (?, ?, 'health_records', 'success', ?, ?)");
                $ls->bind_param('isss', $uid, $act, $ip, $ua); $ls->execute(); $ls->close();

                flash('success', "Health record #$new_id created successfully.");
                header('Location: ' . BASE_URL . '/modules/records/view_record.php?id=' . $new_id); exit;
            } else {
                $stmt->close();
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

// Get patients list
$patients = [];
if ($role === 'clinician' || $role === 'admin') {
    $r = $conn->query(
        "SELECT p.patient_id, u.full_name, u.email FROM patients p JOIN users u ON p.user_id = u.user_id WHERE u.is_active=1 ORDER BY u.full_name");
    while ($row = $r->fetch_assoc()) $patients[] = $row;
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h2 class="fw-bold mb-0">Create Health Record</h2>
        <p class="text-muted mb-0">Add a new health record for a patient</p>
    </div>
</div>
<?= render_flash() ?>
<?php foreach ($errors as $e): ?>
<div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-2"></i><?= $e ?></div>
<?php endforeach; ?>

<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card">
    <div class="card-body p-4">
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Patient *</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">— Select Patient —</option>
                        <?php foreach ($patients as $pt): ?>
                        <option value="<?= $pt['patient_id'] ?>" <?= ((int)($_POST['patient_id'] ?? 0) === (int)$pt['patient_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pt['full_name']) ?> (<?= htmlspecialchars($pt['email']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Record Type *</label>
                    <select name="record_type" class="form-select" required>
                        <option value="">— Select Type —</option>
                        <?php foreach (['consultation','lab_result','prescription','imaging','discharge_summary','immunization'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($_POST['record_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required placeholder="e.g. Initial Consultation for Hypertension">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Diagnosis</label>
                    <input type="text" name="diagnosis" class="form-control" value="<?= htmlspecialchars($_POST['diagnosis'] ?? '') ?>" placeholder="e.g. Type 2 Diabetes Mellitus">
                </div>
                <div class="col-md-6">
                    <label class="form-label">ICD-10 Code</label>
                    <input type="text" name="icd_code" class="form-control" value="<?= htmlspecialchars($_POST['icd_code'] ?? '') ?>" placeholder="e.g. E11">
                </div>
                <div class="col-12">
                    <label class="form-label">Description / Notes</label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Clinical notes, findings, treatment plan..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_sensitive" class="form-check-input" id="sensitiveCheck" <?= !empty($_POST['is_sensitive']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sensitiveCheck">
                            <strong>Sensitive Record</strong>
                            <span class="text-muted small d-block">Check for psychiatric, HIV/AIDS, substance use records. Requires explicit patient consent to view.</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Save Record</button>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
