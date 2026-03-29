<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$faqsByCat = [];
try {
    $stmt = $pdo->query("SELECT id, category, question, answer FROM faq_entries WHERE is_active = 1 ORDER BY category ASC, sort_order ASC, id ASC");
    foreach ($stmt->fetchAll() as $row) {
        $cat = (string)($row['category'] ?: 'General');
        $faqsByCat[$cat][] = $row;
    }
} catch (Throwable) {
    $faqsByCat = [
        'General' => [
            ['id'=>1,'category'=>'General','question'=>'How do I book a facility?','answer'=>'Create an account, sign in, then use “Book a Facility” from your dashboard.'],
            ['id'=>2,'category'=>'General','question'=>'How long does approval take?','answer'=>'Approvals follow an 8-step chain and may vary depending on availability of approvers.'],
        ],
    ];
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="banner text-white mb-4" style="background-image:url('assets/images/ndmubgp.jpg')">
  <div class="banner-content container py-5">
    <div class="d-flex align-items-center gap-3">
      <img src="assets/images/ndmulogo.png" alt="NDMU" width="48" height="48" style="object-fit:contain">
      <div>
        <h1 class="h2 fw-bold mb-1">Frequently Asked Questions</h1>
        <div class="text-white-50">Find quick answers before you book.</div>
      </div>
    </div>
  </div>
</div>

<div class="container pb-5">
  <div class="row justify-content-center mb-3">
    <div class="col-lg-8">
      <input id="faqSearch" class="form-control form-control-lg" placeholder="Search FAQs...">
    </div>
  </div>

  <div class="accordion" id="faqAccordion">
    <?php $idx = 0; foreach ($faqsByCat as $cat => $items): ?>
      <div class="mb-3">
        <h2 class="h5 fw-semibold mb-2"><?= e($cat) ?></h2>
        <?php foreach ($items as $item): $idx++; ?>
          <div class="accordion-item faq-item" data-q="<?= e(strtolower((string)$item['question'] . ' ' . (string)$item['answer'])) ?>">
            <h2 class="accordion-header" id="heading<?= (int)$idx ?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= (int)$idx ?>" aria-expanded="false" aria-controls="collapse<?= (int)$idx ?>">
                <?= e((string)$item['question']) ?>
              </button>
            </h2>
            <div id="collapse<?= (int)$idx ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= (int)$idx ?>" data-bs-parent="#faqAccordion">
              <div class="accordion-body"><?= nl2br(e((string)$item['answer'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
  (function(){
    const input = document.getElementById('faqSearch');
    const items = Array.from(document.querySelectorAll('.faq-item'));
    input?.addEventListener('input', () => {
      const q = (input.value || '').trim().toLowerCase();
      items.forEach(it => {
        const hay = it.getAttribute('data-q') || '';
        it.style.display = (!q || hay.includes(q)) ? '' : 'none';
      });
    });
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
