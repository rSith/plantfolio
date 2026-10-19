<?php
// Five stars filled up to a score, e.g. ★★★★☆ (COM-05, COM-13, wireframe p7 "Rating" and p21).
// Expects $score — a review's score (1–5, a whole number) or an average such as 4.9, which fills the nearest whole star.
// Optional $size = 'sm' for the small stars of a review.
// The five icons are one image for screen readers, named "Rated 4.9 out of 5", so nobody hears "star" five times.

$filled      = (int) round($score);
$score_words = is_float($score) ? number_format($score, 1) : $score;   // an average keeps one decimal: 5.0, 4.9
?>
<span class="stars<?= isset($size) ? ' stars-' . e($size) : '' ?>" role="img" aria-label="Rated <?= e($score_words) ?> out of 5">
  <?php for ($star = 1; $star <= 5; $star++): ?>
    <span class="star<?= $star <= $filled ? ' is-filled' : '' ?>"><?= icon('star') ?></span>
  <?php endfor; ?>
</span>
