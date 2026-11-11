<?php
// Care row: one reminder, when it is due and a Mark done button (CORE-05, wireframe p10).
// Expects $reminder — a care_reminders row joined with its plant (nickname, photo) and species (common_name).
// Optional:
//   $action  — the page Mark done is sent to (default the Dashboard); it moves the date on and leaves a toast
//              with Undo (reminder_mark_done(), includes/reminder-form.php)
//   $compact — true on the plant's own page: no photo or plant name, the task is the row's title (2026-11-11)
// data-reminder, data-task, data-interval and data-due let plant.js move the date when the Add to story popup
// records the same care.

$due     = care_due($reminder['next_due']);
$compact = !empty($compact);
$action  = $action ?? url('dashboard.php');
// "Due today" and "Overdue" have their own colours; a later date ("Tomorrow", "In 7 days") is a quiet grey tag
$badge   = $due['state'] === 'upcoming' ? 'neutral' : $due['state'];
?>
<div class="care-row<?= $compact ? ' care-row-compact' : '' ?>" data-reminder data-task="<?= e($reminder['task']) ?>" data-interval="<?= e($reminder['interval_days'] ?? '') ?>">
  <?php if (!$compact): ?>
    <img class="thumb" src="<?= e(photo_url($reminder['photo'])) ?>" alt="">
  <?php endif; ?>
  <div class="care-row-text">
    <?php if ($compact): ?>
      <span class="care-row-task-title"><?= task_name($reminder['task']) ?> <span class="care-row-task">· <?= e(schedule_words($reminder)) ?></span></span>
    <?php else: ?>
      <span><strong><?= e($reminder['nickname']) ?></strong> <span class="care-row-species">· <?= e($reminder['common_name']) ?></span></span>
      <span class="care-row-task"><?= task_name($reminder['task']) ?> · <?= e(schedule_words($reminder)) ?></span>
    <?php endif; ?>
  </div>
  <span class="badge badge-<?= e($badge) ?>" data-due><?= e($due['text']) ?></span>
  <form class="care-row-form" method="post" action="<?= e($action) ?>">
    <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
    <input type="hidden" name="form" value="done">
    <?php // Which reminder: its plant and task, the pair the UNIQUE key makes unique. The server checks it may be marked. ?>
    <input type="hidden" name="reminder" value="<?= e($reminder['plant_id'] . '-' . $reminder['task']) ?>">
    <?php // On a phone only the tick is shown, so the full name of the action lives in aria-label ?>
    <button class="btn btn-secondary btn-sm" aria-label="Mark done: <?= e(task_label($reminder['task'])) ?> <?= e($reminder['nickname']) ?>">
      <?= icon('check') ?> <span class="care-row-btn-label">Mark done</span>
    </button>
  </form>
</div>
