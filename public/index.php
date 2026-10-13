<?php
// Temporary placeholder — replaced by the real Landing page (P01, task CORE-27) in week 2.
// Its job right now: the "blank page" that shows the page frame from CORE-06 — header, bottom bar and footer.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

$page_title = 'Welcome';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <h1>PlantFolio</h1>
  <p>Where your plants tell their story.</p>

  <?php if ($current_user): ?>
    <p>You are seeing the frame as the member <strong>@<?= e($current_user['username']) ?></strong>.</p>
    <p><a class="btn btn-secondary" href="<?= e(url('?guest')) ?>">View as a visitor</a></p>
  <?php else: ?>
    <p>You are seeing the frame as a <strong>visitor</strong> who is not logged in.</p>
    <p><a class="btn btn-secondary" href="<?= e(url('')) ?>">View as a member</a></p>
  <?php endif; ?>

  <p class="text-small">Setup works — PHP <?= e(PHP_VERSION) ?> is running (<?= e(config('app_env')) ?>).
    Most links in the frame lead to pages that are built from week 2 onward, so they show "Not Found" for now.
    The components are in the <a href="<?= e(url('styleguide.php')) ?>">style guide</a>.</p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
