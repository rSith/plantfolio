<?php
// Dashboard (P03, wireframe p10): the home screen after login — "What do my plants need today?" and
// a garden overview in one glance (CORE-24, UI half). With no plants it shows state S1 instead (CORE-25).
// Members only. Reads the reminders and garden counts from mock data.
// No "Needs attention" panel (p10, element 4): the member learns a plant is unwell from a Health Check they run
// themselves, not from the Dashboard guessing for them.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';
require_once __DIR__ . '/../includes/reminder-form.php';   // Add a reminder: checks, saving, toast

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
// The checks and the saving are shared with the plant pages (includes/reminder-form.php). Stage F keeps the
// reminder in the session, so it shows in All reminders and on the plant's page. Stage B: INSERT … (CORE-22).

// The member's own plants, A to Z, for the plant picker. Only these ids are accepted (ownership check).
$pick_plants = $mock_plants;
usort($pick_plants, fn ($a, $b) => strcasecmp($a['nickname'], $b['nickname']));

$reminder_values = reminder_defaults();
$reminder_errors = [];   // field name => message, printed under that field in the popup

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_text('form') === 'reminder') {
    [$reminder_values, $reminder_errors] = reminder_form_save($plants_by_id);
    if (!$reminder_errors) {
        header('Location: ' . url('dashboard.php'));   // POST, then redirect: a reload cannot add it twice
        exit;
    }
}

// ── Mark done (CORE-23) ─────────────────────────────────
// Shared with the Plant Profile: reminder_mark_done() in includes/reminder-form.php moves the date on, adds a care
// line to the plant's Story and leaves the toast. Only a reminder in Today's care (Nimali's, due today or overdue)
// can be marked here.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_text('form') === 'done') {
    reminder_mark_done(post_text('reminder'), $mock_reminders);
    header('Location: ' . url('dashboard.php'));
    exit;
}

// Undo in the toast: takes back the last Add reminder or Mark done (reminder_undo(), includes/reminder-form.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_text('form') === 'undo_reminder') {
    reminder_undo();
    header('Location: ' . url('dashboard.php'));
    exit;
}

// The toast shows once; its Undo stays possible until the next Add reminder or Mark done
$toast = reminder_toast_take();

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
      <?php // S1 (a new member, no plants yet): the same boxes and options, each with a short first-step message ?>
        <?php // Both boxes are .dash-box: on a desktop they are the same fixed height, side by side (dashboard.css) ?>
        <section class="panel dash-box" aria-labelledby="care-title">
          <div class="panel-head">
            <h2 class="panel-title" id="care-title">Today's care
              <?php if ($care_count > 0): ?><span class="badge badge-neutral"><?= e($care_count) ?> due</span><?php endif; ?>
            </h2>
            <?php // app.js opens the All reminders popup; without JavaScript the link shows My Garden sorted by next care ?>
            <a class="panel-link" href="<?= e(url('garden.php?sort=care')) ?>" data-dialog="all-reminders" aria-haspopup="dialog">All reminders</a>
          </div>

          <div class="dash-box-body">
          <?php if ($care_count > 0): ?>
            <?php // Three tasks show; more scroll inside the box, with no scrollbar drawn (.scroll-quiet) ?>
            <div class="care-list scroll-quiet">
              <?php foreach ($mock_reminders as $reminder): ?>
                <?php partial('care-row', ['reminder' => $reminder]); ?>
              <?php endforeach; ?>
            </div>
          <?php elseif (!$has_plants): ?>
            <p class="panel-empty"><?= icon('sprout') ?> No reminders yet. Start by adding a plant.</p>
          <?php else: ?>
            <p class="panel-empty"><?= icon('check') ?> Nothing due today. Your plants are all looked after.</p>
          <?php endif; ?>
          </div>
          <?php // No "Coming up this week" row: All reminders (top right) already shows the days ahead ?>

          <?php // Opens the Add a reminder popup below (app.js) ?>
          <button type="button" class="dash-add-btn" data-dialog="add-reminder" aria-haspopup="dialog"><?= icon('plus') ?> Add a reminder</button>
        </section>
    </div>

    <?php // No Quick actions panel: Add plant is at the bottom of Garden at a glance, the AI tools in Plant Tools ?>
    <div class="dash-side">
        <section class="panel dash-box" aria-labelledby="glance-title">
          <div class="panel-head">
            <h2 class="panel-title" id="glance-title">Garden at a glance</h2>
          </div>
          <div class="dash-box-body">
          <?php if (!$has_plants): ?>
            <p class="panel-empty"><?= icon('sprout') ?> No plants yet. Start by adding your first plant.</p>
          <?php else: ?>
          <p class="glance-total">
            <strong><?= e($current_user['plant_count']) ?></strong>
            <span>plants in<br><?= e($current_user['collection_count']) ?> collections</span>
          </p>

          <?php // Where the plants are: one line per collection with its count and a few photos; each opens it in My Garden.
                // Stage B: the collections query My Garden already uses (plant_count and three cover photos per row). ?>
          <ul class="glance-collections scroll-quiet">
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
          <?php endif; ?>
          </div>

          <?php // The box ends like Today's care: a dashed add button, here to Add Plant (P06) ?>
          <a class="dash-add-btn" href="<?= e(url('plant-form.php')) ?>"><?= icon('plus') ?> Add plant</a>
        </section>
    </div>
  </div>

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
          <p class="panel-empty"><?= icon('info') ?> No reminders yet.</p>
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

    <?php // Add a reminder popup (partials/reminder-dialog.php), shared with the plant pages ?>
    <?php partial('reminder-dialog', [
        'action' => url('dashboard.php'),
        'values' => $reminder_values,
        'errors' => $reminder_errors,
        'plants' => $pick_plants,
        'plant'  => null,
    ]); ?>

  <?php if ($toast): ?>
    <?php partial('reminder-toast', ['toast' => $toast, 'action' => url('dashboard.php')]); ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
