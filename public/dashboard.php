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

$care_count = count($mock_reminders);   // due today or overdue
$has_plants = $current_user['plant_count'] > 0;

// Each plant appears once on the page: a plant already in Today's care is left out of Needs attention.
// Overdue care is therefore always in Today's care, so Needs attention only explains a Health Check or a note.
// Stage B: … AND plants.id NOT IN (the plant_id of every reminder due today or overdue).
$care_plant_ids  = array_column($mock_reminders, 'plant_id');
$attention       = array_values(array_filter($mock_attention, fn ($plant) => !in_array($plant['id'], $care_plant_ids, true)));
$attention_count = count($attention);

// Garden at a glance: each status's share of the garden becomes the width of its part of the bar
$health_counts = $mock_garden_stats['health_counts'];
$plant_total   = array_sum($health_counts);

// Exchange activity: "4 people are interested in your Pothos 'Golden' …", or "in 2 of your listings"
$interest_count  = count($mock_new_interests);
$interest_titles = array_unique(array_column($mock_new_interests, 'title'));
$interest_target = count($interest_titles) === 1
    ? 'your ' . reset($interest_titles)
    : count($interest_titles) . ' of your listings';

$page_title = 'Dashboard';
$page_css   = 'dashboard.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <?php // Just the greeting and the date: the counts are in the sections below, so they are not repeated here ?>
  <div class="dash-head">
    <h1 class="dash-title"><?= e($greeting) ?>, <?= e($first_name) ?></h1>
    <p class="dash-date"><?= e(date('l, j F')) ?></p>
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
              <?php foreach ($attention as $plant): ?>
                <?php
                // The reason: a Health Check result, else the newest note
                $from_check = $plant['log_source'] === 'ml' && $plant['predicted_label'] !== null;
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
        </section>
      <?php endif; ?>

      <?php if ($interest_count > 0): ?>
        <section class="panel" aria-labelledby="exchange-title">
          <h2 class="panel-title" id="exchange-title">Exchange activity</h2>
          <div class="exchange-news">
            <span class="avatar-stack">
              <?php foreach (array_slice($mock_new_interests, 0, 3) as $interest): ?>
                <?php partial('avatar', ['member' => $interest, 'size' => 'sm']); ?>
              <?php endforeach; ?>
            </span>
            <p><strong><?= e($interest_count) ?> <?= $interest_count === 1 ? 'person is' : 'people are' ?></strong> interested in <?= e($interest_target) ?></p>
          </div>
          <a class="btn btn-secondary btn-sm exchange-btn" href="<?= e(url('my-exchanges.php')) ?>">View interests</a>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
