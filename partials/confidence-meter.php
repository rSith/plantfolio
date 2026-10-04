<?php
// Confidence meter: a labelled bar for an ML result (CORE-05, wireframes p14, p15 and p24).
// Expects $prediction — an ml_predictions row; only its confidence (0–1) is used.
// The bar's colour always comes with the fixed word: High, Medium or Not sure.

$percent = round($prediction['confidence'] * 100);
$level   = confidence_level($prediction['confidence']);
?>
<div class="confidence confidence-<?= e($level['key']) ?>">
  <div class="confidence-head">
    <span class="confidence-title">Confidence</span>
    <span class="confidence-label"><?= e($level['label']) ?></span>
  </div>
  <div class="confidence-row">
    <?php // The bar only repeats the number beside it, so screen readers skip it ?>
    <div class="meter" aria-hidden="true"><span class="meter-fill" style="width: <?= e($percent) ?>%"></span></div>
    <strong class="confidence-value"><?= e($percent) ?>%</strong>
  </div>
</div>
