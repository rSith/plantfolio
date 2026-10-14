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

// One ML result of each type: the species result feeds the collage chip, both feed the smart tools teaser.
$species_result = $mock_predictions[array_search('species', array_column($mock_predictions, 'type'))];
$disease_result = $mock_predictions[array_search('disease', array_column($mock_predictions, 'type'))];
$species_plant  = $mock_plants[array_search($species_result['plant_id'], array_column($mock_plants, 'id'))];
$disease_plant  = $mock_plants[array_search($disease_result['plant_id'], array_column($mock_plants, 'id'))];

// "Fresh on the exchange": the four newest open listings (wireframe p8, UX notes).
// Stage B: … WHERE status = 'open' ORDER BY created_at DESC LIMIT 4. The mock array is already newest first.
$fresh_listings = array_slice($mock_listings, 0, 4);

// "How it works": three steps that mirror the product, add → track → swap (wireframe p8, element 4).
$steps = [
    ['icon' => 'camera',  'title' => 'Add your plants',
     'text' => 'Snap a photo and PlantFolio suggests the species and fills in its care guide.'],
    ['icon' => 'droplet', 'title' => 'Track their care',
     'text' => 'See what to water today and keep a health story for every plant.'],
    ['icon' => 'repeat',  'title' => 'Share and swap',
     'text' => 'Show off your collection and trade cuttings with people nearby.'],
];

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
        <?= icon('sparkles') ?> Identified: <?= e(prediction_name($species_result)) ?> · <?= e(round($species_result['confidence'] * 100)) ?>%
      </p>
      <p class="collage-chip collage-chip-care collage-chip-<?= e($collage_due['state']) ?>">
        <?= icon(task_icon($collage_care['task'])) ?> <?= e(task_label($collage_care['task'])) ?> <?= e($collage_care['nickname']) ?> <?= e($collage_when) ?>
      </p>
    </div>
  </div>
</section>

<?php // The header's "How it works" link jumps here (index.php#how-it-works) ?>
<section class="landing-section how" id="how-it-works" aria-labelledby="how-title">
  <div class="container">
    <div class="section-head">
      <p class="label">How it works</p>
      <h2 id="how-title">Three simple habits for happier plants</h2>
    </div>
    <ol class="steps">
      <?php foreach ($steps as $number => $step): ?>
        <li class="step">
          <span class="step-icon"><?= icon($step['icon']) ?></span>
          <h3><?= e($number + 1) ?> · <?= e($step['title']) ?></h3>
          <p><?= e($step['text']) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="landing-section tools" aria-labelledby="tools-title">
  <div class="container">
    <div class="section-head">
      <p class="label"><?= icon('sparkles') ?> Smart tools</p>
      <h2 id="tools-title">A second opinion when you need one</h2>
      <p class="section-lead">Two helpers built on machine learning. They suggest, you decide: nothing is saved until you press Save,
        and when the model is not sure, it says so.</p>
    </div>
    <div class="tool-grid">
      <article class="tool-card">
        <span class="step-icon"><?= icon('sparkles') ?></span>
        <h3>Plant Identifier</h3>
        <p>Not sure what you brought home? Take a photo and get the three most likely species, each with how sure the model is.</p>
        <div class="tool-example">
          <p class="tool-example-name">
            <?= e($species_plant['nickname']) ?> looks like a <strong><?= e(prediction_name($species_result)) ?></strong>
            <span class="scientific-name"><?= e($species_result['predicted_label']) ?></span>
          </p>
          <?php partial('confidence-meter', ['prediction' => $species_result]); ?>
        </div>
        <a class="tool-link" href="<?= e(url('identify.php')) ?>">Try Plant Identifier <?= icon('arrow-right') ?></a>
      </article>

      <article class="tool-card">
        <span class="step-icon"><?= icon('activity') ?></span>
        <h3>Health Check</h3>
        <p>Spots or yellow leaves? Get the likely cause and simple care steps, then add the result to your plant's story.</p>
        <div class="tool-example">
          <p class="tool-example-name">
            <?= e($disease_plant['nickname']) ?> the <?= e(strtolower($disease_plant['common_name'])) ?>: <strong><?= e(prediction_name($disease_result)) ?></strong> found
          </p>
          <?php partial('confidence-meter', ['prediction' => $disease_result]); ?>
        </div>
        <a class="tool-link" href="<?= e(url('health-check.php')) ?>">Try Health Check <?= icon('arrow-right') ?></a>
      </article>
    </div>
  </div>
</section>

<section class="landing-section fresh" aria-labelledby="fresh-title">
  <div class="container">
    <div class="fresh-head">
      <div>
        <p class="label">Fresh on the exchange</p>
        <h2 id="fresh-title">Plants looking for a new home</h2>
      </div>
      <a class="tool-link" href="<?= e(url('exchange.php')) ?>">See all <?= e($mock_site_stats['open_listing_count']) ?> listings <?= icon('arrow-right') ?></a>
    </div>
    <div class="fresh-grid">
      <?php foreach ($fresh_listings as $listing): ?>
        <?php partial('listing-card', ['listing' => $listing]); ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
