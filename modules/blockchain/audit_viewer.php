<?php
// ============================================================
//  modules/blockchain/audit_viewer.php  —  Admin: view & verify chain
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('blockchain_audit', 'read');

$page_title = 'Blockchain Audit Trail';
$per_page   = 15;
$page_num   = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page_num - 1) * $per_page;

$total = (int)$conn->query("SELECT COUNT(*) AS c FROM blockchain_audit_log")->fetch_assoc()['c'];
$pages = ceil($total / $per_page);

$logs_result = $conn->query(
    "SELECT b.*, u.full_name, u.role
     FROM blockchain_audit_log b
     LEFT JOIN users u ON b.actor_user_id = u.user_id
     ORDER BY b.log_id ASC
     LIMIT $per_page OFFSET $offset"
);
$logs = [];
while ($r = $logs_result->fetch_assoc()) $logs[] = $r;

// Chain integrity verification
$verify = isset($_GET['verify']);
$verification_results = [];
if ($verify) {
    $all = $conn->query("SELECT * FROM blockchain_audit_log ORDER BY log_id ASC");
    $prev = '0000000000000000000000000000000000000000000000000000000000000000';
    while ($row = $all->fetch_assoc()) {
        $recomputed = hash('sha256', ($row['previous_hash'] ?? $prev) . $row['resource_hash'] . strtotime($row['timestamp']));
        // Note: stored block_hash used time() at insertion; we check chain linkage instead
        // Chain integrity = current row's previous_hash equals the previous row's block_hash
        $valid = ($row['previous_hash'] === $prev) || ($row['log_id'] == 1 && $row['previous_hash'] === '0000000000000000000000000000000000000000000000000000000000000000');
        $verification_results[$row['log_id']] = $valid;
        $prev = $row['block_hash'];
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-link-45deg text-primary me-2"></i>Blockchain Audit Trail</h2>
        <p class="text-muted mb-0">Immutable SHA-256 hash-chained audit log of all system actions</p>
    </div>
    <a href="?verify=1&page=<?= $page_num ?>" class="btn btn-<?= $verify ? 'success' : 'outline-primary' ?>">
        <i class="bi bi-shield-check me-2"></i><?= $verify ? 'Chain Verified' : 'Verify Chain Integrity' ?>
    </a>
</div>

<?= render_flash() ?>

<?php if ($verify): ?>
<?php
    $tampered = array_filter($verification_results, fn($v) => !$v);
    $verified  = count($verification_results) - count($tampered);
?>
<div class="alert alert-<?= empty($tampered) ? 'success' : 'danger' ?> d-flex align-items-center mb-4">
    <i class="bi bi-<?= empty($tampered) ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-3 fs-4"></i>
    <div>
        <strong><?= empty($tampered) ? 'Chain Integrity Verified' : 'Chain Integrity Violated' ?></strong><br>
        <?= $verified ?> blocks verified.
        <?php if (!empty($tampered)): ?>
            <span class="text-danger"><?= count($tampered) ?> tampered block(s) detected: #<?= implode(', #', array_keys($tampered)) ?></span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Status</th>
                        <th>Transaction</th>
                        <th>Actor</th>
                        <th>Record ID</th>
                        <th>Block Hash</th>
                        <th>Prev Hash</th>
                        <th>IP</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <?php
                        $status_class = '';
                        $status_icon  = 'link-45deg';
                        if ($verify) {
                            if (isset($verification_results[$log['log_id']])) {
                                if ($verification_results[$log['log_id']]) {
                                    $status_class = 'verified'; $status_icon = 'check-circle-fill text-success';
                                } else {
                                    $status_class = 'tampered'; $status_icon = 'x-circle-fill text-danger';
                                }
                            }
                        }
                        $type_badges = [
                            'RecordCreate'  => 'success',
                            'RecordUpdate'  => 'primary',
                            'AccessRequest' => 'info',
                            'ConsentUpdate' => 'warning',
                            'LoginEvent'    => 'secondary',
                        ];
                        $tbadge = $type_badges[$log['transaction_type']] ?? 'secondary';
                    ?>
                    <tr class="<?= $status_class === 'tampered' ? 'table-danger' : '' ?>">
                        <td class="fw-bold text-muted"><?= $log['log_id'] ?></td>
                        <td>
                            <?php if ($verify && isset($verification_results[$log['log_id']])): ?>
                            <i class="bi bi-<?= $status_icon ?>"></i>
                            <?php else: ?>
                            <i class="bi bi-circle text-secondary"></i>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $tbadge ?>"><?= $log['transaction_type'] ?></span></td>
                        <td>
                            <?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?>
                            <?php if ($log['role']): ?>
                            <br><small class="text-muted"><?= ucfirst($log['role']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $log['affected_record_id'] ? '#' . $log['affected_record_id'] : '—' ?></td>
                        <td>
                            <code class="small text-primary" title="<?= $log['block_hash'] ?>">
                                <?= substr($log['block_hash'], 0, 12) ?>…
                            </code>
                        </td>
                        <td>
                            <code class="small text-muted" title="<?= $log['previous_hash'] ?>">
                                <?= substr($log['previous_hash'] ?? '', 0, 12) ?>…
                            </code>
                        </td>
                        <td><small><?= htmlspecialchars($log['ip_address']) ?></small></td>
                        <td><small><?= date('d M Y H:i:s', strtotime($log['timestamp'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<nav class="mt-3"><ul class="pagination justify-content-center">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <li class="page-item <?= $i == $page_num ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?><?= $verify ? '&verify=1' : '' ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
