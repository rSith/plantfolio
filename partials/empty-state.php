<?php
// Empty state (S1, wireframe p24): an illustration, a one-line message and one clear next step (CORE-25).
// Expects $title, $text and $actions — a list of ['label', 'href', 'icon']; the first is the primary button,
// any others are secondary. An action may give 'url' (a full address, e.g. from query_url()) instead of 'href'.
// Optional $image: a file in assets/img/ (default: empty-garden.svg).
// The same pattern covers an empty garden (P03, P04), empty search results (P09) and no listings (P10).

$image = $image ?? 'empty-garden.svg';
?>
<div class="empty-state">
  <img class="empty-state-image" src="<?= e(url('assets/img/' . $image)) ?>" alt="">
  <h2 class="empty-state-title"><?= e($title) ?></h2>
  <p class="empty-state-text"><?= e($text) ?></p>
  <div class="empty-state-actions">
    <?php foreach ($actions as $number => $action): ?>
      <a class="btn <?= $number === 0 ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e($action['url'] ?? url($action['href'])) ?>"><?= icon($action['icon']) ?> <?= e($action['label']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
