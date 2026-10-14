<?php
// Dashboard (P03, wireframe p10): the home screen after login — "What do my plants need today?" and
// "Is anything wrong?" in one glance (CORE-24, UI half). With no plants it shows state S1 instead (CORE-25).
// Members only. Reads the reminders, plants needing attention and garden counts from mock data.

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

// The summary chips count the same rows the sections below print, so the numbers always agree.
$care_count      = count($mock_reminders);   // due today or overdue
$attention_count = count($mock_attention);
$has_plants      = $current_user['plant_count'] > 0;

$page_title = 'Dashboard';
$page_css   = 'dashboard.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <div class="dash-head">
    <div>
      <h1 class="dash-title"><?= e($greeting) ?>, <?= e($first_name) ?></h1>
      <p class="dash-summary">
        <span class="dash-date"><?= e(date('l, j F')) ?></span>
        <?php if ($care_count > 0): ?>
          <span class="badge badge-due"><?= icon('droplet') ?> <?= e($care_count) ?> care <?= $care_count === 1 ? 'task' : 'tasks' ?> due</span>
        <?php endif; ?>
        <?php if ($attention_count > 0): ?>
          <span class="badge badge-warning"><?= icon('triangle-alert') ?> <?= e($attention_count) ?> <?= $attention_count === 1 ? 'plant needs' : 'plants need' ?> attention</span>
        <?php endif; ?>
      </p>
    </div>
    <?php if ($has_plants): ?>
      <?php // No reminders page exists yet; My Garden shows each plant's next care until one is decided ?>
      <a class="btn btn-secondary btn-sm dash-week" href="<?= e(url('garden.php')) ?>"><?= icon('calendar') ?> This week</a>
    <?php endif; ?>
  </div>

  <div class="dash-grid">
    <div class="dash-main">
      <section class="panel" aria-labelledby="care-title">
        <div class="panel-head">
          <h2 class="panel-title" id="care-title">Today's care
            <?php if ($care_count > 0): ?><span class="badge badge-neutral"><?= e($care_count) ?> due</span><?php endif; ?>
          </h2>
          <a class="panel-link" href="<?= e(url('garden.php')) ?>">All reminders</a>
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

        <a class="panel-foot" href="<?= e(url('garden.php')) ?>">
          <span>Coming up this week · <strong><?= e($mock_garden_stats['upcoming_count']) ?> <?= $mock_garden_stats['upcoming_count'] === 1 ? 'task' : 'tasks' ?></strong></span>
          <?= icon('chevron-right') ?>
        </a>
      </section>

      <?php if ($attention_count > 0): ?>
        <section class="panel" aria-labelledby="attention-title">
          <div class="panel-head">
            <h2 class="panel-title" id="attention-title">Needs attention</h2>
            <span class="panel-count"><?= e($attention_count) ?> <?= $attention_count === 1 ? 'plant' : 'plants' ?></span>
          </div>

          <div class="panel-list">
            <?php foreach ($mock_attention as $plant): ?>
              <?php
              // The reason, in order of importance: a Health Check result, else overdue care, else the newest note
              $from_check = $plant['log_source'] === 'ml' && $plant['predicted_label'] !== null;
              $due        = $plant['next_due'] ? care_due($plant['next_due']) : null;
              $overdue    = $due && $due['state'] === 'overdue';
              $late_days  = $overdue ? -days_until($plant['next_due']) : 0;
              $late_text  = $late_days . ($late_days === 1 ? ' day' : ' days');   // "Watering is 2 days overdue"
              $plant_url  = url('plant.php?id=' . $plant['id']);
              // A Health Check result is always a disease prediction
              $condition  = $from_check ? prediction_name(['type' => 'disease', 'predicted_label' => $plant['predicted_label']]) : '';
              ?>
              <article class="attention-item">
                <img class="attention-photo" src="<?= e(photo_url($plant['photo'])) ?>" alt="Photo of <?= e($plant['nickname']) ?>">
                <div class="attention-body">
                  <h3 class="attention-name">
                    <a href="<?= e($plant_url) ?>"><?= e($plant['nickname']) ?></a>
                    <?php partial('status-badge', ['status' => $plant['health_status']]); ?>
                  </h3>
                  <p class="attention-meta"><?= e($plant['common_name']) ?> · <?= e($plant['collection_name']) ?></p>

                  <p class="attention-reason">
                    <?php if ($from_check): ?>
                      <?= icon('sparkles') ?>
                      <span>Health check found <strong><?= e(mb_strtolower($condition)) ?></strong>
                        (<?= e(round($plant['confidence'] * 100)) ?>% confidence) on <?= e(format_day($plant['logged_at'])) ?>.</span>
                    <?php elseif ($overdue): ?>
                      <?= icon(task_icon($plant['task'])) ?>
                      <span><?= e($plant['task'] === 'water' ? 'Watering' : 'Fertilising') ?> is <?= e($late_text) ?> overdue. <?= e($plant['log_note']) ?></span>
                    <?php else: ?>
                      <?= icon('info') ?>
                      <span><?= e($plant['log_note']) ?></span>
                    <?php endif; ?>
                  </p>

                  <div class="attention-actions">
                    <?php if ($from_check): ?>
                      <?php // The care plan lives on the plant's profile; "Log update" opens a modal there from week 5 ?>
                      <a class="btn btn-primary btn-sm" href="<?= e($plant_url) ?>">View care plan</a>
                      <a class="btn btn-secondary btn-sm" href="<?= e($plant_url) ?>">Log update</a>
                    <?php elseif ($overdue): ?>
                      <?php // Stage F: does nothing yet. Stage B saves it like Mark done (CORE-23). ?>
                      <button type="button" class="btn btn-secondary btn-sm"><?= icon(task_icon($plant['task'])) ?> <?= e($plant['task'] === 'water' ? 'Mark watered' : 'Mark fertilised') ?></button>
                    <?php else: ?>
                      <a class="btn btn-secondary btn-sm" href="<?= e($plant_url) ?>">Log update</a>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
