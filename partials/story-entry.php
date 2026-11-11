<?php
// One entry of a plant's Story on the Plant Profile (P05), drawn by its type (CORE-19, UI half; 2026-11-11):
//   care         — one compact line: task icon, "Watered", the date
//   note, photo  — a card with the note; a photo is shown 160px wide under the text
//   health_check — a card with the AI result (disease, confidence word and %)
//   milestone    — a small highlighted card, e.g. "Added to garden"
// A status badge shows only when the entry has a status (a care line has none).
// Expects $log — a health_logs row (type, care_task, status, source, note, photo, logged_at), joined with its
// ml_predictions row for an AI entry (prediction_type, predicted_label, confidence).
// data-kind / data-photo / data-health let story.js filter the timeline (All, Photos, Health, Care).

$type      = $log['type'];
$has_photo = !empty($log['photo']);
$is_health = $type === 'health_check' || $log['status'] !== null;
$kinds     = ['note' => 'Update', 'photo' => 'Photo', 'health_check' => 'Health check', 'milestone' => 'Milestone'];
?>
<li class="story-entry story-entry-<?= e(str_replace('_', '-', $type)) ?>" data-kind="<?= e($type) ?>" data-photo="<?= $has_photo ? '1' : '0' ?>" data-health="<?= $is_health ? '1' : '0' ?>">
  <?php if ($type === 'care'): ?>
    <p class="story-care">
      <?php // .task-<name> gives the task its colour, the same as in the Reminders card ?>
      <span class="story-care-task task task-<?= e($log['care_task']) ?>"><?= icon(task_icon($log['care_task'])) ?> <?= e(care_done_label($log['care_task'])) ?></span>
      <span class="story-care-date">· <?= e(date('j M', strtotime($log['logged_at']))) ?></span>
      <?php if ($log['note'] !== ''): ?>
        <span class="story-care-note">· <?= e($log['note']) ?></span>
      <?php endif; ?>
    </p>
  <?php else: ?>
    <p class="story-date"><?= e(date('j M Y', strtotime($log['logged_at']))) ?> · <?= e($kinds[$type] ?? 'Update') ?></p>
    <div class="story-card<?= $type === 'milestone' ? ' story-milestone' : '' ?>">
      <?php if ($type === 'milestone'): ?>
        <span class="story-milestone-icon"><?= icon('sprout') ?></span>
      <?php endif; ?>
      <div class="story-body">
        <?php if ($log['status'] !== null): ?>
          <?php partial('status-badge', ['status' => $log['status']]); ?>
        <?php endif; ?>
        <p class="story-note"><?= e($log['note']) ?></p>
        <?php if ($log['source'] === 'ml'): ?>
          <?php $percent = round($log['confidence'] * 100); ?>
          <p class="story-ai">
            <?= icon('sparkles') ?>
            <?php // One <span>: the line is a flex row, which would put a gap between every bit of text ?>
            <span>
              <?php if ($log['prediction_type'] === 'species'): ?>
                Species identified by AI: <em><?= e($log['predicted_label']) ?></em> · <?= e($percent) ?>% confidence
              <?php else: ?>
                Health check found <?= e(mb_strtolower(prediction_name(['type' => 'disease', 'predicted_label' => $log['predicted_label']]))) ?>
                · <?= e(confidence_level($log['confidence'])['label']) ?> confidence, <?= e($percent) ?>%
              <?php endif; ?>
            </span>
          </p>
        <?php endif; ?>
        <?php if ($has_photo && $type !== 'milestone'): ?>
          <img class="story-photo" src="<?= e(photo_url($log['photo'])) ?>" alt="Photo from <?= e(date('j M Y', strtotime($log['logged_at']))) ?>" width="160" height="160" loading="lazy">
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</li>
