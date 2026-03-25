<?php
// ============================================================
//  modules/messaging/inbox.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/\\');
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_login();
check_permission('messages', 'read');

$uid = (int)$_SESSION['user_id'];
$page_title = 'Inbox';

$view_id = (int)($_GET['id'] ?? 0);
$tab     = $_GET['tab'] ?? 'inbox';

// Mark as read if viewing
if ($view_id) {
    $stmt = $conn->prepare("UPDATE messages SET is_read=1 WHERE message_id=? AND receiver_id=?");
    $stmt->bind_param('ii', $view_id, $uid); $stmt->execute(); $stmt->close();
}

// Fetch messages
$per_page = 10; $page_num = max(1,(int)($_GET['page']??1)); $offset = ($page_num-1)*$per_page;

if ($tab === 'sent') {
    $total = (int)$conn->query("SELECT COUNT(*) AS c FROM messages WHERE sender_id=$uid")->fetch_assoc()['c'];
    $pages = ceil($total / $per_page);
    $msgs_r = $conn->query("SELECT m.*, u.full_name AS other_name FROM messages m JOIN users u ON m.receiver_id=u.user_id WHERE m.sender_id=$uid ORDER BY m.sent_at DESC LIMIT $per_page OFFSET $offset");
} else {
    $total = (int)$conn->query("SELECT COUNT(*) AS c FROM messages WHERE receiver_id=$uid")->fetch_assoc()['c'];
    $pages = ceil($total / $per_page);
    $msgs_r = $conn->query("SELECT m.*, u.full_name AS other_name FROM messages m JOIN users u ON m.sender_id=u.user_id WHERE m.receiver_id=$uid ORDER BY m.sent_at DESC LIMIT $per_page OFFSET $offset");
}
$msgs = []; while ($row = $msgs_r->fetch_assoc()) $msgs[] = $row;

// Viewing single message
$current_msg = null;
if ($view_id) {
    $stmt = $conn->prepare("SELECT m.*, u_s.full_name AS sender_name, u_r.full_name AS receiver_name FROM messages m JOIN users u_s ON m.sender_id=u_s.user_id JOIN users u_r ON m.receiver_id=u_r.user_id WHERE m.message_id=? AND (m.sender_id=? OR m.receiver_id=?) LIMIT 1");
    $stmt->bind_param('iii', $view_id, $uid, $uid); $stmt->execute();
    $current_msg = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

// Users list for composing
$users = [];
$r = $conn->query("SELECT user_id, full_name, role FROM users WHERE is_active=1 AND user_id != $uid ORDER BY role, full_name");
while ($row = $r->fetch_assoc()) $users[] = $row;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="page-wrapper">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="fw-bold mb-1"><i class="bi bi-envelope text-primary me-2"></i>Messages</h2></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
        <i class="bi bi-pencil-square me-2"></i>Compose
    </button>
</div>
<?= render_flash() ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-pills nav-fill gap-1">
                    <li class="nav-item"><a class="nav-link <?= $tab==='inbox'?'active':'' ?>" href="?tab=inbox">Inbox</a></li>
                    <li class="nav-item"><a class="nav-link <?= $tab==='sent'?'active':'' ?>" href="?tab=sent">Sent</a></li>
                </ul>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($msgs)): ?>
                <div class="p-4 text-center text-muted">No messages.</div>
                <?php else: ?>
                <?php foreach ($msgs as $m): ?>
                <a href="?id=<?= $m['message_id'] ?>&tab=<?= $tab ?>" class="list-group-item list-group-item-action <?= $m['message_id']==$view_id?'active':'' ?> <?= ($tab==='inbox' && !$m['is_read'] && $m['message_id']!=$view_id)?'fw-bold':'' ?>">
                    <div class="d-flex justify-content-between">
                        <span class="small"><?= htmlspecialchars($m['other_name']) ?></span>
                        <small><?= date('d M', strtotime($m['sent_at'])) ?></small>
                    </div>
                    <div class="small text-truncate"><?= htmlspecialchars($m['subject'] ?? '(No subject)') ?></div>
                    <?php if ($tab==='inbox' && !$m['is_read']): ?><span class="badge bg-primary" style="font-size:.6rem">NEW</span><?php endif; ?>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($pages > 1): ?>
            <div class="card-footer"><nav><ul class="pagination justify-content-center pagination-sm mb-0">
            <?php for ($i=1;$i<=$pages;$i++): ?>
            <li class="page-item <?= $i==$page_num?'active':'' ?>"><a class="page-link" href="?tab=<?= $tab ?>&page=<?= $i ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            </ul></nav></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if ($current_msg): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-1"><?= htmlspecialchars($current_msg['subject'] ?? '(No subject)') ?></h5>
                <small class="text-muted">
                    From: <strong><?= htmlspecialchars($current_msg['sender_name']) ?></strong>
                    &nbsp;→&nbsp;
                    To: <strong><?= htmlspecialchars($current_msg['receiver_name']) ?></strong>
                    &nbsp;·&nbsp; <?= date('d M Y H:i', strtotime($current_msg['sent_at'])) ?>
                </small>
            </div>
            <div class="card-body">
                <p style="line-height:1.8"><?= nl2br(htmlspecialchars($current_msg['body'])) ?></p>
                <?php if ($current_msg['receiver_id'] == $uid): ?>
                <hr>
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#composeModal"
                        onclick="setReply(<?= $current_msg['sender_id'] ?>, '<?= htmlspecialchars(addslashes($current_msg['sender_name'])) ?>', 'Re: <?= htmlspecialchars(addslashes($current_msg['subject'] ?? '')) ?>')">
                    <i class="bi bi-reply me-2"></i>Reply
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card text-center p-5">
            <i class="bi bi-envelope-open fs-1 text-muted d-block mb-2"></i>
            <p class="text-muted">Select a message to read it.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Compose Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="<?= BASE_URL ?>/modules/messaging/send_message.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Compose Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">To *</label>
                    <select name="receiver_id" id="composeReceiver" class="form-select" required>
                        <option value="">— Select Recipient —</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= ucfirst($u['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" id="composeSubject" class="form-control" placeholder="Message subject">
                </div>
                <div class="mb-3">
                    <label class="form-label">Message *</label>
                    <textarea name="body" class="form-control" rows="6" required placeholder="Write your message here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send me-2"></i>Send Message</button>
            </div>
        </form>
    </div>
</div>
<script>
function setReply(receiverId, receiverName, subject) {
    var modal = new bootstrap.Modal(document.getElementById('composeModal'));
    document.getElementById('composeReceiver').value = receiverId;
    document.getElementById('composeSubject').value  = subject;
    modal.show();
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
