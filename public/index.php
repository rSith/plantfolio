<?php
// Landing page (P01, wireframe p8): what PlantFolio is, on one screen, and one way in, "Start your garden" (CORE-27, UI half).
// Public page. Reads the counts, the avatar row and the collage from mock data; stage B swaps in queries.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage B: a signed-in member skips this page and goes to their Dashboard (wireframe p8):
//   if ($current_user) { header('Location: ' . url('dashboard.php')); exit; }
// Until dashboard.php exists, members see the Landing page too.

// "1,200+": rounded down to the hundred, so the claim stays true however many members there are.
$member_count = $mock_site_stats['member_count'];
$member_text  = $member_count >= 100
    ? number_format(intdiv($member_count, 100) * 100) . '+'
    : number_format($member_count);

// The collage previews the three core features with real rows: a plant profile, a reminder and an AI result.
$collage_plant = $mock_plants[array_search(42, array_column($mock_plants, 'id'))];           // Monty
$collage_care  = $mock_reminders[array_search(42, array_column($mock_reminders, 'plant_id'))]; // Monty's watering
$collage_due   = care_due($collage_care['next_due']);
$collage_when  = $collage_due['state'] === 'due' ? 'today' : '· ' . $collage_due['text'];   // "Water Monty today"
$prediction_percent = round($mock_prediction['confidence'] * 100);

$page_title = 'Plant care & exchange community';
$page_css   = 'landing.css';

include __DIR__ . '/../includes/header.php';
?>
<section class="hero">
  <div class="container hero-inner">
    <div class="hero-text">
      <p class="hero-tag"><?= icon('leaf') ?> Plant care &amp; exchange community</p>
      <h1 class="display hero-title">Where your plants tell their <em>story</em></h1>
      <p class="hero-lead">Give every plant its own profile, never miss a watering, and swap cuttings with plant lovers near you.</p>

      <div class="hero-actions">
        <a class="btn btn-primary btn-lg" href="<?= e(url('register.php')) ?>">Start your garden, it's free</a>
        <a class="btn btn-secondary btn-lg" href="<?= e(url('explore.php')) ?>">Explore plants</a>
      </div>

      <div class="hero-proof">
        <span class="avatar-stack">
          <?php foreach ($mock_recent_members as $member): ?>
            <?php partial('avatar', ['member' => $member, 'size' => 'sm']); ?>
          <?php endforeach; ?>
        </span>
        <p>Join <strong><?= e($member_text) ?></strong> plant lovers sharing their gardens</p>
      </div>
    </div>

    <?php // A picture made of real components. Hidden from screen readers: "How it works" says the same in words. ?>
    <div class="collage" aria-hidden="true">
      <div class="card collage-card collage-card-main">
        <div class="card-media">
          <img src="<?= e(photo_url($collage_plant['photo'])) ?>" alt="">
          <?php partial('status-badge', ['status' => $collage_plant['health_status']]); ?>
        </div>
        <div class="card-body">
          <p class="plant-card-name"><?= e($collage_plant['nickname']) ?></p>
          <p class="scientific-name"><?= e($collage_plant['scientific_name']) ?></p>
        </div>
      </div>

      <div class="card collage-card collage-card-side">
        <div class="card-media">
          <img src="<?= e(photo_url($mock_featured_plant['photo'])) ?>" alt="">
        </div>
        <div class="collage-card-owner">
          <?php partial('avatar', ['member' => $mock_featured_plant, 'size' => 'sm']); ?>
          <span class="collage-username">@<?= e($mock_featured_plant['username']) ?></span>
          <span class="collage-likes"><?= icon('heart') ?> <?= e($mock_featured_plant['like_count']) ?></span>
        </div>
      </div>

      <p class="collage-chip collage-chip-ai">
        <?= icon('sparkles') ?> Identified: <?= e($mock_prediction['common_name']) ?> · <?= e($prediction_percent) ?>%
      </p>
      <p class="collage-chip collage-chip-care collage-chip-<?= e($collage_due['state']) ?>">
        <?= icon(task_icon($collage_care['task'])) ?> <?= e(task_label($collage_care['task'])) ?> <?= e($collage_care['nickname']) ?> <?= e($collage_when) ?>
      </p>
    </div>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
