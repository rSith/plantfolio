<?php
// Health alert on the Plant Profile (P05), under the action buttons: what the newest AI health check found, so a
// sick plant's problem is seen at once instead of down in the Story (CORE-18, UI half; 2026-11-11).
// Expects:
//   $log       — the health_logs row of that check, joined with its ml_predictions row
//                (predicted_label, confidence, logged_at)
//   $status    — the plant's health_status: needs_attention or sick. It picks the tint, the same as the status badge.
//   $treat_url — where "See how to treat it" leads: the Disease control card of the AI care guide
// The colour is never the only signal: the words name the problem and the confidence word is always shown.

$disease = prediction_name(['type' => 'disease', 'predicted_label' => $log['predicted_label']]);
$level   = confidence_level($log['confidence']);
$percent = round($log['confidence'] * 100);
?>
<section class="health-alert health-alert-<?= e(str_replace('_', '-', $status)) ?>" aria-labelledby="health-alert-title">
  <span class="health-alert-icon"><?= icon('triangle-alert') ?></span>
  <div class="health-alert-body">
    <h2 class="health-alert-title" id="health-alert-title"><?= e($disease) ?> found</h2>
    <p class="health-alert-meta">
      Health check on <?= e(date('j M Y', strtotime($log['logged_at']))) ?> · <?= e($level['label']) ?> confidence, <?= e($percent) ?>%
    </p>
    <a class="health-alert-link" href="<?= e($treat_url) ?>">See how to treat it <?= icon('arrow-right') ?></a>
  </div>
</section>
