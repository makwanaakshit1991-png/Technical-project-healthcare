<?php
// ============================================================
//  modules/lab_results/upload_lab_result.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('lab_results', 'write');

$uid = (int)$_SESSION['user_id'];
$page_title = 'Upload Lab Result';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $patient_id    = (int)$_POST['patient_id'];
        $test_name     = htmlspecialchars(trim($_POST['test_name'] ?? ''));
        $result_value  = htmlspecialchars(trim($_POST['result_value'] ?? ''));
        $unit          = htmlspecialchars(trim($_POST['unit'] ?? ''));
        $normal_range  = htmlspecialchars(trim($_POST['normal_range'] ?? ''));
        $lab_name      = htmlspecialchars(trim($_POST['lab_name'] ?? ''));
        $result_date   = $_POST['result_date'] ?? date('Y-m-d');
        $notes         = htmlspecialchars(trim($_POST['notes'] ?? ''));
        $is_abnormal   = isset($_POST['is_abnormal']) ? 1 : 0;

        if (!$patient_id)       $errors[] = 'Patient is required.';
        if (empty($test_name))  $errors[] = 'Test name is required.';
        if (empty($result_value)) $errors[] = 'Result value is required.';

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO lab_results (patient_id, ordered_by, test_name, result_value, unit, normal_range, is_abnormal, lab_name, result_date, notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('iissssisss', $patient_id, $uid, $test_name, $result_value, $unit, $normal_range, $is_abnormal, $lab_name, $result_date, $notes);
            if ($stmt->execute()) {
                $stmt->close();
                flash('success', 'Lab result uploaded successfully.');
                header('Location: ' . BASE_URL . '/modules/lab_results/view_lab_results.php'); exit;
            } else {
                $stmt->close(); $errors[] = 'Failed to save lab result.';
            }
        }
    }
}

$patients = [];
$r = $conn->query("SELECT p.patient_id, u.full_name FROM patients p JOIN users u ON p.user_id = u.user_id WHERE u.is_active=1 ORDER BY u.full_name");
while ($row = $r->fetch_assoc()) $patients[] = $row;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= BASE_URL ?>/modules/lab_results/view_lab_results.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <div><h2 class="fw-bold mb-0">Upload Lab Result</h2></div>
</div>
<?= render_flash() ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= $e ?></div><?php endforeach; ?>
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
                <option value="<?= $pt['patient_id'] ?>"><?= htmlspecialchars($pt['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label">Test Name *</label>
            <input type="text" name="test_name" class="form-control" value="<?= htmlspecialchars($_POST['test_name']??'') ?>" required placeholder="e.g. HbA1c">
        </div>
        <div class="col-md-4">
            <label class="form-label">Result Date</label>
            <input type="date" name="result_date" class="form-control" value="<?= htmlspecialchars($_POST['result_date']??date('Y-m-d')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Result Value *</label>
            <input type="text" name="result_value" class="form-control" value="<?= htmlspecialchars($_POST['result_value']??'') ?>" required placeholder="e.g. 7.8">
        </div>
        <div class="col-md-4">
            <label class="form-label">Unit</label>
            <input type="text" name="unit" class="form-control" value="<?= htmlspecialchars($_POST['unit']??'') ?>" placeholder="e.g. %">
        </div>
        <div class="col-md-4">
            <label class="form-label">Normal Range</label>
            <input type="text" name="normal_range" class="form-control" value="<?= htmlspecialchars($_POST['normal_range']??'') ?>" placeholder="e.g. 4.0-5.6">
        </div>
        <div class="col-md-6">
            <label class="form-label">Lab Name</label>
            <input type="text" name="lab_name" class="form-control" value="<?= htmlspecialchars($_POST['lab_name']??'') ?>" placeholder="e.g. City Diagnostic Lab">
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="checkbox" name="is_abnormal" class="form-check-input" id="abnormalCheck">
                <label class="form-check-label text-danger fw-semibold" for="abnormalCheck">
                    <i class="bi bi-exclamation-triangle me-1"></i>Mark as Abnormal
                </label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Clinical notes, interpretation..."><?= htmlspecialchars($_POST['notes']??'') ?></textarea>
        </div>
    </div>
    <div class="d-flex gap-3 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Upload Result</button>
        <a href="<?= BASE_URL ?>/modules/lab_results/view_lab_results.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div>
</div></div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
