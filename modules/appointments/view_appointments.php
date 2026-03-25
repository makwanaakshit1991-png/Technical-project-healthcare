<?php
// ============================================================
//  modules/appointments/view_appointments.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('appointments', 'read');

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$page_title = 'Appointments';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (csrf_validate()) {
        $aid    = (int)$_POST['appointment_id'];
        $status = in_array($_POST['new_status'], ['scheduled','completed','cancelled','no_show']) ? $_POST['new_status'] : 'scheduled';
        $notes  = htmlspecialchars($_POST['notes'] ?? '');
        $stmt = $conn->prepare("UPDATE appointments SET status=?, notes=? WHERE appointment_id=?");
        $stmt->bind_param('ssi', $status, $notes, $aid); $stmt->execute(); $stmt->close();
        flash('success', 'Appointment status updated.');
    }
    header('Location: ' . BASE_URL . '/modules/appointments/view_appointments.php'); exit;
}

// Build query
$where = '1=1';
if ($role === 'patient') {
    $r = $conn->query("SELECT patient_id FROM patients WHERE user_id = $uid LIMIT 1");
    $pid = (int)$r->fetch_assoc()['patient_id'];
    $where .= " AND a.patient_id = $pid";
} elseif ($role === 'clinician') {
    $where .= " AND a.clinician_id = $uid";
}

$per_page = 10; $page_num = max(1,(int)($_GET['page']??1)); $offset = ($page_num-1)*$per_page;
$total = (int)$conn->query("SELECT COUNT(*) AS c FROM appointments a WHERE $where")->fetch_assoc()['c'];
$pages = ceil($total / $per_page);

$appts = [];
$r = $conn->query(
    "SELECT a.*, u_p.full_name AS patient_name, u_c.full_name AS clinician_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.patient_id
     JOIN users u_p ON p.user_id = u_p.user_id
     JOIN users u_c ON a.clinician_id = u_c.user_id
     WHERE $where ORDER BY a.appointment_date DESC, a.appointment_time DESC
     LIMIT $per_page OFFSET $offset");
while ($row = $r->fetch_assoc()) $appts[] = $row;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-calendar3 text-primary me-2"></i>Appointments</h2>
        <p class="text-muted mb-0"><?= $total ?> appointment<?= $total != 1 ? 's' : '' ?></p>
    </div>
    <?php if (in_array($role, ['patient','admin'])): ?>
    <a href="<?= BASE_URL ?>/modules/appointments/book_appointment.php" class="btn btn-primary"><i class="bi bi-calendar-plus me-2"></i>Book New</a>
    <?php endif; ?>
</div>
<?= render_flash() ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Date & Time</th><th>Patient</th><th>Clinician</th><th>Purpose</th><th>Status</th><th>Notes</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($appts)): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">No appointments found.</td></tr>
            <?php else: ?>
            <?php foreach ($appts as $a): ?>
            <?php
            $sbadge = ['scheduled'=>'primary','completed'=>'success','cancelled'=>'secondary','no_show'=>'warning'];
            $sc = $sbadge[$a['status']] ?? 'secondary';
            $is_future = strtotime($a['appointment_date']) >= strtotime('today');
            ?>
            <tr>
                <td>
                    <div class="fw-semibold"><?= date('d M Y', strtotime($a['appointment_date'])) ?></div>
                    <small class="text-muted"><?= date('H:i', strtotime($a['appointment_time'])) ?></small>
                </td>
                <td><?= htmlspecialchars($a['patient_name']) ?></td>
                <td><?= htmlspecialchars($a['clinician_name']) ?></td>
                <td class="text-truncate" style="max-width:180px"><?= htmlspecialchars($a['purpose'] ?? '—') ?></td>
                <td><span class="badge bg-<?= $sc ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
                <td class="text-truncate" style="max-width:120px"><small><?= htmlspecialchars($a['notes'] ?? '—') ?></small></td>
                <td>
                    <?php if (in_array($role, ['clinician','admin']) && $a['status'] === 'scheduled'): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateModal"
                            onclick="setApptUpdate(<?= $a['appointment_id'] ?>,'<?= $a['status'] ?>')">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php elseif ($role === 'patient' && $a['status'] === 'scheduled' && $is_future): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="appointment_id" value="<?= $a['appointment_id'] ?>">
                        <input type="hidden" name="new_status" value="cancelled">
                        <input type="hidden" name="notes" value="Cancelled by patient">
                        <input type="hidden" name="update_status" value="1">
                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Cancel this appointment?"><i class="bi bi-x-circle"></i></button>
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

<?php if ($pages > 1): ?>
<nav class="mt-3"><ul class="pagination justify-content-center">
<?php for ($i = 1; $i <= $pages; $i++): ?>
<li class="page-item <?= $i == $page_num ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
<?php endfor; ?>
</ul></nav>
<?php endif; ?>
</div>

<!-- Update Modal -->
<div class="modal fade" id="updateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="appointment_id" id="apptId">
            <div class="modal-header">
                <h5 class="modal-title">Update Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="new_status" id="apptStatus" class="form-select">
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No Show</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
<script>
function setApptUpdate(id, status) {
    document.getElementById('apptId').value = id;
    document.getElementById('apptStatus').value = status;
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
