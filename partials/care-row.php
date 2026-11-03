<?php
// Care row: one reminder with its plant, when it is due and a Mark done button (CORE-05, wireframe p10).
// Expects $reminder — a care_reminders row joined with its plant (nickname, photo) and species (common_name).
// Mark done is a small POST form to the Dashboard (CORE-23): the task leaves Today's care and its next date moves on.

$due = care_due($reminder['next_due']);
?>
<div class="care-row">
  <img class="thumb" src="<?= e(photo_url($reminder['photo'])) ?>" alt="">
  <div class="care-row-text">
    <span><strong><?= e($reminder['nickname']) ?></strong> <span class="care-row-species">· <?= e($reminder['common_name']) ?></span></span>
    <span class="care-row-task"><?= task_name($reminder['task']) ?> · <?= e(schedule_words($reminder)) ?></span>
  </div>
  <span class="badge badge-<?= e($due['state']) ?>"><?= e($due['text']) ?></span>
  <form class="care-row-form" method="post" action="<?= e(url('dashboard.php')) ?>">
    <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
    <input type="hidden" name="form" value="done">
    <?php // Which reminder: its plant and task, the pair the UNIQUE key makes unique. The server checks it is due and Nimali's. ?>
    <input type="hidden" name="reminder" value="<?= e($reminder['plant_id'] . '-' . $reminder['task']) ?>">
    <?php // On a phone only the tick is shown, so the full name of the action lives in aria-label ?>
    <button class="btn btn-secondary btn-sm" aria-label="Mark done: <?= e(task_label($reminder['task'])) ?> <?= e($reminder['nickname']) ?>">
      <?= icon('check') ?> <span class="care-row-btn-label">Mark done</span>
    </button>
  </form>
</div>
