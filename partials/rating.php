<?php
// Trader rating in its short form, e.g. ★ 4.9 (23) (CORE-05, wireframe p7).
// Expects $rating — any row with rating_avg and rating_count: a user, or a listing joined with its owner's rating.
?>
<?php if ($rating['rating_count'] > 0): ?>
  <span class="rating">
    <span aria-hidden="true"><?= icon('star') ?> <strong><?= e(number_format($rating['rating_avg'], 1)) ?></strong> <span class="rating-count">(<?= e($rating['rating_count']) ?>)</span></span>
    <span class="visually-hidden">Rated <?= e(number_format($rating['rating_avg'], 1)) ?> out of 5 from <?= e($rating['rating_count']) ?> ratings</span>
  </span>
<?php else: ?>
  <span class="rating rating-none">No ratings yet</span>
<?php endif; ?>
