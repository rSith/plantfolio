<?php
// Empty state (S1, wireframe p24): an illustration, a one-line message and one clear next step (CORE-25).
// Expects $title, $text and $actions — a list of ['label', 'href', 'icon']; the first is the primary button,
// any others are secondary. An action may give 'url' (a full address, e.g. from query_url()) instead of 'href'.
// Optional $image: a file in assets/img/ (default: empty-garden.svg).
// Optional $heading: 'h1' when the empty state is the whole page (a "not found" page), so that page still has its
// one main heading for screen readers; otherwise the title is an h2 under the page's own h1. Both look the same.
// The same pattern covers an empty garden (P03, P04), empty search results (P09), no listings (P10) and not found pages.

$image   = $image ?? 'empty-garden.svg';
$heading = ($heading ?? 'h2') === 'h1' ? 'h1' : 'h2';   // only these two tags can be printed
?>
<div class="empty-state">
  <img class="empty-state-image" src="<?= e(url('assets/img/' . $image)) ?>" alt="">
  <<?= $heading ?> class="empty-state-title"><?= e($title) ?></<?= $heading ?>>
  <p class="empty-state-text"><?= e($text) ?></p>
  <div class="empty-state-actions">
    <?php foreach ($actions as $number => $action): ?>
      <a class="btn <?= $number === 0 ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e($action['url'] ?? url($action['href'])) ?>"><?= icon($action['icon']) ?> <?= e($action['label']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
