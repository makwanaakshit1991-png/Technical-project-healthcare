<?php
// ============================================================
//  modules/records/update_record.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../modules/blockchain/audit_logger.php';
require_login();
check_permission('health_records', 'write');

$uid = (int)$_SESSION['user_id'];
$rid = (int)($_GET['id'] ?? $_POST['record_id'] ?? 0);

if (!$rid) { header('Location: ' . BASE_URL . '/modules/records/search_records.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM health_records WHERE record_id = ? LIMIT 1");
$stmt->bind_param('i', $rid); $stmt->execute();
$record = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$record) { flash('error', 'Record not found.'); header('Location: ' . BASE_URL . '/modules/records/search_records.php'); exit; }

// Only original clinician or admin
if ($_SESSION['role'] !== 'admin' && (int)$record['clinician_id'] !== $uid) {
    include __DIR__ . '/../../includes/403.php'; exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $title       = htmlspecialchars(trim($_POST['title'] ?? ''));
        $description = htmlspecialchars(trim($_POST['description'] ?? ''));
        $diagnosis   = htmlspecialchars(trim($_POST['diagnosis'] ?? ''));
        $icd_code    = htmlspecialchars(trim($_POST['icd_code'] ?? ''));
        $is_sensitive = isset($_POST['is_sensitive']) ? 1 : 0;

        if (empty($title)) $errors[] = 'Title is required.';

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE health_records SET title=?, description=?, diagnosis=?, icd_code=?, is_sensitive=? WHERE record_id=?");
            $stmt->bind_param('ssssii', $title, $description, $diagnosis, $icd_code, $is_sensitive, $rid);
            if ($stmt->execute()) {
                $stmt->close();
                log_transaction('RecordUpdate', $uid, $rid, ['action' => 'record_update', 'record_id' => $rid, 'changes' => compact('title','diagnosis')], $_SERVER['REMOTE_ADDR'] ?? '');
                flash('success', 'Record updated successfully.');
                header('Location: ' . BASE_URL . '/modules/records/view_record.php?id=' . $rid); exit;
            } else {
                $stmt->close(); $errors[] = 'Update failed.';
            }
        }
    }
}

$page_title = 'Edit Record #' . $rid;
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= BASE_URL ?>/modules/records/view_record.php?id=<?= $rid ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <div><h2 class="fw-bold mb-0">Edit Health Record #<?= $rid ?></h2></div>
</div>
<?= render_flash() ?>
<?php foreach ($errors as $e): ?>
<div class="alert alert-danger py-2"><?= $e ?></div>
<?php endforeach; ?>
<div class="row justify-content-center"><div class="col-lg-8">
<div class="card"><div class="card-body p-4">
<form method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="record_id" value="<?= $rid ?>">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Title *</label>
            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($_POST['title'] ?? $record['title']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Diagnosis</label>
            <input type="text" name="diagnosis" class="form-control" value="<?= htmlspecialchars($_POST['diagnosis'] ?? $record['diagnosis']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">ICD-10 Code</label>
            <input type="text" name="icd_code" class="form-control" value="<?= htmlspecialchars($_POST['icd_code'] ?? $record['icd_code']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Description / Notes</label>
            <textarea name="description" class="form-control" rows="6"><?= htmlspecialchars($_POST['description'] ?? $record['description']) ?></textarea>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="checkbox" name="is_sensitive" class="form-check-input" id="sensitiveCheck"
                       <?= ((int)($_POST['is_sensitive'] ?? $record['is_sensitive'])) ? 'checked' : '' ?>>
                <label class="form-check-label" for="sensitiveCheck"><strong>Sensitive Record</strong></label>
            </div>
        </div>
    </div>
    <div class="d-flex gap-3 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button>
        <a href="<?= BASE_URL ?>/modules/records/view_record.php?id=<?= $rid ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div>
</div></div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
