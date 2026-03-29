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
        if ($action === 'create') {
            $category = sanitizeInput($_POST['category'] ?? 'General');
            $question = sanitizeInput($_POST['question'] ?? '');
            $answer = sanitizeInput($_POST['answer'] ?? '');
            $sort = (int)($_POST['sort_order'] ?? 0);
            $active = !empty($_POST['is_active']) ? 1 : 0;
            if ($question === '' || $answer === '') redirectWithMessage('admin_faq.php', 'danger', 'Question and answer are required.');
            $stmt = $pdo->prepare('INSERT INTO faq_entries (category, question, answer, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$category, $question, $answer, $sort, $active]);
            redirectWithMessage('admin_faq.php', 'success', 'FAQ added.');
        }
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM faq_entries WHERE id = ?');
            $stmt->execute([$id]);
            redirectWithMessage('admin_faq.php', 'success', 'FAQ deleted.');
        }
        if ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE faq_entries SET is_active = IF(is_active=1,0,1) WHERE id = ?');
            $stmt->execute([$id]);
            redirectWithMessage('admin_faq.php', 'success', 'FAQ updated.');
        }
    } catch (Throwable) {
        redirectWithMessage('admin_faq.php', 'danger', 'Action failed.');
    }
}

$rows = [];
try {
    $rows = $pdo->query('SELECT * FROM faq_entries ORDER BY category ASC, sort_order ASC, id DESC')->fetchAll();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

<h1 class="h4 fw-bold mb-3">FAQ</h1>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
  <div class="card-body p-4">
    <h2 class="h6 fw-semibold mb-3">Add FAQ Entry</h2>
    <form method="post" class="row g-2">
      <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="col-md-2"><input class="form-control" name="category" placeholder="Category" value="General"></div>
      <div class="col-md-2"><input class="form-control" type="number" name="sort_order" placeholder="Order" value="0"></div>
      <div class="col-12"><input class="form-control" name="question" placeholder="Question" required></div>
      <div class="col-12"><textarea class="form-control" name="answer" rows="3" placeholder="Answer" required></textarea></div>
      <div class="col-12 d-flex gap-3 align-items-center">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" id="faqActive" checked>
          <label class="form-check-label" for="faqActive">Active</label>
        </div>
        <button class="btn btn-warning fw-semibold">Add</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-4">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Category</th>
            <th>Question</th>
            <th>Order</th>
            <th>Active</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e((string)$r['category']) ?></td>
              <td><?= e((string)$r['question']) ?></td>
              <td><?= (int)$r['sort_order'] ?></td>
              <td><?= (int)$r['is_active'] === 1 ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-2 flex-wrap">
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary"><?= (int)$r['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this FAQ?');">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
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

