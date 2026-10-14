<?php
// Care row: one reminder with its plant, when it is due and a Mark done button (CORE-05, wireframe p10).
// Expects $reminder — a care_reminders row joined with its plant (nickname, photo) and species (common_name).
// Stage F: the button does nothing yet. Stage B puts it inside a POST form (CORE-23).

$due   = care_due($reminder['next_due']);
$every = $reminder['interval_days'] == 1 ? 'every day' : 'every ' . $reminder['interval_days'] . ' days';
?>
<div class="care-row">
  <img class="thumb" src="<?= e(photo_url($reminder['photo'])) ?>" alt="">
  <div class="care-row-text">
    <span><strong><?= e($reminder['nickname']) ?></strong> <span class="care-row-species">· <?= e($reminder['common_name']) ?></span></span>
    <span class="care-row-task"><?= icon(task_icon($reminder['task'])) ?> <?= e(task_label($reminder['task'])) ?> · <?= e($every) ?></span>
  </div>
  <span class="badge badge-<?= e($due['state']) ?>"><?= e($due['text']) ?></span>
  <?php // On a phone only the tick is shown, so the full name of the action lives in aria-label ?>
  <button type="button" class="btn btn-secondary btn-sm" aria-label="Mark done: <?= e(task_label($reminder['task'])) ?> <?= e($reminder['nickname']) ?>">
    <?= icon('check') ?> <span class="care-row-btn-label">Mark done</span>
  </button>
</div>
