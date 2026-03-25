<?php
// ============================================================
//  modules/appointments/book_appointment.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('appointments', 'write');

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$page_title = 'Book Appointment';

// Get patient_id
$pid = 0;
if ($role === 'patient') {
    $r = $conn->query("SELECT patient_id FROM patients WHERE user_id = $uid LIMIT 1");
    $row = $r->fetch_assoc();
    if (!$row) { flash('error', 'Patient profile not found.'); header('Location: ' . BASE_URL . '/dashboards/patient_dashboard.php'); exit; }
    $pid = (int)$row['patient_id'];
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $clinician_id = (int)$_POST['clinician_id'];
        $appt_date    = $_POST['appointment_date'] ?? '';
        $appt_time    = $_POST['appointment_time'] ?? '';
        $purpose      = htmlspecialchars(trim($_POST['purpose'] ?? ''));

        if ($role === 'admin' || $role === 'clinician') {
            $pid = (int)$_POST['patient_id'];
        }

        if (!$clinician_id)              $errors[] = 'Please select a clinician.';
        if (empty($appt_date))           $errors[] = 'Please select a date.';
        if (empty($appt_time))           $errors[] = 'Please select a time.';
        if (!$pid)                       $errors[] = 'Patient not found.';
        if ($appt_date < date('Y-m-d'))  $errors[] = 'Appointment date must be in the future.';

        if (empty($errors)) {
            // Conflict check
            $chk = $conn->prepare("SELECT appointment_id FROM appointments WHERE clinician_id=? AND appointment_date=? AND appointment_time=? AND status='scheduled' LIMIT 1");
            $chk->bind_param('iss', $clinician_id, $appt_date, $appt_time);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = 'This time slot is already booked. Please choose a different time.';
            }
            $chk->close();
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO appointments (patient_id, clinician_id, appointment_date, appointment_time, purpose) VALUES (?,?,?,?,?)");
            $stmt->bind_param('iisss', $pid, $clinician_id, $appt_date, $appt_time, $purpose);
            if ($stmt->execute()) {
                $stmt->close();
                flash('success', 'Appointment booked for ' . date('d M Y', strtotime($appt_date)) . ' at ' . $appt_time . '.');
                header('Location: ' . BASE_URL . '/modules/appointments/view_appointments.php'); exit;
            } else {
                $stmt->close(); $errors[] = 'Booking failed. Please try again.';
            }
        }
    }
}

// Get clinicians
$clinicians = [];
$r = $conn->query("SELECT user_id, full_name, institution FROM users WHERE role='clinician' AND is_active=1 ORDER BY full_name");
while ($row = $r->fetch_assoc()) $clinicians[] = $row;

// Get patients (for admin/clinician)
$patients = [];
if (in_array($role, ['admin','clinician'])) {
    $r = $conn->query("SELECT p.patient_id, u.full_name FROM patients p JOIN users u ON p.user_id = u.user_id WHERE u.is_active=1 ORDER BY u.full_name");
    while ($row = $r->fetch_assoc()) $patients[] = $row;
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= BASE_URL ?>/modules/appointments/view_appointments.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <div><h2 class="fw-bold mb-0">Book Appointment</h2></div>
</div>
<?= render_flash() ?>
<?php foreach ($errors as $e): ?>
<div class="alert alert-danger py-2"><?= $e ?></div>
<?php endforeach; ?>
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
        <div class="col-12">
            <label class="form-label">Clinician *</label>
            <select name="clinician_id" class="form-select" required>
                <option value="">— Select Clinician —</option>
                <?php foreach ($clinicians as $c): ?>
                <option value="<?= $c['user_id'] ?>" <?= ((int)($_POST['clinician_id'] ?? 0) === (int)$c['user_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['full_name']) ?><?= $c['institution'] ? ' — ' . htmlspecialchars($c['institution']) : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Date *</label>
            <input type="date" name="appointment_date" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= htmlspecialchars($_POST['appointment_date'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Time *</label>
            <select name="appointment_time" class="form-select" required>
                <option value="">— Select Time —</option>
                <?php
                for ($h = 8; $h <= 17; $h++) {
                    foreach (['00','30'] as $m) {
                        $time = sprintf('%02d:%s:00', $h, $m);
                        $label = date('H:i', strtotime($time));
                        $sel = ($_POST['appointment_time'] ?? '') === $time ? 'selected' : '';
                        echo "<option value=\"$time\" $sel>$label</option>";
                    }
                }
                ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Purpose / Reason</label>
            <input type="text" name="purpose" class="form-control" placeholder="e.g. Annual check-up, Follow-up on diabetes management" value="<?= htmlspecialchars($_POST['purpose'] ?? '') ?>">
        </div>
    </div>
    <div class="d-flex gap-3 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-calendar-check me-2"></i>Book Appointment</button>
        <a href="<?= BASE_URL ?>/modules/appointments/view_appointments.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div>
</div></div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
