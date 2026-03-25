<?php
// ============================================================
//  modules/insurance/submit_claim.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('insurance_claims', 'write');

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$page_title = 'Submit Insurance Claim';
$errors = [];

// Get patient_id for current user
$pid = 0;
if ($role === 'patient') {
    $r = $conn->query("SELECT patient_id FROM patients WHERE user_id=$uid LIMIT 1");
    $pid = (int)$r->fetch_assoc()['patient_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) { $errors[] = 'Invalid CSRF token.'; }
    else {
        if (in_array($role, ['admin','clinician'])) $pid = (int)$_POST['patient_id'];
        $insurer_id   = (int)$_POST['insurer_id'];
        $record_id    = (int)$_POST['record_id'] ?: null;
        $claim_amount = (float)$_POST['claim_amount'];
        $description  = htmlspecialchars(trim($_POST['description'] ?? ''));

        if (!$pid)          $errors[] = 'Patient is required.';
        if (!$insurer_id)   $errors[] = 'Insurer is required.';
        if ($claim_amount <= 0) $errors[] = 'Claim amount must be greater than 0.';

        // Check consent
        if ($pid && $insurer_id && !has_consent($pid, $insurer_id)) {
            $errors[] = 'The patient has not granted consent to this insurer. Please set up consent first.';
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO insurance_claims (patient_id, insurer_id, record_id, claim_amount, description) VALUES (?,?,?,?,?)");
            $stmt->bind_param('iiids', $pid, $insurer_id, $record_id, $claim_amount, $description);
            if ($stmt->execute()) {
                $stmt->close();
                flash('success', 'Insurance claim submitted successfully.');
                header('Location: ' . BASE_URL . '/modules/insurance/view_claims.php'); exit;
            } else {
                $stmt->close(); $errors[] = 'Failed to submit claim.';
            }
        }
    }
}

// Data for form
$insurers = [];
$r = $conn->query("SELECT user_id, full_name, institution FROM users WHERE role='insurer' AND is_active=1 ORDER BY full_name");
while ($row = $r->fetch_assoc()) $insurers[] = $row;

$patients = [];
if (in_array($role, ['admin','clinician'])) {
    $r = $conn->query("SELECT p.patient_id, u.full_name FROM patients p JOIN users u ON p.user_id=u.user_id WHERE u.is_active=1 ORDER BY u.full_name");
    while ($row = $r->fetch_assoc()) $patients[] = $row;
}

$records = [];
if ($pid) {
    $r = $conn->query("SELECT record_id, title, record_type FROM health_records WHERE patient_id=$pid ORDER BY created_at DESC LIMIT 30");
    while ($row = $r->fetch_assoc()) $records[] = $row;
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= BASE_URL ?>/modules/insurance/view_claims.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <div><h2 class="fw-bold mb-0">Submit Insurance Claim</h2></div>
</div>
<?= render_flash() ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= $e ?></div><?php endforeach; ?>
<div class="row justify-content-center"><div class="col-lg-7">
<div class="card"><div class="card-body p-4">
<form method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="row g-3">
        <?php if (in_array($role, ['admin','clinician'])): ?>
        <div class="col-12">
            <label class="form-label">Patient *</label>
            <select name="patient_id" class="form-select" required>
                <option value="">— Select Patient —</option>
                <?php foreach ($patients as $pt): ?>
                <option value="<?= $pt['patient_id'] ?>"><?= htmlspecialchars($pt['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-6">
            <label class="form-label">Insurance Provider *</label>
            <select name="insurer_id" class="form-select" required>
                <option value="">— Select Insurer —</option>
                <?php foreach ($insurers as $ins): ?>
                <option value="<?= $ins['user_id'] ?>"><?= htmlspecialchars($ins['full_name']) ?><?= $ins['institution'] ? ' — '.$ins['institution'] : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Related Health Record</label>
            <select name="record_id" class="form-select">
                <option value="">— None / Not Applicable —</option>
                <?php foreach ($records as $rec): ?>
                <option value="<?= $rec['record_id'] ?>">#<?= $rec['record_id'] ?> — <?= htmlspecialchars($rec['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Claim Amount (₹) *</label>
            <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" name="claim_amount" class="form-control" step="0.01" min="0.01" value="<?= htmlspecialchars($_POST['claim_amount']??'') ?>" required>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Describe the claim, services covered, diagnosis..."><?= htmlspecialchars($_POST['description']??'') ?></textarea>
        </div>
    </div>
    <div class="d-flex gap-3 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-2"></i>Submit Claim</button>
        <a href="<?= BASE_URL ?>/modules/insurance/view_claims.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div>
</div></div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
