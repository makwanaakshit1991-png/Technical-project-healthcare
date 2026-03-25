<?php
// ============================================================
//  modules/prescriptions/add_prescription.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('prescriptions', 'write');

$uid  = (int)$_SESSION['user_id'];
$page_title = 'Add Prescription';

$errors = [];
$allergy_warning = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $patient_id  = (int)$_POST['patient_id'];
        $medication  = htmlspecialchars(trim($_POST['medication_name'] ?? ''));
        $dosage      = htmlspecialchars(trim($_POST['dosage'] ?? ''));
        $frequency   = htmlspecialchars(trim($_POST['frequency'] ?? ''));
        $duration    = (int)$_POST['duration_days'];
        $instructions= htmlspecialchars(trim($_POST['instructions'] ?? ''));

        if (!$patient_id)          $errors[] = 'Patient is required.';
        if (empty($medication))    $errors[] = 'Medication name is required.';
        if (empty($dosage))        $errors[] = 'Dosage is required.';
        if ($duration < 1)         $errors[] = 'Duration must be at least 1 day.';

        // Allergy check
        if ($patient_id && !empty($medication)) {
            $stmt = $conn->prepare("SELECT allergies FROM patients WHERE patient_id = ?");
            $stmt->bind_param('i', $patient_id); $stmt->execute();
            $al = $stmt->get_result()->fetch_assoc(); $stmt->close();
            $allergies = strtolower($al['allergies'] ?? '');
            $med_lower = strtolower($medication);
            foreach (explode(',', $allergies) as $alg) {
                if (trim($alg) && strpos($med_lower, trim($alg)) !== false) {
                    $allergy_warning = "⚠️ ALLERGY ALERT: Patient has a recorded allergy to '" . htmlspecialchars(trim($alg)) . "'. Review before prescribing.";
                    break;
                }
            }
        }

        if (empty($errors) && empty($allergy_warning)) {
            $stmt = $conn->prepare("INSERT INTO prescriptions (patient_id, prescribing_clinician_id, medication_name, dosage, frequency, duration_days, instructions) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('iissssi', $patient_id, $uid, $medication, $dosage, $frequency, $duration, $instructions);
            if ($stmt->execute()) {
                $stmt->close();
                flash('success', "Prescription for $medication created.");
                header('Location: ' . BASE_URL . '/modules/prescriptions/view_prescriptions.php'); exit;
            } else {
                $stmt->close(); $errors[] = 'Failed to save prescription.';
            }
        }
    }
}

// Patients list
$patients = [];
$r = $conn->query("SELECT p.patient_id, u.full_name FROM patients p JOIN users u ON p.user_id = u.user_id WHERE u.is_active=1 ORDER BY u.full_name");
while ($row = $r->fetch_assoc()) $patients[] = $row;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= BASE_URL ?>/modules/prescriptions/view_prescriptions.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <div><h2 class="fw-bold mb-0">Add Prescription</h2></div>
</div>
<?= render_flash() ?>
<?php if ($allergy_warning): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-octagon-fill me-2"></i><?= $allergy_warning ?></div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
<div class="alert alert-danger py-2"><?= $e ?></div>
<?php endforeach; ?>
<div class="row justify-content-center"><div class="col-lg-7">
<div class="card"><div class="card-body p-4">
<form method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Patient *</label>
            <select name="patient_id" class="form-select" required>
                <option value="">— Select Patient —</option>
                <?php foreach ($patients as $pt): ?>
                <option value="<?= $pt['patient_id'] ?>" <?= ((int)($_POST['patient_id']??0)===(int)$pt['patient_id'])?'selected':'' ?>><?= htmlspecialchars($pt['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label">Medication Name *</label>
            <input type="text" name="medication_name" class="form-control" value="<?= htmlspecialchars($_POST['medication_name']??'') ?>" required placeholder="e.g. Metformin">
        </div>
        <div class="col-md-4">
            <label class="form-label">Dosage *</label>
            <input type="text" name="dosage" class="form-control" value="<?= htmlspecialchars($_POST['dosage']??'') ?>" required placeholder="e.g. 500mg">
        </div>
        <div class="col-md-6">
            <label class="form-label">Frequency</label>
            <select name="frequency" class="form-select">
                <option value="Once daily">Once daily</option>
                <option value="Twice daily">Twice daily</option>
                <option value="Three times daily">Three times daily</option>
                <option value="Four times daily">Four times daily</option>
                <option value="Every 8 hours">Every 8 hours</option>
                <option value="As needed">As needed (PRN)</option>
                <option value="Once weekly">Once weekly</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Duration (days) *</label>
            <input type="number" name="duration_days" class="form-control" value="<?= htmlspecialchars($_POST['duration_days']??'30') ?>" min="1" max="365" required>
        </div>
        <div class="col-12">
            <label class="form-label">Instructions</label>
            <textarea name="instructions" class="form-control" rows="3" placeholder="Special instructions, warnings, food interaction notes..."><?= htmlspecialchars($_POST['instructions']??'') ?></textarea>
        </div>
    </div>
    <div class="d-flex gap-3 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Prescription</button>
        <a href="<?= BASE_URL ?>/modules/prescriptions/view_prescriptions.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div>
</div></div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
