<?php
// Plant Profile (P05, wireframe p12): one page per plant that tells its story — photos, identity, care needs,
// reminders and a dated health timeline (CORE-18, UI half). Simplified on 2026-11-11: each fact is shown once,
// so the care tiles and the Reminders and Photos tabs were removed. plant.php?id=42 shows Monty.
// Logged in · public read: anyone may open a public plant and sees it read-only, with an owner card and a like
// button (CORE-21, UI half); a private plant is "not found" to everyone but its owner.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';
require_once __DIR__ . '/../includes/care-guide.php';

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

$reminders = array_values(array_filter($mock_plant_reminders, fn ($r) => $r['plant_id'] === $plant['id']));
usort($reminders, fn ($a, $b) => $a['next_due'] <=> $b['next_due']);   // soonest first

$story  = array_values(array_filter($mock_health_logs, fn ($log) => $log['plant_id'] === $plant['id']));
usort($story, fn ($a, $b) => $b['logged_at'] <=> $a['logged_at']);   // newest first (stage B: ORDER BY logged_at DESC)
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

// No tabs any more (Ranshitha's sketch, 2026-11-11): the care guide sits under the name, so the Story is the only
// long section left and needs no tab. An old link with ?tab=care or ?tab=photos simply shows the page.

// AI care guide (under the name): one saved guide per species. Stage B: care_guide_get() reads the care_guides row;
// when there is none, the card shows Generating and care-guide.js asks api/care-guide.php for it.
$guide_species = care_guide_species($plant);
$guide_row     = $guide_species === null ? null : ($mock_care_guides[normalise_species($guide_species)] ?? null);
$guide         = $guide_row ? json_decode($guide_row['guide_json'], true) : null;
if ($guide_species === null) {
    $guide_state = 'no-species';
} else {
    $guide_state = $guide ? 'ready' : 'generating';
}
// Stage F previews of the other states, like identify.php?offline: &guide=generating, offline, invalid or no-species
$guide_preview = get_text('guide');
if (in_array($guide_preview, ['generating', 'offline', 'invalid', 'no-species'], true)) {
    $guide_state = $guide_preview;
}

// Story stats for the side panel. Stage B: small COUNT() queries, or counted from the rows already loaded.
$this_month    = date('Y-m');
$watered_month = count(array_filter($story, fn ($log) => $log['type'] === 'care' && $log['care_task'] === 'water'
    && substr($log['logged_at'], 0, 7) === $this_month));
$last_update   = $story ? $story[0]['logged_at'] : null;

$collection_url = url('garden.php?collection=' . $plant['collection_id']);
$page_title     = $plant['nickname'];
$page_css       = 'plant.css';
$page_js        = 'story.js';   // story filters and the reminders' Done buttons (UI only in stage F)

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

    <?php // Right column (phone: straight after the photo): who it is, then how to care for it ?>
    <div class="plant-info">
      <?php // Identity (p12, element 3). Owner: Edit at the top right, Health check and List for exchange under the
            // "Updated" line. Phone: the name first, then all buttons in one wrapping row. No status badge here:
            // each Story entry shows the status it was given. ?>
      <div class="plant-head">
        <div class="plant-identity">
          <h1 class="plant-name"><?= e($plant['nickname']) ?></h1>
          <p class="plant-species"><span class="scientific-name"><?= e($plant['scientific_name']) ?></span> · <?= e($plant['common_name']) ?></p>
          <p class="plant-meta">Updated <?= e(mb_strtolower(time_ago($plant['last_logged_at']))) ?></p>
        </div>
        <?php if ($is_owner): ?>
          <a class="btn btn-primary plant-edit-btn" href="<?= e(url('plant-form.php?id=' . $plant['id'])) ?>"><?= icon('pencil') ?> Edit</a>
          <div class="plant-tools">
            <a class="btn btn-secondary btn-sm" href="<?= e(url('health-check.php?plant=' . $plant['id'])) ?>"><?= icon('activity') ?> Health check</a>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('listing-form.php?plant=' . $plant['id'])) ?>"><?= icon('repeat') ?> List for exchange</a>
          </div>
        <?php else: ?>
          <?php // Read-only view (p12, UX notes): a like button instead of the edit controls ?>
          <div class="plant-tools">
            <?php if ($current_user): ?>
              <?php // Stage F: does nothing yet. Stage B saves the like (COM-15, a Could task). ?>
              <button type="button" class="btn btn-secondary btn-sm" aria-pressed="false"><?= icon('heart') ?> Like · <?= e($like_count) ?></button>
            <?php else: ?>
              <a class="btn btn-secondary btn-sm" href="<?= e(url('login.php')) ?>"><?= icon('heart') ?> Log in to like · <?= e($like_count) ?></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php // How to care for it ?>
      <?php partial('care-guide', [
          'plant'     => $plant,
          'is_owner'  => $is_owner,
          'state'     => $guide_state,
          'guide'     => $guide,
          'guide_row' => $guide_row,
          'species'   => $guide_species,
      ]); ?>
    </div>

    <?php // Left column under the photo on a desktop; after the care guide on a phone ?>
    <div class="plant-aside">
      <?php // Quick facts: the small facts that used to be spread over the meta line and the Reminders card ?>
      <section class="panel plant-panel" aria-labelledby="facts-title">
        <h2 class="panel-title" id="facts-title">Quick facts</h2>
        <dl class="quick-facts">
          <?php if ($is_owner): ?>
            <?php // Only the owner sees the collection: a visitor's breadcrumb shows the owner, not their collections ?>
            <div>
              <dt><?= icon('layout-grid') ?> Collection</dt>
              <dd>
                <?php if ($plant['collection_id'] !== null): ?>
                  <a href="<?= e($collection_url) ?>"><?= e($plant['collection_name']) ?></a>
                <?php else: ?>
                  No collection
                <?php endif; ?>
              </dd>
            </div>
          <?php endif; ?>
          <div>
            <dt><?= icon('calendar') ?> <?= $is_owner ? 'With you since' : 'Growing since' ?></dt>
            <dd><?= e(date('M Y', strtotime($plant['acquired_on']))) ?></dd>
          </div>
          <?php if ($is_owner): ?>
            <div>
              <dt><?= icon($plant['is_public'] ? 'eye' : 'lock') ?> Visibility</dt>
              <dd><?= $plant['is_public'] ? 'Public · shown in Explore' : 'Private · only you can see it' ?></dd>
            </div>
          <?php endif; ?>
          <?php if ($plant['is_public']): ?>
            <div>
              <dt><?= icon('heart') ?> Likes</dt>
              <dd><?= e($like_count) ?> from the community</dd>
            </div>
          <?php endif; ?>
        </dl>
      </section>

      <?php if ($is_owner): ?>
        <?php // Reminders card (p12, element 7): active reminders with their next dates ?>
        <section class="panel plant-panel" aria-labelledby="reminders-title">
          <div class="panel-head">
            <h2 class="panel-title" id="reminders-title">Reminders</h2>
            <a class="panel-link" href="<?= e(url('plant-form.php?id=' . $plant['id'] . '#reminders')) ?>"><?= icon('plus') ?> Add</a>
          </div>
          <?php // Done ✓ (CORE-23, UI half): story.js adds a "Watered" line to the Story and moves the date forward
                // without a reload. Stage F changes the page only; stage B saves both (like the Dashboard's Mark done). ?>
          <ul class="side-reminders">
            <?php foreach ($reminders as $reminder): ?>
              <?php $due = due_label($reminder['next_due']); ?>
              <li data-reminder data-task="<?= e($reminder['task']) ?>" data-interval="<?= e($reminder['interval_days']) ?>" data-done-label="<?= e(care_done_label($reminder['task'])) ?>">
                <span class="side-reminder-text"><?= task_name($reminder['task']) ?> · <?= e(repeat_words($reminder)) ?></span>
                <span class="side-due side-due-<?= e($due['state']) ?>" data-due><?= e($due['text']) ?></span>
                <button type="button" class="btn btn-secondary btn-sm side-done" data-reminder-done aria-label="Done: <?= e(task_label($reminder['task'])) ?> <?= e($plant['nickname']) ?>"><?= icon('check') ?> Done</button>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="visually-hidden" role="status" data-story-status></p>
        </section>
      <?php else: ?>
        <?php // Owner card (p12, UX notes): whose plant this is. No contact details: those belong to the exchange. ?>
        <section class="panel plant-panel owner-card" aria-labelledby="owner-title">
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
          </ul>
          <a class="btn btn-secondary owner-card-btn" href="<?= e($owner_url) ?>">View profile</a>
        </section>
      <?php endif; ?>
    </div>
  </div>

  <?php // Plant story (p12, element 6): full width under both columns, newest first. Each entry type looks different
        // (partials/story-entry.php). Desktop: a sticky panel beside the timeline with filters and stats; phone:
        // the filters as a scrolling chip row above it, no stats. ?>
  <section class="plant-story-section" aria-labelledby="story-title">
    <div class="story-head">
      <h2 class="plant-story-title" id="story-title">Story</h2>
      <?php if ($is_owner): ?>
        <a class="story-add-link" href="<?= e(url('plant-form.php?id=' . $plant['id'] . '#story-update')) ?>"><?= icon('plus') ?> Add to story</a>
      <?php endif; ?>
    </div>

    <?php if (!$story): ?>
      <p class="panel-empty"><?= icon('info') ?> No story entries yet.</p>
    <?php else: ?>
      <div class="story-layout">
        <aside class="story-panel" aria-label="Story filters and stats">
          <?php // Filters only hide and show entries on this page (story.js); without JavaScript every entry shows ?>
          <div class="story-filters" role="group" aria-label="Show">
            <?php foreach (['all' => 'All', 'photos' => 'Photos', 'health' => 'Health', 'care' => 'Care'] as $key => $label): ?>
              <button type="button" class="chip story-filter<?= $key === 'all' ? ' is-selected' : '' ?>" data-story-filter="<?= e($key) ?>" aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>"><?= e($label) ?></button>
            <?php endforeach; ?>
          </div>
          <ul class="story-stats">
            <li><?= icon('cloud-rain') ?> <span>Watered <strong data-stat-watered><?= e($watered_month) ?></strong> <span data-stat-watered-word><?= $watered_month === 1 ? 'time' : 'times' ?></span> this month</span></li>
            <li><?= icon('calendar') ?> <span>Last update: <strong data-stat-last><?= e(date('j M', strtotime($last_update))) ?></strong></span></li>
            <li><?= icon('book-open') ?> <span><strong data-stat-count><?= e(count($story)) ?></strong> entries in the story</span></li>
          </ul>
        </aside>

        <div class="story-main">
          <ol class="story" data-story>
            <?php foreach ($story as $log): ?>
              <?php partial('story-entry', ['log' => $log]); ?>
            <?php endforeach; ?>
          </ol>
          <p class="panel-empty story-filter-empty" data-story-empty hidden><?= icon('info') ?> No entries of this kind yet.</p>
        </div>
      </div>

      <?php if ($is_owner): ?>
        <?php // What story.js copies when Done is pressed: an empty care line, filled in by the script ?>
        <template id="story-care-template">
          <li class="story-entry story-entry-care" data-kind="care" data-photo="0" data-health="0">
            <p class="story-care"><span class="story-care-task"></span> <span class="story-care-date"></span></p>
          </li>
        </template>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
