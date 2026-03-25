<?php
// includes/navbar.php
$role = $_SESSION['role'] ?? '';
$full_name = $_SESSION['full_name'] ?? 'User';

$role_dashboard = [
    'patient'    => BASE_URL . '/dashboards/patient_dashboard.php',
    'clinician'  => BASE_URL . '/dashboards/clinician_dashboard.php',
    'pharmacist' => BASE_URL . '/dashboards/pharmacist_dashboard.php',
    'insurer'    => BASE_URL . '/dashboards/insurer_dashboard.php',
    'admin'      => BASE_URL . '/dashboards/admin_dashboard.php',
];
$dashboard_url = $role_dashboard[$role] ?? BASE_URL . '/index.php';

// Unread messages count
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    global $conn;
    $uid = (int)$_SESSION['user_id'];
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM messages WHERE receiver_id = $uid AND is_read = 0");
    if ($res) $unread_count = (int)$res->fetch_assoc()['cnt'];
}

$role_labels = ['patient'=>'Patient','clinician'=>'Clinician','pharmacist'=>'Pharmacist','insurer'=>'Insurer','admin'=>'Admin'];
$role_badges = ['patient'=>'primary','clinician'=>'success','pharmacist'=>'info','insurer'=>'warning','admin'=>'danger'];
$badge_color  = $role_badges[$role] ?? 'secondary';
$role_label   = $role_labels[$role]  ?? ucfirst($role);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="<?= $dashboard_url ?>">
            <i class="bi bi-hospital me-2"></i>SHRS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $dashboard_url ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
                </li>
                <?php if (in_array($role, ['clinician','patient','admin'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-file-medical me-1"></i>Records</a>
                    <ul class="dropdown-menu">
                        <?php if (in_array($role, ['clinician','admin'])): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/records/create_record.php"><i class="bi bi-plus-circle me-2"></i>New Record</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/records/search_records.php"><i class="bi bi-search me-2"></i>Search Records</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-calendar3 me-1"></i>Appointments</a>
                    <ul class="dropdown-menu">
                        <?php if (in_array($role, ['patient','admin'])): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/appointments/book_appointment.php"><i class="bi bi-calendar-plus me-2"></i>Book Appointment</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/appointments/view_appointments.php"><i class="bi bi-calendar-check me-2"></i>View Appointments</a></li>
                    </ul>
                </li>
                <?php if (in_array($role, ['clinician','patient','pharmacist','admin'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-capsule me-1"></i>Prescriptions</a>
                    <ul class="dropdown-menu">
                        <?php if (in_array($role, ['clinician','admin'])): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/prescriptions/add_prescription.php"><i class="bi bi-plus me-2"></i>Add Prescription</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/prescriptions/view_prescriptions.php"><i class="bi bi-list-ul me-2"></i>View Prescriptions</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (in_array($role, ['clinician','patient','insurer','admin'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-flask me-1"></i>Lab Results</a>
                    <ul class="dropdown-menu">
                        <?php if (in_array($role, ['clinician','admin'])): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/lab_results/upload_lab_result.php"><i class="bi bi-upload me-2"></i>Upload Result</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/lab_results/view_lab_results.php"><i class="bi bi-clipboard2-pulse me-2"></i>View Results</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($role === 'patient'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/modules/consent/manage_consent.php"><i class="bi bi-shield-check me-1"></i>Consent</a>
                </li>
                <?php endif; ?>
                <?php if (in_array($role, ['patient','clinician','insurer','admin'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-receipt me-1"></i>Insurance</a>
                    <ul class="dropdown-menu">
                        <?php if (in_array($role, ['patient','clinician','admin'])): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/insurance/submit_claim.php"><i class="bi bi-send me-2"></i>Submit Claim</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/insurance/view_claims.php"><i class="bi bi-list-check me-2"></i>View Claims</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-2">
                    <a class="nav-link position-relative" href="<?= BASE_URL ?>/modules/messaging/inbox.php">
                        <i class="bi bi-envelope fs-5"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem">
                            <?= $unread_count ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                        <div class="avatar-circle bg-white text-primary"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
                        <span class="d-none d-md-inline"><?= htmlspecialchars($full_name) ?></span>
                        <span class="badge bg-<?= $badge_color ?> d-none d-md-inline"><?= $role_label ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?= htmlspecialchars($full_name) ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($role === 'admin'): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/manage_users.php"><i class="bi bi-people me-2"></i>Manage Users</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/system_logs.php"><i class="bi bi-journal-text me-2"></i>System Logs</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/blockchain/audit_viewer.php"><i class="bi bi-link-45deg me-2"></i>Audit Trail</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
