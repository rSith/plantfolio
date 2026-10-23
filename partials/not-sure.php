<?php
// Low confidence (state S3, wireframe p24): below 60% a result is never presented as an answer (ML-17, UI half).
// The same state is used when the disease model does not support the plant (p24).
// Expects $title, $text and $actions — a list of ['label', 'url', 'icon']; the first is the primary button.
// Optional $confidence (0–1): the top result's confidence, shown as "Not sure · 42%" with a grey bar. Leave it out when
// there is no number to show (a plant the model does not know at all).
?>
<section class="form-card not-sure" aria-labelledby="not-sure-title">
  <?php if (isset($confidence)): ?>
    <?php $percent = round($confidence * 100); ?>
    <span class="badge badge-warning"><?= icon('triangle-alert') ?> Not sure · <?= e($percent) ?>%</span>
  <?php else: ?>
    <span class="badge badge-warning"><?= icon('triangle-alert') ?> Not supported</span>
  <?php endif; ?>
  <h2 class="not-sure-title" id="not-sure-title"><?= e($title) ?></h2>
  <?php if (isset($confidence)): ?>
    <?php // The bar only repeats the number in the badge, so screen readers skip it ?>
    <div class="confidence-row not-sure-meter" aria-hidden="true">
      <div class="meter"><span class="meter-fill" style="width: <?= e($percent) ?>%"></span></div>
      <strong class="confidence-value"><?= e($percent) ?>%</strong>
    </div>
  <?php endif; ?>
  <p class="not-sure-text"><?= e($text) ?></p>
  <div class="not-sure-actions">
    <?php foreach ($actions as $number => $action): ?>
      <a class="btn <?= $number === 0 ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e($action['url']) ?>"><?= icon($action['icon']) ?> <?= e($action['label']) ?></a>
    <?php endforeach; ?>
  </div>
</section>
