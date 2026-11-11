<?php
// The message after Add reminder or Mark done (CORE-23 toast), with Undo. Stays until the member closes it.
// Expects $toast — from reminder_toast_take() (includes/reminder-form.php): message, key (null: no Undo), icon —
// and $action, the page Undo is sent to (the page showing the toast).
// Undo is a POST, because it changes data. Without JavaScript the × is a link that loads the page again, without
// the toast. Moved here from dashboard.php on 2026-11-11, so the plant pages show the same toast.
?>
<div class="toast-region">
  <div class="toast" role="status">
    <?= icon($toast['icon'] ?? 'bell') ?>
    <span class="toast-message"><?= e($toast['message']) ?></span>
    <?php if ($toast['key']): ?>
      <form method="post" action="<?= e($action) ?>">
        <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
        <input type="hidden" name="form" value="undo_reminder">
        <button class="toast-action">Undo</button>
      </form>
    <?php endif; ?>
    <a class="toast-close" href="<?= e($action) ?>" data-toast-close aria-label="Close message"><?= icon('x') ?></a>
  </div>
</div>
