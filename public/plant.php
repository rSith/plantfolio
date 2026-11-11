<?php
// Plant Profile (P05, wireframe p12): one page per plant that tells its story — photos, identity, care needs,
// reminders and a dated health timeline (CORE-18, UI half). Simplified on 2026-11-11: each fact is shown once,
// so the care tiles and the Reminders and Photos tabs were removed. plant.php?id=42 shows Monty.
// Logged in · public read: anyone may open a public plant and sees it read-only, with an owner card and a like
// button (CORE-21, UI half); a private plant is "not found" to everyone but its owner.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// ── Which plant ─────────────────────────────────────────
// Stage B: SELECT plants.*, plant_species.*, collections.name … WHERE plants.id = ? (one prepared query).

// The mock rows are split in two (Nimali's plants and other members'); the plants table holds them all.
$plant_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$plant    = null;
foreach (array_merge($mock_plants, $mock_community_plants) as $row) {
    if ($row['id'] === $plant_id) {
        $plant = $row;
        break;
    }
}

$is_owner = $plant && $current_user && $plant['user_id'] === $current_user['id'];

// A private plant answers exactly like a missing one, so nobody can even learn that it exists (privacy rule).
if (!$plant || (!$plant['is_public'] && !$is_owner)) {
    http_response_code(404);   // tells browsers and search engines that the page does not exist
    $page_title = 'Plant not found';
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="container page">
      <section class="panel">
        <?php partial('empty-state', [
            'heading' => 'h1',   // the whole page: its one main heading
            'title'   => 'Plant not found',
            'text'    => 'This plant does not exist, or its owner keeps it private.',
            'actions' => $current_user
                ? [['label' => 'Back to My Garden', 'href' => 'garden.php', 'icon' => 'leaf']]
                : [['label' => 'Explore plants', 'href' => 'explore.php', 'icon' => 'search']],
        ]); ?>
      </section>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// ── Everything shown about it ───────────────────────────
// Each list is one table filtered by plant_id: WHERE plant_id = ? in stage B.

$species = $mock_species[$plant['species_id']] ?? null;

$reminders = array_values(array_filter($mock_plant_reminders, fn ($r) => $r['plant_id'] === $plant['id']));
usort($reminders, fn ($a, $b) => $a['next_due'] <=> $b['next_due']);   // soonest first

$story  = array_values(array_filter($mock_health_logs, fn ($log) => $log['plant_id'] === $plant['id']));   // newest first
$photos = array_values(array_filter($mock_plant_photos, fn ($photo) => $photo['plant_id'] === $plant['id']));
if (!$photos) {
    // No gallery rows yet: the profile photo is the only one
    $photos = [['id' => 0, 'plant_id' => $plant['id'], 'path' => $plant['photo'], 'created_at' => $plant['acquired_on']]];
}
// The thumbnail of the photo the large frame shows first, so it can be marked as chosen
$shown_photo = array_search($plant['photo'], array_column($photos, 'path'), true);
$like_count = $mock_like_counts[$plant['id']] ?? 0;

// The owner, joined from users (public columns only). Visitors see an owner card instead of the edit controls.
$owner     = $mock_users[$plant['user_id']];
$owner_url = url('user.php?u=' . urlencode($owner['username']));

// Tabs (p12, element 6): links with ?tab=, so each tab has an address and works without JavaScript.
// #plant-tabs makes the browser scroll down to the tabs instead of the top of the page.
// Only two: reminders are in the side card and photos in the gallery and the story, so their tabs only repeated them.
$tabs = [
    'story' => 'Story',
    'care'  => 'Care',
];
$tab = get_text('tab');   // tab[]=x or tab=%FF count as "not given" (helpers.php), so they fall back to Story
if (!array_key_exists($tab, $tabs)) {
    $tab = 'story';
}

/** The words in front of a story entry: the oldest is the day the plant arrived, an AI diagnosis is a Health check. */
function story_kind($log, $is_oldest)
{
    if ($is_oldest) {
        return 'Added to garden';
    }
    return $log['prediction_type'] === 'disease' ? 'Health check' : 'Update';
}

$collection_url = url('garden.php?collection=' . $plant['collection_id']);
$page_title     = $plant['nickname'];
$page_css       = 'plant.css';

// The navigation follows the breadcrumb: your own plant is part of My Garden, anyone else's is part of Explore
$nav_area = $is_owner ? 'garden' : 'explore';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <?php // The owner sees where the plant lives in their garden; anyone else sees whose plant it is ?>
  <nav aria-label="Breadcrumb">
    <ol class="breadcrumb plant-breadcrumb">
      <?php if ($is_owner): ?>
        <li><a href="<?= e(url('garden.php')) ?>">My Garden</a></li>
        <?php // A plant in no collection sits straight under My Garden ?>
        <?php if ($plant['collection_id'] !== null): ?>
          <li><a href="<?= e($collection_url) ?>"><?= e($plant['collection_name']) ?></a></li>
        <?php endif; ?>
      <?php else: ?>
        <li><a href="<?= e(url('explore.php')) ?>">Explore</a></li>
        <li><a href="<?= e($owner_url) ?>">@<?= e($owner['username']) ?></a></li>
      <?php endif; ?>
      <li><span aria-current="page"><?= e($plant['nickname']) ?></span></li>
    </ol>
  </nav>

  <div class="plant-top">
    <?php // Photo gallery (p12, element 2): the profile photo large, then thumbnails and the one Add tile.
          // A thumbnail is a button that shows its photo in the large frame (app.js, data-gallery). ?>
    <div class="plant-gallery" data-gallery>
      <img class="plant-photo" src="<?= e(photo_url($plant['photo'])) ?>" alt="Photo of <?= e($plant['nickname']) ?>, a <?= e($plant['common_name']) ?>" data-gallery-main>
      <div class="plant-thumbs">
        <?php foreach (array_slice($photos, 0, 4) as $number => $photo): ?>
          <button type="button" class="plant-thumb" data-photo-src="<?= e(photo_url($photo['path'])) ?>" data-photo-alt="Photo of <?= e($plant['nickname']) ?>, <?= e(date('j M Y', strtotime($photo['created_at']))) ?>" aria-pressed="<?= $number === $shown_photo ? 'true' : 'false' ?>">
            <img src="<?= e(photo_url($photo['path'])) ?>" alt="Show photo <?= e($number + 1) ?> of <?= e(count($photos)) ?>, <?= e(date('j M Y', strtotime($photo['created_at']))) ?>" width="80" height="80" loading="lazy">
          </button>
        <?php endforeach; ?>
        <?php if ($is_owner): ?>
          <?php // Adding a photo is part of the edit form until uploads are wired in stage B (CORE-17) ?>
          <a class="plant-thumb plant-thumb-add" href="<?= e(url('plant-form.php?id=' . $plant['id'] . '#photo')) ?>"><?= icon('camera') ?> Add</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="plant-info">
      <?php // Identity and status (p12, element 3) ?>
      <h1 class="plant-name"><?= e($plant['nickname']) ?></h1>
      <p class="plant-species"><span class="scientific-name"><?= e($plant['scientific_name']) ?></span> · <?= e($plant['common_name']) ?></p>
      <?php // No collection here: the breadcrumb above already names it ?>
      <p class="plant-meta">
        <?php partial('status-badge', ['status' => $plant['health_status']]); ?>
        <span>Updated <?= e(mb_strtolower(time_ago($plant['last_logged_at']))) ?> · <?= $is_owner ? 'With you' : 'Growing' ?> since <?= e(date('M Y', strtotime($plant['acquired_on']))) ?></span>
      </p>

      <?php if ($is_owner): ?>
        <?php // Primary actions (p12, element 4): one green button; the rest are secondary or in the ⋯ menu ?>
        <div class="plant-actions">
          <?php // Stage F: does nothing yet. Week 5 opens the Log a health update dialog (S5, CORE-19). ?>
          <button type="button" class="btn btn-primary"><?= icon('pencil') ?> Log update</button>
          <a class="btn btn-secondary" href="<?= e(url('health-check.php?plant=' . $plant['id'])) ?>"><?= icon('activity') ?> Health check</a>
          <a class="btn btn-secondary" href="<?= e(url('listing-form.php?plant=' . $plant['id'])) ?>"><?= icon('repeat') ?> List for exchange</a>
          <?php partial('plant-menu', ['plant' => $plant]); ?>
        </div>
      <?php else: ?>
        <?php // Read-only view (p12, UX notes): a like button instead of the edit controls ?>
        <div class="plant-actions">
          <?php if ($current_user): ?>
            <?php // Stage F: does nothing yet. Stage B saves the like (COM-15, a Could task). ?>
            <button type="button" class="btn btn-secondary" aria-pressed="false"><?= icon('heart') ?> Like · <?= e($like_count) ?></button>
          <?php else: ?>
            <a class="btn btn-secondary" href="<?= e(url('login.php')) ?>"><?= icon('heart') ?> Log in to like · <?= e($like_count) ?></a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <div class="plant-bottom">
    <div class="plant-main">
      <nav class="tabs" id="plant-tabs" aria-label="About this plant">
        <?php foreach ($tabs as $key => $label): ?>
          <a class="tab<?= $key === $tab ? ' is-active' : '' ?>" href="<?= e(url('plant.php?id=' . $plant['id'] . ($key === 'story' ? '' : '&tab=' . $key) . '#plant-tabs')) ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>>
            <?= e($label) ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="tab-panel">
        <?php if ($tab === 'story'): ?>
          <?php // Plant story (p12, element 6): newest first; an AI entry carries a sparkle and its confidence ?>
          <?php if (!$story): ?>
            <p class="panel-empty"><?= icon('info') ?> No story entries yet.</p>
          <?php endif; ?>
          <ol class="story">
            <?php foreach ($story as $number => $log): ?>
              <?php $is_oldest = $number === count($story) - 1; ?>
              <li class="story-entry">
                <p class="story-date"><?= e(date('j M Y', strtotime($log['logged_at']))) ?> · <?= e(story_kind($log, $is_oldest)) ?></p>
                <div class="story-card">
                  <?php if ($log['photo']): ?>
                    <img class="thumb" src="<?= e(photo_url($log['photo'])) ?>" alt="">
                  <?php endif; ?>
                  <div class="story-body">
                    <?php partial('status-badge', ['status' => $log['status']]); ?>
                    <p class="story-note"><?= e($log['note']) ?></p>
                    <?php if ($log['source'] === 'ml'): ?>
                      <?php $percent = round($log['confidence'] * 100); ?>
                      <p class="story-ai">
                        <?= icon('sparkles') ?>
                        <?php // One <span>: the line is a flex row, which would put a gap between every bit of text ?>
                        <span>
                          <?php if ($log['prediction_type'] === 'species'): ?>
                            Species identified by AI: <em><?= e($log['predicted_label']) ?></em> · <?= e($percent) ?>% confidence
                          <?php else: ?>
                            Health check found <?= e(mb_strtolower(prediction_name(['type' => 'disease', 'predicted_label' => $log['predicted_label']]))) ?> · <?= e($percent) ?>% confidence
                          <?php endif; ?>
                        </span>
                      </p>
                    <?php endif; ?>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>

        <?php else: ?>
          <?php if ($species): ?>
            <dl class="care-guide">
              <div><dt><?= icon('sun') ?> Light</dt><dd><?= e($species['light']) ?></dd></div>
              <div><dt><?= icon('droplet') ?> Water</dt><dd><?= e($species['water']) ?></dd></div>
              <div><dt><?= icon('droplets') ?> Humidity</dt><dd><?= e($species['humidity']) ?></dd></div>
              <div><dt><?= icon('paw-print') ?> Pets</dt><dd><?= $species['pet_safe'] ? 'Safe for cats and dogs' : 'Not safe for cats and dogs: keep it out of reach' ?></dd></div>
              <div><dt><?= icon('calendar') ?> Usual schedule</dt><dd>Water every <?= e($species['water_interval_days']) ?> days · fertilise every <?= e($species['fertilise_interval_days']) ?> days</dd></div>
            </dl>
            <p class="care-notes"><?= e($species['notes']) ?></p>
          <?php else: ?>
            <p class="panel-empty"><?= icon('info') ?> No care guide for this species yet.</p>
          <?php endif; ?>

        <?php endif; ?>
      </div>
    </div>

    <?php if ($is_owner): ?>
      <?php // Reminders card (p12, element 7): active reminders with their next dates, visibility and likes ?>
      <aside class="panel plant-side" aria-labelledby="reminders-title">
        <div class="panel-head">
          <h2 class="panel-title" id="reminders-title">Reminders</h2>
          <a class="panel-link" href="<?= e(url('plant-form.php?id=' . $plant['id'] . '#reminders')) ?>"><?= icon('plus') ?> Add</a>
        </div>
        <ul class="side-reminders">
          <?php foreach ($reminders as $reminder): ?>
            <?php $due = care_due($reminder['next_due']); ?>
            <li>
              <span><?= task_name($reminder['task']) ?> · <?= e(repeat_words($reminder)) ?></span>
              <span class="side-due side-due-<?= e($due['state']) ?>"><?= e($due['state'] === 'upcoming' ? format_day($reminder['next_due']) : ($due['state'] === 'due' ? 'Today' : $due['text'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <ul class="plant-facts">
          <li>
            <?php if ($plant['is_public']): ?>
              <?= icon('eye') ?> Public · shown in Explore
            <?php else: ?>
              <?= icon('lock') ?> Private · only you can see it
            <?php endif; ?>
          </li>
          <?php if ($plant['is_public']): ?>
            <li><?= icon('heart') ?> <?= e($like_count) ?> <?= $like_count === 1 ? 'like' : 'likes' ?> from the community</li>
          <?php endif; ?>
        </ul>
      </aside>
    <?php else: ?>
      <?php // Owner card (p12, UX notes): whose plant this is. No contact details: those belong to the exchange. ?>
      <aside class="panel plant-side owner-card" aria-labelledby="owner-title">
        <h2 class="label" id="owner-title">Grown by</h2>
        <div class="owner-card-member">
          <?php partial('avatar', ['member' => $owner]); ?>
          <span>
            <strong><?= e($owner['full_name']) ?></strong>
            <span class="text-small">@<?= e($owner['username']) ?> · <?= e($owner['location']) ?></span>
          </span>
        </div>
        <ul class="plant-facts">
          <li><?= icon('leaf') ?> <?= e($owner['plant_count']) ?> plants · member since <?= e(date('M Y', strtotime($owner['created_at']))) ?></li>
          <li><?php partial('rating', ['rating' => $owner]); ?> as a trader</li>
          <li><?= icon('heart') ?> <?= e($like_count) ?> <?= $like_count === 1 ? 'like' : 'likes' ?> from the community</li>
        </ul>
        <a class="btn btn-secondary owner-card-btn" href="<?= e($owner_url) ?>">View profile</a>
      </aside>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
