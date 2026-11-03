<?php
// Dashboard (P03, wireframe p10): the home screen after login — "What do my plants need today?" and
// a garden overview in one glance (CORE-24, UI half). With no plants it shows state S1 instead (CORE-25).
// Members only. Reads the reminders and garden counts from mock data.
// No "Needs attention" panel (p10, element 4): the member learns a plant is unwell from a Health Check they run
// themselves, not from the Dashboard guessing for them.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage F stand-in for require_login(): a visitor (?guest) is sent to Log in, exactly as stage B will do.
if (!$current_user) {
    header('Location: ' . url('login.php'));
    exit;
}

// "Good morning, Nimali": the greeting follows the clock, the name is the first word of full_name.
$hour       = (int) date('G');   // 0–23
$greeting   = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$first_name = explode(' ', $current_user['full_name'])[0];

$care_count = count($mock_reminders);   // due today or overdue
$has_plants = $current_user['plant_count'] > 0;

// All reminders popup: every reminder of the member, soonest first, grouped by day.
// Stage B: SELECT care_reminders.*, plants.nickname, plants.photo FROM care_reminders JOIN plants …
//          WHERE plants.user_id = ? ORDER BY next_due, nickname
$plants_by_id  = array_column($mock_plants, null, 'id');
$all_reminders = [];
foreach ($mock_plant_reminders as $reminder) {
    $plant           = $plants_by_id[$reminder['plant_id']];
    $all_reminders[] = $reminder + ['nickname' => $plant['nickname'], 'photo' => $plant['photo']];
}
// Comparing two small arrays sorts by the date first, then by name within the same day
usort($all_reminders, fn ($a, $b) => [$a['next_due'], $a['nickname']] <=> [$b['next_due'], $b['nickname']]);

$reminder_days = [];   // '2026-11-03' => the reminders due that day
foreach ($all_reminders as $reminder) {
    $reminder_days[$reminder['next_due']][] = $reminder;
}

/** The heading over one day of the All reminders list: "Overdue · 1 Nov", "Today", "Tomorrow" or "Saturday 7 Nov". */
function reminder_day($date)
{
    $days = days_until($date);
    if ($days < 0) {
        return 'Overdue · ' . format_day($date);
    }
    if ($days === 0) {
        return 'Today';
    }
    if ($days === 1) {
        return 'Tomorrow';
    }
    return date('l j M', strtotime($date));
}

// ── Add a reminder (popup) ──────────────────────────────
// Set like an alarm on a phone: which plant, which care task, at what time from which day, and how it repeats.
// Stage F: the form is checked here and the reminder is kept in the session (mock_add_reminder()), so it shows in
// All reminders and on the plant's page. Stage B: INSERT INTO care_reminders … (CORE-22).

$weekdays = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
             'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
$repeat_options = [
    'once'     => 'Does not repeat',
    'daily'    => 'Every day',
    'days'     => 'On chosen days',
    'interval' => 'Every few days',
];

// The member's own plants, A to Z, for the plant picker. Only these ids are accepted (ownership check).
$pick_plants = $mock_plants;
usort($pick_plants, fn ($a, $b) => strcasecmp($a['nickname'], $b['nickname']));

$today           = date('Y-m-d');
$reminder_values = ['plant' => '', 'task' => 'water', 'time' => '08:00', 'start' => $today,
                    'repeat' => 'interval', 'days' => [], 'interval' => '7'];
$reminder_errors = [];   // field name => message, printed under that field in the popup

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_text('form') === 'reminder') {
    foreach (['plant', 'task', 'time', 'start', 'repeat', 'interval'] as $name) {
        $reminder_values[$name] = post_text($name);
    }
    // The day boxes arrive as a list (days[]=mon&days[]=wed): keep only text that is a known day
    $posted_days = $_POST['days'] ?? [];
    $posted_days = is_array($posted_days) ? array_filter($posted_days, 'is_string') : [];
    $reminder_values['days'] = array_values(array_intersect(array_keys($weekdays), $posted_days));

    // The same rules the browser checks, checked again here: the browser's checks can be switched off
    $plant_id = $reminder_values['plant'];
    if (!ctype_digit($plant_id) || !isset($plants_by_id[(int) $plant_id])) {
        $reminder_errors['plant'] = 'Choose one of your plants.';
    }
    if (!in_array($reminder_values['task'], reminder_tasks(), true)) {
        $reminder_errors['task'] = 'Choose a care task.';
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $reminder_values['time'])) {
        $reminder_errors['time'] = 'Choose a time.';
    }
    // '!' starts the date at midnight; comparing it back as text rejects dates like 2026-02-31
    $start = DateTime::createFromFormat('!Y-m-d', $reminder_values['start']);
    if (!$start || $start->format('Y-m-d') !== $reminder_values['start'] || $reminder_values['start'] < $today) {
        $reminder_errors['start'] = 'Choose today or a later day.';
    }
    if (!array_key_exists($reminder_values['repeat'], $repeat_options)) {
        $reminder_errors['repeat'] = 'Choose how often it repeats.';
    } elseif ($reminder_values['repeat'] === 'days' && !$reminder_values['days']) {
        $reminder_errors['days'] = 'Choose at least one day.';
    } elseif ($reminder_values['repeat'] === 'interval'
        && (!ctype_digit($reminder_values['interval']) || $reminder_values['interval'] < 2 || $reminder_values['interval'] > 365)) {
        $reminder_errors['interval'] = 'Use a number from 2 to 365.';
    }

    if (!$reminder_errors) {
        $repeat   = $reminder_values['repeat'];
        $reminder = [
            'id'            => 900 + count($_SESSION['preview_reminders'] ?? []),   // made up: stage B's AUTO_INCREMENT
            'plant_id'      => (int) $plant_id,
            'task'          => $reminder_values['task'],
            'interval_days' => $repeat === 'daily' ? 1 : ($repeat === 'interval' ? (int) $reminder_values['interval'] : null),
            'last_done'     => null,
            'next_due'      => first_due($reminder_values['start'], $repeat, $reminder_values['days']),
            'remind_time'   => $reminder_values['time'],
            'repeat_type'   => $repeat,
            'repeat_days'   => $repeat === 'days' ? $reminder_values['days'] : [],
        ];
        $previous = mock_add_reminder($reminder);

        // The toast on the next page; Undo needs the key and the row this one replaced, kept here, not in the form
        $plant = $plants_by_id[$reminder['plant_id']];
        $_SESSION['reminder_toast'] = [
            'message'  => 'Reminder added: ' . task_label($reminder['task']) . ' ' . $plant['nickname'] . ' · ' . schedule_words($reminder),
            'key'      => $reminder['plant_id'] . '-' . $reminder['task'],
            'previous' => $previous,
            'undone'   => 'Reminder cancelled.',
        ];
        header('Location: ' . url('dashboard.php'));   // POST, then redirect: a reload cannot add it twice
        exit;
    }
}

// ── Mark done (CORE-23) ─────────────────────────────────
// The task leaves Today's care: last_done becomes today and next_due moves on by the reminder's own rule. A one-off
// reminder is finished, so it goes. Only a reminder in Today's care (Nimali's, due today or overdue) can be marked.
// Stage B: UPDATE care_reminders SET last_done = CURDATE(), next_due = … WHERE id = ? (owner checked through plants),
//          or DELETE for a one-off reminder.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_text('form') === 'done') {
    $key = post_text('reminder');
    $row = null;
    foreach ($mock_reminders as $due_row) {
        if ($due_row['plant_id'] . '-' . $due_row['task'] === $key) {
            $row = $due_row;
        }
    }

    if ($row) {
        $reminder = array_diff_key($row, array_flip(['nickname', 'photo', 'common_name']));   // the care_reminders columns only
        $done     = task_done_label($row['task']) . ' ' . $row['nickname'];

        if (($reminder['repeat_type'] ?? 'interval') === 'once') {
            $updated = ['plant_id' => $reminder['plant_id'], 'task' => $reminder['task'], 'deleted' => true];
            $message = $done . ' · reminder finished';
        } else {
            $updated = array_merge($reminder, ['last_done' => $today, 'next_due' => next_due_after($reminder, $today)]);
            $message = $done . ' · next on ' . format_day($updated['next_due']);
        }

        $_SESSION['reminder_toast'] = [
            'message'  => $message,
            'key'      => $key,
            'previous' => mock_add_reminder($updated),
            'undone'   => task_label($row['task']) . ' ' . $row['nickname'] . ' is back on today\'s list.',
            'icon'     => 'check',
        ];
    }
    header('Location: ' . url('dashboard.php'));
    exit;
}

// Undo in the toast: takes back the last Add reminder or Mark done. Only the session knows which reminder that was,
// so nothing in the form can point it at another member's reminder.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_text('form') === 'undo_reminder') {
    $undo = $_SESSION['reminder_undo'] ?? null;
    unset($_SESSION['reminder_undo']);
    if ($undo) {
        mock_undo_reminder($undo['key'], $undo['previous']);
        $_SESSION['reminder_toast'] = ['message' => $undo['undone'], 'key' => null, 'previous' => null, 'undone' => null];
    }
    header('Location: ' . url('dashboard.php'));
    exit;
}

// The toast shows once: it is taken out of the session as soon as it is read. Its Undo stays possible until the
// next Add reminder or Mark done.
$toast = $_SESSION['reminder_toast'] ?? null;
unset($_SESSION['reminder_toast']);
if ($toast && $toast['key']) {
    $_SESSION['reminder_undo'] = ['key' => $toast['key'], 'previous' => $toast['previous'], 'undone' => $toast['undone']];
}

/**
 * The next due day after a task was done on $done_day (Mark done): the next chosen weekday for "On chosen days",
 * otherwise $done_day plus interval_days (1 for every day), the rule next_due = last_done + interval_days.
 */
function next_due_after($reminder, $done_day)
{
    if (($reminder['repeat_type'] ?? 'interval') === 'days') {
        return first_due(date('Y-m-d', strtotime($done_day . ' +1 day')), 'days', $reminder['repeat_days']);
    }
    return date('Y-m-d', strtotime($done_day . ' +' . (int) $reminder['interval_days'] . ' days'));
}

/**
 * The first day a new reminder is due: its start day, or for "On chosen days" the first chosen weekday from then.
 * date('D') gives "Mon", which lowercased matches the day values of the form.
 */
function first_due($start, $repeat, $days)
{
    if ($repeat !== 'days') {
        return $start;
    }
    for ($offset = 0; $offset < 7; $offset++) {
        $day = date('Y-m-d', strtotime($start . ' +' . $offset . ' days'));
        if (in_array(strtolower(date('D', strtotime($day))), $days, true)) {
            return $day;
        }
    }
    return $start;
}

/** aria-invalid and aria-describedby for a field with an error, so a screen reader reads the message with it. */
function reminder_error_attrs($field, $errors)
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="reminder-' . $field . '-error"' : '';
}

// Garden at a glance: each status's share of the garden becomes the width of its part of the bar
$health_counts = $mock_garden_stats['health_counts'];
$plant_total   = array_sum($health_counts);

$page_title = 'Dashboard';
$page_css   = 'dashboard.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <?php // Greeting banner: just the greeting and the date (the counts are in the sections below), with leaves on the right ?>
  <div class="dash-head">
    <div class="dash-head-text">
      <h1 class="dash-title"><?= e($greeting) ?>, <?= e($first_name) ?></h1>
      <p class="dash-date"><?= icon('calendar') ?> <?= e(date('l, j F')) ?></p>
    </div>
    <img class="dash-head-art" src="<?= e(url('assets/img/dashboard-leaves.svg')) ?>" alt="">
  </div>

  <div class="dash-grid">
    <div class="dash-main">
      <?php if (!$has_plants): ?>
        <?php // S1, right after sign-up: one clear next step plus the AI shortcut, instead of empty lists (p24) ?>
        <section class="panel">
          <?php partial('empty-state', [
              'title'   => 'Your garden is waiting',
              'text'    => 'Add your first plant to start its story.',
              'actions' => [
                  ['label' => 'Add a plant',          'href' => 'plant-form.php', 'icon' => 'plus'],
                  ['label' => 'Identify from a photo', 'href' => 'identify.php',   'icon' => 'sparkles'],
              ],
          ]); ?>
        </section>
      <?php else: ?>
        <section class="panel" aria-labelledby="care-title">
          <div class="panel-head">
            <h2 class="panel-title" id="care-title">Today's care
              <?php if ($care_count > 0): ?><span class="badge badge-neutral"><?= e($care_count) ?> due</span><?php endif; ?>
            </h2>
            <?php // app.js opens the All reminders popup; without JavaScript the link shows My Garden sorted by next care ?>
            <a class="panel-link" href="<?= e(url('garden.php?sort=care')) ?>" data-dialog="all-reminders" aria-haspopup="dialog">All reminders</a>
          </div>

          <?php if ($care_count > 0): ?>
            <div class="panel-list">
              <?php foreach ($mock_reminders as $reminder): ?>
                <?php partial('care-row', ['reminder' => $reminder]); ?>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="panel-empty"><?= icon('check') ?> Nothing due today. Your plants are all looked after.</p>
          <?php endif; ?>
          <?php // No "Coming up this week" row: All reminders (top right) already shows the days ahead ?>

          <?php // Opens the Add a reminder popup below (app.js) ?>
          <button type="button" class="add-reminder-btn" data-dialog="add-reminder" aria-haspopup="dialog"><?= icon('plus') ?> Add a reminder</button>
        </section>
      <?php endif; ?>
    </div>

    <?php // No Quick actions panel: the header's Add plant and Plant Tools (the + menu on a phone) already do those jobs ?>
    <div class="dash-side">
      <?php if ($has_plants): ?>
        <section class="panel" aria-labelledby="glance-title">
          <div class="panel-head">
            <h2 class="panel-title" id="glance-title">Garden at a glance</h2>
            <a class="panel-link" href="<?= e(url('garden.php')) ?>">My Garden</a>
          </div>
          <p class="glance-total">
            <strong><?= e($current_user['plant_count']) ?></strong>
            <span>plants in<br><?= e($current_user['collection_count']) ?> collections</span>
          </p>

          <?php // The bar repeats the numbers in the list below it, so screen readers skip it ?>
          <div class="health-bar" aria-hidden="true">
            <?php foreach ($health_counts as $status => $count): ?>
              <?php if ($count > 0): ?>
                <span class="health-bar-<?= e(str_replace('_', '-', $status)) ?>" style="width: <?= e(round($count / $plant_total * 100, 1)) ?>%"></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <ul class="health-legend">
            <?php foreach ($health_counts as $status => $count): ?>
              <li><span class="health-dot health-bar-<?= e(str_replace('_', '-', $status)) ?>"></span> <?= e(health_label($status)) ?> <strong><?= e($count) ?></strong></li>
            <?php endforeach; ?>
          </ul>

          <?php // Where the plants are: one line per collection with its count and a few photos; each opens it in My Garden.
                // Stage B: the collections query My Garden already uses (plant_count and three cover photos per row). ?>
          <ul class="glance-collections">
            <?php foreach ($mock_collections as $collection): ?>
              <?php $more = $collection['plant_count'] - count($collection['cover_photos']); ?>
              <li>
                <a class="glance-collection" href="<?= e(url('garden.php?collection=' . $collection['id'])) ?>">
                  <span class="glance-collection-name">
                    <?= e($collection['name']) ?>
                    <?php if (!$collection['is_public']): ?><?= icon('lock') ?><span class="visually-hidden">(private)</span><?php endif; ?>
                  </span>
                  <strong class="glance-collection-count"><?= e($collection['plant_count']) ?></strong>
                  <span class="glance-photos" aria-hidden="true">
                    <?php foreach ($collection['cover_photos'] as $photo): ?>
                      <img src="<?= e(photo_url($photo)) ?>" alt="" loading="lazy">
                    <?php endforeach; ?>
                    <?php if ($more > 0): ?><span class="glance-more">+<?= e($more) ?></span><?php endif; ?>
                  </span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($has_plants): ?>
    <?php // All reminders popup (MOD-01). A <dialog> stays hidden until app.js calls showModal(). ?>
    <dialog class="modal" id="all-reminders" aria-labelledby="all-reminders-title">
      <div class="modal-head">
        <h2 class="modal-title" id="all-reminders-title">All reminders</h2>
        <?php // method="dialog": pressing this button closes the popup, no JavaScript needed ?>
        <form method="dialog">
          <button class="icon-btn" aria-label="Close"><?= icon('x') ?></button>
        </form>
      </div>
      <div class="modal-body">
        <?php if (!$reminder_days): ?>
          <p class="panel-empty"><?= icon('info') ?> No reminders yet. Add them on a plant's Edit page.</p>
        <?php endif; ?>
        <?php foreach ($reminder_days as $date => $day_reminders): ?>
          <h3 class="label reminder-day reminder-day-<?= e(care_due($date)['state']) ?>"><?= e(reminder_day($date)) ?></h3>
          <ul class="reminder-rows">
            <?php foreach ($day_reminders as $reminder): ?>
              <li class="reminder-row">
                <img class="thumb" src="<?= e(photo_url($reminder['photo'])) ?>" alt="">
                <span class="reminder-row-text">
                  <a href="<?= e(url('plant.php?id=' . $reminder['plant_id'])) ?>"><strong><?= e($reminder['nickname']) ?></strong></a>
                  <span class="reminder-row-task"><?= task_name($reminder['task']) ?> · <?= e(schedule_words($reminder)) ?></span>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </div>
    </dialog>

    <?php // Add a reminder popup (MOD-01): one POST form. It opens at once when the server sent it back with an error. ?>
    <dialog class="modal modal-wide" id="add-reminder" aria-labelledby="add-reminder-title"<?= $reminder_errors ? ' data-open-on-load' : '' ?>>
      <form class="modal-form" method="post" action="<?= e(url('dashboard.php')) ?>">
        <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
        <input type="hidden" name="form" value="reminder">

        <div class="modal-head">
          <h2 class="modal-title" id="add-reminder-title">Add a reminder</h2>
          <?php // formmethod="dialog" closes the popup instead of sending the form; formnovalidate skips the checks ?>
          <button class="icon-btn" formmethod="dialog" formnovalidate aria-label="Close"><?= icon('x') ?></button>
        </div>

        <div class="modal-body">
          <?php // 1. Which plant: the member's garden as small photo cards; each card is a radio button ?>
          <fieldset class="reminder-step">
            <legend class="reminder-step-title">1. Which plant?</legend>
            <div class="plant-picks">
              <?php foreach ($pick_plants as $plant): ?>
                <label class="plant-pick">
                  <input class="visually-hidden" type="radio" name="plant" value="<?= e($plant['id']) ?>" required<?= (string) $plant['id'] === $reminder_values['plant'] ? ' checked' : '' ?><?= reminder_error_attrs('plant', $reminder_errors) ?>>
                  <img class="plant-pick-photo" src="<?= e(photo_url($plant['photo'])) ?>" alt="">
                  <span class="plant-pick-name"><?= e($plant['nickname']) ?></span>
                  <span class="plant-pick-meta"><?= e($plant['collection_name']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <?php if (isset($reminder_errors['plant'])): ?>
              <p class="field-error" id="reminder-plant-error"><?= e($reminder_errors['plant']) ?></p>
            <?php endif; ?>
          </fieldset>

          <?php // 2. Which care: the most common jobs (reminder_tasks() in helpers.php) ?>
          <fieldset class="reminder-step">
            <legend class="reminder-step-title">2. Which care?</legend>
            <?php // A dropdown that shows each task's icon and colour, which a <select> cannot: a <details> menu like
                  // Plant Tools in the header, holding one radio button per task. app.js copies the choice into the
                  // closed box and closes the menu. ?>
            <details class="menu task-picker" data-picker>
              <summary class="input task-picker-summary">
                <span data-picker-value>
                  <?php if (in_array($reminder_values['task'], reminder_tasks(), true)): ?>
                    <?= task_name($reminder_values['task']) ?>
                  <?php else: ?>
                    <span class="task-picker-empty">Choose a care task</span>
                  <?php endif; ?>
                </span>
                <span class="menu-chevron"><?= icon('chevron-down') ?></span>
              </summary>
              <div class="menu-panel task-picker-panel">
                <?php foreach (reminder_tasks() as $task): ?>
                  <label class="menu-item task-picker-option">
                    <input class="visually-hidden" type="radio" name="task" value="<?= e($task) ?>" required<?= $task === $reminder_values['task'] ? ' checked' : '' ?><?= reminder_error_attrs('task', $reminder_errors) ?>>
                    <span data-picker-label><?= task_name($task) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </details>
            <?php if (isset($reminder_errors['task'])): ?>
              <p class="field-error" id="reminder-task-error"><?= e($reminder_errors['task']) ?></p>
            <?php endif; ?>
          </fieldset>

          <?php // 3. When: like a phone alarm, a big time, a first day and a repeat rule ?>
          <fieldset class="reminder-step">
            <legend class="reminder-step-title">3. When?</legend>
            <div class="alarm-row">
              <div class="field">
                <label class="field-label" for="reminder-time">Time</label>
                <input class="input alarm-time" type="time" id="reminder-time" name="time" value="<?= e($reminder_values['time']) ?>" required<?= reminder_error_attrs('time', $reminder_errors) ?>>
                <?php if (isset($reminder_errors['time'])): ?>
                  <p class="field-error" id="reminder-time-error"><?= e($reminder_errors['time']) ?></p>
                <?php endif; ?>
              </div>
              <div class="field">
                <label class="field-label" for="reminder-start">Starting</label>
                <input class="input" type="date" id="reminder-start" name="start" value="<?= e($reminder_values['start']) ?>" min="<?= e($today) ?>" required<?= reminder_error_attrs('start', $reminder_errors) ?>>
                <?php if (isset($reminder_errors['start'])): ?>
                  <p class="field-error" id="reminder-start-error"><?= e($reminder_errors['start']) ?></p>
                <?php endif; ?>
              </div>
            </div>

            <div class="field">
              <label class="field-label" for="reminder-repeat">Repeat</label>
              <select class="input" id="reminder-repeat" name="repeat"<?= reminder_error_attrs('repeat', $reminder_errors) ?>>
                <?php foreach ($repeat_options as $value => $label): ?>
                  <option value="<?= e($value) ?>"<?= $value === $reminder_values['repeat'] ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($reminder_errors['repeat'])): ?>
                <p class="field-error" id="reminder-repeat-error"><?= e($reminder_errors['repeat']) ?></p>
              <?php endif; ?>
            </div>

            <?php // Shown only for "On chosen days" (data-show-when, app.js): the round day buttons of an alarm app ?>
            <div class="field" data-show-when="repeat=days">
              <span class="field-label" id="reminder-days-label">On these days</span>
              <div class="weekdays" role="group" aria-labelledby="reminder-days-label">
                <?php foreach ($weekdays as $value => $name): ?>
                  <label class="weekday">
                    <input class="visually-hidden" type="checkbox" name="days[]" value="<?= e($value) ?>"<?= in_array($value, $reminder_values['days'], true) ? ' checked' : '' ?><?= reminder_error_attrs('days', $reminder_errors) ?>>
                    <span aria-hidden="true"><?= e($name[0]) ?></span>
                    <span class="visually-hidden"><?= e($name) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <?php if (isset($reminder_errors['days'])): ?>
                <p class="field-error" id="reminder-days-error"><?= e($reminder_errors['days']) ?></p>
              <?php endif; ?>
            </div>

            <?php // Shown only for "Every few days" ?>
            <div class="field" data-show-when="repeat=interval">
              <label class="field-label" for="reminder-interval">Every how many days?</label>
              <span class="interval-row">
                <input class="input interval-input" type="number" id="reminder-interval" name="interval" value="<?= e($reminder_values['interval']) ?>" min="2" max="365" inputmode="numeric"<?= reminder_error_attrs('interval', $reminder_errors) ?>>
                days
              </span>
              <?php if (isset($reminder_errors['interval'])): ?>
                <p class="field-error" id="reminder-interval-error"><?= e($reminder_errors['interval']) ?></p>
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
  <?php endif; ?>

  <?php if ($toast): ?>
    <?php // The popup message after Add reminder or Mark done (CORE-23 toast): stays until the member closes it. Undo is a POST,
          // because it changes data. Without JavaScript the × is a link that loads the page again, without the toast. ?>
    <div class="toast-region">
      <div class="toast" role="status">
        <?= icon($toast['icon'] ?? 'bell') ?>
        <span class="toast-message"><?= e($toast['message']) ?></span>
        <?php if ($toast['key']): ?>
          <form method="post" action="<?= e(url('dashboard.php')) ?>">
            <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
            <input type="hidden" name="form" value="undo_reminder">
            <button class="toast-action">Undo</button>
          </form>
        <?php endif; ?>
        <a class="toast-close" href="<?= e(url('dashboard.php')) ?>" data-toast-close aria-label="Close message"><?= icon('x') ?></a>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
