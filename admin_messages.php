<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('admin');
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();
    $action = sanitizeInput($_POST['action'] ?? '');
    try {
        if ($action === 'mark_read') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?');
            $stmt->execute([$id]);
            redirectWithMessage('admin_messages.php', 'success', 'Marked as read.');
        }
        if ($action === 'reply') {
            $id = (int)($_POST['id'] ?? 0);
            $reply = sanitizeInput($_POST['reply'] ?? '');
            if ($reply === '') redirectWithMessage('admin_messages.php', 'danger', 'Reply message is required.');
            $stmt = $pdo->prepare('SELECT email, subject FROM contact_messages WHERE id = ?');
            $stmt->execute([$id]);
            $m = $stmt->fetch();
            if (!$m) redirectWithMessage('admin_messages.php', 'danger', 'Message not found.');
            @mail((string)$m['email'], 'Re: ' . (string)$m['subject'], $reply);
            $stmt = $pdo->prepare('UPDATE contact_messages SET is_read = 1, replied_at = NOW() WHERE id = ?');
            $stmt->execute([$id]);
            redirectWithMessage('admin_messages.php', 'success', 'Reply sent (best effort).');
        }
    } catch (Throwable) {
        redirectWithMessage('admin_messages.php', 'danger', 'Action failed.');
    }
}

$rows = [];
try {
    $rows = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC, id DESC')->fetchAll();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

<h1 class="h4 fw-bold mb-3">Contact Messages</h1>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body p-4">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>From</th>
            <th>Email</th>
            <th>Subject</th>
            <th>Received</th>
            <th>Read</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr class="<?= (int)$r['is_read'] === 0 ? 'unread-highlight' : '' ?>">
              <td><?= (int)$r['id'] ?></td>
              <td><?= e((string)$r['name']) ?></td>
              <td><?= e((string)$r['email']) ?></td>
              <td><?= e((string)$r['subject']) ?></td>
              <td class="small"><?= e(formatDateTime((string)$r['created_at'])) ?></td>
              <td><?= (int)$r['is_read'] === 1 ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning text-dark">No</span>' ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#view<?= (int)$r['id'] ?>">View</button>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                  <input type="hidden" name="action" value="mark_read">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-primary">Mark Read</button>
                </form>

                <div class="modal fade" id="view<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title"><?= e((string)$r['subject']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="mb-2"><b>From:</b> <?= e((string)$r['name']) ?> (<?= e((string)$r['email']) ?>)</div>
                        <div class="mb-3"><b>Message:</b><br><?= nl2br(e((string)$r['message'])) ?></div>
                        <hr>
                        <form method="post">
                          <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                          <input type="hidden" name="action" value="reply">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <label class="form-label">Reply (sent via mail())</label>
                          <textarea class="form-control" name="reply" rows="4" required></textarea>
                          <button class="btn btn-warning mt-3 fw-semibold">Send Reply</button>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_sidebar_end.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

