<?php
// Add a reminder popup (MOD-01, CORE-22 UI half): set like an alarm on a phone — which plant, which care task, at
// what time from which day, and how it repeats. One POST form; the page checks it with reminder_form_save()
// (includes/reminder-form.php). It opens at once when the server sent it back with an error.
// Expects:
//   $action  — the page the form is sent to (the page showing the popup)
//   $values  — the field values (reminder_defaults(), or what was sent after an error)
//   $errors  — field name => message
//   $plants  — the member's plants to choose from (Dashboard), or null when the page is about one plant
//   $plant   — that one plant (Plant Profile, Edit plant), or null on the Dashboard
// Opened by any button with data-dialog="add-reminder" (app.js).

$weekdays = reminder_weekdays();
$step     = $plant ? 0 : 1;   // with one plant, "Which plant?" is not asked, so the steps start at 1 with the care
?>
<dialog class="modal modal-wide" id="add-reminder" aria-labelledby="add-reminder-title"<?= $errors ? ' data-open-on-load' : '' ?>>
  <form class="modal-form" method="post" action="<?= e($action) ?>">
    <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
    <input type="hidden" name="form" value="reminder">

    <div class="modal-head">
      <h2 class="modal-title" id="add-reminder-title">Add a reminder<?= $plant ? ' for ' . e($plant['nickname']) : '' ?></h2>
      <?php // formmethod="dialog" closes the popup instead of sending the form; formnovalidate skips the checks ?>
      <button class="icon-btn" formmethod="dialog" formnovalidate aria-label="Close"><?= icon('x') ?></button>
    </div>

    <div class="modal-body">
      <?php if ($plant): ?>
        <?php // The plant is the page's own: sent as a hidden field, and checked on the server like any choice ?>
        <input type="hidden" name="plant" value="<?= e($plant['id']) ?>">
      <?php else: ?>
        <?php // 1. Which plant: the member's garden as small photo cards; each card is a radio button ?>
        <fieldset class="reminder-step">
          <legend class="reminder-step-title">1. Which plant?</legend>
          <?php if (!$plants): ?>
            <p class="field-hint">No plants in your garden yet. Add one first.</p>
          <?php endif; ?>
          <div class="plant-picks">
            <?php foreach ($plants as $pick): ?>
              <label class="plant-pick">
                <input class="visually-hidden" type="radio" name="plant" value="<?= e($pick['id']) ?>" required<?= (string) $pick['id'] === $values['plant'] ? ' checked' : '' ?><?= reminder_error_attrs('plant', $errors) ?>>
                <img class="plant-pick-photo" src="<?= e(photo_url($pick['photo'])) ?>" alt="">
                <span class="plant-pick-name"><?= e($pick['nickname']) ?></span>
                <span class="plant-pick-meta"><?= e($pick['collection_name'] ?? 'No collection') ?></span>
              </label>
            <?php endforeach; ?>
            <?php // The plant is not in the garden yet: add it first (P06), then come back for its reminder ?>
            <a class="plant-pick plant-pick-add" href="<?= e(url('plant-form.php')) ?>">
              <span class="plant-pick-add-icon"><?= icon('plus') ?></span>
              <span class="plant-pick-name">Add plant</span>
            </a>
          </div>
          <?php if (isset($errors['plant'])): ?>
            <p class="field-error" id="reminder-plant-error"><?= e($errors['plant']) ?></p>
          <?php endif; ?>
        </fieldset>
      <?php endif; ?>
      <?php if ($plant && isset($errors['plant'])): ?>
        <p class="field-error" id="reminder-plant-error"><?= e($errors['plant']) ?></p>
      <?php endif; ?>

      <?php // Which care: the most common jobs (reminder_tasks() in helpers.php) ?>
      <fieldset class="reminder-step">
        <legend class="reminder-step-title"><?= e($step + 1) ?>. Which care?</legend>
        <?php // A dropdown that shows each task's icon and colour, which a <select> cannot: a <details> menu like
              // Plant Tools in the header, holding one radio button per task. app.js copies the choice into the
              // closed box and closes the menu. ?>
        <details class="menu task-picker" data-picker>
          <summary class="input task-picker-summary">
            <span data-picker-value>
              <?php if (in_array($values['task'], reminder_tasks(), true)): ?>
                <?= task_name($values['task']) ?>
              <?php else: ?>
                <span class="task-picker-empty">Choose a care task</span>
              <?php endif; ?>
            </span>
            <span class="menu-chevron"><?= icon('chevron-down') ?></span>
          </summary>
          <div class="menu-panel task-picker-panel">
            <?php foreach (reminder_tasks() as $task): ?>
              <label class="menu-item task-picker-option">
                <input class="visually-hidden" type="radio" name="task" value="<?= e($task) ?>" required<?= $task === $values['task'] ? ' checked' : '' ?><?= reminder_error_attrs('task', $errors) ?>>
                <span data-picker-label><?= task_name($task) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </details>
        <?php if (isset($errors['task'])): ?>
          <p class="field-error" id="reminder-task-error"><?= e($errors['task']) ?></p>
        <?php endif; ?>
      </fieldset>

      <?php // When: like a phone alarm, a big time, a first day and a repeat rule ?>
      <fieldset class="reminder-step">
        <legend class="reminder-step-title"><?= e($step + 2) ?>. When?</legend>
        <div class="alarm-row">
          <div class="field">
            <label class="field-label" for="reminder-time">Time</label>
            <input class="input alarm-time" type="time" id="reminder-time" name="time" value="<?= e($values['time']) ?>" required<?= reminder_error_attrs('time', $errors) ?>>
            <?php if (isset($errors['time'])): ?>
              <p class="field-error" id="reminder-time-error"><?= e($errors['time']) ?></p>
            <?php endif; ?>
          </div>
          <div class="field">
            <label class="field-label" for="reminder-start">Starting</label>
            <input class="input" type="date" id="reminder-start" name="start" value="<?= e($values['start']) ?>" min="<?= e(date('Y-m-d')) ?>" required<?= reminder_error_attrs('start', $errors) ?>>
            <?php if (isset($errors['start'])): ?>
              <p class="field-error" id="reminder-start-error"><?= e($errors['start']) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <div class="field">
          <label class="field-label" for="reminder-repeat">Repeat</label>
          <select class="input" id="reminder-repeat" name="repeat"<?= reminder_error_attrs('repeat', $errors) ?>>
            <?php foreach (reminder_repeat_options() as $value => $label): ?>
              <option value="<?= e($value) ?>"<?= $value === $values['repeat'] ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['repeat'])): ?>
            <p class="field-error" id="reminder-repeat-error"><?= e($errors['repeat']) ?></p>
          <?php endif; ?>
        </div>

        <?php // Shown only for "On chosen days" (data-show-when, app.js): the round day buttons of an alarm app ?>
        <div class="field" data-show-when="repeat=days">
          <span class="field-label" id="reminder-days-label">On these days</span>
          <div class="weekdays" role="group" aria-labelledby="reminder-days-label">
            <?php foreach ($weekdays as $value => $name): ?>
              <label class="weekday">
                <input class="visually-hidden" type="checkbox" name="days[]" value="<?= e($value) ?>"<?= in_array($value, $values['days'], true) ? ' checked' : '' ?><?= reminder_error_attrs('days', $errors) ?>>
                <span aria-hidden="true"><?= e($name[0]) ?></span>
                <span class="visually-hidden"><?= e($name) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if (isset($errors['days'])): ?>
            <p class="field-error" id="reminder-days-error"><?= e($errors['days']) ?></p>
          <?php endif; ?>
        </div>

        <?php // Shown only for "Every few days" ?>
        <div class="field" data-show-when="repeat=interval">
          <label class="field-label" for="reminder-interval">Every how many days?</label>
          <span class="interval-row">
            <input class="input interval-input" type="number" id="reminder-interval" name="interval" value="<?= e($values['interval']) ?>" min="2" max="365" inputmode="numeric"<?= reminder_error_attrs('interval', $errors) ?>>
            days
          </span>
          <?php if (isset($errors['interval'])): ?>
            <p class="field-error" id="reminder-interval-error"><?= e($errors['interval']) ?></p>
          <?php endif; ?>
        </div>
      </fieldset>
    </div>

    <div class="modal-foot">
      <button class="btn btn-secondary" formmethod="dialog" formnovalidate>Cancel</button>
      <button class="btn btn-primary" type="submit"><?= icon('bell') ?> Add reminder</button>
    </div>
  </form>
</dialog>
